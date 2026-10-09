<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

/**
 * Halyard's feedback portal, for the Playground demo and screenshots:
 * `wp ruvelo-feedback demo --yes`. Halyard is a made-up B2B billing
 * platform; Maya Okafor runs product and Tom Reyes builds it.
 */
final class Demo
{
    /** @var array<string, int> name => user id */
    private static array $people = [];

    /**
     * @return array{requests: int, votes: int, comments: int, entries: int}
     */
    public static function seed(): array
    {
        $content = require RUVELO_FEEDBACK_DIR.'demo/content.php';
        if (! is_array($content)) {
            return ['requests' => 0, 'votes' => 0, 'comments' => 0, 'entries' => 0];
        }

        Notifications::pause(true);
        Install::install();
        Install::ensureLabels();
        update_option('blogname', 'Halyard');
        update_option('blogdescription', 'Billing for B2B software');
        update_option('comment_moderation', 0);
        update_option('comment_previously_approved', 0);
        update_option('thread_comments', 1);

        // The team.
        $maya = self::person('Maya Okafor', 'administrator');
        $tom = self::person('Tom Reyes', 'editor');
        self::person('Inès Laurent', 'subscriber');
        $kenji = self::person('Kenji Mori', 'subscriber');
        foreach ($content['customers'] as $name) {
            self::person((string) $name, 'subscriber');
        }
        $crowd = self::crowd();

        foreach ($content['categories'] as $name) {
            if (! term_exists((string) $name, PostTypes::CATEGORY)) {
                wp_insert_term((string) $name, PostTypes::CATEGORY);
            }
        }

        $closesAt = [];
        foreach ($content['entries'] as [, , $days, , , $ships]) {
            foreach ($ships as $ship) {
                $closesAt[$ship] = $days + 1;
            }
        }

        $requests = [];
        $votes = 0;
        foreach ($content['requests'] as [$key, $title, $category, $days, $count, $author, $body, $changes, $tags]) {
            $id = self::request((string) $title, (string) $body, self::person((string) $author), (float) $days, $category, $tags);
            $requests[$key] = $id;

            $closes = $closesAt[$key] ?? ($changes !== [] && end($changes)[0] === 'closed' ? end($changes)[1] + 1 : 0);
            mt_srand(crc32((string) $key)); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand -- The demo is the same every time.
            $pool = $crowd;
            shuffle($pool);
            $exclude = [self::person((string) $author), $maya, $kenji];
            $pool = array_values(array_diff($pool, $exclude));
            for ($i = 0; $i < $count - 1 && isset($pool[$i]); $i++) {
                $span = max(0.5, $days - $closes);
                $votes += self::vote($id, $pool[$i], $days - $span * (mt_rand(0, 1000) / 1000) ** 2.2); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- Seeded above.
            }
        }

        // Kenji voted for these; dark mode is the one he cares about most.
        foreach (['dark', 'sso', 'webhooks', 'keys'] as $key) {
            $votes += self::vote($requests[$key], $kenji, 5);
        }

        $comments = 0;
        foreach ($content['comments'] as [$key, $author, $days, $body, $replies]) {
            $parent = self::comment($requests[$key], (string) $author, (float) $days, (string) $body);
            $comments++;
            foreach ($replies as [$replyAuthor, $replyDays, $reply]) {
                self::comment($requests[$key], (string) $replyAuthor, (float) $replyDays, (string) $reply, $parent);
                $comments++;
            }
        }

        foreach ($content['requests'] as [$key, , , , , , , $changes]) {
            foreach ($changes as [$status, $days, $note, $by]) {
                Requests::changeStatus($requests[$key], (string) $status, (string) $note, $by === 'Maya' ? $maya : $tom, false);
                self::backdateLastChange($requests[$key], (float) $days);
            }
        }

        foreach ($content['duplicates'] as [$title, $into, $days, $count, $author, $body]) {
            $id = self::request((string) $title, (string) $body, self::person((string) $author), (float) $days, null, []);
            foreach (array_slice($crowd, 40, (int) $count) as $voter) {
                $votes += self::vote($id, $voter, $days - 0.5);
            }
            Requests::merge($id, $requests[$into], $maya);
        }

        // Changelog entries, oldest first, shipping the requests they announce.
        $entries = 0;
        foreach (array_reverse($content['entries']) as [$key, $title, $days, $labels, $cover, $ships, $body]) {
            $date = self::date((float) $days);
            $id = wp_insert_post([
                'post_type' => PostTypes::ENTRY,
                'post_title' => (string) $title,
                'post_content' => self::markdown((string) $body),
                'post_status' => 'draft',
                'post_author' => $maya,
                'post_date' => get_date_from_gmt($date),
                'post_date_gmt' => $date,
            ]);
            wp_set_object_terms($id, $labels, PostTypes::LABEL);
            if (is_string($cover)) {
                self::cover($id, $cover);
            }
            update_post_meta($id, '_ruvelo_requests', array_map(static fn (string $ship): int => $requests[$ship], $ships));
            wp_update_post(['ID' => $id, 'post_status' => 'publish', 'post_date' => get_date_from_gmt($date), 'post_date_gmt' => $date]);
            foreach ($ships as $ship) {
                self::backdateLastChange($requests[$ship], (float) $days);
            }
            $entries++;
        }

        [$draftTitle, $draftBody, $draftLabels] = $content['draft'];
        $draft = wp_insert_post(['post_type' => PostTypes::ENTRY, 'post_title' => $draftTitle, 'post_content' => self::markdown($draftBody), 'post_status' => 'draft', 'post_author' => $maya]);
        wp_set_object_terms($draft, $draftLabels, PostTypes::LABEL);
        update_post_meta($draft, '_ruvelo_requests', [$requests['dark']]);

        foreach ($content['pending'] as [$title, $category, $author, $body]) {
            Requests::create(['title' => $title, 'body' => $body, 'category' => $category], self::person((string) $author), null, true);
        }

        Requests::pin($requests['xero']);
        wp_set_object_terms($requests['usage'], ['Enterprise', 'Most requested'], PostTypes::TAG);

        // Kenji last looked at the changelog ten days ago: two updates are new to him.
        update_user_meta($kenji, 'ruvelo_feedback_seen', time() - 10 * DAY_IN_SECONDS);

        $pages = Setup::createPages();
        if (is_array($pages)) {
            self::header($pages);
        }
        Notifications::pause(false);
        wp_cache_flush();

        return ['requests' => count($requests), 'votes' => $votes, 'comments' => $comments, 'entries' => $entries];
    }

