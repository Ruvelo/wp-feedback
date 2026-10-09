<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Admin;

use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Stats;
use Ruvelo\WP\Feedback\Support\Html;

/**
 * Feedback → Stats: the numbers that matter, without a dashboard product.
 */
final class StatsPage
{
    public const SLUG = 'ruvelo-feedback-stats';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 15);
    }

    public static function menu(): void
    {
        add_submenu_page('edit.php?post_type='.PostTypes::REQUEST, __('Feedback stats', 'ruvelo-feedback'), __('Stats', 'ruvelo-feedback'), Settings::CAP, self::SLUG, [self::class, 'render']);
    }

    public static function render(): void
    {
        $stats = Stats::summary();
        $weeks = $stats['votes_per_week'];
        $max = max(1, ...array_values($weeks));
        ?>
        <div class="wrap ruvelo-fb-admin">
            <h1><?php esc_html_e('Feedback stats', 'ruvelo-feedback'); ?></h1>

            <div class="ruvelo-fb-stats">
                <?php
                $cards = [
                    [__('Open requests', 'ruvelo-feedback'), $stats['open']],
                    [__('Votes in the last 30 days', 'ruvelo-feedback'), $stats['votes_30d']],
                    [__('Shipped in the last 90 days', 'ruvelo-feedback'), $stats['shipped_90d']],
                    [__('Waiting for approval', 'ruvelo-feedback'), $stats['pending']],
                ];
        foreach ($cards as [$label, $value]) { ?>
                    <div class="ruvelo-fb-stat"><small><?php echo esc_html($label); ?></small><strong><?php echo esc_html(number_format_i18n((int) $value)); ?></strong></div>
                <?php } ?>
            </div>

            <h2><?php esc_html_e('Votes per week', 'ruvelo-feedback'); ?></h2>
            <div class="ruvelo-fb-chart" style="--ruvelo-fb-weeks: <?php echo esc_attr((string) count($weeks)); ?>" role="img" aria-label="<?php esc_attr_e('Votes per week for the last 12 weeks', 'ruvelo-feedback'); ?>">
                <?php foreach ($weeks as $monday => $count) { ?>
                    <div class="ruvelo-fb-chart-col">
                        <div class="ruvelo-fb-chart-bar" style="height: <?php echo esc_attr((string) round($count / $max * 100, 1)); ?>%"><span><?php echo esc_html(number_format_i18n($count)); ?></span></div>
                        <span><?php echo esc_html(wp_date('M j', (int) strtotime($monday.' 12:00 UTC')) ?: ''); ?></span>
                    </div>
                <?php } ?>
            </div>

            <div class="ruvelo-fb-stats-cols">
                <div>
                    <h2><?php esc_html_e('Top open requests', 'ruvelo-feedback'); ?></h2>
                    <table class="widefat striped">
                        <thead><tr><th><?php esc_html_e('Request', 'ruvelo-feedback'); ?></th><th><?php esc_html_e('Status', 'ruvelo-feedback'); ?></th><th class="num"><?php esc_html_e('Votes', 'ruvelo-feedback'); ?></th></tr></thead>
                        <tbody>
                        <?php if ($stats['top'] === []) { ?>
                            <tr><td colspan="3"><?php esc_html_e('No open requests yet.', 'ruvelo-feedback'); ?></td></tr>
                        <?php } ?>
                        <?php foreach ($stats['top'] as $post) { ?>
                            <tr>
                                <td><a href="<?php echo esc_url((string) get_edit_post_link($post->ID)); ?>"><?php echo esc_html(get_the_title($post)); ?></a></td>
                                <td><?php echo Html::status(Requests::status($post->ID)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?></td>
                                <td class="num"><?php echo esc_html(number_format_i18n(Requests::votes($post->ID))); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div>
                    <h2><?php esc_html_e('Requests by status', 'ruvelo-feedback'); ?></h2>
                    <table class="widefat striped">
                        <tbody>
                        <?php
                $total = max(1, array_sum($stats['by_status']));
        foreach (Settings::statuses() as $key => $status) {
            $count = $stats['by_status'][$key] ?? 0; ?>
                            <tr>
                                <td><?php echo Html::status($key); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?></td>
                                <td><span class="ruvelo-fb-meter"><span style="width: <?php echo esc_attr((string) round($count / $total * 100, 1)); ?>%"></span></span></td>
                                <td class="num"><?php echo esc_html(number_format_i18n($count)); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}
