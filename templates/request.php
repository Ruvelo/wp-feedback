<?php
/**
 * One request: votes, the story so far, and the conversation.
 *
 * @var array<string, mixed> $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Comments;
use Ruvelo\WP\Feedback\Frontend\Urls;
use Ruvelo\WP\Feedback\Frontend\Views;
use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Support\Html;
use Ruvelo\WP\Feedback\Support\Text;
use Ruvelo\WP\Feedback\Viewer;

defined('ABSPATH') || exit;

/** @var WP_Post $ruvelo_post */
$ruvelo_post = $view['post'];
$ruvelo_status = (string) $view['status'];
$ruvelo_votes = (int) $view['votes'];
$ruvelo_category = get_the_terms($ruvelo_post->ID, PostTypes::CATEGORY);
$ruvelo_category = is_array($ruvelo_category) && $ruvelo_category !== [] ? $ruvelo_category[0] : null;
$ruvelo_tags = get_the_terms($ruvelo_post->ID, PostTypes::TAG);
$ruvelo_author = Requests::authorName($ruvelo_post);
$ruvelo_created = (int) strtotime($ruvelo_post->post_date_gmt.' UTC');
/** @var array{from: string, to: string, note: string, by: int, at: int, entry: int}|null $ruvelo_latest */
$ruvelo_latest = $view['latest'];
/** @var WP_Post|null $ruvelo_entry */
$ruvelo_entry = $view['entry'];
$ruvelo_board = Urls::board();
?>
<div <?php echo $view['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped attributes.?>>
    <?php if ($view['nav']) {
        echo Views::nav('board'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the template.
    } ?>
    <?php echo Views::notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Views::notice().?>

    <ol class="ruvelo-fb-crumbs" aria-label="<?php esc_attr_e('Breadcrumbs', 'ruvelo-feedback'); ?>">
        <?php if ($ruvelo_board !== '') { ?>
            <li><a href="<?php echo esc_url($ruvelo_board); ?>"><?php esc_html_e('Feedback', 'ruvelo-feedback'); ?></a></li>
            <?php if ($ruvelo_category !== null) { ?>
                <li><a href="<?php echo esc_url(Urls::boardWith(['fb_category' => $ruvelo_category->slug, 'fb_status' => 'all'])); ?>"><?php echo esc_html($ruvelo_category->name); ?></a></li>
            <?php } ?>
        <?php } ?>
        <li aria-current="page">
            <?php
            /* translators: %d: request number */
            echo esc_html(sprintf(__('Request #%d', 'ruvelo-feedback'), $ruvelo_post->ID));
?>
        </li>
    </ol>

    <div class="ruvelo-fb-cols">
        <article>
            <div class="ruvelo-fb-request-head">
                <?php echo Views::voteButton($ruvelo_post, (bool) $view['voted'], true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the template.?>
                <div>
                    <div class="ruvelo-fb-meta" style="margin-top: 0">
                        <?php echo Html::status($ruvelo_status); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
                        <?php if ($ruvelo_post->post_status === 'pending') { ?>
                            <span class="ruvelo-fb-chip ruvelo-fb-tone-warning"><?php esc_html_e('Waiting for approval', 'ruvelo-feedback'); ?></span>
                        <?php } ?>
                        <?php if (Requests::isPinned($ruvelo_post->ID)) { ?>
                            <span><?php echo Html::icon('pin'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?><?php esc_html_e('Pinned', 'ruvelo-feedback'); ?></span>
                        <?php } ?>
                    </div>
                    <h1><?php echo esc_html(get_the_title($ruvelo_post)); ?></h1>
                    <p class="ruvelo-fb-byline">
                        <?php echo Html::avatar($ruvelo_author, 'ruvelo-fb-avatar--small'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
                        <span>
                            <?php
                printf(
                    /* translators: 1: author name, 2: "3 days ago" */
                    esc_html__('%1$s asked %2$s', 'ruvelo-feedback'),
                    '<strong style="color: var(--ruvelo-feedback-ink); font-weight: 500">'.esc_html($ruvelo_author).'</strong>',
                    '<time datetime="'.esc_attr(gmdate('c', $ruvelo_created)).'" title="'.esc_attr(Html::date($ruvelo_created)).'">'.esc_html(Html::relative($ruvelo_created)).'</time>'
                );
?>
                        </span>
                    </p>
                </div>
            </div>

            <?php if (trim($ruvelo_post->post_content) !== '') { ?>
                <div class="ruvelo-fb-prose ruvelo-fb-request-body"><?php echo Views::body($ruvelo_post); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() in Views::body().?></div>
            <?php } ?>

            <?php if ($ruvelo_status === 'shipped') {
                $ruvelo_changed = (int) get_post_meta($ruvelo_post->ID, '_ruvelo_status_changed', true); ?>
                <div class="ruvelo-fb-shipped">
                    <span class="ruvelo-fb-shipped-icon"><?php echo Html::icon('check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?></span>
                    <p>
                        <strong>
                            <?php
                            echo esc_html(
                                $ruvelo_changed > 0
                                /* translators: %s: date */
                                ? sprintf(__('Shipped on %s.', 'ruvelo-feedback'), Html::date($ruvelo_changed))
                                : __('Shipped.', 'ruvelo-feedback')
                            );
                ?>
                        </strong>
                        <?php if ($ruvelo_entry !== null) { ?>
                            <?php esc_html_e('Read about it in the changelog:', 'ruvelo-feedback'); ?> <a href="<?php echo esc_url((string) get_permalink($ruvelo_entry)); ?>"><?php echo esc_html(get_the_title($ruvelo_entry)); ?></a>
                        <?php } else { ?>
                            <?php esc_html_e('Thanks to everyone who asked for it.', 'ruvelo-feedback'); ?>
                        <?php } ?>
                    </p>
                </div>
            <?php } ?>

            <?php if ($ruvelo_latest !== null) {
                $ruvelo_by = get_userdata($ruvelo_latest['by']);
                $ruvelo_by_name = $ruvelo_by !== false ? $ruvelo_by->display_name : Settings::name(); ?>
                <section class="ruvelo-fb-update" aria-label="<?php esc_attr_e('Latest update from the team', 'ruvelo-feedback'); ?>">
                    <div class="ruvelo-fb-update-head">
                        <?php echo Html::avatar($ruvelo_by_name, 'ruvelo-fb-avatar--small'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
                        <strong><?php echo esc_html($ruvelo_by_name); ?></strong>
                        <?php if ($ruvelo_latest['from'] === $ruvelo_latest['to']) { ?>
                            <span><?php esc_html_e('posted an update', 'ruvelo-feedback'); ?></span>
                        <?php } else { ?>
                            <span><?php esc_html_e('marked this', 'ruvelo-feedback'); ?></span>
                            <?php echo Html::status($ruvelo_latest['to']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
                        <?php } ?>
                        <time class="ruvelo-fb-muted" datetime="<?php echo esc_attr(gmdate('c', $ruvelo_latest['at'])); ?>"><?php echo esc_html(Html::relative($ruvelo_latest['at'])); ?></time>
                    </div>
                    <div class="ruvelo-fb-prose"><?php echo wp_kses_post(wpautop(make_clickable(esc_html($ruvelo_latest['note'])))); ?></div>
                </section>
            <?php } ?>

            <?php if ($view['duplicates'] !== []) { ?>
                <section class="ruvelo-fb-section" aria-labelledby="ruvelo-fb-dupes">
                    <h2 id="ruvelo-fb-dupes"><?php esc_html_e('Also asked as', 'ruvelo-feedback'); ?> <small><?php esc_html_e('merged here, with their votes', 'ruvelo-feedback'); ?></small></h2>
                    <ul class="ruvelo-fb-history">
                        <?php foreach ($view['duplicates'] as $ruvelo_dup) { ?>
                            <li><?php echo Html::dot('neutral'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?><div><strong style="color: var(--ruvelo-feedback-ink); font-weight: 500"><?php echo esc_html(get_the_title($ruvelo_dup)); ?></strong>
                                <?php $ruvelo_dup_text = Text::excerpt($ruvelo_dup->post_content, 240); ?>
                                <?php if ($ruvelo_dup_text !== '') { ?><div class="ruvelo-fb-prose"><?php echo esc_html($ruvelo_dup_text); ?></div><?php } ?>
                            </div></li>
                        <?php } ?>
                    </ul>
                </section>
            <?php } ?>

            <section class="ruvelo-fb-section" id="comments" aria-labelledby="ruvelo-fb-comments-title">
                <h2 id="ruvelo-fb-comments-title"><?php esc_html_e('Comments', 'ruvelo-feedback'); ?> <small><?php echo esc_html(number_format_i18n((int) $ruvelo_post->comment_count)); ?></small></h2>
                <?php echo Comments::render($ruvelo_post); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core comment markup, escaped by core and our walker.?>
            </section>
        </article>

        <aside class="ruvelo-fb-side">
            <div class="ruvelo-fb-panel">
                <dl class="ruvelo-fb-facts">
                    <div>
                        <dt><?php esc_html_e('Voters', 'ruvelo-feedback'); ?></dt>
                        <dd>
                            <div class="ruvelo-fb-voters">
                                <?php if ($view['voters'] !== []) { ?>
                                    <span class="ruvelo-fb-avatars">
                                        <?php foreach ($view['voters'] as $ruvelo_voter) {
                                            $ruvelo_voter_user = $ruvelo_voter['user_id'] > 0 ? get_userdata($ruvelo_voter['user_id']) : false;
                                            $ruvelo_voter_name = $ruvelo_voter_user !== false ? $ruvelo_voter_user->display_name : __('Guest', 'ruvelo-feedback'); ?>
                                            <span title="<?php echo esc_attr($ruvelo_voter_name); ?>"><?php echo Html::avatar($ruvelo_voter_name, 'ruvelo-fb-avatar--small'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?></span>
                                        <?php } ?>
                                    </span>
                                <?php } ?>
                                <p>
                                    <?php
                                    if ($view['voted']) {
                                        /* translators: %s: number of votes */
                                        echo wp_kses(sprintf(_n('<strong>%s</strong> vote, including yours', '<strong>%s</strong> votes, including yours', $ruvelo_votes, 'ruvelo-feedback'), number_format_i18n($ruvelo_votes)), ['strong' => []]);
                                    } else {
                                        /* translators: %s: number of votes */
                                        echo wp_kses(sprintf(_n('<strong>%s</strong> vote', '<strong>%s</strong> votes', $ruvelo_votes, 'ruvelo-feedback'), number_format_i18n($ruvelo_votes)), ['strong' => []]);
                                    }
?>
                                </p>
                            </div>
                        </dd>
                    </div>
                    <div>
                        <dt><?php esc_html_e('Status', 'ruvelo-feedback'); ?></dt>
                        <dd><?php echo Html::status($ruvelo_status); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?></dd>
                    </div>
                    <?php if ($ruvelo_category !== null) { ?>
                        <div>
                            <dt><?php esc_html_e('Category', 'ruvelo-feedback'); ?></dt>
                            <dd><?php echo esc_html($ruvelo_category->name); ?></dd>
                        </div>
                    <?php } ?>
                    <?php if (is_array($ruvelo_tags) && $ruvelo_tags !== []) { ?>
                        <div>
                            <dt><?php esc_html_e('Tags', 'ruvelo-feedback'); ?></dt>
                            <dd><?php foreach ($ruvelo_tags as $ruvelo_tag) { ?><span class="ruvelo-fb-chip ruvelo-fb-chip--outline"><?php echo esc_html($ruvelo_tag->name); ?></span><?php } ?></dd>
                        </div>
                    <?php } ?>
                </dl>
            </div>

            <?php if (Viewer::guestVotes() && Viewer::key() === '' && ! Requests::isLocked($ruvelo_post->ID) && $ruvelo_post->post_status === 'publish') { ?>
                <form class="ruvelo-fb-panel ruvelo-fb-panel--soft" id="ruvelo-fb-guest-vote" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <h3><?php esc_html_e('Vote with your email', 'ruvelo-feedback'); ?></h3>
                    <p><?php esc_html_e('We’ll send you a link to confirm, and tell you when this ships.', 'ruvelo-feedback'); ?></p>
                    <input type="hidden" name="action" value="ruvelo_feedback_guest_vote">
                    <input type="hidden" name="request" value="<?php echo esc_attr((string) $ruvelo_post->ID); ?>">
                    <?php wp_nonce_field('ruvelo_feedback_guest_vote_'.$ruvelo_post->ID, '_ruvelo_nonce', false); ?>
                    <label for="ruvelo-fb-guest-email"><?php esc_html_e('Email', 'ruvelo-feedback'); ?></label>
                    <input type="email" id="ruvelo-fb-guest-email" name="email" required autocomplete="email" style="width: 100%">
                    <span class="ruvelo-fb-honey" aria-hidden="true"><label for="ruvelo-fb-website-v"><?php esc_html_e('Website', 'ruvelo-feedback'); ?></label><input type="text" id="ruvelo-fb-website-v" name="website" tabindex="-1" autocomplete="off"></span>
                    <button type="submit" class="ruvelo-fb-btn"><?php esc_html_e('Send me the link', 'ruvelo-feedback'); ?></button>
                </form>
            <?php } ?>

            <?php if ($view['history'] !== []) { ?>
                <div>
                    <h2><?php esc_html_e('History', 'ruvelo-feedback'); ?></h2>
                    <ol class="ruvelo-fb-history">
                        <?php foreach (array_reverse($view['history']) as $ruvelo_change) { ?>
                            <li>
                                <?php echo Html::dot(Settings::statuses()[$ruvelo_change['to']]['color'] ?? 'neutral'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
                                <div>
                                    <?php echo esc_html($ruvelo_change['from'] === $ruvelo_change['to'] ? __('Update', 'ruvelo-feedback') : Settings::statusLabel($ruvelo_change['to'])); ?>
                                    <time datetime="<?php echo esc_attr(gmdate('c', $ruvelo_change['at'])); ?>">· <?php echo esc_html(Html::date($ruvelo_change['at'])); ?></time>
                                </div>
                            </li>
                        <?php } ?>
                        <li>
                            <?php echo Html::dot('neutral'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
                            <div><?php esc_html_e('Posted', 'ruvelo-feedback'); ?> <time datetime="<?php echo esc_attr(gmdate('c', $ruvelo_created)); ?>">· <?php echo esc_html(Html::date($ruvelo_created)); ?></time></div>
                        </li>
                    </ol>
                </div>
            <?php } ?>

            <?php if ($view['manager']) {
                echo Views::render('parts/manage', ['post' => $ruvelo_post, 'status' => $ruvelo_status]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the template.
            } ?>
        </aside>
    </div>
    <div class="ruvelo-fb-sr" aria-live="polite" data-ruvelo-live></div>
</div>
