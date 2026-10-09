<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

/**
 * Requests and changelog entries are ordinary post types, so they get the
 * admin screens, revisions, search, comments and REST routes for free.
 */
final class PostTypes
{
    public const REQUEST = 'ruvelo_request';

    public const ENTRY = 'ruvelo_entry';

    public const CATEGORY = 'ruvelo_request_category';

    public const TAG = 'ruvelo_request_tag';

    public const LABEL = 'ruvelo_entry_label';

    public const MERGED = 'ruvelo_merged';

    public static function register(): void
    {
        $caps = self::capabilities();

        register_post_type(self::REQUEST, [
            'labels' => [
                'name' => __('Feature requests', 'ruvelo-feedback'),
                'singular_name' => __('Feature request', 'ruvelo-feedback'),
                'menu_name' => __('Feedback', 'ruvelo-feedback'),
                'all_items' => __('Requests', 'ruvelo-feedback'),
                'add_new' => __('Add request', 'ruvelo-feedback'),
                'add_new_item' => __('Add request', 'ruvelo-feedback'),
                'edit_item' => __('Edit request', 'ruvelo-feedback'),
                'new_item' => __('New request', 'ruvelo-feedback'),
                'view_item' => __('View request', 'ruvelo-feedback'),
                'search_items' => __('Search requests', 'ruvelo-feedback'),
                'not_found' => __('No requests yet.', 'ruvelo-feedback'),
                'not_found_in_trash' => __('No requests in the trash.', 'ruvelo-feedback'),
                'item_published' => __('Request published.', 'ruvelo-feedback'),
                'item_updated' => __('Request updated.', 'ruvelo-feedback'),
            ],
            'description' => __('Ideas and feature requests from your users.', 'ruvelo-feedback'),
            'public' => true,
            'show_in_menu' => true,
            'menu_position' => 26,
            'menu_icon' => 'dashicons-lightbulb',
            'show_in_rest' => true,
            'rest_base' => 'ruvelo-requests',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'author', 'comments', 'revisions'],
            'rewrite' => ['slug' => self::requestBase(), 'with_front' => false],
            'capabilities' => $caps,
            'map_meta_cap' => true,
            'taxonomies' => [self::CATEGORY, self::TAG],
        ]);

        register_post_type(self::ENTRY, [
            'labels' => [
                'name' => __('Changelog', 'ruvelo-feedback'),
                'singular_name' => __('Changelog entry', 'ruvelo-feedback'),
                'menu_name' => __('Changelog', 'ruvelo-feedback'),
                'all_items' => __('Changelog', 'ruvelo-feedback'),
                'add_new' => __('Write an update', 'ruvelo-feedback'),
                'add_new_item' => __('Write a changelog entry', 'ruvelo-feedback'),
                'edit_item' => __('Edit changelog entry', 'ruvelo-feedback'),
                'new_item' => __('New changelog entry', 'ruvelo-feedback'),
                'view_item' => __('View entry', 'ruvelo-feedback'),
                'view_items' => __('View changelog', 'ruvelo-feedback'),
                'search_items' => __('Search the changelog', 'ruvelo-feedback'),
                'not_found' => __('Nothing in the changelog yet.', 'ruvelo-feedback'),
                'not_found_in_trash' => __('No entries in the trash.', 'ruvelo-feedback'),
                'archives' => __('Changelog', 'ruvelo-feedback'),
            ],
            'description' => __('New features, improvements and fixes.', 'ruvelo-feedback'),
            'public' => true,
            'show_in_menu' => 'edit.php?post_type='.self::REQUEST,
            'show_in_rest' => true,
            'rest_base' => 'ruvelo-changelog',
            'has_archive' => self::changelogBase(),
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions'],
            'rewrite' => ['slug' => self::changelogBase(), 'with_front' => false, 'feeds' => true],
            'capabilities' => $caps,
            'map_meta_cap' => true,
            'taxonomies' => [self::LABEL],
        ]);

        $termCaps = [
            'manage_terms' => Settings::CAP,
            'edit_terms' => Settings::CAP,
            'delete_terms' => Settings::CAP,
            'assign_terms' => Settings::CAP,
        ];

