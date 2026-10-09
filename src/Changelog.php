<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Changelog entries, the requests they ship, and who has seen what.
 */
final class Changelog
{
    /**
     * Writes an entry. Publishing it ships its linked requests.
     *
     * @param list<string> $labels Label slugs.
     * @param list<int> $requests Request ids this entry ships.
     */
    public static function create(string $title, string $content, array $labels = [], array $requests = [], bool $publish = true, int $author = 0): int|WP_Error
    {
        $title = Support\Text::line($title, 200);
        if ($title === '') {
            return new WP_Error('ruvelo_feedback_title', __('Give the entry a title.', 'ruvelo-feedback'), ['status' => 422]);
        }
        $id = wp_insert_post([
            'post_type' => PostTypes::ENTRY,
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'draft',
            'post_author' => $author,
        ], true);
        if ($id instanceof WP_Error) {
            return $id;
        }
        if ($labels !== []) {
            wp_set_object_terms($id, $labels, PostTypes::LABEL);
        }
        self::setRequests($id, $requests);
        if ($publish) {
            self::publish($id);
        }

        return $id;
    }

    /**
     * @param list<int> $requests
     */
    public static function setRequests(int $entryId, array $requests): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $requests), static fn (int $id): bool => Requests::get($id) !== null)));
        update_post_meta($entryId, '_ruvelo_requests', $ids);
        if (get_post_status($entryId) === 'publish') {
            self::ship($entryId);
        }
    }

    /**
     * @return list<int>
     */
    public static function requestIds(int $entryId): array
    {
        $ids = get_post_meta($entryId, '_ruvelo_requests', true);

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    public static function publish(int $entryId): bool
    {
        $post = get_post($entryId);
        if (! $post instanceof WP_Post || $post->post_type !== PostTypes::ENTRY) {
            return false;
        }
        if ($post->post_status !== 'publish') {
            // Drafts have no publish date yet: it goes live now.
            $now = current_time('mysql', true);
            wp_update_post(['ID' => $entryId, 'post_status' => 'publish', 'post_date_gmt' => $now, 'post_date' => get_date_from_gmt($now), 'edit_date' => true]);
        }

        return true;
    }

    /**
     * Hooked to transition_post_status: when an entry goes live, its
     * requests ship and their voters are told.
     */
    public static function onTransition(string $new, string $old, WP_Post $post): void
    {
        if ($post->post_type !== PostTypes::ENTRY || $new !== 'publish') {
            return;
        }
        self::ship($post->ID);
        if ($old !== 'publish') {
            /**
             * Fires once when a changelog entry goes live.
             *
             * @param int $entryId
             */
            do_action('ruvelo_feedback_entry_published', $post->ID);
        }
    }

    /**
     * Marks every linked request as shipped in this entry. Requests already
     * shipped in this entry are left alone, so nobody hears twice.
     */
    public static function ship(int $entryId): void
    {
        $entry = get_post($entryId);
        $by = $entry instanceof WP_Post ? (int) $entry->post_author : 0;
        foreach (self::requestIds($entryId) as $requestId) {
            if (Requests::status($requestId) === 'shipped' && Requests::entryId($requestId) === $entryId) {
                continue;
            }
            Requests::changeStatus($requestId, 'shipped', '', $by, true, $entryId);
        }
    }

    /**
     * @return array{posts: list<WP_Post>, total: int, pages: int}
     */
    public static function entries(int $page = 1, int $perPage = 10, string $label = ''): array
    {
        $args = [
            'post_type' => PostTypes::ENTRY,
            'post_status' => 'publish',
            'posts_per_page' => $perPage,
            'paged' => max(1, $page),
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        if ($label !== '') {
            $args['tax_query'] = [['taxonomy' => PostTypes::LABEL, 'field' => 'slug', 'terms' => sanitize_title($label)]]; // phpcs:ignore WordPress.DB.SlowDBQuery -- One label.
        }
        $query = new WP_Query($args);

        return [
            'posts' => array_values(array_filter($query->posts, static fn ($post): bool => $post instanceof WP_Post)),
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
        ];
    }

    /**
     * @return list<array{slug: string, label: string, color: string}>
     */
    public static function labelsOf(int $entryId): array
    {
        $terms = get_the_terms($entryId, PostTypes::LABEL);
        if (! is_array($terms)) {
            return [];
        }
        $known = Settings::labels();
        $labels = [];
        foreach ($terms as $term) {
            $color = get_term_meta($term->term_id, 'ruvelo_color', true);
            $labels[] = [
                'slug' => $term->slug,
                'label' => $term->name,
                'color' => is_string($color) && Settings::validColor($color) ? $color : ($known[$term->slug]['color'] ?? 'accent'),
            ];
        }

        return $labels;
    }

    // ------------------------------------------------------------------
    // What's new

    /**
     * When this visitor last looked at the changelog: user meta for people
     * who are signed in, a cookie for guests. 0 means never.
     */
    public static function lastSeen(int $userId = -1): int
    {
        $userId = $userId < 0 ? get_current_user_id() : $userId;
        if ($userId > 0) {
            return (int) get_user_meta($userId, 'ruvelo_feedback_seen', true);
        }

        return isset($_COOKIE[Viewer::SEEN_COOKIE]) ? absint(wp_unslash($_COOKIE[Viewer::SEEN_COOKIE])) : 0;
    }

    public static function markSeen(int $userId = -1): void
    {
        $userId = $userId < 0 ? get_current_user_id() : $userId;
        if ($userId > 0) {
            update_user_meta($userId, 'ruvelo_feedback_seen', time());

            return;
        }
        if (! headers_sent()) {
            setcookie(Viewer::SEEN_COOKIE, (string) time(), ['expires' => time() + YEAR_IN_SECONDS, 'path' => COOKIEPATH, 'secure' => is_ssl(), 'httponly' => false, 'samesite' => 'Lax']);
        }
        $_COOKIE[Viewer::SEEN_COOKIE] = (string) time();
    }

    /**
     * The point after which entries count as new: the last visit, or for
     * someone who never looked, the last 30 days.
     */
    public static function since(int $userId = -1): int
    {
        $seen = self::lastSeen($userId);
        $fresh = (int) apply_filters('ruvelo_feedback_fresh_days', 30);

        return $seen > 0 ? $seen : time() - max(1, $fresh) * DAY_IN_SECONDS;
    }

    public static function unreadCount(int $userId = -1): int
    {
        global $wpdb;

        $since = gmdate('Y-m-d H:i:s', self::since($userId));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One indexed count per page view.
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_date_gmt > %s", PostTypes::ENTRY, $since));
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(WP_Post $post): array
    {
        $requests = [];
        foreach (self::requestIds($post->ID) as $id) {
            $request = Requests::get($id);
            if ($request !== null && $request->post_status === 'publish') {
                $requests[] = ['id' => $id, 'title' => get_the_title($request), 'url' => (string) get_permalink($request), 'votes' => Requests::votes($id)];
            }
        }

        return [
            'id' => $post->ID,
            'title' => get_the_title($post),
            'slug' => $post->post_name,
            'url' => (string) get_permalink($post),
            'date' => mysql_to_rfc3339($post->post_date_gmt),
            'labels' => self::labelsOf($post->ID),
            'excerpt' => Support\Text::excerpt($post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content, 240),
            'content' => apply_filters('the_content', $post->post_content), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
            'cover' => has_post_thumbnail($post) ? (string) get_the_post_thumbnail_url($post, 'large') : null,
            'requests' => $requests,
        ];
    }
}
