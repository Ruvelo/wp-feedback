<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

use WP_Error;
use WP_Post;
use WP_Query;

/**
 * The one write path for requests. Forms, REST, WP-CLI, abilities and the
 * admin all go through here, so counts stay right and the hooks always fire.
 *
 * It does not check capabilities: callers do (see Permissions).
 */
final class Requests
{
    public const SORTS = ['top', 'new', 'trending'];

    /**
     * Posts a request.
     *
     * @param array{title?: mixed, body?: mixed, category?: mixed, tags?: mixed, status?: mixed} $data
     * @param array{name?: string, email?: string}|null $guest For guest submissions.
     * @return int|WP_Error The request id.
     */
    public static function create(array $data, int $userId = 0, ?array $guest = null, ?bool $moderate = null): int|WP_Error
    {
        $title = Support\Text::line(is_scalar($data['title'] ?? null) ? (string) $data['title'] : '', 160);
        $body = Support\Text::plain(is_scalar($data['body'] ?? null) ? (string) $data['body'] : '', 10000);

        if (mb_strlen($title) < 3) {
            return new WP_Error('ruvelo_feedback_title', __('Give your request a title of at least 3 characters.', 'ruvelo-feedback'), ['status' => 422]);
        }

        $email = '';
        if ($userId === 0) {
            $email = sanitize_email((string) ($guest['email'] ?? ''));
            if (! is_email($email)) {
                return new WP_Error('ruvelo_feedback_email', __('Enter a valid email address so we can tell you when it ships.', 'ruvelo-feedback'), ['status' => 422]);
            }
        }

        $status = is_string($data['status'] ?? null) && Settings::isStatus($data['status']) ? $data['status'] : 'under_review';
        if ($moderate === null) {
            $moderation = Settings::string('moderation');
            $moderate = $moderation === 'all' || ($moderation === 'guests' && $userId === 0);
            if (user_can($userId, Settings::CAP)) {
                $moderate = false;
            }
        }

        $id = wp_insert_post([
            'post_type' => PostTypes::REQUEST,
            'post_title' => $title,
            'post_content' => Support\Text::toBlocks($body),
            'post_status' => $moderate ? 'pending' : 'publish',
            'post_author' => $userId,
            'comment_status' => 'open',
            'meta_input' => [
                '_ruvelo_status' => $status,
                '_ruvelo_votes' => 0,
                '_ruvelo_pinned' => 0,
                '_ruvelo_status_changed' => time(),
            ],
        ], true);

        if ($id instanceof WP_Error) {
            return $id;
        }

        if ($userId === 0) {
            update_post_meta($id, '_ruvelo_guest_email', $email);
            update_post_meta($id, '_ruvelo_guest_name', Support\Text::line((string) ($guest['name'] ?? ''), 80));
        }

        $category = $data['category'] ?? null;
        if (is_numeric($category) && (int) $category > 0 && term_exists((int) $category, PostTypes::CATEGORY)) {
            wp_set_object_terms($id, [(int) $category], PostTypes::CATEGORY);
        } elseif (is_string($category) && $category !== '') {
            $term = get_term_by('slug', sanitize_title($category), PostTypes::CATEGORY) ?: get_term_by('name', $category, PostTypes::CATEGORY);
            if ($term instanceof \WP_Term) {
                wp_set_object_terms($id, [$term->term_id], PostTypes::CATEGORY);
            }
        }
        if (isset($data['tags']) && is_array($data['tags'])) {
            wp_set_object_terms($id, array_values(array_filter(array_map(static fn ($tag): string => is_scalar($tag) ? sanitize_text_field((string) $tag) : '', $data['tags']))), PostTypes::TAG);
        }

        // Asking for something counts as wanting it.
        if ($userId > 0) {
            Votes::add($id, Votes::userKey($userId), $userId);
        }
        Votes::recount($id);

        /**
         * Fires after a request is posted. Check the post status: it is
         * "pending" when it waits for moderation.
         *
         * @param int $id The request id.
         * @param int $userId The author, 0 for a guest.
         */
        do_action('ruvelo_feedback_request_created', $id, $userId);

        return $id;
    }

