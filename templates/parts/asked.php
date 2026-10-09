<?php
/**
 * "You asked for this": the requests a changelog entry answers.
 *
 * @var array{post: WP_Post} $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Changelog;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Support\Html;

defined('ABSPATH') || exit;

$ruvelo_asked = array_values(array_filter(array_map([Requests::class, 'get'], Changelog::requestIds($view['post']->ID)), static fn ($post): bool => $post !== null && $post->post_status === 'publish'));
if ($ruvelo_asked === []) {
    return;
}
$ruvelo_total = array_sum(array_map(static fn (WP_Post $post): int => Requests::votes($post->ID), $ruvelo_asked));
?>
<aside class="ruvelo-fb-asked" aria-label="<?php esc_attr_e('Requests this answers', 'ruvelo-feedback'); ?>">
    <h3>
        <?php
        /* translators: %s: number of votes */
        echo esc_html(sprintf(_n('You asked for this: %s vote', 'You asked for this: %s votes', $ruvelo_total, 'ruvelo-feedback'), number_format_i18n($ruvelo_total)));
?>
    </h3>
    <ul>
        <?php foreach ($ruvelo_asked as $ruvelo_request) {
            $ruvelo_votes = Requests::votes($ruvelo_request->ID); ?>
            <li><span class="ruvelo-fb-shipped-icon" style="width: 1.25rem; height: 1.25rem"><?php echo Html::icon('check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?></span><a href="<?php echo esc_url((string) get_permalink($ruvelo_request)); ?>"><?php echo esc_html(get_the_title($ruvelo_request)); ?></a><span class="ruvelo-fb-chip ruvelo-fb-chip--quiet">
                <?php
                /* translators: %s: number of votes */
                echo esc_html(sprintf(_n('%s vote', '%s votes', $ruvelo_votes, 'ruvelo-feedback'), number_format_i18n($ruvelo_votes)));
            ?>
            </span></li>
        <?php } ?>
    </ul>
</aside>
