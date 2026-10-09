<?php
/**
 * One changelog entry.
 *
 * @var array<string, mixed> $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Urls;
use Ruvelo\WP\Feedback\Frontend\Views;
use Ruvelo\WP\Feedback\Support\Html;

defined('ABSPATH') || exit;

/** @var WP_Post $ruvelo_post */
$ruvelo_post = $view['post'];
$ruvelo_time = (int) strtotime($ruvelo_post->post_date_gmt.' UTC');
/** @var WP_Post|null $ruvelo_newer */
$ruvelo_newer = $view['newer'];
/** @var WP_Post|null $ruvelo_older */
$ruvelo_older = $view['older'];
?>
<div <?php echo $view['wrapper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped attributes.?>>
    <?php if ($view['nav']) {
        echo Views::nav('changelog'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the template.
    } ?>
    <div style="max-width: 46rem; margin: 0 auto">
        <ol class="ruvelo-fb-crumbs" aria-label="<?php esc_attr_e('Breadcrumbs', 'ruvelo-feedback'); ?>">
            <li><a href="<?php echo esc_url(Urls::changelog()); ?>"><?php esc_html_e('Changelog', 'ruvelo-feedback'); ?></a></li>
            <li aria-current="page"><time datetime="<?php echo esc_attr(gmdate('c', $ruvelo_time)); ?>"><?php echo esc_html(Html::date($ruvelo_time)); ?></time></li>
        </ol>
        <article>
            <?php echo Views::render('parts/labels', ['post' => $ruvelo_post, 'link' => true]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
            <h1 style="font-size: clamp(1.6rem, 3.2vw, 2.2rem); letter-spacing: -.035em; margin: 0 0 1.25rem"><?php echo esc_html(get_the_title($ruvelo_post)); ?></h1>
            <?php if (has_post_thumbnail($ruvelo_post)) {
                echo get_the_post_thumbnail($ruvelo_post, 'large', ['class' => 'ruvelo-fb-cover', 'alt' => '']);
            } ?>
            <div class="ruvelo-fb-prose"><?php echo apply_filters('the_content', $ruvelo_post->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter; content written by managers.?></div>
            <?php echo Views::render('parts/asked', ['post' => $ruvelo_post]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
        </article>
        <?php if ($ruvelo_newer !== null || $ruvelo_older !== null) { ?>
            <nav class="ruvelo-fb-pager" aria-label="<?php esc_attr_e('More updates', 'ruvelo-feedback'); ?>">
                <?php if ($ruvelo_older !== null) { ?>
                    <a href="<?php echo esc_url((string) get_permalink($ruvelo_older)); ?>"><small><?php esc_html_e('Older', 'ruvelo-feedback'); ?></small><?php echo esc_html(get_the_title($ruvelo_older)); ?></a>
                <?php } ?>
                <?php if ($ruvelo_newer !== null) { ?>
                    <a href="<?php echo esc_url((string) get_permalink($ruvelo_newer)); ?>"><small><?php esc_html_e('Newer', 'ruvelo-feedback'); ?></small><?php echo esc_html(get_the_title($ruvelo_newer)); ?></a>
                <?php } ?>
            </nav>
        <?php } ?>
    </div>
</div>