    /**
     * Records a vote. Returns true when it was added, false when it was
     * already there, or an error when voting is closed.
     */
    public static function vote(int $id, int $userId, string $email = '', bool $confirmed = true): bool|WP_Error
    {
        $post = self::get($id);
        if ($post === null || $post->post_status !== 'publish') {
            return new WP_Error('ruvelo_feedback_not_found', __('That request doesn’t exist, or isn’t public yet.', 'ruvelo-feedback'), ['status' => 404]);
        }
        if (self::isLocked($id)) {
            return new WP_Error('ruvelo_feedback_locked', __('Voting is closed for this request.', 'ruvelo-feedback'), ['status' => 409]);
        }

        $voter = $userId > 0 ? Votes::userKey($userId) : Votes::emailKey($email);
        $added = Votes::add($id, $voter, $userId, $userId > 0 ? '' : strtolower(trim($email)), $confirmed);
        Votes::recount($id);

        if ($added && $confirmed) {
            /**
             * Fires when a vote is added or taken back.
             *
             * @param int $id The request id.
             * @param string $voter "u:<id>" or "e:<hash>".
             * @param bool $added True for a new vote, false when it was removed.
             */
            do_action('ruvelo_feedback_voted', $id, $voter, true);
        }

        return $added;
    }

    public static function unvote(int $id, int $userId, string $email = ''): bool|WP_Error
    {
        if (self::isLocked($id)) {
            return new WP_Error('ruvelo_feedback_locked', __('Voting is closed for this request.', 'ruvelo-feedback'), ['status' => 409]);
        }
        $voter = $userId > 0 ? Votes::userKey($userId) : Votes::emailKey($email);
        $removed = Votes::remove($id, $voter);
        Votes::recount($id);
        if ($removed) {
            do_action('ruvelo_feedback_voted', $id, $voter, false);
        }

        return $removed;
    }

    /**
     * Confirms a guest's vote from the link in their email.
     */
    public static function confirmGuestVote(int $id, string $email): bool
    {
        $voter = Votes::emailKey($email);
        $vote = Votes::find($id, $voter);
        if ($vote === null) {
            return false;
        }
        if (! $vote['confirmed']) {
            Votes::confirm($id, $voter);
            Votes::recount($id);
            do_action('ruvelo_feedback_voted', $id, $voter, true);
        }

        return true;
    }

    /**
     * Changes a request's status, records it in the history, and queues the
     * emails to its author and voters (sent later by WP-Cron, never here).
     */
    public static function changeStatus(int $id, string $to, string $note = '', int $by = 0, bool $notify = true, int $entryId = 0): bool|WP_Error
    {
        $post = self::get($id);
        if ($post === null) {
            return new WP_Error('ruvelo_feedback_not_found', __('That request doesn’t exist.', 'ruvelo-feedback'), ['status' => 404]);
        }
        if (! Settings::isStatus($to)) {
            return new WP_Error('ruvelo_feedback_status', __('That isn’t one of the statuses. Pick one from the list.', 'ruvelo-feedback'), ['status' => 422]);
        }

        $from = self::status($id);
        $note = Support\Text::plain($note, 5000);
        if ($from === $to && $note === '' && $entryId === 0) {
            return false;
        }

        update_post_meta($id, '_ruvelo_status', $to);
        if ($from !== $to) {
            update_post_meta($id, '_ruvelo_status_changed', time());
        }
        if ($entryId > 0) {
            update_post_meta($id, '_ruvelo_entry', $entryId);
        }

        $history = self::history($id);
        $history[] = ['from' => $from, 'to' => $to, 'note' => $note, 'by' => $by, 'at' => time(), 'entry' => $entryId];
        update_post_meta($id, '_ruvelo_history', $history);

        $shouldNotify = $notify
            && $post->post_status === 'publish'
            && ($from !== $to || $note !== '')
            && in_array($to, self::notifyStatuses(), true);
        if ($shouldNotify) {
            Notifications::queue($id, $entryId > 0 && $to === 'shipped' ? 'shipped' : 'status', $from, $to, $note, $entryId, $by);
        }

        /**
         * Fires right after a request's status changes (or a note is added).
         * Emails are queued, not sent: listen to `ruvelo_feedback_notification`
         * for each recipient.
         *
         * @param int $id The request id.
         * @param string $from Old status.
         * @param string $to New status.
         * @param string $note The public note, may be empty.
         * @param int $by The user who changed it.
         * @param int $entryId The changelog entry it shipped in, or 0.
         */
        do_action('ruvelo_feedback_status_changed', $id, $from, $to, $note, $by, $entryId);

        return true;
    }

