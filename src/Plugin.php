<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

use WP_Post;

/**
 * Wires the plugin into WordPress. Nothing here talks to the outside world:
 * no remote requests, no telemetry, no admin notices outside its own screens.
 */
final class Plugin
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        add_action('init', [PostTypes::class, 'register'], 5);
        add_action('init', [self::class, 'init'], 10);
        add_action('rest_api_init', [Rest\Controller::class, 'register']);
        add_filter('posts_clauses', [Requests::class, 'trendingClauses'], 10, 2);
        add_action('transition_post_status', [Changelog::class, 'onTransition'], 10, 3);
        add_action(Notifications::CRON_HOOK, [Notifications::class, 'cron']);
        add_action('wp_after_insert_post', [self::class, 'defaults'], 10, 2);
        add_action('before_delete_post', [self::class, 'deleted'], 10, 2);
        foreach (['ruvelo_feedback_status_changed', 'ruvelo_feedback_requests_merged', 'ruvelo_feedback_request_created', 'ruvelo_feedback_request_approved', 'save_post_'.PostTypes::REQUEST] as $hook) {
            add_action($hook, [self::class, 'flushCounts']);
        }

        Frontend\Forms::register();
        Frontend\Templates::register();
        Privacy::register();

        if (function_exists('wp_register_ability')) {
            Abilities::register();
        }
        if (is_admin()) {
            Admin\Admin::register();
        }
        if (defined('WP_CLI') && WP_CLI) {
            Cli\Command::register();
        }
    }

    public static function init(): void
    {
        Install::maybeUpgrade();
        Install::ensureLabels();
        Blocks::register();
    }

    /**
     * Requests created in the admin or through core REST get the same
     * defaults as those posted through the plugin.
     */
    public static function defaults(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== PostTypes::REQUEST || wp_is_post_revision($postId)) {
            return;
        }
        $defaults = ['_ruvelo_status' => 'under_review', '_ruvelo_votes' => 0, '_ruvelo_pinned' => 0, '_ruvelo_status_changed' => time()];
        foreach ($defaults as $key => $value) {
            if (! metadata_exists('post', $postId, $key)) {
                update_post_meta($postId, $key, $value);
            }
        }
    }

    public static function deleted(int $postId, WP_Post $post): void
    {
        if ($post->post_type === PostTypes::REQUEST) {
            Votes::deleteFor($postId);
            do_action('ruvelo_feedback_request_deleted', $postId);
        }
    }

    public static function flushCounts(): void
    {
        wp_cache_delete('counts', 'ruvelo_feedback');
    }
}
