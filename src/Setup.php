<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

use Ruvelo\WP\Feedback\Frontend\Templates;
use Ruvelo\WP\Feedback\Frontend\Urls;
use WP_Error;

/**
 * "Create the feedback pages": a Feedback page with the board, and Roadmap
 * and Suggest a feature pages under it. On a block theme they use the wide
 * "Feedback portal" template.
 */
final class Setup
{
    /**
     * @return array<string, int>|WP_Error The page ids.
     */
    public static function createPages(): array|WP_Error
    {
        $existing = Urls::pages();
        $template = wp_is_block_theme() ? Templates::PAGE_TEMPLATE : '';
        $pages = [
            'board' => [__('Feedback', 'ruvelo-feedback'), 'feedback', '<!-- wp:ruvelo/feedback-board {"align":"wide"} /-->', 0],
            'roadmap' => [__('Roadmap', 'ruvelo-feedback'), 'roadmap', '<!-- wp:ruvelo/roadmap {"align":"wide"} /-->', 'board'],
            'submit' => [__('Suggest a feature', 'ruvelo-feedback'), 'new', '<!-- wp:ruvelo/submit-request /-->', 'board'],
        ];

        $ids = $existing;
        $author = get_current_user_id();
        if ($author === 0) {
            $admins = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID']);
            $author = (int) ($admins[0] ?? 0);
        }
        foreach ($pages as $key => [$title, $slug, $content, $parent]) {
            if (isset($ids[$key])) {
                continue;
            }
            $id = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $title,
                'post_name' => $slug,
                'post_content' => $content,
                'post_parent' => is_string($parent) ? ($ids[$parent] ?? 0) : 0,
                'comment_status' => 'closed',
                'post_author' => $author,
            ], true);
            if ($id instanceof WP_Error) {
                return $id;
            }
            if ($template !== '') {
                update_post_meta($id, '_wp_page_template', $template);
            }
            $ids[$key] = $id;
        }
        update_option(Urls::PAGES_OPTION, $ids);
        flush_rewrite_rules(false);

        /**
         * Fires after the feedback pages are created.
         *
         * @param array<string, int> $ids board, roadmap, submit => page id
         */
        do_action('ruvelo_feedback_pages_created', $ids);

        return $ids;
    }

    public static function hasPages(): bool
    {
        return isset(Urls::pages()['board']);
    }
}