    /**
     * @return list<string>
     */
    private static function notifyStatuses(): array
    {
        $statuses = Settings::get('notify_statuses');

        return is_array($statuses) ? array_values(array_filter($statuses, 'is_string')) : [];
    }

    /**
     * Merges a duplicate into another request. Votes move over (people who
     * voted for both keep one vote), comments move over, and the duplicate
     * redirects to the request it was merged into.
     */
    public static function merge(int $duplicate, int $into, int $by = 0): int|WP_Error
    {
        if ($duplicate === $into) {
            return new WP_Error('ruvelo_feedback_merge', __('A request can’t be merged into itself.', 'ruvelo-feedback'), ['status' => 422]);
        }
        $dup = self::get($duplicate);
        $target = self::get($into);
        if ($dup === null || $target === null) {
            return new WP_Error('ruvelo_feedback_not_found', __('That request doesn’t exist.', 'ruvelo-feedback'), ['status' => 404]);
        }
        if ($target->post_status === PostTypes::MERGED) {
            return new WP_Error('ruvelo_feedback_merge', __('That request was itself merged. Merge into the request it points to.', 'ruvelo-feedback'), ['status' => 422]);
        }
        if ($dup->post_status === PostTypes::MERGED) {
            return new WP_Error('ruvelo_feedback_merge', __('This request was already merged.', 'ruvelo-feedback'), ['status' => 422]);
        }

        $moved = Votes::move($duplicate, $into);
        Votes::recount($duplicate);
        Votes::recount($into);

        // The duplicate's author wants the same thing: count them in.
        $author = (int) $dup->post_author;
        if ($author > 0) {
            Votes::add($into, Votes::userKey($author), $author);
            Votes::recount($into);
        }

        // Comments follow the conversation.
        $comments = get_comments(['post_id' => $duplicate, 'fields' => 'ids', 'status' => 'all']);
        foreach (is_array($comments) ? $comments : [] as $commentId) {
            wp_update_comment(['comment_ID' => $commentId instanceof \WP_Comment ? (int) $commentId->comment_ID : (int) $commentId, 'comment_post_ID' => $into]);
        }
        wp_update_comment_count_now($duplicate);
        wp_update_comment_count_now($into);

        update_post_meta($duplicate, '_ruvelo_merged_into', $into);
        wp_update_post(['ID' => $duplicate, 'post_status' => PostTypes::MERGED]);

        $merged = get_post_meta($into, '_ruvelo_duplicates', true);
        $merged = is_array($merged) ? $merged : [];
        $merged[] = $duplicate;
        update_post_meta($into, '_ruvelo_duplicates', array_values(array_unique(array_map('intval', $merged))));

        /**
         * Fires after a duplicate is merged.
         *
         * @param int $duplicate The merged request.
         * @param int $into The request it was merged into.
         * @param int $moved How many votes moved over.
         * @param int $by Who merged it.
         */
        do_action('ruvelo_feedback_requests_merged', $duplicate, $into, $moved, $by);

        return $moved;
    }

    public static function pin(int $id, bool $pinned = true): void
    {
        update_post_meta($id, '_ruvelo_pinned', $pinned ? 1 : 0);
        do_action('ruvelo_feedback_request_pinned', $id, $pinned);
    }

    public static function approve(int $id): bool
    {
        $post = self::get($id);
        if ($post === null || $post->post_status !== 'pending') {
            return false;
        }
        wp_publish_post($id);
        do_action('ruvelo_feedback_request_approved', $id);

        return true;
    }

    // ------------------------------------------------------------------
    // Reading

    public static function get(int $id): ?WP_Post
    {
        $post = get_post($id);

        return $post instanceof WP_Post && $post->post_type === PostTypes::REQUEST ? $post : null;
    }

