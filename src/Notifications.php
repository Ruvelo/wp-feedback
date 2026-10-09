<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

use WP_Post;

/**
 * Closing the loop. A status change queues one row in the outbox; WP-Cron
 * then emails the author and voters in batches, so a request with thousands
 * of voters never sends mail inside a page load.
 */
final class Notifications
{
    public const CRON_HOOK = 'ruvelo_feedback_send_notifications';

    public const OPTOUTS = 'ruvelo_feedback_optouts';

    private static bool $paused = false;

    /**
     * Stops queueing while true: for imports and the demo.
     */
    public static function pause(bool $paused = true): void
    {
        self::$paused = $paused;
    }

    public static function queue(int $requestId, string $event, string $from, string $to, string $note, int $entryId, int $actor): int
    {
        global $wpdb;

        if (self::$paused) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        $wpdb->insert(Install::outboxTable(), [
            'request_id' => $requestId,
            'event' => $event,
            'status_from' => $from,
            'status_to' => $to,
            'note' => $note,
            'entry_id' => $entryId,
            'actor_id' => $actor,
            'created_at' => current_time('mysql', true),
        ], ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s']);
        $id = (int) $wpdb->insert_id;

        self::schedule();

        return $id;
    }

    public static function schedule(int $delay = 30): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time() + $delay, self::CRON_HOOK);
        }
    }

    public static function pending(): int
    {
        global $wpdb;

        $table = Install::outboxTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE done_at IS NULL");
    }

    /**
     * The WP-Cron callback.
     */
    public static function cron(): void
    {
        self::run();
    }

    /**
     * The cron job: sends up to one batch, then schedules itself again if
     * there is more to send.
     *
     * @return int Emails handled in this run.
     */
    public static function run(?int $batch = null): int
    {
        global $wpdb;

        $batch = $batch ?? max(1, Settings::int('batch_size'));
        $table = Install::outboxTable();
        $handled = 0;

        while ($handled < $batch) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
            $row = $wpdb->get_row("SELECT * FROM {$table} WHERE done_at IS NULL ORDER BY id ASC LIMIT 1", ARRAY_A);
            if (! is_array($row)) {
                break;
            }
            $handled += self::process($row, $batch - $handled);
        }

        if (self::pending() > 0) {
            self::schedule(60);
        }

        return $handled;
    }

    /**
     * Works through one outbox row: the author first, then voters in id
     * order, remembering where it stopped.
     *
     * @param array<string, mixed> $row
     */
    private static function process(array $row, int $budget): int
    {
        global $wpdb;

        $outbox = Install::outboxTable();
        $votes = Install::votesTable();
        $id = (int) $row['id'];
        $requestId = (int) $row['request_id'];
        $request = Requests::get($requestId);

        if ($request === null || $request->post_status !== 'publish') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
            $wpdb->update($outbox, ['done_at' => current_time('mysql', true)], ['id' => $id]);

            return 0;
        }

        $actor = (int) $row['actor_id'];
        $authorEmail = strtolower(Requests::authorEmail($request));
        $authorId = (int) $request->post_author;
        $sent = 0;
        $handled = 0;

        if (! (int) $row['author_done']) {
            $optedOut = (bool) get_post_meta($requestId, '_ruvelo_author_optout', true);
            if (! $optedOut && $authorEmail !== '' && ($authorId === 0 || $authorId !== $actor)) {
                $sent += self::deliver(['user_id' => $authorId, 'email' => $authorEmail, 'name' => Requests::authorName($request), 'role' => 'author'], $row, $request) ? 1 : 0;
            }
            $handled++;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
            $wpdb->update($outbox, ['author_done' => 1], ['id' => $id]);
        }

        $cursor = (int) $row['last_vote_id'];
        $limit = max(1, $budget - $handled);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
        $voters = $wpdb->get_results($wpdb->prepare("SELECT id, user_id, email FROM {$votes} WHERE request_id = %d AND confirmed = 1 AND notify = 1 AND id > %d ORDER BY id ASC LIMIT %d", $requestId, $cursor, $limit), ARRAY_A);
        $voters = is_array($voters) ? $voters : [];

        foreach ($voters as $voter) {
            $cursor = (int) $voter['id'];
            $handled++;
            $userId = (int) $voter['user_id'];
            if ($userId > 0 && ($userId === $actor || $userId === $authorId)) {
                continue;
            }
            $user = $userId > 0 ? get_userdata($userId) : false;
            $email = $user !== false ? $user->user_email : (string) $voter['email'];
            if ($email === '' || strtolower($email) === $authorEmail) {
                continue;
            }
            $sent += self::deliver(['user_id' => $userId, 'email' => $email, 'name' => $user !== false ? $user->display_name : '', 'role' => 'voter'], $row, $request) ? 1 : 0;
        }

        $finished = count($voters) < $limit;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        $wpdb->update($outbox, array_filter([
            'last_vote_id' => $cursor,
            'sent_count' => (int) $row['sent_count'] + $sent,
            'done_at' => $finished ? current_time('mysql', true) : null,
        ], static fn ($value): bool => $value !== null), ['id' => $id]);

        return max(1, $handled);
    }

    /**
     * Tells one person. Fires `ruvelo_feedback_notification` for every
     * recipient (an inbox plugin can listen), then emails them unless they
     * opted out or a filter says not to.
     *
     * @param array{user_id: int, email: string, name: string, role: string} $recipient
     * @param array<string, mixed> $row
     */
    private static function deliver(array $recipient, array $row, WP_Post $request): bool
    {
        $message = self::message($row, $request, $recipient);

        /**
         * Fires for each person told about a status change, whether or not
         * they get an email. Use it to deliver through another channel.
         *
         * @param array{user_id: int, email: string, name: string, role: string} $recipient
         * @param array{subject: string, title: string, body: string, url: string, request_id: int, status: string, event: string, unsubscribe: string} $message
         */
        do_action('ruvelo_feedback_notification', $recipient, $message);

        if (! Settings::bool('emails') || self::optedOut($recipient['email'])) {
            return false;
        }

        /**
         * Filters whether this email is sent. Return false to deliver some
         * other way (see `ruvelo_feedback_notification`).
         *
         * @param bool $send
         * @param array<string, mixed> $recipient
         * @param array<string, mixed> $message
         */
        if (! apply_filters('ruvelo_feedback_send_email', true, $recipient, $message)) {
            return false;
        }

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'List-Unsubscribe: <'.$message['unsubscribe'].'>',
        ];

        return wp_mail($recipient['email'], $message['subject'], self::text($message, $recipient), $headers);
    }

    /**
     * @param array<string, mixed> $row
     * @param array{user_id: int, email: string, name: string, role: string} $recipient
     * @return array{subject: string, title: string, body: string, url: string, request_id: int, status: string, event: string, unsubscribe: string}
     */
    public static function message(array $row, WP_Post $request, array $recipient): array
    {
        $to = (string) $row['status_to'];
        $title = get_the_title($request);
        $label = Settings::statusLabel($to);
        $entryId = (int) $row['entry_id'];
        $entry = $entryId > 0 ? get_post($entryId) : null;
        $note = (string) $row['note'];

        if ($row['event'] === 'shipped' && $entry instanceof WP_Post) {
            /* translators: %s: request title */
            $headline = sprintf(__('Shipped: %s', 'ruvelo-feedback'), $title);
            /* translators: %s: changelog entry title */
            $body = sprintf(__('Something you asked for is live. Read about it in “%s”.', 'ruvelo-feedback'), get_the_title($entry));
            $url = (string) get_permalink($entry);
        } else {
            /* translators: 1: status, 2: request title */
            $headline = sprintf(__('%1$s: %2$s', 'ruvelo-feedback'), $label, $title);
            $body = $note !== '' ? $note : self::defaultBody($to);
            $url = (string) get_permalink($request);
        }

        $message = [
            /* translators: 1: site or product name, 2: headline */
            'subject' => sprintf(__('[%1$s] %2$s', 'ruvelo-feedback'), Settings::name(), $headline),
            'title' => $headline,
            'body' => $body,
            'url' => $url,
            'request_id' => $request->ID,
            'status' => $to,
            'event' => (string) $row['event'],
            'unsubscribe' => self::unsubscribeUrl($recipient['email'], $request->ID),
        ];

        /**
         * Filters a notification before it is sent.
         *
         * @param array<string, mixed> $message
         * @param WP_Post $request
         * @param array<string, mixed> $recipient
         */
        $filtered = apply_filters('ruvelo_feedback_message', $message, $request, $recipient);

        return is_array($filtered) ? array_merge($message, array_intersect_key($filtered, $message)) : $message;
    }

    private static function defaultBody(string $status): string
    {
        return match ($status) {
            'planned' => __('It’s on the roadmap. We’ll let you know when work starts.', 'ruvelo-feedback'),
            'in_progress' => __('We’re building it now.', 'ruvelo-feedback'),
            'shipped' => __('It’s live. Thanks for asking for it.', 'ruvelo-feedback'),
            'closed' => __('We’ve decided not to do this for now. Thanks for taking the time to ask.', 'ruvelo-feedback'),
            default => __('Its status changed.', 'ruvelo-feedback'),
        };
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $recipient
     */
    private static function text(array $message, array $recipient): string
    {
        $lines = [];
        $name = is_string($recipient['name'] ?? null) ? $recipient['name'] : '';
        /* translators: %s: first name */
        $lines[] = $name !== '' ? sprintf(__('Hi %s,', 'ruvelo-feedback'), strtok($name, ' ')) : __('Hi,', 'ruvelo-feedback');
        $lines[] = '';
        $lines[] = $recipient['role'] === 'author'
            ? __('There’s news about the request you posted:', 'ruvelo-feedback')
            : __('There’s news about a request you voted for:', 'ruvelo-feedback');
        $lines[] = '';
        $lines[] = (string) $message['title'];
        $lines[] = '';
        $lines[] = (string) $message['body'];
        $lines[] = '';
        $lines[] = (string) $message['url'];
        $lines[] = '';
        $lines[] = '—';
        /* translators: %s: site name */
        $lines[] = sprintf(__('You’re getting this because you asked for or voted on this at %s.', 'ruvelo-feedback'), Settings::name());
        /* translators: %s: unsubscribe link */
        $lines[] = sprintf(__('Stop these emails: %s', 'ruvelo-feedback'), (string) $message['unsubscribe']);

        return implode("\n", $lines);
    }

    // ------------------------------------------------------------------
    // Unsubscribing

    public static function token(string $email, int $requestId): string
    {
        return substr(wp_hash('unsubscribe|'.strtolower($email).'|'.$requestId), 0, 24);
    }

    public static function unsubscribeUrl(string $email, int $requestId): string
    {
        return add_query_arg([
            'ruvelo_feedback_unsubscribe' => $requestId,
            'email' => rawurlencode(strtolower($email)),
            'token' => self::token($email, $requestId),
        ], home_url('/'));
    }

    public static function verify(string $email, int $requestId, string $token): bool
    {
        return $email !== '' && hash_equals(self::token($email, $requestId), $token);
    }

    /**
     * @param string $scope "request" stops emails about one request, "all" every feedback email.
     */
    public static function unsubscribe(string $email, int $requestId, string $scope): void
    {
        $email = strtolower(trim($email));
        if ($scope === 'all') {
            $optouts = get_option(self::OPTOUTS, []);
            $optouts = is_array($optouts) ? $optouts : [];
            $optouts[] = hash('sha256', $email);
            update_option(self::OPTOUTS, array_values(array_unique($optouts)), false);
        } else {
            $user = get_user_by('email', $email);
            Votes::setNotify($requestId, $user !== false ? Votes::userKey($user->ID) : Votes::emailKey($email), false);
            $request = Requests::get($requestId);
            if ($request !== null && strtolower(Requests::authorEmail($request)) === $email) {
                update_post_meta($requestId, '_ruvelo_author_optout', 1);
            }
        }

        /**
         * Fires after someone unsubscribes.
         *
         * @param string $email
         * @param int $requestId
         * @param string $scope "request" or "all".
         */
        do_action('ruvelo_feedback_unsubscribed', $email, $requestId, $scope);
    }

    public static function optedOut(string $email): bool
    {
        $optouts = get_option(self::OPTOUTS, []);

        return is_array($optouts) && in_array(hash('sha256', strtolower(trim($email))), $optouts, true);
    }
}
