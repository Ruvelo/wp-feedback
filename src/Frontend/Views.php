<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Frontend;

use Ruvelo\WP\Feedback\Changelog;
use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Viewer;
use Ruvelo\WP\Feedback\Votes;
use WP_Post;
use WP_Term;

/**
 * Server-side rendering for the blocks, shortcodes and templates. Each view
 * loads a template from templates/, which a theme can override by copying
 * it to <theme>/ruvelo-feedback/<name>.php.
 */
final class Views
{
    /**
     * @param array<string, mixed> $view
     */
    public static function render(string $template, array $view): string
    {
        $file = locate_template('ruvelo-feedback/'.$template.'.php');
        if ($file === '') {
            $file = RUVELO_FEEDBACK_DIR.'templates/'.$template.'.php';
        }

        /**
         * Filters the template file used for a view.
         *
         * @param string $file
         * @param string $template e.g. "board", "parts/card".
         * @param array<string, mixed> $view
         */
        $file = (string) apply_filters('ruvelo_feedback_template', $file, $template, $view);
        if (! is_readable($file)) {
            return '';
        }

        ob_start();
        (static function (string $__file, array $view): void {
            include $__file;
        })($file, $view);

        return (string) ob_get_clean();
    }

    /**
     * Wrapper attributes for a view: the scope class and the colour scheme.
     *
     * @return array<string, string>
     */
    public static function wrapperArgs(string $name, string $extra = ''): array
    {
        $args = ['class' => trim('ruvelo-fb ruvelo-fb--'.$name.' '.$extra)];
        $scheme = Settings::string('color_scheme');
        if ($scheme === 'light' || $scheme === 'dark') {
            $args['data-theme'] = $scheme;
        }

        return $args;
    }

    /**
     * The same attributes as a string, for shortcodes and templates.
     */
    public static function wrapper(string $name, string $extra = ''): string
    {
        $html = [];
        foreach (self::wrapperArgs($name, $extra) as $key => $value) {
            $html[] = $key.'="'.esc_attr($value).'"';
        }

        return implode(' ', $html);
    }