    public static function status(int $id): string
    {
        $status = get_post_meta($id, '_ruvelo_status', true);

        return is_string($status) && $status !== '' ? $status : 'under_review';
    }

    public static function votes(int $id): int
    {
        return (int) get_post_meta($id, '_ruvelo_votes', true);
    }

    public static function isPinned(int $id): bool
    {
        return (bool) get_post_meta($id, '_ruvelo_pinned', true);
    }

    public static function isLocked(int $id): bool
    {
        return in_array(self::status($id), Settings::lockedStatuses(), true);
    }

    public static function entryId(int $id): int
    {
        return (int) get_post_meta($id, '_ruvelo_entry', true);
    }

    /**
     * @return list<array{from: string, to: string, note: string, by: int, at: int, entry: int}>
     */
    public static function history(int $id): array
    {
        $history = get_post_meta($id, '_ruvelo_history', true);
        if (! is_array($history)) {
            return [];
        }
        $clean = [];
        foreach ($history as $row) {
            if (is_array($row)) {
                $clean[] = [
                    'from' => (string) ($row['from'] ?? ''),
                    'to' => (string) ($row['to'] ?? ''),
                    'note' => (string) ($row['note'] ?? ''),
                    'by' => (int) ($row['by'] ?? 0),
                    'at' => (int) ($row['at'] ?? 0),
                    'entry' => (int) ($row['entry'] ?? 0),
                ];
            }
        }

        return $clean;
    }

    public static function authorName(WP_Post $post): string
    {
        if ((int) $post->post_author > 0) {
            $user = get_userdata((int) $post->post_author);
            if ($user !== false) {
                return $user->display_name;
            }
        }
        $guest = get_post_meta($post->ID, '_ruvelo_guest_name', true);

        return is_string($guest) && $guest !== '' ? $guest : __('A guest', 'ruvelo-feedback');
    }

    public static function authorEmail(WP_Post $post): string
    {
        if ((int) $post->post_author > 0) {
            $user = get_userdata((int) $post->post_author);

            return $user !== false ? $user->user_email : '';
        }
        $email = get_post_meta($post->ID, '_ruvelo_guest_email', true);

        return is_string($email) ? $email : '';
    }

    /**
     * The board: published requests filtered and sorted.
     *
     * @param array{status?: string, category?: string, tag?: string, search?: string, sort?: string, page?: int, per_page?: int} $args
     * @return array{posts: list<WP_Post>, total: int, pages: int}
     */
    public static function board(array $args = []): array
    {
        $status = $args['status'] ?? 'open';
        $sort = in_array($args['sort'] ?? 'top', self::SORTS, true) ? ($args['sort'] ?? 'top') : 'top';
        $perPage = max(1, min(100, (int) ($args['per_page'] ?? Settings::int('per_page'))));

        $query = [
            'post_type' => PostTypes::REQUEST,
            'post_status' => 'publish',
            'posts_per_page' => $perPage,
            'paged' => max(1, (int) ($args['page'] ?? 1)),
            'ignore_sticky_posts' => true,
            'meta_query' => [
                'relation' => 'AND',
                'pinned' => ['key' => '_ruvelo_pinned', 'type' => 'NUMERIC'],
                'votes' => ['key' => '_ruvelo_votes', 'type' => 'NUMERIC'],
            ],
            'ruvelo_feedback_sort' => $sort,
        ];

        if ($status === 'open') {
            $query['meta_query']['status'] = ['key' => '_ruvelo_status', 'value' => Settings::openStatuses(), 'compare' => 'IN'];
        } elseif ($status !== 'all' && Settings::isStatus($status)) {
            $query['meta_query']['status'] = ['key' => '_ruvelo_status', 'value' => $status];
        }

        $tax = [];
        if (! empty($args['category'])) {
            $tax[] = ['taxonomy' => PostTypes::CATEGORY, 'field' => 'slug', 'terms' => sanitize_title($args['category'])];
        }
        if (! empty($args['tag'])) {
            $tax[] = ['taxonomy' => PostTypes::TAG, 'field' => 'slug', 'terms' => sanitize_title($args['tag'])];
        }
        if ($tax !== []) {
            $query['tax_query'] = $tax;
        }
        if (! empty($args['search'])) {
            $query['s'] = sanitize_text_field($args['search']);
        }

        $query['orderby'] = match ($sort) {
            'new' => ['pinned' => 'DESC', 'date' => 'DESC'],
            default => ['pinned' => 'DESC', 'votes' => 'DESC', 'date' => 'DESC'],
        };

        /**
         * Filters the WP_Query arguments for the board.
         *
         * @param array<string, mixed> $query
         * @param array<string, mixed> $args
         */
        $query = apply_filters('ruvelo_feedback_board_query', $query, $args);

        $result = new WP_Query(is_array($query) ? $query : []);
        $posts = array_values(array_filter($result->posts, static fn ($post): bool => $post instanceof WP_Post));

        return ['posts' => $posts, 'total' => (int) $result->found_posts, 'pages' => (int) $result->max_num_pages];
    }