        register_taxonomy(self::CATEGORY, [self::REQUEST], [
            'labels' => [
                'name' => __('Categories', 'ruvelo-feedback'),
                'singular_name' => __('Category', 'ruvelo-feedback'),
                'add_new_item' => __('Add a category', 'ruvelo-feedback'),
                'search_items' => __('Search categories', 'ruvelo-feedback'),
                'not_found' => __('No categories yet.', 'ruvelo-feedback'),
            ],
            'public' => true,
            'publicly_queryable' => false,
            'hierarchical' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rest_base' => 'ruvelo-request-categories',
            'rewrite' => false,
            'capabilities' => $termCaps,
        ]);

        register_taxonomy(self::TAG, [self::REQUEST], [
            'labels' => [
                'name' => __('Tags', 'ruvelo-feedback'),
                'singular_name' => __('Tag', 'ruvelo-feedback'),
                'add_new_item' => __('Add a tag', 'ruvelo-feedback'),
                'search_items' => __('Search tags', 'ruvelo-feedback'),
                'not_found' => __('No tags yet.', 'ruvelo-feedback'),
            ],
            'public' => true,
            'publicly_queryable' => false,
            'hierarchical' => false,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rest_base' => 'ruvelo-request-tags',
            'rewrite' => false,
            'capabilities' => $termCaps,
        ]);

        register_taxonomy(self::LABEL, [self::ENTRY], [
            'labels' => [
                'name' => __('Labels', 'ruvelo-feedback'),
                'singular_name' => __('Label', 'ruvelo-feedback'),
                'add_new_item' => __('Add a label', 'ruvelo-feedback'),
                'search_items' => __('Search labels', 'ruvelo-feedback'),
                'not_found' => __('No labels yet.', 'ruvelo-feedback'),
            ],
            'public' => true,
            'publicly_queryable' => false,
            'hierarchical' => true, // Checkboxes in the editor, not free text.
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rest_base' => 'ruvelo-entry-labels',
            'rewrite' => false,
            'capabilities' => $termCaps,
        ]);

        register_post_status(self::MERGED, [
            'label' => _x('Merged', 'request status', 'ruvelo-feedback'),
            'public' => false,
            'internal' => false,
            'protected' => true,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => false,
            'show_in_admin_status_list' => true,
            /* translators: %s: number of merged requests */
            'label_count' => _n_noop('Merged <span class="count">(%s)</span>', 'Merged <span class="count">(%s)</span>', 'ruvelo-feedback'),
        ]);

        self::registerMeta();
    }

    /**
     * Every primitive capability maps to the one feedback capability, so
     * only managers write requests and entries through the admin or core REST.
     *
     * @return array<string, string>
     */
    private static function capabilities(): array
    {
        $caps = [];
        foreach (['edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'create_posts'] as $cap) {
            $caps[$cap] = Settings::CAP;
        }

        return $caps;
    }

    private static function registerMeta(): void
    {
        // Read-only in core REST: changes go through the plugin's write path,
        // which keeps counts right and tells voters.
        $readonly = static fn (): bool => false;
        $meta = [
            '_ruvelo_status' => ['type' => 'string', 'default' => 'under_review'],
            '_ruvelo_votes' => ['type' => 'integer', 'default' => 0],
            '_ruvelo_pinned' => ['type' => 'boolean', 'default' => false],
            '_ruvelo_merged_into' => ['type' => 'integer', 'default' => 0],
            '_ruvelo_entry' => ['type' => 'integer', 'default' => 0],
        ];
        foreach ($meta as $key => $args) {
            register_post_meta(self::REQUEST, $key, $args + ['single' => true, 'show_in_rest' => false, 'auth_callback' => $readonly]);
        }
        register_post_meta(self::ENTRY, '_ruvelo_requests', ['type' => 'array', 'single' => true, 'show_in_rest' => false, 'auth_callback' => $readonly]);
    }

    public static function requestBase(): string
    {
        $base = apply_filters('ruvelo_feedback_request_slug', 'feedback/requests');

        return is_string($base) && $base !== '' ? trim($base, '/') : 'feedback/requests';
    }

    public static function changelogBase(): string
    {
        $base = apply_filters('ruvelo_feedback_changelog_slug', 'feedback/changelog');

        return is_string($base) && $base !== '' ? trim($base, '/') : 'feedback/changelog';
    }
}
