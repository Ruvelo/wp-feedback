<?php
/**
 * The roadmap: planned, in progress and shipped.
 *
 * @var array<string, mixed> $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Urls;
use Ruvelo\WP\Feedback\Frontend\Views;
use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Support\Html;

defined('ABSPATH') || exit;

/** @var array<string, list<WP_Post>> $ruvelo_columns */
$ruvelo_columns = $view['columns'];
$ruvelo_board = Urls::board();
?>
<div <?php echo $view['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped attributes.?>>
    <?php if ($view['nav']) {
        echo Views::nav('roadmap'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the template.
    } ?>
    <?php if ($view['heading']) { ?>
        <div class="ruvelo-fb-head">
            <div>
                <h1><?php esc_html_e('Roadmap', 'ruvelo-feedback'); ?></h1>
                <p class="ruvelo-fb-lede"><?php esc_html_e('What we’re planning, what we’re building now, and what just shipped. Vote on the board to move things up.', 'ruvelo-feedback'); ?></p>
            </div>
            <?php if ($ruvelo_board !== '') { ?>
                <a class="ruvelo-fb-btn ruvelo-fb-btn--quiet" href="<?php echo esc_url($ruvelo_board); ?>"><?php esc_html_e('Browse all requests', 'ruvelo-feedback'); ?></a>
            <?php } ?>
        </div>
    <?php } ?>

    <div class="ruvelo-fb-roadmap" style="--ruvelo-fb-columns: <?php echo esc_attr((string) max(1, count($ruvelo_columns))); ?>">
        <?php foreach ($ruvelo_columns as $ruvelo_status => $ruvelo_posts) {
            $ruvelo_color = Settings::statuses()[$ruvelo_status]['color'] ?? 'neutral'; ?>
            <section <?php echo Html::toneAttrs($ruvelo_color, 'ruvelo-fb-column'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?> aria-labelledby="ruvelo-fb-col-<?php echo esc_attr($ruvelo_status); ?>">
                <h2 id="ruvelo-fb-col-<?php echo esc_attr($ruvelo_status); ?>"><span class="ruvelo-fb-dot" aria-hidden="true"></span><?php echo esc_html(Settings::statusLabel($ruvelo_status)); ?><span class="ruvelo-fb-count"><?php echo esc_html(number_format_i18n(count($ruvelo_posts))); ?></span></h2>
                <?php if ($ruvelo_posts === []) { ?>
                    <p class="ruvelo-fb-empty"><?php esc_html_e('Nothing here yet.', 'ruvelo-feedback'); ?></p>
                <?php } else { ?>
                    <ol>
                        <?php foreach ($ruvelo_posts as $ruvelo_post) {
                            $ruvelo_votes = Requests::votes($ruvelo_post->ID);
                            $ruvelo_voted = isset($view['voted'][$ruvelo_post->ID]);
                            $ruvelo_category = get_the_terms($ruvelo_post->ID, PostTypes::CATEGORY);
                            $ruvelo_entry = Requests::entryId($ruvelo_post->ID);
                            $ruvelo_changed = (int) get_post_meta($ruvelo_post->ID, '_ruvelo_status_changed', true);
                            /* translators: %s: number of votes */
                            $ruvelo_label = sprintf(_n('%s vote', '%s votes', $ruvelo_votes, 'ruvelo-feedback'), number_format_i18n($ruvelo_votes)).($ruvelo_voted ? ', '.__('including yours', 'ruvelo-feedback') : ''); ?>
                            <li class="ruvelo-fb-rm-card">
                                <div>
                                    <a href="<?php echo esc_url((string) get_permalink($ruvelo_post)); ?>"><?php echo esc_html(get_the_title($ruvelo_post)); ?></a>
                                    <div class="ruvelo-fb-meta">
                                        <?php if (is_array($ruvelo_category) && $ruvelo_category !== []) { ?><span><?php echo esc_html($ruvelo_category[0]->name); ?></span><?php } ?>
                                        <?php if ($ruvelo_status === 'shipped' && $ruvelo_entry > 0 && get_post_status($ruvelo_entry) === 'publish') { ?>
                                            <span><?php esc_html_e('In', 'ruvelo-feedback'); ?> <em style="font-style: normal; color: var(--ruvelo-feedback-text-2)"><?php echo esc_html(wp_html_excerpt(get_the_title($ruvelo_entry), 40, '…')); ?></em></span>
                                        <?php } elseif ($ruvelo_status === 'shipped' && $ruvelo_changed > 0) { ?>
                                            <span><?php echo esc_html(Html::date($ruvelo_changed)); ?></span>
                                        <?php } ?>
                                    </div>
                                </div>
                                <span class="ruvelo-fb-rm-votes<?php echo $ruvelo_voted ? ' is-voted' : ''; ?>" role="img" aria-label="<?php echo esc_attr($ruvelo_label); ?>"><?php echo Html::icon('up'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?><?php echo esc_html(number_format_i18n($ruvelo_votes)); ?></span>
                            </li>
                        <?php } ?>
                    </ol>
                <?php } ?>
            </section>
        <?php } ?>
    </div>
</div>
