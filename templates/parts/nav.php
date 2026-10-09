<?php
/**
 * The portal tabs: Feedback, Roadmap, Changelog.
 *
 * @var array{links: array<string, array{0: string, 1: string, 2: string}>, current: string} $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Support\Html;

defined('ABSPATH') || exit;
?>
<nav class="ruvelo-fb-portal-nav" aria-label="<?php esc_attr_e('Feedback portal', 'ruvelo-feedback'); ?>">
    <?php foreach ($view['links'] as $ruvelo_key => [$ruvelo_url, $ruvelo_label, $ruvelo_icon]) { ?>
        <a href="<?php echo esc_url($ruvelo_url); ?>" <?php echo $ruvelo_key === $view['current'] ? 'aria-current="page"' : ''; ?>><?php echo Html::icon($ruvelo_icon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.?><?php echo esc_html($ruvelo_label); ?></a>
    <?php } ?>
</nav>
