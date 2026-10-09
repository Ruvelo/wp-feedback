<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Admin;

use Ruvelo\WP\Feedback\Changelog;
use Ruvelo\WP\Feedback\Frontend\Urls;
use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Support\Html;
use WP_Post;
use WP_Query;

/**
 * Requests and entries in the standard admin screens: columns, filters,
 * bulk status changes, a moderation count, and the feedback panels in the
 * editor.
 */
final class Admin
{
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_menu', [self::class, 'order'], 99);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);

        $type = PostTypes::REQUEST;
        add_filter("manage_{$type}_posts_columns", [self::class, 'columns']);
        add_action("manage_{$type}_posts_custom_column", [self::class, 'column'], 10, 2);
        add_filter("manage_edit-{$type}_sortable_columns", [self::class, 'sortable']);
        add_filter('default_hidden_columns', [self::class, 'hiddenColumns'], 10, 2);
        add_action('pre_get_posts', [self::class, 'listQuery']);
        add_action('restrict_manage_posts', [self::class, 'statusFilter']);
        add_filter("bulk_actions-edit-{$type}", [self::class, 'bulkActions']);
        add_filter("handle_bulk_actions-edit-{$type}", [self::class, 'handleBulk'], 10, 3);
        add_filter('post_row_actions', [self::class, 'rowActions'], 10, 2);
        add_action('admin_post_ruvelo_feedback_approve', [self::class, 'approveLink']);
        add_action('admin_notices', [self::class, 'notices']);
        add_filter('display_post_states', [self::class, 'postStates'], 10, 2);

        add_action('add_meta_boxes_'.PostTypes::REQUEST, [self::class, 'requestBoxes']);
        add_action('add_meta_boxes_'.PostTypes::ENTRY, [self::class, 'entryBoxes']);
        add_action('save_post_'.PostTypes::REQUEST, [self::class, 'saveRequest'], 10, 2);
        add_action('save_post_'.PostTypes::ENTRY, [self::class, 'saveEntry'], 10, 2);

