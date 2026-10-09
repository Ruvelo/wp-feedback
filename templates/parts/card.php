<?php
/**
 * A request on the board.
 *
 * @var array{post: WP_Post, voted: bool} $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Frontend\Views;
use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Support\Html;
use Ruvelo\WP\Feedback\Support\Text;

defined('ABSPATH') || exit;

$ruvelo_post = $view['post'];
$ruvelo_pinned = Requests::isPinned($ruvelo_post->ID);
$ruvelo_excerpt = Text::excerpt($ruvelo_post->post_content, 200);
$ruvelo_category = get_the_terms($ruvelo_post->ID, PostTypes::CATEGORY);
$ruvelo_comments = (int) $ruvelo_post->comment_count;
?>
<li class="ruvelo-fb-card<?php echo $ruvelo_pinned ? ' is-pinned' : ''; ?>" data-ruvelo-card="<?php echo esc_attr((string) $ruvelo_post->ID); ?>">
    <?php echo Views::voteButton($ruvelo_post, $view['voted']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the template.?>
    <div class="ruvelo-fb-card-body">
        <h2 class="ruvelo-fb-card-title"><a href="<?php echo esc_url((string) get_permalink($ruvelo_post)); ?>"><?php echo esc_html(get_the_title($ruvelo_post)); ?></a></h2>
        <?php if ($ruvelo_excerpt !== '') { ?>
            <p class="ruvelo-fb-card-text"><?php echo esc_html($ruvelo_excerpt); ?></p>
        <?php } ?>
        <div class="ruvelo-fb-meta">
            <?php echo Html::status(Requests::status($ruvelo_post->ID)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Html::status().?>
            <?php if ($ruvelo_pinned) { ?>
                <span><?php echo Html::icon('pin'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.?><?php esc_html_e('Pinned', 'ruvelo-feedback'); ?></span>
            <?php } ?>
            <?php if (is_array($ruvelo_category) && $ruvelo_category !== []) { ?>
                <span><?php echo esc_html($ruvelo_category[0]->name); ?></span>
            <?php } ?>
            <span><?php echo Html::icon('comment'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.?><?php echo esc_html(number_format_i18n($ruvelo_comments)); ?><span class="ruvelo-fb-sr"> <?php echo esc_html(_n('comment', 'comments', $ruvelo_comments, 'ruvelo-feedback')); ?></span></span>
        </div>
    </div>
</li>
