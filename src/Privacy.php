<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

/**
 * Tools → Export/Erase Personal Data: votes and guest emails.
 */
final class Privacy
{
    public static function register(): void
    {
        add_filter('wp_privacy_personal_data_exporters', [self::class, 'exporters']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'erasers']);
        add_action('admin_init', [self::class, 'policy']);
    }

    /**
     * @param array<string, mixed> $exporters
     * @return array<string, mixed>
     */
    public static function exporters(array $exporters): array
    {
        $exporters['ruvelo-feedback'] = ['exporter_friendly_name' => __('Feedback votes and requests', 'ruvelo-feedback'), 'callback' => [self::class, 'export']];

        return $exporters;
    }

    /**
     * @param array<string, mixed> $erasers
     * @return array<string, mixed>
     */
    public static function erasers(array $erasers): array
    {
        $erasers['ruvelo-feedback'] = ['eraser_friendly_name' => __('Feedback votes and requests', 'ruvelo-feedback'), 'callback' => [self::class, 'erase']];

        return $erasers;
    }

    /**
     * @return array{data: list<array<string, mixed>>, done: bool}
     */
    public static function export(string $email, int $page = 1): array
    {
        $data = [];
        foreach (self::votes($email) as $vote) {
            $data[] = [
                'group_id' => 'ruvelo-feedback-votes',
                'group_label' => __('Feedback votes', 'ruvelo-feedback'),
                'item_id' => 'ruvelo-vote-'.$vote['id'],
                'data' => [
                    ['name' => __('Request', 'ruvelo-feedback'), 'value' => get_the_title((int) $vote['request_id'])],
                    ['name' => __('Voted on', 'ruvelo-feedback'), 'value' => (string) $vote['created_at']],
                ],
            ];
        }
        foreach (self::guestRequests($email) as $id) {
            $data[] = [
                'group_id' => 'ruvelo-feedback-requests',
                'group_label' => __('Feedback requests', 'ruvelo-feedback'),
                'item_id' => 'ruvelo-request-'.$id,
                'data' => [
                    ['name' => __('Title', 'ruvelo-feedback'), 'value' => get_the_title($id)],
                    ['name' => __('Name given', 'ruvelo-feedback'), 'value' => (string) get_post_meta($id, '_ruvelo_guest_name', true)],
                ],
            ];
        }

        return ['data' => $data, 'done' => true];
    }

    /**
     * Removes the person's votes and the email on requests they posted as a
     * guest. The requests themselves stay: other people voted on them.
     *
     * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
     */
    public static function erase(string $email, int $page = 1): array
    {
        global $wpdb;

        $removed = false;
        foreach (self::votes($email) as $vote) {
            $wpdb->delete(Install::votesTable(), ['id' => (int) $vote['id']], ['%d']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
            Votes::recount((int) $vote['request_id']);
            $removed = true;
        }
        foreach (self::guestRequests($email) as $id) {
            delete_post_meta($id, '_ruvelo_guest_email');
            update_post_meta($id, '_ruvelo_guest_name', __('A guest', 'ruvelo-feedback'));
            $removed = true;
        }

        return ['items_removed' => $removed, 'items_retained' => false, 'messages' => [], 'done' => true];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function votes(string $email): array
    {
        global $wpdb;

        $user = get_user_by('email', $email);
        $table = Install::votesTable();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        $rows = $wpdb->get_results($wpdb->prepare('SELECT id, request_id, created_at FROM %i WHERE voter = %s OR voter = %s', $table, Votes::emailKey($email), $user !== false ? Votes::userKey($user->ID) : ''), ARRAY_A);

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @return list<int>
     */
    private static function guestRequests(string $email): array
    {
        $ids = get_posts([
            'post_type' => PostTypes::REQUEST,
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 100,
            'meta_key' => '_ruvelo_guest_email', // phpcs:ignore WordPress.DB.SlowDBQuery -- Privacy requests are rare.
            'meta_value' => $email, // phpcs:ignore WordPress.DB.SlowDBQuery -- Privacy requests are rare.
        ]);

        return array_values(array_map('intval', $ids));
    }

    public static function policy(): void
    {
        if (! function_exists('wp_add_privacy_policy_content')) {
            return;
        }
        wp_add_privacy_policy_content('Ruvelo Feedback', wp_kses_post('<p>'.__('When you vote for or post a feature request, we store which request you voted for and when. If you vote or post without an account, we store the email address you give us, to confirm your vote and to tell you when the request’s status changes. Every such email has an unsubscribe link. Nothing is shared with anyone outside this site.', 'ruvelo-feedback').'</p>'));
    }
}
