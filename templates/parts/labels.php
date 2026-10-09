<?php
/**
 * An entry's labels.
 *
 * @var array{post: WP_Post, link: bool} $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Changelog;
use Ruvelo\WP\Feedback\Frontend\Urls;
use Ruvelo\WP\Feedback\Support\Html;

defined('ABSPATH') || exit;

$ruvelo_labels = Changelog::labelsOf($view['post']->ID);
if ($ruvelo_labels === []) {
    return;
}
?>
<div class="ruvelo-fb-labels">
    <?php foreach ($ruvelo_labels as $ruvelo_label) { ?>
        <?php if ($view['link']) { ?>
            <a <?php echo Html::toneAttrs($ruvelo_label['color'], 'ruvelo-fb-chip'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?> href="<?php echo esc_url(add_query_arg('fb_label', $ruvelo_label['slug'], Urls::changelog())); ?>"><?php echo esc_html($ruvelo_label['label']); ?></a>
        <?php } else { ?>
            <span <?php echo Html::toneAttrs($ruvelo_label['color'], 'ruvelo-fb-chip'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>><?php echo esc_html($ruvelo_label['label']); ?></span>
        <?php } ?>
    <?php } ?>
</div>
