<?php
/**
 * "Suggest a feature": the form, with similar requests as the title is typed.
 *
 * @var array<string, mixed> $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Urls;
use Ruvelo\WP\Feedback\Frontend\Views;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Viewer;

defined('ABSPATH') || exit;

/** @var array{title: string, body: string, category: int, name: string, email: string}|null $ruvelo_draft */
$ruvelo_draft = $view['draft'];
$ruvelo_similar = $view['similar'];
?>
<div <?php echo $view['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped attributes.?>>
    <?php if ($view['nav']) {
        echo Views::nav('board'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the template.
    } ?>
    <div style="max-width: 42rem">
        <?php echo Views::notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
        <div class="ruvelo-fb-head" style="margin-bottom: 1rem">
            <div>
                <h1><?php esc_html_e('Suggest a feature', 'ruvelo-feedback'); ?></h1>
                <p class="ruvelo-fb-lede"><?php esc_html_e('Tell us what you’re trying to do and what gets in the way. Others can vote for it, and you’ll hear when it ships.', 'ruvelo-feedback'); ?></p>
            </div>
        </div>

        <?php if (! $view['can_post']) { ?>
            <p class="ruvelo-fb-signin">
                <a href="<?php echo esc_url(Viewer::loginUrl()); ?>"><?php esc_html_e('Sign in', 'ruvelo-feedback'); ?></a> <?php esc_html_e('to suggest a feature. You can browse and read everything without an account.', 'ruvelo-feedback'); ?>
            </p>
        <?php } else { ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-ruvelo-submit>
                <input type="hidden" name="action" value="ruvelo_feedback_submit">
                <input type="hidden" name="js" value="0" data-ruvelo-js>
                <input type="hidden" name="return" value="<?php echo esc_url(Urls::here()); ?>">
                <?php wp_nonce_field('ruvelo_feedback_submit', '_ruvelo_nonce', false); ?>

                <label for="ruvelo-fb-title"><?php esc_html_e('Title', 'ruvelo-feedback'); ?></label>
                <input type="text" id="ruvelo-fb-title" name="title" required minlength="3" maxlength="160" value="<?php echo esc_attr($view['title']); ?>" placeholder="<?php esc_attr_e('A short summary, like “Export reports to CSV”', 'ruvelo-feedback'); ?>" autocomplete="off" aria-describedby="ruvelo-fb-similar-region" data-ruvelo-title>

                <div id="ruvelo-fb-similar-region" aria-live="polite" data-ruvelo-similar>
                    <?php if ($ruvelo_similar !== []) { ?>
                        <div class="ruvelo-fb-similar">
                            <h2><?php esc_html_e('Is it one of these? Vote for it instead.', 'ruvelo-feedback'); ?></h2>
                            <ul>
                                <?php foreach ($ruvelo_similar as $ruvelo_post) { ?>
                                    <li><a href="<?php echo esc_url((string) get_permalink($ruvelo_post)); ?>"><span class="ruvelo-fb-similar-votes"><?php echo esc_html(number_format_i18n(Requests::votes($ruvelo_post->ID))); ?></span><span class="ruvelo-fb-similar-title"><?php echo esc_html(get_the_title($ruvelo_post)); ?></span><span class="ruvelo-fb-chip ruvelo-fb-chip--quiet"><?php echo esc_html(Settings::statusLabel(Requests::status($ruvelo_post->ID))); ?></span></a></li>
                                <?php } ?>
                            </ul>
                        </div>
                    <?php } ?>
                </div>

                <label for="ruvelo-fb-body"><?php esc_html_e('Details', 'ruvelo-feedback'); ?> <span class="ruvelo-fb-muted"><?php esc_html_e('(optional)', 'ruvelo-feedback'); ?></span></label>
                <textarea id="ruvelo-fb-body" name="body" rows="6" maxlength="10000" aria-describedby="ruvelo-fb-body-hint"><?php echo esc_textarea($ruvelo_draft['body'] ?? ''); ?></textarea>
                <p class="ruvelo-fb-hint" id="ruvelo-fb-body-hint"><?php esc_html_e('What would it let you do? Lines starting with “- ” become a list, and **bold** works.', 'ruvelo-feedback'); ?></p>

                <?php if ($view['categories'] !== []) { ?>
                    <label for="ruvelo-fb-category"><?php esc_html_e('Category', 'ruvelo-feedback'); ?> <span class="ruvelo-fb-muted"><?php esc_html_e('(optional)', 'ruvelo-feedback'); ?></span></label>
                    <select id="ruvelo-fb-category" name="category">
                        <option value=""><?php esc_html_e('Not sure', 'ruvelo-feedback'); ?></option>
                        <?php foreach ($view['categories'] as $ruvelo_term) { ?>
                            <option value="<?php echo esc_attr((string) $ruvelo_term->term_id); ?>" <?php selected($ruvelo_term->term_id, $ruvelo_draft['category'] ?? 0); ?>><?php echo esc_html($ruvelo_term->name); ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>

                <?php if ($view['guest']) { ?>
                    <div class="ruvelo-fb-fields">
                        <div>
                            <label for="ruvelo-fb-name"><?php esc_html_e('Your name', 'ruvelo-feedback'); ?></label>
                            <input type="text" id="ruvelo-fb-name" name="name" maxlength="80" autocomplete="name" value="<?php echo esc_attr($ruvelo_draft['name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="ruvelo-fb-email"><?php esc_html_e('Email', 'ruvelo-feedback'); ?></label>
                            <input type="email" id="ruvelo-fb-email" name="email" required maxlength="190" autocomplete="email" value="<?php echo esc_attr($ruvelo_draft['email'] ?? ''); ?>" aria-describedby="ruvelo-fb-email-hint" style="font: inherit; font-size: .925rem; width: 100%; padding: .55rem .75rem; border: 1px solid var(--ruvelo-feedback-line); border-radius: var(--ruvelo-feedback-radius); background: var(--ruvelo-feedback-bg); color: inherit">
                        </div>
                    </div>
                    <p class="ruvelo-fb-hint" id="ruvelo-fb-email-hint"><?php esc_html_e('Only to tell you when it ships. Never shown, never shared. New requests from guests are checked before they appear.', 'ruvelo-feedback'); ?></p>
                <?php } ?>

                <span class="ruvelo-fb-honey" aria-hidden="true"><label for="ruvelo-fb-website"><?php esc_html_e('Website', 'ruvelo-feedback'); ?></label><input type="text" id="ruvelo-fb-website" name="website" tabindex="-1" autocomplete="off"></span>

                <?php if ($ruvelo_draft !== null && $ruvelo_similar !== []) { ?>
                    <label class="ruvelo-fb-check"><input type="checkbox" name="confirm_new" value="1"> <?php esc_html_e('None of these: post my request', 'ruvelo-feedback'); ?></label>
                <?php } ?>

                <div class="ruvelo-fb-row">
                    <button type="submit" class="ruvelo-fb-btn"><?php esc_html_e('Post request', 'ruvelo-feedback'); ?></button>
                    <?php if (Urls::board() !== '') { ?>
                        <a class="ruvelo-fb-btn ruvelo-fb-btn--quiet" href="<?php echo esc_url(Urls::board()); ?>"><?php esc_html_e('Cancel', 'ruvelo-feedback'); ?></a>
                    <?php } ?>
                </div>
            </form>
        <?php } ?>
    </div>
</div>
