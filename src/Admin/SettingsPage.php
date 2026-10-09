<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Admin;

use Ruvelo\WP\Feedback\Frontend\Urls;
use Ruvelo\WP\Feedback\Notifications;
use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Setup;

/**
 * Feedback → Settings: who can post and vote, moderation, emails, statuses,
 * and the one-click "Create the feedback pages".
 */
final class SettingsPage
{
    public const SLUG = 'ruvelo-feedback-settings';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 20);
        add_action('admin_init', [self::class, 'settings']);
        add_action('admin_post_ruvelo_feedback_setup', [self::class, 'setup']);
    }

    public static function menu(): void
    {
        add_submenu_page('edit.php?post_type='.PostTypes::REQUEST, __('Feedback settings', 'ruvelo-feedback'), __('Settings', 'ruvelo-feedback'), 'manage_options', self::SLUG, [self::class, 'render']);
    }

    public static function settings(): void
    {
        register_setting('ruvelo_feedback', Settings::OPTION, [
            'type' => 'object',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default' => Settings::defaults(),
            'show_in_rest' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $pick = static fn (string $key, array $allowed, string $default): string => isset($input[$key]) && is_string($input[$key]) && in_array($input[$key], $allowed, true) ? $input[$key] : $default;

        $labels = [];
        $colors = [];
        foreach (array_keys(Settings::statuses()) as $key) {
            if (isset($input['status_labels'][$key]) && is_string($input['status_labels'][$key])) {
                $labels[$key] = sanitize_text_field($input['status_labels'][$key]);
            }
            if (isset($input['status_colors'][$key]) && is_string($input['status_colors'][$key]) && Settings::validColor($input['status_colors'][$key])) {
                $colors[$key] = $input['status_colors'][$key];
            }
        }
        $notify = isset($input['notify_statuses']) && is_array($input['notify_statuses'])
            ? array_values(array_filter(array_map('sanitize_key', $input['notify_statuses']), [Settings::class, 'isStatus']))
            : [];

        $clean = [
            'name' => isset($input['name']) && is_string($input['name']) ? sanitize_text_field($input['name']) : '',
            'post' => $pick('post', ['users', 'anyone'], 'users'),
            'vote' => $pick('vote', ['users', 'anyone'], 'users'),
            'moderation' => $pick('moderation', ['none', 'guests', 'all'], 'guests'),
            'emails' => ! empty($input['emails']),
            'notify_statuses' => $notify,
            'status_labels' => $labels,
            'status_colors' => $colors,
            'color_scheme' => $pick('color_scheme', ['light', 'auto', 'dark'], 'light'),
            'per_page' => max(5, min(100, absint($input['per_page'] ?? 20))),
            'similar' => max(1, min(20, absint($input['similar'] ?? 5))),
            'batch_size' => max(1, min(500, absint($input['batch_size'] ?? 50))),
            'delete_data' => ! empty($input['delete_data']),
        ];
        Settings::flush();

        return $clean;
    }

    public static function setup(): void
    {
        check_admin_referer('ruvelo_feedback_setup');
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Only administrators can create pages here.', 'ruvelo-feedback'), '', ['response' => 403]);
        }
        $result = Setup::createPages();
        $args = is_array($result) ? ['ruvelo_setup' => 'done'] : ['ruvelo_setup' => 'failed'];
        wp_safe_redirect(add_query_arg($args, admin_url('edit.php?post_type='.PostTypes::REQUEST.'&page='.self::SLUG)));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $s = Settings::all();
        $name = Settings::OPTION;
        $pages = Urls::pages();
        $statuses = Settings::statuses();
        $defaults = [
            'under_review' => __('Under review', 'ruvelo-feedback'),
            'planned' => __('Planned', 'ruvelo-feedback'),
            'in_progress' => __('In progress', 'ruvelo-feedback'),
            'shipped' => __('Shipped', 'ruvelo-feedback'),
            'closed' => __('Closed', 'ruvelo-feedback'),
        ];
        $notify = is_array($s['notify_statuses'] ?? null) ? $s['notify_statuses'] : [];
        $radio = static function (string $key, string $value, string $label, string $help = '') use ($s, $name): void {
            printf(
                '<label class="ruvelo-fb-choice"><input type="radio" name="%s[%s]" value="%s" %s> <span><strong>%s</strong>%s</span></label>',
                esc_attr($name),
                esc_attr($key),
                esc_attr($value),
                checked(($s[$key] ?? '') === $value, true, false),
                esc_html($label),
                $help !== '' ? '<br><span class="description">'.esc_html($help).'</span>' : ''
            );
        };
        ?>
        <div class="wrap ruvelo-fb-admin">
            <h1><?php esc_html_e('Feedback settings', 'ruvelo-feedback'); ?></h1>

            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
            $setup = isset($_GET['ruvelo_setup']) ? sanitize_key(wp_unslash($_GET['ruvelo_setup'])) : '';
        if ($setup === 'done') {
            echo '<div class="notice notice-success"><p>'.esc_html__('Your feedback pages are ready.', 'ruvelo-feedback').'</p></div>';
        } elseif ($setup === 'failed') {
            echo '<div class="notice notice-error"><p>'.esc_html__('The pages couldn’t be created. Check that you can publish pages, then try again.', 'ruvelo-feedback').'</p></div>';
        }
        ?>

            <div class="ruvelo-fb-setup">
                <span class="ruvelo-fb-setup-mark" aria-hidden="true">F</span>
                <div>
                    <?php if (! isset($pages['board'])) { ?>
                        <h2><?php esc_html_e('Give your users a feedback portal', 'ruvelo-feedback'); ?></h2>
                        <p><?php esc_html_e('Creates a Feedback page with the board, and Roadmap and Suggest a feature pages under it. The changelog lives at its own address. You can edit every page afterwards like any other.', 'ruvelo-feedback'); ?></p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="ruvelo_feedback_setup">
                            <?php wp_nonce_field('ruvelo_feedback_setup'); ?>
                            <?php submit_button(__('Create the feedback pages', 'ruvelo-feedback'), 'primary', 'submit', false); ?>
                        </form>
                    <?php } else { ?>
                        <h2><?php esc_html_e('Your feedback portal', 'ruvelo-feedback'); ?></h2>
                        <ul class="ruvelo-fb-setup-links">
                            <?php foreach (['board' => __('Feedback board', 'ruvelo-feedback'), 'roadmap' => __('Roadmap', 'ruvelo-feedback'), 'submit' => __('Suggest a feature', 'ruvelo-feedback')] as $key => $label) { ?>
                                <?php if (isset($pages[$key])) { ?>
                                    <li><a href="<?php echo esc_url((string) get_permalink($pages[$key])); ?>"><?php echo esc_html($label); ?></a></li>
                                <?php } ?>
                            <?php } ?>
                            <li><a href="<?php echo esc_url(Urls::changelog()); ?>"><?php esc_html_e('Changelog', 'ruvelo-feedback'); ?></a></li>
                            <li><a href="<?php echo esc_url(Urls::feed()); ?>"><?php esc_html_e('Changelog RSS', 'ruvelo-feedback'); ?></a></li>
                        </ul>
                        <p class="description"><?php esc_html_e('Add the “What’s New” block to your header to show people what changed since their last visit.', 'ruvelo-feedback'); ?></p>
                    <?php } ?>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('ruvelo_feedback'); ?>

                <h2 class="title"><?php esc_html_e('Who can take part', 'ruvelo-feedback'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Posting requests', 'ruvelo-feedback'); ?></th>
                        <td><fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e('Posting requests', 'ruvelo-feedback'); ?></legend>
                            <?php $radio('post', 'users', __('Signed-in users', 'ruvelo-feedback')); ?>
                            <?php $radio('post', 'anyone', __('Anyone, with an email address', 'ruvelo-feedback'), __('Guests leave a name and email, so they can be told when it ships.', 'ruvelo-feedback')); ?>
                        </fieldset></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Voting', 'ruvelo-feedback'); ?></th>
                        <td><fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e('Voting', 'ruvelo-feedback'); ?></legend>
                            <?php $radio('vote', 'users', __('Signed-in users', 'ruvelo-feedback'), __('One vote per account.', 'ruvelo-feedback')); ?>
                            <?php $radio('vote', 'anyone', __('Anyone who confirms an email address', 'ruvelo-feedback'), __('Guests get a link by email; the vote counts once they click it. One vote per address.', 'ruvelo-feedback')); ?>
                        </fieldset></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Moderation', 'ruvelo-feedback'); ?></th>
                        <td><fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e('Moderation', 'ruvelo-feedback'); ?></legend>
                            <?php $radio('moderation', 'guests', __('Hold requests from guests', 'ruvelo-feedback')); ?>
                            <?php $radio('moderation', 'all', __('Hold every new request', 'ruvelo-feedback')); ?>
                            <?php $radio('moderation', 'none', __('Publish right away', 'ruvelo-feedback')); ?>
                            <p class="description"><?php esc_html_e('Held requests wait under Feedback → Moderation. Comments follow your Discussion settings.', 'ruvelo-feedback'); ?></p>
                        </fieldset></td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('Emails', 'ruvelo-feedback'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Close the loop', 'ruvelo-feedback'); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[emails]" value="1" <?php checked(! empty($s['emails'])); ?>> <?php esc_html_e('Email authors and voters when a request changes status', 'ruvelo-feedback'); ?></label>
                            <p class="description"><?php esc_html_e('Sent in the background by WP-Cron, a batch at a time, never while someone waits for a page. Every email has an unsubscribe link.', 'ruvelo-feedback'); ?></p>
                            <?php $pending = Notifications::pending(); ?>
                            <?php if ($pending > 0) { ?>
                                <p class="description">
                                    <?php
                                /* translators: %s: number of status changes */
                                echo esc_html(sprintf(_n('%s status change is waiting to be emailed.', '%s status changes are waiting to be emailed.', $pending, 'ruvelo-feedback'), number_format_i18n($pending)));
                                ?>
                                </p>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Email about', 'ruvelo-feedback'); ?></th>
                        <td><fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e('Statuses that send an email', 'ruvelo-feedback'); ?></legend>
                            <?php foreach ($statuses as $key => $status) { ?>
                                <label style="margin-right: 1.25rem"><input type="checkbox" name="<?php echo esc_attr($name); ?>[notify_statuses][]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $notify, true)); ?>> <?php echo esc_html($status['label']); ?></label>
                            <?php } ?>
                        </fieldset></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ruvelo-fb-batch"><?php esc_html_e('Emails per batch', 'ruvelo-feedback'); ?></label></th>
                        <td><input type="number" id="ruvelo-fb-batch" class="small-text" min="1" max="500" name="<?php echo esc_attr($name); ?>[batch_size]" value="<?php echo esc_attr((string) Settings::int('batch_size')); ?>"> <span class="description"><?php esc_html_e('Per cron run, about a minute apart.', 'ruvelo-feedback'); ?></span></td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('Statuses', 'ruvelo-feedback'); ?></h2>
                <p><?php esc_html_e('Rename a status or change its colour. Developers can add statuses with the ruvelo_feedback_statuses filter.', 'ruvelo-feedback'); ?></p>
                <table class="widefat striped ruvelo-fb-statuses" style="max-width: 44rem">
                    <thead><tr><th><?php esc_html_e('Status', 'ruvelo-feedback'); ?></th><th><?php esc_html_e('Label', 'ruvelo-feedback'); ?></th><th><?php esc_html_e('Colour', 'ruvelo-feedback'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($statuses as $key => $status) { ?>
                        <tr>
                            <td><code><?php echo esc_html($key); ?></code></td>
                            <td><input type="text" class="regular-text" name="<?php echo esc_attr($name); ?>[status_labels][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($status['label']); ?>" placeholder="<?php echo esc_attr($defaults[$key] ?? ''); ?>" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: status key */ __('Label for %s', 'ruvelo-feedback'), $key)); ?>"></td>
                            <td>
                                <select name="<?php echo esc_attr($name); ?>[status_colors][<?php echo esc_attr($key); ?>]" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: status key */ __('Colour for %s', 'ruvelo-feedback'), $key)); ?>">
                                    <?php foreach (['neutral' => __('Grey', 'ruvelo-feedback'), 'accent' => __('Ultramarine', 'ruvelo-feedback'), 'lilac' => __('Lilac', 'ruvelo-feedback'), 'warning' => __('Amber', 'ruvelo-feedback'), 'success' => __('Green', 'ruvelo-feedback'), 'danger' => __('Red', 'ruvelo-feedback')] as $tone => $label) { ?>
                                        <option value="<?php echo esc_attr($tone); ?>" <?php selected($status['color'], $tone); ?>><?php echo esc_html($label); ?></option>
                                    <?php } ?>
                                </select>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>

                <h2 class="title"><?php esc_html_e('Look', 'ruvelo-feedback'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ruvelo-fb-name"><?php esc_html_e('Product name', 'ruvelo-feedback'); ?></label></th>
                        <td><input type="text" id="ruvelo-fb-name" class="regular-text" name="<?php echo esc_attr($name); ?>[name]" value="<?php echo esc_attr((string) ($s['name'] ?? '')); ?>" placeholder="<?php echo esc_attr((string) get_bloginfo('name')); ?>"> <p class="description"><?php esc_html_e('Used in emails and the changelog. Leave empty to use the site title.', 'ruvelo-feedback'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Colour scheme', 'ruvelo-feedback'); ?></th>
                        <td><fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e('Colour scheme', 'ruvelo-feedback'); ?></legend>
                            <?php $radio('color_scheme', 'light', __('Light', 'ruvelo-feedback'), __('Best with light themes, like most.', 'ruvelo-feedback')); ?>
                            <?php $radio('color_scheme', 'auto', __('Follow each visitor’s system setting', 'ruvelo-feedback'), __('For themes that switch to dark mode too.', 'ruvelo-feedback')); ?>
                            <?php $radio('color_scheme', 'dark', __('Dark', 'ruvelo-feedback')); ?>
                        </fieldset></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ruvelo-fb-per-page"><?php esc_html_e('Requests per page', 'ruvelo-feedback'); ?></label></th>
                        <td><input type="number" id="ruvelo-fb-per-page" class="small-text" min="5" max="100" name="<?php echo esc_attr($name); ?>[per_page]" value="<?php echo esc_attr((string) Settings::int('per_page')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ruvelo-fb-similar"><?php esc_html_e('Similar requests to suggest', 'ruvelo-feedback'); ?></label></th>
                        <td><input type="number" id="ruvelo-fb-similar" class="small-text" min="1" max="20" name="<?php echo esc_attr($name); ?>[similar]" value="<?php echo esc_attr((string) Settings::int('similar')); ?>"></td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('When you delete the plugin', 'ruvelo-feedback'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Your data', 'ruvelo-feedback'); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[delete_data]" value="1" <?php checked(! empty($s['delete_data'])); ?>> <?php esc_html_e('Also delete every request, vote, changelog entry and setting', 'ruvelo-feedback'); ?></label>
                            <p class="description"><?php esc_html_e('Off by default: deleting the plugin keeps your content, so you can reinstall it later. Scheduled emails are always cleared.', 'ruvelo-feedback'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save changes', 'ruvelo-feedback')); ?>
            </form>
        </div>
        <?php
    }
}
