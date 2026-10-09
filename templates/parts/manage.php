<?php
/**
 * The manager's panel on a request page.
 *
 * @var array{post: WP_Post, status: string} $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Settings;

defined('ABSPATH') || exit;

$ruvelo_post = $view['post'];
$ruvelo_action = admin_url('admin-post.php');
$ruvelo_targets = get_posts([
    'post_type' => PostTypes::REQUEST,
    'post_status' => 'publish',
    'posts_per_page' => 101,
    'meta_key' => '_ruvelo_votes', // phpcs:ignore WordPress.DB.SlowDBQuery -- Sorting by votes, managers only.
    'orderby' => 'meta_value_num',
    'order' => 'DESC',
]);
$ruvelo_targets = array_values(array_filter($ruvelo_targets, static fn (WP_Post $target): bool => $target->ID !== $ruvelo_post->ID));
$ruvelo_hidden = static function (string $op) use ($ruvelo_post): void {
    echo '<input type="hidden" name="action" value="ruvelo_feedback_manage">';
    echo '<input type="hidden" name="op" value="'.esc_attr($op).'">';
    echo '<input type="hidden" name="request" value="'.esc_attr((string) $ruvelo_post->ID).'">';
    wp_nonce_field('ruvelo_feedback_manage_'.$ruvelo_post->ID, '_ruvelo_nonce', false);
};
?>
<div class="ruvelo-fb-panel ruvelo-fb-manage">
    <h2><?php esc_html_e('Manage', 'ruvelo-feedback'); ?></h2>
    <?php if ($ruvelo_post->post_status === 'pending') { ?>
        <form method="post" action="<?php echo esc_url($ruvelo_action); ?>">
            <?php $ruvelo_hidden('approve'); ?>
            <button type="submit" class="ruvelo-fb-btn ruvelo-fb-btn--small"><?php esc_html_e('Approve and publish', 'ruvelo-feedback'); ?></button>
        </form>
    <?php } ?>
    <form method="post" action="<?php echo esc_url($ruvelo_action); ?>">
        <?php $ruvelo_hidden('status'); ?>
        <label for="ruvelo-fb-m-status"><?php esc_html_e('Status', 'ruvelo-feedback'); ?></label>
        <select id="ruvelo-fb-m-status" name="status">
            <?php foreach (Settings::statuses() as $ruvelo_key => $ruvelo_status) { ?>
                <option value="<?php echo esc_attr($ruvelo_key); ?>" <?php selected($ruvelo_key, $view['status']); ?>><?php echo esc_html($ruvelo_status['label']); ?></option>
            <?php } ?>
        </select>
        <label for="ruvelo-fb-m-note"><?php esc_html_e('Public note', 'ruvelo-feedback'); ?> <span class="ruvelo-fb-muted"><?php esc_html_e('(optional)', 'ruvelo-feedback'); ?></span></label>
        <textarea id="ruvelo-fb-m-note" name="note" rows="3" maxlength="5000"></textarea>
        <label class="ruvelo-fb-check"><input type="checkbox" name="notify" value="1" checked> <?php esc_html_e('Email the author and voters', 'ruvelo-feedback'); ?></label>
        <div class="ruvelo-fb-row"><button type="submit" class="ruvelo-fb-btn ruvelo-fb-btn--small"><?php esc_html_e('Update status', 'ruvelo-feedback'); ?></button></div>
    </form>
    <details>
        <summary><?php esc_html_e('Pin, merge or edit', 'ruvelo-feedback'); ?></summary>
        <form method="post" action="<?php echo esc_url($ruvelo_action); ?>">
            <?php $ruvelo_hidden('pin'); ?>
            <input type="hidden" name="pinned" value="<?php echo Requests::isPinned($ruvelo_post->ID) ? '0' : '1'; ?>">
            <div class="ruvelo-fb-row"><button type="submit" class="ruvelo-fb-btn ruvelo-fb-btn--quiet ruvelo-fb-btn--small"><?php echo esc_html(Requests::isPinned($ruvelo_post->ID) ? __('Unpin', 'ruvelo-feedback') : __('Pin to the top', 'ruvelo-feedback')); ?></button></div>
        </form>
        <?php if ($ruvelo_targets !== []) { ?>
            <form method="post" action="<?php echo esc_url($ruvelo_action); ?>">
                <?php $ruvelo_hidden('merge'); ?>
                <label for="ruvelo-fb-m-into"><?php esc_html_e('Merge this duplicate into', 'ruvelo-feedback'); ?></label>
                <select id="ruvelo-fb-m-into" name="into" required>
                    <option value=""><?php esc_html_e('Choose a request…', 'ruvelo-feedback'); ?></option>
                    <?php foreach ($ruvelo_targets as $ruvelo_target) { ?>
                        <option value="<?php echo esc_attr((string) $ruvelo_target->ID); ?>"><?php echo esc_html(get_the_title($ruvelo_target).' ('.number_format_i18n(Requests::votes($ruvelo_target->ID)).')'); ?></option>
                    <?php } ?>
                </select>
                <p class="ruvelo-fb-hint"><?php esc_html_e('Votes and comments move over. Nobody is counted twice.', 'ruvelo-feedback'); ?></p>
                <div class="ruvelo-fb-row"><button type="submit" class="ruvelo-fb-btn ruvelo-fb-btn--quiet ruvelo-fb-btn--small"><?php esc_html_e('Merge', 'ruvelo-feedback'); ?></button></div>
            </form>
        <?php } ?>
        <?php $ruvelo_edit = get_edit_post_link($ruvelo_post->ID); ?>
        <?php if (is_string($ruvelo_edit) && $ruvelo_edit !== '') { ?>
            <div class="ruvelo-fb-row"><a class="ruvelo-fb-btn ruvelo-fb-btn--quiet ruvelo-fb-btn--small" href="<?php echo esc_url($ruvelo_edit); ?>"><?php esc_html_e('Edit in the admin', 'ruvelo-feedback'); ?></a></div>
        <?php } ?>
    </details>
</div>
