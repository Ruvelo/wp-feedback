<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Frontend;

use Ruvelo\WP\Feedback\PostTypes;

/**
 * Where the portal lives. The setup screen creates the pages and stores
 * their ids; the changelog is the entry post type's archive.
 */
final class Urls
{
    public const PAGES_OPTION = 'ruvelo_feedback_pages';

    /**
     * @return array<string, int> board, roadmap, submit => page id
     */
    public static function pages(): array
    {
        $pages = get_option(self::PAGES_OPTION, []);
        $clean = [];
        foreach (is_array($pages) ? $pages : [] as $key => $id) {
            if (is_string($key) && is_numeric($id) && get_post_status((int) $id) === 'publish') {
                $clean[$key] = (int) $id;
            }
        }

        return $clean;
    }

    public static function page(string $key): string
    {
        $id = self::pages()[$key] ?? 0;

        return $id > 0 ? (string) get_permalink($id) : '';
    }

    public static function board(): string
    {
        return self::page('board');
    }

    public static function roadmap(): string
    {
        return self::page('roadmap');
    }

    public static function changelog(): string
    {
        return (string) get_post_type_archive_link(PostTypes::ENTRY);
    }

    public static function feed(): string
    {
        return (string) get_post_type_archive_feed_link(PostTypes::ENTRY);
    }

    /**
     * The "Suggest a feature" form: its own page, or the board with
     * ?ruvelo_suggest=1 when there is no such page.
     */
    public static function submit(string $title = ''): string
    {
        $url = self::page('submit');
        if ($url === '') {
            $board = self::board();
            $url = $board !== '' ? add_query_arg('ruvelo_suggest', '1', $board) : '';
        }
        if ($url !== '' && $title !== '') {
            $url = add_query_arg('fb_title', rawurlencode($title), $url);
        }

        return $url;
    }

    /**
     * The board URL with filters.
     *
     * @param array<string, string|int|null> $args
     */
    public static function boardWith(array $args, string $base = ''): string
    {
        $base = $base !== '' ? $base : (self::board() !== '' ? self::board() : self::here());
        $base = remove_query_arg(['fb_status', 'fb_category', 'fb_tag', 'fb_sort', 'fb_q', 'fb_page', 'ruvelo_feedback'], $base);
        $args = array_filter($args, static fn ($value): bool => $value !== null && $value !== '');

        return $args === [] ? $base : add_query_arg(array_map('rawurlencode', array_map('strval', $args)), $base);
    }

    /**
     * The current URL without the plugin's notice parameters.
     */
    public static function here(): string
    {
        global $wp;

        $path = is_object($wp) && isset($wp->request) ? (string) $wp->request : '';
        $url = home_url($path !== '' ? user_trailingslashit($path) : '/');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the query string to rebuild links.
        $query = array_map(static fn ($value) => is_string($value) ? sanitize_text_field(wp_unslash($value)) : '', $_GET);
        unset($query['ruvelo_feedback'], $query['ruvelo_msg']);

        return $query === [] ? $url : add_query_arg(array_map('rawurlencode', $query), $url);
    }
}