        SettingsPage::register();
        StatsPage::register();
    }

    public static function menu(): void
    {
        global $menu;

        $pending = self::pendingCount();
        $parent = 'edit.php?post_type='.PostTypes::REQUEST;
        add_submenu_page(
            $parent,
            __('Waiting for approval', 'ruvelo-feedback'),
            $pending > 0
                ? sprintf('%s <span class="awaiting-mod count-%d"><span class="pending-count">%s</span></span>', esc_html__('Moderation', 'ruvelo-feedback'), $pending, esc_html(number_format_i18n($pending)))
                : esc_html__('Moderation', 'ruvelo-feedback'),
            Settings::CAP,
            $parent.'&post_status=pending'
        );

        if ($pending > 0 && is_array($menu)) {
            foreach ($menu as $key => $item) {
                if (isset($item[2]) && $item[2] === $parent) {
                    $menu[$key][0] .= sprintf(' <span class="awaiting-mod count-%d"><span class="pending-count">%s</span></span>', $pending, esc_html(number_format_i18n($pending))); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Adding the moderation count, as core does for comments.
                }
            }
        }
    }

    /**
     * Requests, Moderation, Changelog, then the rest.
     */
    public static function order(): void
    {
        global $submenu;

        $parent = 'edit.php?post_type='.PostTypes::REQUEST;
        if (! isset($submenu[$parent]) || ! is_array($submenu[$parent])) {
            return;
        }
        $rank = static function (array $item) use ($parent): int {
            $slug = (string) ($item[2] ?? '');
            $order = [$parent => 0, $parent.'&post_status=pending' => 1, 'edit.php?post_type='.PostTypes::ENTRY => 2, 'post-new.php?post_type='.PostTypes::REQUEST => 3];
            foreach ($order as $key => $position) {
                if ($slug === $key) {
                    return $position;
                }
            }

            return str_contains($slug, 'taxonomy') ? 4 : 5;
        };
        $items = array_values($submenu[$parent]);
        usort($items, static fn (array $a, array $b): int => $rank($a) <=> $rank($b));
        $submenu[$parent] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering our own submenu.
    }

    public static function pendingCount(): int
    {
        $counts = wp_count_posts(PostTypes::REQUEST);

        return (int) ($counts->pending ?? 0);
    }

    public static function assets(string $hook): void
    {
        $screen = get_current_screen();
        if ($screen === null || ! in_array($screen->post_type, [PostTypes::REQUEST, PostTypes::ENTRY], true)) {
            return;
        }
        wp_enqueue_style('ruvelo-feedback-admin', RUVELO_FEEDBACK_URL.'assets/css/admin.css', [], RUVELO_FEEDBACK_VERSION);
    }

    // ------------------------------------------------------------------
    // The requests list

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public static function columns(array $columns): array
    {
        $out = [];
        foreach ($columns as $key => $label) {
            if ($key === 'title') {
                $out['ruvelo_votes'] = '<span title="'.esc_attr__('Votes', 'ruvelo-feedback').'">'.esc_html__('Votes', 'ruvelo-feedback').'</span>';
            }
            $out[$key] = $label;
            if ($key === 'title') {
                $out['ruvelo_status'] = __('Status', 'ruvelo-feedback');
            }
        }
        unset($out['author']);
        $out['ruvelo_author'] = __('Asked by', 'ruvelo-feedback');

        return $out;
    }

    public static function column(string $column, int $postId): void
    {
        $post = get_post($postId);
        if (! $post instanceof WP_Post) {
            return;
        }
        switch ($column) {
            case 'ruvelo_votes':
                echo '<span class="ruvelo-fb-admin-votes">'.esc_html(number_format_i18n(Requests::votes($postId))).'</span>';
                break;
            case 'ruvelo_status':
                echo Html::status(Requests::status($postId)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Html::status().
                if (Requests::isPinned($postId)) {
                    echo ' <span class="ruvelo-fb-admin-pin">'.esc_html__('Pinned', 'ruvelo-feedback').'</span>';
                }
                if ($post->post_status === PostTypes::MERGED) {
                    $into = (int) get_post_meta($postId, '_ruvelo_merged_into', true);
                    /* translators: %s: request title */
                    echo '<br><small>'.esc_html(sprintf(__('Merged into “%s”', 'ruvelo-feedback'), get_the_title($into))).'</small>';
                }
                break;
            case 'ruvelo_author':
                $email = (int) $post->post_author === 0 ? Requests::authorEmail($post) : '';
                echo esc_html(Requests::authorName($post));
                if ($email !== '') {
                    echo '<br><small>'.esc_html($email).' · '.esc_html__('guest', 'ruvelo-feedback').'</small>';
                }
                break;
        }
    }

    /**
     * @param list<string> $hidden
     * @return list<string>
     */
    public static function hiddenColumns(array $hidden, \WP_Screen $screen): array
    {
        if ($screen->id === 'edit-'.PostTypes::REQUEST) {
            $hidden[] = 'taxonomy-'.PostTypes::TAG;
        }

        return $hidden;
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public static function sortable(array $columns): array
    {
        $columns['ruvelo_votes'] = 'ruvelo_votes';

        return $columns;
    }

    public static function listQuery(WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query() || $query->get('post_type') !== PostTypes::REQUEST) {
            return;
        }
        if ($query->get('orderby') === 'ruvelo_votes') {
            $query->set('meta_key', '_ruvelo_votes');
            $query->set('orderby', 'meta_value_num');
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List filter.
        $status = isset($_GET['ruvelo_status']) ? sanitize_key(wp_unslash($_GET['ruvelo_status'])) : '';
        if ($status !== '' && Settings::isStatus($status)) {
            $query->set('meta_query', [['key' => '_ruvelo_status', 'value' => $status]]);
        }
    }

    public static function statusFilter(string $postType): void
    {
        if ($postType !== PostTypes::REQUEST) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List filter.
        $current = isset($_GET['ruvelo_status']) ? sanitize_key(wp_unslash($_GET['ruvelo_status'])) : '';
        echo '<label class="screen-reader-text" for="ruvelo-status-filter">'.esc_html__('Filter by status', 'ruvelo-feedback').'</label>';
        echo '<select name="ruvelo_status" id="ruvelo-status-filter"><option value="">'.esc_html__('All statuses', 'ruvelo-feedback').'</option>';
        foreach (Settings::statuses() as $key => $status) {
            printf('<option value="%s"%s>%s</option>', esc_attr($key), selected($current, $key, false), esc_html($status['label']));
        }
        echo '</select>';
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public static function bulkActions(array $actions): array
    {
        foreach (Settings::statuses() as $key => $status) {
            /* translators: %s: status */
            $actions['ruvelo_status_'.$key] = sprintf(__('Mark as %s', 'ruvelo-feedback'), $status['label']);
        }
        $actions['ruvelo_approve'] = __('Approve', 'ruvelo-feedback');

        return $actions;
    }

    /**
     * Bulk actions go through the same write path, so voters are told.
     *
     * @param list<int> $ids
     */
    public static function handleBulk(string $redirect, string $action, array $ids): string
    {
        if (! current_user_can(Settings::CAP)) {
            return $redirect;
        }
        $done = 0;
        if ($action === 'ruvelo_approve') {
            foreach ($ids as $id) {
                $done += Requests::approve((int) $id) ? 1 : 0;
            }
        } elseif (str_starts_with($action, 'ruvelo_status_')) {
            $status = substr($action, strlen('ruvelo_status_'));
            foreach ($ids as $id) {
                $done += Requests::changeStatus((int) $id, $status, '', get_current_user_id()) === true ? 1 : 0;
            }
        } else {
            return $redirect;
        }

        return add_query_arg('ruvelo_bulk', $done, $redirect);
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public static function rowActions(array $actions, WP_Post $post): array
    {
        if ($post->post_type === PostTypes::REQUEST && $post->post_status === 'pending' && current_user_can(Settings::CAP)) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=ruvelo_feedback_approve&request='.$post->ID), 'ruvelo_feedback_approve_'.$post->ID);
            $actions = ['ruvelo_approve' => '<a href="'.esc_url($url).'">'.esc_html__('Approve', 'ruvelo-feedback').'</a>'] + $actions;
        }

        return $actions;
    }

    public static function approveLink(): void
    {
        $id = isset($_GET['request']) ? absint($_GET['request']) : 0;
        check_admin_referer('ruvelo_feedback_approve_'.$id);
        if (! current_user_can(Settings::CAP)) {
            wp_die(esc_html__('You can’t manage feedback on this site.', 'ruvelo-feedback'), '', ['response' => 403]);
        }
        Requests::approve($id);
        wp_safe_redirect(add_query_arg('ruvelo_bulk', 1, wp_get_referer() ?: admin_url('edit.php?post_type='.PostTypes::REQUEST)));
        exit;
    }

    public static function notices(): void
    {
        $screen = get_current_screen();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
        if ($screen === null || $screen->post_type !== PostTypes::REQUEST || ! isset($_GET['ruvelo_bulk'])) {
            return;
        }
        $count = absint($_GET['ruvelo_bulk']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        /* translators: %s: number of requests */
        $text = sprintf(_n('%s request updated. Its author and voters will be emailed.', '%s requests updated. Their authors and voters will be emailed.', $count, 'ruvelo-feedback'), number_format_i18n($count));
        echo '<div class="notice notice-success is-dismissible"><p>'.esc_html($text).'</p></div>';
    }

    /**
     * @param array<string, string> $states
     * @return array<string, string>
     */
    public static function postStates(array $states, WP_Post $post): array
    {
        $pages = Urls::pages();
        $labels = ['board' => __('Feedback board', 'ruvelo-feedback'), 'roadmap' => __('Roadmap', 'ruvelo-feedback'), 'submit' => __('Feedback form', 'ruvelo-feedback')];
        foreach ($pages as $key => $id) {
            if ($id === $post->ID && isset($labels[$key])) {
                $states['ruvelo_feedback_'.$key] = $labels[$key];
            }
        }

        return $states;
    }

    // ------------------------------------------------------------------
    // Editor panels

    public static function requestBoxes(WP_Post $post): void
    {
        add_meta_box('ruvelo-feedback-request', __('Feedback', 'ruvelo-feedback'), [self::class, 'requestBox'], PostTypes::REQUEST, 'side', 'high');
        add_meta_box('ruvelo-feedback-history', __('Status history', 'ruvelo-feedback'), [self::class, 'historyBox'], PostTypes::REQUEST, 'normal', 'default');
    }

    public static function requestBox(WP_Post $post): void
    {
        wp_nonce_field('ruvelo_feedback_request_box', 'ruvelo_feedback_request_nonce');
        $status = Requests::status($post->ID);
        $targets = get_posts([
            'post_type' => PostTypes::REQUEST,
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $targets = array_values(array_filter($targets, static fn (WP_Post $target): bool => $target->ID !== $post->ID));
        $guest = (int) $post->post_author === 0 ? Requests::authorEmail($post) : '';
        ?>
        <div class="ruvelo-fb-box">
            <p class="ruvelo-fb-box-votes"><strong><?php echo esc_html(number_format_i18n(Requests::votes($post->ID))); ?></strong> <?php echo esc_html(_n('vote', 'votes', Requests::votes($post->ID), 'ruvelo-feedback')); ?></p>
            <?php if ($guest !== '') { ?>
                <p class="description">
                    <?php
                    /* translators: 1: name, 2: email */
                    echo esc_html(sprintf(__('Asked by %1$s (%2$s), a guest.', 'ruvelo-feedback'), Requests::authorName($post), $guest));
                ?>
                </p>
            <?php } ?>
            <?php if ($post->post_status === PostTypes::MERGED) { ?>
                <p><?php
                $into = (int) get_post_meta($post->ID, '_ruvelo_merged_into', true);
                /* translators: %s: request title */
                echo esc_html(sprintf(__('Merged into “%s”. Its page redirects there.', 'ruvelo-feedback'), get_the_title($into)));
                ?></p>
            <?php } else { ?>
                <p>
                    <label for="ruvelo-fb-status"><strong><?php esc_html_e('Status', 'ruvelo-feedback'); ?></strong></label><br>
                    <select id="ruvelo-fb-status" name="ruvelo_feedback_status" class="widefat">
                        <?php foreach (Settings::statuses() as $key => $item) { ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html($item['label']); ?></option>
                        <?php } ?>
                    </select>
                </p>
                <p>
                    <label for="ruvelo-fb-note"><strong><?php esc_html_e('Public note', 'ruvelo-feedback'); ?></strong> <span class="description"><?php esc_html_e('(optional)', 'ruvelo-feedback'); ?></span></label>
                    <textarea id="ruvelo-fb-note" name="ruvelo_feedback_note" rows="3" class="widefat" placeholder="<?php esc_attr_e('Shown on the request and in the email', 'ruvelo-feedback'); ?>"></textarea>
                </p>
                <p><label><input type="checkbox" name="ruvelo_feedback_notify" value="1" checked> <?php esc_html_e('Email the author and voters', 'ruvelo-feedback'); ?></label></p>
                <p><label><input type="checkbox" name="ruvelo_feedback_pinned" value="1" <?php checked(Requests::isPinned($post->ID)); ?>> <?php esc_html_e('Pin to the top of the board', 'ruvelo-feedback'); ?></label></p>
                <?php if ($targets !== []) { ?>
                    <p>
                        <label for="ruvelo-fb-merge"><strong><?php esc_html_e('Merge into', 'ruvelo-feedback'); ?></strong></label><br>
                        <select id="ruvelo-fb-merge" name="ruvelo_feedback_merge" class="widefat">
                            <option value=""><?php esc_html_e('Not a duplicate', 'ruvelo-feedback'); ?></option>
                            <?php foreach ($targets as $target) { ?>
                                <option value="<?php echo esc_attr((string) $target->ID); ?>"><?php echo esc_html(get_the_title($target)); ?></option>
                            <?php } ?>
                        </select>
                        <span class="description"><?php esc_html_e('Votes and comments move over; nobody is counted twice.', 'ruvelo-feedback'); ?></span>
                    </p>
                <?php } ?>
            <?php } ?>
        </div>
        <?php
    }

    public static function historyBox(WP_Post $post): void
    {
        $history = array_reverse(Requests::history($post->ID));
        if ($history === []) {
            echo '<p class="description">'.esc_html__('No status changes yet.', 'ruvelo-feedback').'</p>';

            return;
        }
        echo '<ol class="ruvelo-fb-admin-history">';
        foreach ($history as $change) {
            $by = get_userdata($change['by']);
            echo '<li>'.Html::status($change['to']).' <span class="description">'.esc_html(Html::date($change['at']).($by !== false ? ' · '.$by->display_name : '')).'</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::status() escapes.
            if ($change['note'] !== '') {
                echo '<p>'.esc_html($change['note']).'</p>';
            }
            echo '</li>';
        }
        echo '</ol>';
    }

    public static function saveRequest(int $postId, WP_Post $post): void
    {
        if (! isset($_POST['ruvelo_feedback_request_nonce']) || ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['ruvelo_feedback_request_nonce'])), 'ruvelo_feedback_request_box')) {
            return;
        }
        if (! current_user_can(Settings::CAP) || wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        $by = get_current_user_id();
        $status = isset($_POST['ruvelo_feedback_status']) ? sanitize_key(wp_unslash($_POST['ruvelo_feedback_status'])) : '';
        $note = isset($_POST['ruvelo_feedback_note']) ? sanitize_textarea_field(wp_unslash($_POST['ruvelo_feedback_note'])) : '';
        $notify = isset($_POST['ruvelo_feedback_notify']);

        // Avoid recursion: merging updates the post.
        remove_action('save_post_'.PostTypes::REQUEST, [self::class, 'saveRequest'], 10);
        if ($status !== '' && Settings::isStatus($status)) {
            Requests::changeStatus($postId, $status, $note, $by, $notify);
        }
        $pinned = isset($_POST['ruvelo_feedback_pinned']);
        if ($pinned !== Requests::isPinned($postId)) {
            Requests::pin($postId, $pinned);
        }
        $into = isset($_POST['ruvelo_feedback_merge']) ? absint($_POST['ruvelo_feedback_merge']) : 0;
        if ($into > 0) {
            Requests::merge($postId, $into, $by);
        }
        add_action('save_post_'.PostTypes::REQUEST, [self::class, 'saveRequest'], 10, 2);
    }

    public static function entryBoxes(WP_Post $post): void
    {
        add_meta_box('ruvelo-feedback-entry', __('Requests this ships', 'ruvelo-feedback'), [self::class, 'entryBox'], PostTypes::ENTRY, 'side', 'default');
    }

    public static function entryBox(WP_Post $post): void
    {
        wp_nonce_field('ruvelo_feedback_entry_box', 'ruvelo_feedback_entry_nonce');
        $linked = Changelog::requestIds($post->ID);
        $requests = get_posts([
            'post_type' => PostTypes::REQUEST,
            'post_status' => 'publish',
            'posts_per_page' => 300,
            'meta_key' => '_ruvelo_votes', // phpcs:ignore WordPress.DB.SlowDBQuery -- Sorting by votes, admin only.
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
        ]);
        echo '<p class="description">'.esc_html__('When this entry is published, these requests are marked Shipped and their voters are emailed a link to it.', 'ruvelo-feedback').'</p>';
        if ($requests === []) {
            echo '<p>'.esc_html__('No requests yet.', 'ruvelo-feedback').'</p>';

            return;
        }
        echo '<div class="ruvelo-fb-picklist">';
        foreach ($requests as $request) {
            $status = Requests::status($request->ID);
            if (in_array($status, ['closed'], true) && ! in_array($request->ID, $linked, true)) {
                continue;
            }
            printf(
                '<label><input type="checkbox" name="ruvelo_feedback_requests[]" value="%d" %s> %s <span class="ruvelo-fb-picklist-meta">%s · %s</span></label>',
                (int) $request->ID,
                checked(in_array($request->ID, $linked, true), true, false),
                esc_html(get_the_title($request)),
                esc_html(Settings::statusLabel($status)),
                /* translators: %s: number of votes */
                esc_html(sprintf(_n('%s vote', '%s votes', Requests::votes($request->ID), 'ruvelo-feedback'), number_format_i18n(Requests::votes($request->ID))))
            );
        }
        echo '</div>';
    }

    public static function saveEntry(int $postId, WP_Post $post): void
    {
        if (! isset($_POST['ruvelo_feedback_entry_nonce']) || ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['ruvelo_feedback_entry_nonce'])), 'ruvelo_feedback_entry_box')) {
            return;
        }
        if (! current_user_can(Settings::CAP) || wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        $ids = isset($_POST['ruvelo_feedback_requests']) && is_array($_POST['ruvelo_feedback_requests']) ? array_map('absint', wp_unslash($_POST['ruvelo_feedback_requests'])) : [];
        Changelog::setRequests($postId, array_values($ids));
    }
}