    /**
     * For "trending": order by votes in the last 14 days. Hooked to posts_clauses.
     *
     * @param array<string, string> $clauses
     * @return array<string, string>
     */
    public static function trendingClauses(array $clauses, WP_Query $query): array
    {
        if ($query->get('ruvelo_feedback_sort') !== 'trending') {
            return $clauses;
        }
        global $wpdb;
        $table = Install::votesTable();
        $days = (int) apply_filters('ruvelo_feedback_trending_days', 14);
        $since = gmdate('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS);
        $clauses['join'] .= $wpdb->prepare(" LEFT JOIN (SELECT request_id, COUNT(*) AS ruvelo_recent FROM {$table} WHERE confirmed = 1 AND created_at >= %s GROUP BY request_id) ruvelo_trend ON ruvelo_trend.request_id = {$wpdb->posts}.ID", $since); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names.
        $clauses['orderby'] = 'COALESCE(ruvelo_trend.ruvelo_recent, 0) DESC, '.$clauses['orderby'];

        return $clauses;
    }

    /**
     * How many published requests are in each status.
     *
     * @return array<string, int>
     */
    public static function countsByStatus(): array
    {
        global $wpdb;

        $cached = wp_cache_get('counts', 'ruvelo_feedback');
        if (is_array($cached)) {
            return $cached;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Grouped count, cached below.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.meta_value AS status, COUNT(*) AS total FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_ruvelo_status' WHERE p.post_type = %s AND p.post_status = 'publish' GROUP BY m.meta_value",
            PostTypes::REQUEST
        ), ARRAY_A);

        $counts = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }
        wp_cache_set('counts', $counts, 'ruvelo_feedback', MINUTE_IN_SECONDS);

