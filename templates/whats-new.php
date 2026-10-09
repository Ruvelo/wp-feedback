<?php
/**
 * "What's new": a bell with an unread count and the latest entries. It is a
 * <details> element, so it opens without JavaScript; with JavaScript it also
 * marks the changelog as seen.
 *
 * @var array<string, mixed> $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Changelog;
use Ruvelo\WP\Feedback\Frontend\Urls;
use Ruvelo\WP\Feedback\Support\Html;
use Ruvelo\WP\Feedback\Support\Text;

defined('ABSPATH') || exit;

$ruvelo_unread = (int) $view['unread'];
$ruvelo_since = (int) $view['since'];
?>
<div <?php echo $view['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped attributes.?>>
    <details class="ruvelo-fb-wn" data-ruvelo-whats-new>
        <summary class="ruvelo-fb-wn-button" aria-label="<?php
        echo esc_attr(
            $ruvelo_unread > 0
            /* translators: 1: "What's new", 2: number of unread updates */
            ? sprintf(_n('%1$s, %2$s new update', '%1$s, %2$s new updates', $ruvelo_unread, 'ruvelo-feedback'), $view['label'], number_format_i18n($ruvelo_unread))
            : $view['label']
        );
?>">
            <?php echo Html::icon('bell'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
            <span><?php echo esc_html($view['label']); ?></span>
            <span class="ruvelo-fb-wn-badge" data-ruvelo-badge <?php echo $ruvelo_unread > 0 ? '' : 'hidden'; ?>><?php echo esc_html(number_format_i18n($ruvelo_unread)); ?></span>
        </summary>
        <div class="ruvelo-fb-wn-panel">
            <div class="ruvelo-fb-wn-head">
                <h2><?php echo esc_html($view['label']); ?></h2>
                <a class="ruvelo-fb-wn-link" href="<?php echo esc_url(Urls::changelog()); ?>"><?php esc_html_e('See all', 'ruvelo-feedback'); ?></a>
            </div>
            <?php if ($view['entries'] === []) { ?>
                <p class="ruvelo-fb-wn-empty"><?php esc_html_e('Nothing yet. Updates will show up here.', 'ruvelo-feedback'); ?></p>
            <?php } else { ?>
                <ul class="ruvelo-fb-wn-list">
                    <?php foreach ($view['entries'] as $ruvelo_entry) {
                        $ruvelo_time = (int) strtotime($ruvelo_entry->post_date_gmt.' UTC');
                        $ruvelo_fresh = $ruvelo_time > $ruvelo_since; ?>
                        <li>
                            <a class="ruvelo-fb-wn-item<?php echo $ruvelo_fresh ? ' is-new' : ''; ?>" href="<?php echo esc_url((string) get_permalink($ruvelo_entry)); ?>">
                                <?php if ($ruvelo_fresh) { ?><span class="ruvelo-fb-wn-dot" aria-hidden="true"></span><span class="ruvelo-fb-sr"><?php esc_html_e('New:', 'ruvelo-feedback'); ?> </span><?php } ?>
                                <span class="ruvelo-fb-wn-top">
                                    <?php foreach (Changelog::labelsOf($ruvelo_entry->ID) as $ruvelo_label) { ?>
                                        <span <?php echo Html::toneAttrs($ruvelo_label['color'], 'ruvelo-fb-wn-chip'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>><?php echo esc_html($ruvelo_label['label']); ?></span>
                                    <?php } ?>
                                    <time datetime="<?php echo esc_attr(gmdate('c', $ruvelo_time)); ?>"><?php echo esc_html(Html::date($ruvelo_time)); ?></time>
                                </span>
                                <span class="ruvelo-fb-wn-title"><?php echo esc_html(get_the_title($ruvelo_entry)); ?></span>
                                <span class="ruvelo-fb-wn-text"><?php echo esc_html(Text::excerpt($ruvelo_entry->post_excerpt !== '' ? $ruvelo_entry->post_excerpt : $ruvelo_entry->post_content, 140)); ?></span>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
            <div class="ruvelo-fb-wn-foot"><a href="<?php echo esc_url(Urls::changelog()); ?>"><?php esc_html_e('Read the changelog', 'ruvelo-feedback'); ?> →</a></div>
        </div>
    </details>
</div>
