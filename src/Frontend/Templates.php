<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Frontend;

use Ruvelo\WP\Feedback\PostTypes;
use WP_Post;

/**
 * Templates for requests and the changelog. Block themes get block
 * templates (editable in the Site Editor); classic themes get a wrapper that
 * calls the theme's header and footer.
 */
final class Templates
{
    public const PAGE_TEMPLATE = 'ruvelo-feedback-portal';

    public static function register(): void
    {
        add_action('init', [self::class, 'blockTemplates'], 20);
        add_filter('template_include', [self::class, 'classic'], 20);
    }

    public static function blockTemplates(): void
    {
        if (! function_exists('register_block_template') || ! wp_is_block_theme()) {
            return;
        }

        $wrap = static fn (string $inner): string => '<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->'."\n"
            .'<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"72rem","wideSize":"72rem"}} -->'."\n"
            .'<main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60)">'.$inner.'</main>'."\n"
            .'<!-- /wp:group -->'."\n"
            .'<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->';

        register_block_template('ruvelo-feedback//single-'.PostTypes::REQUEST, [
            'title' => __('Single feature request', 'ruvelo-feedback'),
            'description' => __('One request from the feedback board, with votes, history and comments.', 'ruvelo-feedback'),
            'content' => $wrap('<!-- wp:ruvelo/feedback-request {"align":"wide"} /-->'),
        ]);
        register_block_template('ruvelo-feedback//single-'.PostTypes::ENTRY, [
            'title' => __('Single changelog entry', 'ruvelo-feedback'),
            'description' => __('One changelog entry and the requests it answers.', 'ruvelo-feedback'),
            'content' => $wrap('<!-- wp:ruvelo/changelog-entry {"align":"wide"} /-->'),
        ]);
        register_block_template('ruvelo-feedback//archive-'.PostTypes::ENTRY, [
            'title' => __('Changelog', 'ruvelo-feedback'),
            'description' => __('Every changelog entry, newest first.', 'ruvelo-feedback'),
            'content' => $wrap('<!-- wp:ruvelo/changelog {"align":"wide"} /-->'),
        ]);
        register_block_template('ruvelo-feedback//'.self::PAGE_TEMPLATE, [
            'title' => __('Feedback portal', 'ruvelo-feedback'),
            'description' => __('A wide page without a title, for the feedback board, roadmap and form.', 'ruvelo-feedback'),
            'content' => $wrap('<!-- wp:post-content {"align":"wide","layout":{"type":"constrained","contentSize":"72rem","wideSize":"72rem"}} /-->'),
            'post_types' => ['page'],
        ]);
    }

    /**
     * Classic themes: render our views inside the theme's header and footer.
     */
    public static function classic(string $template): string
    {
        if (wp_is_block_theme()) {
            return $template;
        }
        if (is_singular(PostTypes::REQUEST) || is_singular(PostTypes::ENTRY) || is_post_type_archive(PostTypes::ENTRY)) {
            // A theme can provide its own: single-ruvelo_request.php and so on.
            $own = locate_template(is_post_type_archive(PostTypes::ENTRY) ? ['archive-'.PostTypes::ENTRY.'.php'] : ['single-'.get_post_type().'.php']);
            if ($own !== '') {
                return $own;
            }

            return RUVELO_FEEDBACK_DIR.'templates/classic/wrapper.php';
        }

        return $template;
    }

    /**
     * What the classic wrapper shows.
     */
    public static function classicContent(): string
    {
        if (is_post_type_archive(PostTypes::ENTRY)) {
            return Views::changelog();
        }
        $post = get_queried_object();
        if ($post instanceof WP_Post && $post->post_type === PostTypes::REQUEST) {
            return Views::request($post);
        }
        if ($post instanceof WP_Post && $post->post_type === PostTypes::ENTRY) {
            return Views::entry($post);
        }

        return '';
    }
}