        return $counts;
    }

    /**
     * Requests whose titles look like this one, to vote instead of posting
     * the same idea again.
     *
     * @return list<WP_Post>
     */
    public static function similar(string $text, int $limit = 0, int $exclude = 0): array
    {
        $limit = $limit > 0 ? $limit : max(1, Settings::int('similar'));
        $words = Support\Text::keywords($text);
        if ($words === []) {
            return [];
        }

        global $wpdb;
        $likes = [];
        $values = [PostTypes::REQUEST];
        foreach ($words as $word) {
            $likes[] = 'p.post_title LIKE %s';
            $values[] = '%'.$wpdb->esc_like($word).'%';
        }
        $values[] = $exclude;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Built from placeholders only.
        $ids = $wpdb->get_col($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status = 'publish' AND (".implode(' OR ', $likes).') AND p.ID <> %d LIMIT 50', ...$values));

        $posts = [];
        $titles = [];
        foreach ($ids as $id) {
            $post = get_post((int) $id);
            if ($post instanceof WP_Post) {
                $posts[] = $post;
                $titles[$post->ID] = Support\Text::keywords($post->post_title);
            }
        }
        // Words that appear in fewer titles, and longer words, say more.
        $frequency = [];
        foreach ($words as $word) {
            $frequency[$word] = max(1, count(array_filter($titles, static fn (array $title): bool => (bool) array_filter($title, static fn (string $candidate): bool => str_contains($candidate, $word) || str_contains($word, $candidate)))));
        }
        $scored = [];
        foreach ($posts as $post) {
            $score = 0.0;
            foreach ($words as $word) {
                foreach ($titles[$post->ID] as $candidate) {
                    $weight = min(10, mb_strlen($word)) / $frequency[$word];
                    if ($candidate === $word) {
                        $score += 2 * $weight;
                    } elseif (str_starts_with($candidate, $word) || str_starts_with($word, $candidate)) {
                        $score += $weight;
                    }
                }
            }
            $scored[] = ['post' => $post, 'score' => $score + min(1, self::votes($post->ID) / 1000)];
        }
        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice(array_map(static fn (array $row): WP_Post => $row['post'], $scored), 0, $limit);
    }

    /**
     * The roadmap: status => requests, most voted first (recently shipped
     * first in a "shipped" column).
     *
     * @return array<string, list<WP_Post>>
     */
    public static function roadmap(int $limit = 20): array
    {
        $columns = [];
        foreach (Settings::roadmapStatuses() as $status) {
            $shipped = in_array($status, Settings::lockedStatuses(), true);
            $query = new WP_Query([
                'post_type' => PostTypes::REQUEST,
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'no_found_rows' => true,
                'meta_query' => [
                    'status' => ['key' => '_ruvelo_status', 'value' => $status],
                    'votes' => ['key' => '_ruvelo_votes', 'type' => 'NUMERIC'],
                    'changed' => ['key' => '_ruvelo_status_changed', 'type' => 'NUMERIC'],
                ],
                'orderby' => $shipped ? ['changed' => 'DESC'] : ['votes' => 'DESC', 'date' => 'DESC'],
            ]);
            $columns[$status] = array_values(array_filter($query->posts, static fn ($post): bool => $post instanceof WP_Post));
        }

        return $columns;
    }

    /**
     * A request as plain data, for REST, abilities and WP-CLI.
     *
     * @return array<string, mixed>
     */
    public static function toArray(WP_Post $post, string $voter = ''): array
    {
        $status = self::status($post->ID);
        $category = get_the_terms($post->ID, PostTypes::CATEGORY);
        $category = is_array($category) && $category !== [] ? $category[0] : null;
        $tags = get_the_terms($post->ID, PostTypes::TAG);
        $entryId = self::entryId($post->ID);
        $entry = $entryId > 0 ? get_post($entryId) : null;
        $name = self::authorName($post);

        return [
            'id' => $post->ID,
            'title' => get_the_title($post),
            'slug' => $post->post_name,
            'url' => (string) get_permalink($post),
            'excerpt' => Support\Text::excerpt($post->post_content, 200),
            'body' => trim(wp_strip_all_tags(Support\Text::stripBlocks($post->post_content), false)),
            'status' => $status,
            'status_label' => Settings::statusLabel($status),
            'votes' => self::votes($post->ID),
            'comments' => (int) $post->comment_count,
            'voted' => $voter !== '' && Votes::has($post->ID, $voter),
            'pinned' => self::isPinned($post->ID),
            'approved' => $post->post_status === 'publish',
            'locked' => self::isLocked($post->ID),
            'author' => ['name' => $name, 'initials' => Support\Html::initials($name)],
            'category' => $category instanceof \WP_Term ? ['id' => $category->term_id, 'name' => $category->name, 'slug' => $category->slug] : null,
            'tags' => is_array($tags) ? array_map(static fn (\WP_Term $tag): array => ['name' => $tag->name, 'slug' => $tag->slug], $tags) : [],
            'entry' => $entry instanceof WP_Post && $entry->post_status === 'publish' ? ['id' => $entry->ID, 'title' => get_the_title($entry), 'url' => (string) get_permalink($entry)] : null,
            'merged_into' => (int) get_post_meta($post->ID, '_ruvelo_merged_into', true) ?: null,
            'created_at' => mysql_to_rfc3339($post->post_date_gmt),
            'status_changed_at' => gmdate('c', (int) get_post_meta($post->ID, '_ruvelo_status_changed', true) ?: (int) strtotime($post->post_date_gmt.' UTC')),
        ];
    }
}