    public static function person(string $name, string $role = 'subscriber'): int
    {
        if (isset(self::$people[$name])) {
            return self::$people[$name];
        }
        $login = self::slug($name);
        $user = get_user_by('login', $login);
        if ($user === false && $name === 'Maya Okafor') {
            // The site's first administrator plays Maya.
            $admins = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID']);
            $user = $admins[0] ?? false;
        }
        if ($user !== false) {
            if ($user->display_name !== $name) {
                wp_update_user(['ID' => $user->ID, 'display_name' => $name, 'first_name' => strtok($name, ' ') ?: $name, 'last_name' => trim((string) strstr($name, ' '))]);
            }

            return self::$people[$name] = $user->ID;
        }
        $id = wp_insert_user([
            'user_login' => $login,
            'user_email' => $login.'@customers.halyard.test',
            'user_pass' => wp_generate_password(24),
            'display_name' => $name,
            'first_name' => strtok($name, ' ') ?: $name,
            'last_name' => trim((string) strstr($name, ' ')),
            'role' => $role,
        ]);

        return self::$people[$name] = is_int($id) ? $id : 0;
    }

    /**
     * Enough customers to vote, inserted directly: hashing 240 passwords
     * would take most of a minute and they never sign in.
     *
     * @return list<int>
     */
    private static function crowd(): array
    {
        global $wpdb;

        $ids = array_values(self::$people);
        $first = ['Alex', 'Sam', 'Noah', 'Emma', 'Liam', 'Ava', 'Lena', 'Omar', 'Ines', 'Arjun', 'Maria', 'Theo', 'Elif', 'Kai', 'Rosa', 'Jin', 'Hugo', 'Ana', 'Ben', 'Lina'];
        $last = ['Martins', 'Novak', 'Silva', 'Becker', 'Okoye', 'Larsen', 'Moreau', 'Kaur', 'Ivanov', 'Costa', 'Fischer', 'Park'];
        $hash = wp_hash_password(wp_generate_password(24));
        $caps = maybe_serialize(['subscriber' => true]);
        foreach ($first as $f) {
            foreach ($last as $l) {
                $name = $f.' '.$l;
                $login = self::slug($name);
                $existing = get_user_by('login', $login);
                if ($existing !== false) {
                    $ids[] = $existing->ID;

                    continue;
                }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Demo seeding.
                $wpdb->insert($wpdb->users, [
                    'user_login' => $login,
                    'user_pass' => $hash,
                    'user_nicename' => $login,
                    'user_email' => $login.'@customers.halyard.test',
                    'user_registered' => gmdate('Y-m-d H:i:s', time() - 300 * DAY_IN_SECONDS),
                    'display_name' => $name,
                ]);
                $id = (int) $wpdb->insert_id;
                $wpdb->insert($wpdb->usermeta, ['user_id' => $id, 'meta_key' => $wpdb->prefix.'capabilities', 'meta_value' => $caps]); // phpcs:ignore WordPress.DB.SlowDBQuery
                $wpdb->insert($wpdb->usermeta, ['user_id' => $id, 'meta_key' => 'first_name', 'meta_value' => $f]); // phpcs:ignore WordPress.DB.SlowDBQuery
                // phpcs:enable
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param list<string> $tags
     */
    private static function request(string $title, string $body, int $author, float $days, ?string $category, array $tags): int
    {
        $date = self::date($days);
        $id = Requests::create(['title' => $title, 'body' => $body, 'category' => $category ?? '', 'tags' => $tags], $author, null, false);
        if (! is_int($id)) {
            return 0;
        }
        wp_update_post(['ID' => $id, 'post_date' => get_date_from_gmt($date), 'post_date_gmt' => $date]);
        update_post_meta($id, '_ruvelo_status_changed', strtotime($date.' UTC'));
        self::backdateVotes($id, $date);

        return $id;
    }

    private static function vote(int $request, int $user, float $days): int
    {
        if (! Votes::add($request, Votes::userKey($user), $user)) {
            return 0;
        }
        self::backdateVotes($request, self::date(max(0.01, $days)), $user);
        Votes::recount($request);

        return 1;
    }

    private static function backdateVotes(int $request, string $date, int $user = 0): void
    {
        global $wpdb;

        $where = ['request_id' => $request];
        if ($user > 0) {
            $where['voter'] = Votes::userKey($user);
        }
        $wpdb->update(Install::votesTable(), ['created_at' => $date], $where); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Demo seeding.
    }

    private static function backdateLastChange(int $request, float $days): void
    {
        $history = Requests::history($request);
        if ($history === []) {
            return;
        }
        $at = (int) strtotime(self::date($days).' UTC');
        $history[count($history) - 1]['at'] = $at;
        update_post_meta($request, '_ruvelo_history', $history);
        update_post_meta($request, '_ruvelo_status_changed', $at);
    }

    private static function comment(int $post, string $author, float $days, string $body, int $parent = 0): int
    {
        $user = get_userdata(self::person($author));
        $date = self::date($days);
        $id = wp_insert_comment([
            'comment_post_ID' => $post,
            'comment_author' => $author,
            'comment_author_email' => $user !== false ? $user->user_email : '',
            'comment_content' => str_replace(['**'], [''], $body),
            'comment_parent' => $parent,
            'user_id' => $user !== false ? $user->ID : 0,
            'comment_date' => get_date_from_gmt($date),
            'comment_date_gmt' => $date,
            'comment_approved' => 1,
        ]);

        return is_int($id) ? $id : 0;
    }

    private static function cover(int $entry, string $svg): void
    {
        $png = RUVELO_FEEDBACK_DIR.'demo/assets/'.str_replace('.svg', '.jpg', $svg);
        if (! is_readable($png)) {
            return;
        }
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/image.php';
        $tmp = wp_tempnam(basename($png));
        copy($png, $tmp);
        $file = ['name' => basename($png), 'tmp_name' => $tmp];
        $id = media_handle_sideload($file, $entry);
        if (is_int($id)) {
            set_post_thumbnail($entry, $id);
            update_post_meta($id, '_wp_attachment_image_alt', '');
        }
    }

    /**
     * On a block theme, a header with links to the portal and the What's
     * New bell, saved as the theme's customised header.
     *
     * @param array<string, int> $pages
     */
    private static function header(array $pages): void
    {
        if (! wp_is_block_theme()) {
            return;
        }
        $theme = get_stylesheet();
        $existing = get_posts(['post_type' => 'wp_template_part', 'name' => 'header', 'post_status' => 'publish', 'tax_query' => [['taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => $theme]]]); // phpcs:ignore WordPress.DB.SlowDBQuery -- Demo seeding.
        if ($existing !== []) {
            return;
        }
        $link = static fn (string $label, string $url): string => '<!-- wp:navigation-link {"label":"'.esc_attr($label).'","url":"'.esc_url($url).'","kind":"custom"} /-->';
        $content = '<!-- wp:group {"align":"full","layout":{"type":"default"}} --><div class="wp-block-group alignfull">'
            .'<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
            .'<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->'
            .'<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">'
            .'<!-- wp:site-title {"level":0} /-->'
            .'<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} --><div class="wp-block-group">'
            .'<!-- wp:navigation {"overlayBackgroundColor":"base","overlayTextColor":"contrast","layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} -->'
            .$link('Pricing', home_url('/'))
            .$link('Feedback', (string) get_permalink($pages['board']))
            .$link('Roadmap', (string) get_permalink($pages['roadmap']))
            .$link('Changelog', Frontend\Urls::changelog())
            .'<!-- /wp:navigation -->'
            .'<!-- wp:ruvelo/whats-new /-->'
            .'</div><!-- /wp:group --></div><!-- /wp:group --></div><!-- /wp:group --></div><!-- /wp:group -->';
        $id = wp_insert_post(['post_type' => 'wp_template_part', 'post_name' => 'header', 'post_title' => 'Header', 'post_status' => 'publish', 'post_content' => $content]);
        if (is_int($id) && $id > 0) {
            wp_set_post_terms($id, [$theme], 'wp_theme');
            wp_set_post_terms($id, ['header'], 'wp_template_part_area');
        }
    }

    private static function date(float $daysAgo): string
    {
        return gmdate('Y-m-d H:i:s', time() - (int) round($daysAgo * DAY_IN_SECONDS));
    }

    private static function slug(string $name): string
    {
        return strtolower((string) preg_replace('/[^a-z]+/i', '.', remove_accents($name)));
    }

    /**
     * Just enough Markdown for the demo's changelog: paragraphs, lists, code
     * fences, **bold**, `code` and [links](url).
     */
    public static function markdown(string $markdown): string
    {
        $blocks = [];
        $chunks = preg_split("/\n{2,}/", trim(str_replace("\r\n", "\n", $markdown))) ?: [];
        $inline = static function (string $text): string {
            $html = esc_html($text);
            $html = (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html);
            $html = (string) preg_replace('/`([^`]+)`/u', '<code>$1</code>', $html);

            return (string) preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/u', static fn (array $m): string => '<a href="'.esc_url(html_entity_decode($m[2])).'">'.$m[1].'</a>', $html);
        };
        foreach ($chunks as $chunk) {
            if (str_starts_with($chunk, '```')) {
                $code = (string) preg_replace('/^```\w*\n|\n?```$/', '', $chunk);
                $blocks[] = "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>".esc_html($code)."</code></pre>\n<!-- /wp:code -->";
            } elseif (preg_match('/^- /', $chunk) === 1) {
                $items = array_map(static fn (string $line): string => "<!-- wp:list-item -->\n<li>".$inline((string) preg_replace('/^- /', '', $line))."</li>\n<!-- /wp:list-item -->", explode("\n", $chunk));
                $blocks[] = "<!-- wp:list -->\n<ul class=\"wp-block-list\">".implode("\n", $items)."</ul>\n<!-- /wp:list -->";
            } else {
                $blocks[] = "<!-- wp:paragraph -->\n<p>".$inline(str_replace("\n", ' ', $chunk))."</p>\n<!-- /wp:paragraph -->";
            }
        }

        return implode("\n\n", $blocks);
    }
}
