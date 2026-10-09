<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

use Ruvelo\WP\Feedback\Frontend\Views;

/**
 * Assets, blocks, shortcodes and patterns.
 */
final class Blocks
{
    public const BLOCKS = ['feedback-board', 'roadmap', 'changelog', 'whats-new', 'submit-request', 'feedback-request', 'changelog-entry'];

    public static function register(): void
    {
        self::assets();
        foreach (self::BLOCKS as $block) {
            register_block_type(RUVELO_FEEDBACK_DIR.'blocks/'.$block);
        }
        self::shortcodes();
        self::patterns();
    }

    public static function assets(): void
    {
        $version = RUVELO_FEEDBACK_VERSION;
        wp_register_style('ruvelo-feedback', RUVELO_FEEDBACK_URL.'assets/css/feedback.css', [], $version);
        wp_register_script('ruvelo-feedback', RUVELO_FEEDBACK_URL.'assets/js/feedback.js', ['wp-i18n'], $version, ['in_footer' => true, 'strategy' => 'defer']);
        wp_localize_script('ruvelo-feedback', 'ruveloFeedback', [
            'rest' => esc_url_raw(rest_url('ruvelo-feedback/v1/')),
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'signedIn' => is_user_logged_in(),
        ]);
        wp_set_script_translations('ruvelo-feedback', 'ruvelo-feedback', RUVELO_FEEDBACK_DIR.'languages');

        wp_register_script(
            'ruvelo-feedback-blocks',
            RUVELO_FEEDBACK_URL.'assets/js/blocks.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n'],
            $version,
            ['in_footer' => false]
        );
        wp_set_script_translations('ruvelo-feedback-blocks', 'ruvelo-feedback', RUVELO_FEEDBACK_DIR.'languages');
    }

    private static function shortcodes(): void
    {
        $map = [
            'ruvelo_feedback_board' => static fn (array $atts): string => Views::board(self::atts($atts)),
            'ruvelo_feedback_roadmap' => static fn (array $atts): string => Views::roadmap(self::atts($atts)),
            'ruvelo_feedback_changelog' => static fn (array $atts): string => Views::changelog(self::atts($atts)),
            'ruvelo_feedback_whats_new' => static fn (array $atts): string => Views::whatsNew(self::atts($atts)),
            'ruvelo_feedback_submit' => static fn (array $atts): string => Views::submit(self::atts($atts)),
        ];
        foreach ($map as $tag => $callback) {
            add_shortcode($tag, static fn ($atts = []): string => $callback(is_array($atts) ? $atts : []));
        }
    }

    /**
     * Shortcode attributes in the blocks' camelCase: per_page => perPage,
     * nav="no" => showNav false.
     *
     * @param array<int|string, mixed> $atts
     * @return array<string, mixed>
     */
    private static function atts(array $atts): array
    {
        $out = [];
        foreach ($atts as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            $value = is_string($value) ? sanitize_text_field($value) : $value;
            $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
            if ($key === 'nav' || $key === 'heading') {
                $out['show'.ucfirst($key)] = ! in_array(strtolower((string) $value), ['0', 'no', 'false', 'off'], true);
            } else {
                $out[$camel] = $value;
            }
        }

        return $out;
    }

    private static function patterns(): void
    {
        register_block_pattern_category('ruvelo-feedback', ['label' => __('Feedback', 'ruvelo-feedback')]);

        register_block_pattern('ruvelo-feedback/board-page', [
            'title' => __('Feedback board page', 'ruvelo-feedback'),
            'description' => __('The board, with tabs to the roadmap and changelog.', 'ruvelo-feedback'),
            'categories' => ['ruvelo-feedback'],
            'postTypes' => ['page'],
            'blockTypes' => ['core/post-content'],
            'content' => '<!-- wp:ruvelo/feedback-board {"align":"wide"} /-->',
        ]);
        register_block_pattern('ruvelo-feedback/roadmap-page', [
            'title' => __('Roadmap page', 'ruvelo-feedback'),
            'categories' => ['ruvelo-feedback'],
            'postTypes' => ['page'],
            'content' => '<!-- wp:ruvelo/roadmap {"align":"wide"} /-->',
        ]);
        register_block_pattern('ruvelo-feedback/changelog-page', [
            'title' => __('Changelog page', 'ruvelo-feedback'),
            'categories' => ['ruvelo-feedback'],
            'postTypes' => ['page'],
            'content' => '<!-- wp:ruvelo/changelog {"align":"wide"} /-->',
        ]);
        register_block_pattern('ruvelo-feedback/roadmap-and-updates', [
            'title' => __('Roadmap and latest updates', 'ruvelo-feedback'),
            'description' => __('A compact roadmap next to a short changelog: good on a home or product page.', 'ruvelo-feedback'),
            'categories' => ['ruvelo-feedback'],
            'content' => '<!-- wp:group {"align":"wide","layout":{"type":"constrained","wideSize":"72rem"}} -->'
                .'<div class="wp-block-group alignwide"><!-- wp:heading {"align":"wide"} --><h2 class="wp-block-heading alignwide">'.esc_html__('What we’re working on', 'ruvelo-feedback').'</h2><!-- /wp:heading -->'
                .'<!-- wp:ruvelo/roadmap {"limit":5,"showNav":false,"showHeading":false,"align":"wide"} /-->'
                .'<!-- wp:heading {"align":"wide"} --><h2 class="wp-block-heading alignwide">'.esc_html__('Recently shipped', 'ruvelo-feedback').'</h2><!-- /wp:heading -->'
                .'<!-- wp:ruvelo/changelog {"perPage":3,"showNav":false,"showHeading":false,"align":"wide","className":"is-style-compact"} /--></div><!-- /wp:group -->',
        ]);
        register_block_pattern('ruvelo-feedback/header-whats-new', [
            'title' => __('Header link with What’s new', 'ruvelo-feedback'),
            'description' => __('A “Feedback” link and the What’s new bell, to drop into a header.', 'ruvelo-feedback'),
            'categories' => ['ruvelo-feedback', 'header'],
            'content' => '<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} --><div class="wp-block-group">'
                .'<!-- wp:paragraph --><p><a href="'.esc_url(Frontend\Urls::board() ?: home_url('/feedback/')).'">'.esc_html__('Feedback', 'ruvelo-feedback').'</a></p><!-- /wp:paragraph -->'
                .'<!-- wp:ruvelo/whats-new /--></div><!-- /wp:group -->',
        ]);
    }
}