    public static function enqueue(): void
    {
        wp_enqueue_style('ruvelo-feedback');
        wp_enqueue_script('ruvelo-feedback');
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $attrs
     */
    public static function board(array $attrs = [], ?string $wrapper = null): string
    {
        self::enqueue();
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters from the query string.
        if (isset($_GET['ruvelo_suggest']) && Urls::page('submit') === '') {
            return self::submit($attrs, $wrapper);
        }
        $filters = [
            'status' => isset($_GET['fb_status']) ? sanitize_key(wp_unslash($_GET['fb_status'])) : (string) ($attrs['status'] ?? 'open'),
            'category' => isset($_GET['fb_category']) ? sanitize_title(wp_unslash($_GET['fb_category'])) : (string) ($attrs['category'] ?? ''),
            'tag' => isset($_GET['fb_tag']) ? sanitize_title(wp_unslash($_GET['fb_tag'])) : '',
            'sort' => isset($_GET['fb_sort']) ? sanitize_key(wp_unslash($_GET['fb_sort'])) : (string) ($attrs['sort'] ?? 'top'),
            'search' => isset($_GET['fb_q']) ? sanitize_text_field(wp_unslash($_GET['fb_q'])) : '',
            'page' => isset($_GET['fb_page']) ? max(1, absint($_GET['fb_page'])) : 1,
        ];
        // phpcs:enable
        if (! in_array($filters['sort'], Requests::SORTS, true)) {
            $filters['sort'] = 'top';
        }
        if ($filters['status'] !== 'open' && $filters['status'] !== 'all' && ! Settings::isStatus($filters['status'])) {
            $filters['status'] = 'open';
        }
        $perPage = (int) ($attrs['perPage'] ?? 0) ?: Settings::int('per_page');
        $result = Requests::board($filters + ['per_page' => $perPage]);
        $ids = array_map(static fn (WP_Post $post): int => $post->ID, $result['posts']);
        $counts = Requests::countsByStatus();
        $open = 0;
        foreach (Settings::openStatuses() as $status) {
            $open += $counts[$status] ?? 0;
        }

        return self::render('board', [
            'wrapper' => $wrapper ?? self::wrapper('board', 'alignwide'),
            'attrs' => $attrs,
            'filters' => $filters,
            'filtered' => $filters['status'] !== 'open' || $filters['category'] !== '' || $filters['tag'] !== '' || $filters['search'] !== '',
            'posts' => $result['posts'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'voted' => Votes::votedAmong($ids, Viewer::key()),
            'counts' => $counts,
            'open' => $open,
            'all' => array_sum($counts),
            'categories' => self::terms(PostTypes::CATEGORY),
            'tags' => self::terms(PostTypes::TAG),
            'nav' => ! isset($attrs['showNav']) || (bool) $attrs['showNav'],
            'heading' => ! isset($attrs['showHeading']) || (bool) $attrs['showHeading'],
        ]);
    }

    public static function request(WP_Post $post, ?string $wrapper = null): string
    {
        self::enqueue();
        if (comments_open($post) && get_option('thread_comments')) {
            wp_enqueue_script('comment-reply');
        }
        $history = Requests::history($post->ID);
        $latest = null;
        foreach (array_reverse($history) as $change) {
            if ($change['note'] !== '') {
                $latest = $change;
                break;
            }
        }
        $duplicates = get_post_meta($post->ID, '_ruvelo_duplicates', true);
        $duplicates = array_values(array_filter(array_map(static fn ($id) => get_post((int) $id), is_array($duplicates) ? $duplicates : []), static fn ($dup): bool => $dup instanceof WP_Post));
        $entryId = Requests::entryId($post->ID);
        $entry = $entryId > 0 ? get_post($entryId) : null;
        $voter = Viewer::key();

        return self::render('request', [
            'wrapper' => $wrapper ?? self::wrapper('request', 'alignwide'),
            'post' => $post,
            'status' => Requests::status($post->ID),
            'votes' => Requests::votes($post->ID),
            'voted' => $voter !== '' && Votes::has($post->ID, $voter),
            'history' => $history,
            'latest' => $latest,
            'duplicates' => $duplicates,
            'entry' => $entry instanceof WP_Post && ($entry->post_status === 'publish' || Viewer::canManage()) ? $entry : null,
            'voters' => Votes::recent($post->ID),
            'manager' => Viewer::canManage(),
            'nav' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    public static function roadmap(array $attrs = [], ?string $wrapper = null): string
    {
        self::enqueue();
        $columns = Requests::roadmap((int) ($attrs['limit'] ?? 20) ?: 20);
        $ids = [];
        foreach ($columns as $posts) {
            foreach ($posts as $post) {
                $ids[] = $post->ID;
            }
        }

        return self::render('roadmap', [
            'wrapper' => $wrapper ?? self::wrapper('roadmap', 'alignwide'),
            'columns' => $columns,
            'voted' => Votes::votedAmong($ids, Viewer::key()),
            'nav' => ! isset($attrs['showNav']) || (bool) $attrs['showNav'],
            'heading' => ! isset($attrs['showHeading']) || (bool) $attrs['showHeading'],
        ]);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    public static function changelog(array $attrs = [], ?string $wrapper = null): string
    {
        self::enqueue();
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
        $label = isset($_GET['fb_label']) ? sanitize_title(wp_unslash($_GET['fb_label'])) : '';
        $page = isset($_GET['fb_page']) ? max(1, absint($_GET['fb_page'])) : 1;
        // phpcs:enable
        $perPage = (int) ($attrs['perPage'] ?? 10) ?: 10;
        $since = Changelog::since();
        $result = Changelog::entries($page, $perPage, $label);
        $drafts = [];
        if (Viewer::canManage()) {
            $drafts = get_posts(['post_type' => PostTypes::ENTRY, 'post_status' => ['draft', 'future', 'pending'], 'posts_per_page' => 5]);
        }

        return self::render('changelog', [
            'wrapper' => $wrapper ?? self::wrapper('changelog', 'alignwide'),
            'entries' => $result['posts'],
            'pages' => $result['pages'],
            'page' => $page,
            'label' => $label,
            'since' => $since,
            'drafts' => $drafts,
            'labels' => self::terms(PostTypes::LABEL),
            'nav' => ! isset($attrs['showNav']) || (bool) $attrs['showNav'],
            'heading' => ! isset($attrs['showHeading']) || (bool) $attrs['showHeading'],
        ]);
    }

    public static function entry(WP_Post $post, ?string $wrapper = null): string
    {
        self::enqueue();
        $newer = get_adjacent_post(false, '', false);
        $older = get_adjacent_post(false, '', true);

        return self::render('entry', [
            'wrapper' => $wrapper ?? self::wrapper('entry', 'alignwide'),
            'post' => $post,
            'newer' => $newer instanceof WP_Post ? $newer : null,
            'older' => $older instanceof WP_Post ? $older : null,
            'nav' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    public static function submit(array $attrs = [], ?string $wrapper = null): string
    {
        self::enqueue();
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Prefill only.
        $title = isset($_GET['fb_title']) ? sanitize_text_field(wp_unslash($_GET['fb_title'])) : '';
        $draft = isset($_GET['fb_draft']) ? Forms::draft(sanitize_key(wp_unslash($_GET['fb_draft']))) : null;
        // phpcs:enable
        if ($draft !== null) {
            $title = $draft['title'];
        }
        $similar = $title !== '' ? Requests::similar($title) : [];

        return self::render('submit', [
            'wrapper' => $wrapper ?? self::wrapper('submit'),
            'title' => $title,
            'draft' => $draft,
            'similar' => $similar,
            'categories' => self::terms(PostTypes::CATEGORY),
            'can_post' => Viewer::canPost(),
            'guest' => ! is_user_logged_in(),
            'nav' => ! isset($attrs['showNav']) || (bool) $attrs['showNav'],
        ]);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    public static function whatsNew(array $attrs = [], ?string $wrapper = null): string
    {
        self::enqueue();
        $limit = (int) ($attrs['limit'] ?? 5) ?: 5;
        $since = Changelog::since();
        $result = Changelog::entries(1, $limit);

        return self::render('whats-new', [
            'wrapper' => $wrapper ?? self::wrapper('whats-new', 'ruvelo-fb-inline'),
            'label' => is_string($attrs['label'] ?? null) && $attrs['label'] !== '' ? $attrs['label'] : __('What’s new', 'ruvelo-feedback'),
            'entries' => $result['posts'],
            'since' => $since,
            'unread' => Changelog::unreadCount(),
        ]);
    }

    // ------------------------------------------------------------------
    // Shared parts

    public static function nav(string $current): string
    {
        $links = array_filter([
            'board' => [Urls::board(), __('Feedback', 'ruvelo-feedback'), 'bulb'],
            'roadmap' => [Urls::roadmap(), __('Roadmap', 'ruvelo-feedback'), 'map'],
            'changelog' => [Urls::changelog(), __('Changelog', 'ruvelo-feedback'), 'sparkle'],
        ], static fn (array $link): bool => $link[0] !== '');
        if (count($links) < 2) {
            return '';
        }

        return self::render('parts/nav', ['links' => $links, 'current' => $current]);
    }

    public static function voteButton(WP_Post $post, bool $voted, bool $big = false): string
    {
        return self::render('parts/vote', ['post' => $post, 'voted' => $voted, 'big' => $big]);
    }

    public static function card(WP_Post $post, bool $voted): string
    {
        return self::render('parts/card', ['post' => $post, 'voted' => $voted]);
    }

    /**
     * The notice after a form posted back (no-JS path).
     */
    public static function notice(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
        $code = isset($_GET['ruvelo_msg']) ? sanitize_key(wp_unslash($_GET['ruvelo_msg'])) : '';
        if ($code === '') {
            return '';
        }
        $messages = [
            'posted' => [__('Thanks! Your request is on the board, with your vote.', 'ruvelo-feedback'), 'success'],
            'pending' => [__('Thanks! Your request will appear once the team has looked at it.', 'ruvelo-feedback'), 'success'],
            'voted' => [__('Your vote is in.', 'ruvelo-feedback'), 'success'],
            'unvoted' => [__('Your vote was taken back.', 'ruvelo-feedback'), 'info'],
            'confirm' => [__('Almost there: check your inbox and click the link to confirm your vote.', 'ruvelo-feedback'), 'info'],
            'confirmed' => [__('Thanks, your vote is confirmed.', 'ruvelo-feedback'), 'success'],
            'updated' => [__('Saved.', 'ruvelo-feedback'), 'success'],
            'merged' => [__('Merged. Votes and comments moved over.', 'ruvelo-feedback'), 'success'],
            'login' => [__('Sign in first, then try again.', 'ruvelo-feedback'), 'error'],
            'rate' => [__('That’s a lot in a short time. Wait a minute and try again.', 'ruvelo-feedback'), 'error'],
            'locked' => [__('Voting is closed for this request.', 'ruvelo-feedback'), 'error'],
            'expired' => [__('The form expired. Reload the page and try again.', 'ruvelo-feedback'), 'error'],
            'invalid' => [__('Something in the form wasn’t right. Check it and try again.', 'ruvelo-feedback'), 'error'],
            'title' => [__('Give your request a title of at least 3 characters.', 'ruvelo-feedback'), 'error'],
            'email' => [__('Enter a valid email address.', 'ruvelo-feedback'), 'error'],
            'badlink' => [__('That link didn’t work. It may be old: try voting again.', 'ruvelo-feedback'), 'error'],
        ];
        if (! isset($messages[$code])) {
            return '';
        }
        [$text, $kind] = $messages[$code];
        $role = $kind === 'error' ? 'alert' : 'status';

        return sprintf('<p class="ruvelo-fb-flash ruvelo-fb-flash--%s" role="%s">%s</p>', esc_attr($kind), esc_attr($role), esc_html($text));
    }

    /**
     * @return list<WP_Term>
     */
    public static function terms(string $taxonomy): array
    {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => $taxonomy === PostTypes::LABEL ? 'term_id' : 'name']);

        return is_array($terms) ? array_values(array_filter($terms, static fn ($term): bool => $term instanceof WP_Term)) : [];
    }

    /**
     * Pagination links using fb_page.
     */
    public static function pagination(int $page, int $pages, string $base): string
    {
        if ($pages <= 1) {
            return '';
        }
        $links = [];
        for ($i = 1; $i <= $pages; $i++) {
            $url = $i === 1 ? remove_query_arg('fb_page', $base) : add_query_arg('fb_page', $i, $base);
            $links[] = $i === $page
                ? '<span aria-current="page">'.esc_html((string) $i).'</span>'
                : '<a href="'.esc_url($url).'">'.esc_html((string) $i).'</a>';
        }

        return '<nav class="ruvelo-fb-pages" aria-label="'.esc_attr__('Pages', 'ruvelo-feedback').'">'.implode('', $links).'</nav>';
    }

    /**
     * A request's description as safe HTML.
     */
    public static function body(WP_Post $post): string
    {
        $content = $post->post_content;
        $html = has_blocks($content) ? do_blocks($content) : wpautop($content);

        return wp_kses_post($html);
    }

    public static function statusLabel(string $key): string
    {
        return Settings::statusLabel($key);
    }
}
