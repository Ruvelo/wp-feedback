<?php
/**
 * The changelog: entries, newest first.
 *
 * @var array<string, mixed> $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Urls;
use Ruvelo\WP\Feedback\Frontend\Views;
use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Support\Html;
use Ruvelo\WP\Feedback\Viewer;

defined('ABSPATH') || exit;

$ruvelo_base = Urls::here();
$ruvelo_archive = Urls::changelog();
$ruvelo_since = (int) $view['since'];
?>
<div <?php echo $view['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped attributes.?>>
    <?php if ($view['nav']) {
        echo Views::nav('changelog'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the template.
    } ?>
    <?php if ($view['heading']) { ?>
        <div class="ruvelo-fb-head">
            <div>
                <h1><?php esc_html_e('What’s new', 'ruvelo-feedback'); ?></h1>
                <p class="ruvelo-fb-lede">
                    <?php
                    /* translators: %s: product or site name */
                    echo esc_html(sprintf(__('New features, improvements and fixes in %s, newest first.', 'ruvelo-feedback'), Settings::name()));
        ?>
                </p>
            </div>
            <div class="ruvelo-fb-feeds">
                <a class="ruvelo-fb-btn ruvelo-fb-btn--quiet ruvelo-fb-btn--small" href="<?php echo esc_url(Urls::feed()); ?>"><?php echo Html::icon('rss'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?><?php esc_html_e('RSS', 'ruvelo-feedback'); ?></a>
                <?php if (Viewer::canManage()) { ?>
                    <a class="ruvelo-fb-btn ruvelo-fb-btn--small" href="<?php echo esc_url(admin_url('post-new.php?post_type=ruvelo_entry')); ?>"><?php esc_html_e('Write an update', 'ruvelo-feedback'); ?></a>
                <?php } ?>
            </div>
        </div>
    <?php } ?>

    <?php if ($view['labels'] !== []) { ?>
        <nav class="ruvelo-fb-seg" aria-label="<?php esc_attr_e('Filter by label', 'ruvelo-feedback'); ?>" style="margin-bottom: 1.5rem">
            <a href="<?php echo esc_url(remove_query_arg(['fb_label', 'fb_page'], $ruvelo_base)); ?>" <?php echo $view['label'] === '' ? 'aria-current="true"' : ''; ?>><?php esc_html_e('Everything', 'ruvelo-feedback'); ?></a>
            <?php foreach ($view['labels'] as $ruvelo_term) { ?>
                <a href="<?php echo esc_url(add_query_arg('fb_label', $ruvelo_term->slug, remove_query_arg('fb_page', $ruvelo_base))); ?>" <?php echo $view['label'] === $ruvelo_term->slug ? 'aria-current="true"' : ''; ?>><?php echo esc_html($ruvelo_term->name); ?></a>
            <?php } ?>
        </nav>
    <?php } ?>

    <?php if ($view['drafts'] !== []) { ?>
        <p class="ruvelo-fb-flash">
            <?php
            $ruvelo_count = count($view['drafts']);
        /* translators: %s: number of entries */
        echo esc_html(sprintf(_n('%s entry not live yet:', '%s entries not live yet:', $ruvelo_count, 'ruvelo-feedback'), number_format_i18n($ruvelo_count)));
        foreach ($view['drafts'] as $ruvelo_i => $ruvelo_draft) {
            echo ($ruvelo_i > 0 ? ', ' : ' ').'<a href="'.esc_url((string) get_edit_post_link($ruvelo_draft->ID)).'">'.esc_html(get_the_title($ruvelo_draft) ?: __('(no title)', 'ruvelo-feedback')).'</a>';
        }
        ?>
        </p>
    <?php } ?>

    <?php if ($view['entries'] === []) { ?>
        <p class="ruvelo-fb-empty"><strong><?php esc_html_e('Nothing here yet.', 'ruvelo-feedback'); ?></strong> <?php esc_html_e('Updates will show up here as they ship.', 'ruvelo-feedback'); ?></p>
    <?php } else { ?>
        <ol class="ruvelo-fb-log">
            <?php foreach ($view['entries'] as $ruvelo_entry) {
                $ruvelo_time = (int) strtotime($ruvelo_entry->post_date_gmt.' UTC'); ?>
                <li id="entry-<?php echo esc_attr($ruvelo_entry->post_name); ?>">
                    <div class="ruvelo-fb-log-date">
                        <time datetime="<?php echo esc_attr(gmdate('c', $ruvelo_time)); ?>"><?php echo esc_html(Html::date($ruvelo_time)); ?></time>
                        <?php if ($ruvelo_time > $ruvelo_since) { ?>
                            <span class="ruvelo-fb-new"><?php esc_html_e('New', 'ruvelo-feedback'); ?></span>
                        <?php } ?>
                    </div>
                    <article>
                        <?php echo Views::render('parts/labels', ['post' => $ruvelo_entry, 'link' => true]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
                        <h2><a href="<?php echo esc_url((string) get_permalink($ruvelo_entry)); ?>"><?php echo esc_html(get_the_title($ruvelo_entry)); ?></a></h2>
                        <?php if (has_post_thumbnail($ruvelo_entry)) {
                            echo get_the_post_thumbnail($ruvelo_entry, 'large', ['class' => 'ruvelo-fb-cover', 'alt' => '', 'loading' => 'lazy']);
                        } ?>
                        <div class="ruvelo-fb-prose"><?php echo apply_filters('the_content', $ruvelo_entry->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter; content written by managers.?></div>
                        <?php echo Views::render('parts/asked', ['post' => $ruvelo_entry]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
                    </article>
                </li>
            <?php } ?>
        </ol>
        <?php echo Views::pagination((int) $view['page'], (int) $view['pages'], $ruvelo_base); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
    <?php } ?>
</div>
