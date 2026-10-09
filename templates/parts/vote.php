<?php
/**
 * The vote button: a form for people who can vote, a sign-in link for those
 * who can't yet, and a plain count once voting is closed.
 *
 * @var array{post: WP_Post, voted: bool, big: bool} $view
 */

declare(strict_types=1);

use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Support\Html;
use Ruvelo\WP\Feedback\Viewer;

defined('ABSPATH') || exit;

$ruvelo_post = $view['post'];
$ruvelo_votes = Requests::votes($ruvelo_post->ID);
$ruvelo_count = number_format_i18n($ruvelo_votes);
/* translators: %s: number of votes */
$ruvelo_votes_text = sprintf(_n('%s vote', '%s votes', $ruvelo_votes, 'ruvelo-feedback'), $ruvelo_count);
$ruvelo_class = 'ruvelo-fb-vote-btn'.($view['big'] ? ' ruvelo-fb-vote-btn--big' : '');
$ruvelo_title = get_the_title($ruvelo_post);

if (Requests::isLocked($ruvelo_post->ID) || $ruvelo_post->post_status !== 'publish') { ?>
    <span class="<?php echo esc_attr($ruvelo_class); ?> is-locked" role="img" aria-label="<?php echo esc_attr($ruvelo_votes_text.(Requests::isLocked($ruvelo_post->ID) ? ', '.__('voting is closed', 'ruvelo-feedback') : '')); ?>"><?php echo Html::icon('up'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.?><span><?php echo esc_html($ruvelo_count); ?></span></span>
<?php } elseif (Viewer::key() !== '' && Viewer::canVote()) { ?>
    <form class="ruvelo-fb-vote" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-ruvelo-vote="<?php echo esc_attr((string) $ruvelo_post->ID); ?>">
        <input type="hidden" name="action" value="ruvelo_feedback_vote">
        <input type="hidden" name="request" value="<?php echo esc_attr((string) $ruvelo_post->ID); ?>">
        <input type="hidden" name="vote" value="<?php echo $view['voted'] ? '0' : '1'; ?>">
        <?php wp_nonce_field('ruvelo_feedback_vote_'.$ruvelo_post->ID, '_ruvelo_nonce', false); ?>
        <button type="submit" class="<?php echo esc_attr($ruvelo_class); ?>" aria-pressed="<?php echo $view['voted'] ? 'true' : 'false'; ?>"
            aria-label="<?php
            /* translators: 1: request title, 2: "12 votes" */
            echo esc_attr(sprintf($view['voted'] ? __('Remove your vote for “%1$s”, %2$s', 'ruvelo-feedback') : __('Vote for “%1$s”, %2$s', 'ruvelo-feedback'), $ruvelo_title, $ruvelo_votes_text));
    ?>"><?php echo Html::icon('up'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.?><span data-ruvelo-count><?php echo esc_html($ruvelo_count); ?></span></button>
    </form>
<?php } elseif (Viewer::guestVotes()) { ?>
    <a class="<?php echo esc_attr($ruvelo_class); ?>" href="<?php echo esc_url(get_permalink($ruvelo_post).'#ruvelo-fb-guest-vote'); ?>" aria-label="<?php
    /* translators: 1: request title, 2: "12 votes" */
    echo esc_attr(sprintf(__('Vote for “%1$s” with your email, %2$s so far', 'ruvelo-feedback'), $ruvelo_title, $ruvelo_votes_text));
    ?>"><?php echo Html::icon('up'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.?><span><?php echo esc_html($ruvelo_count); ?></span></a>
<?php } else { ?>
    <a class="<?php echo esc_attr($ruvelo_class); ?>" href="<?php echo esc_url(Viewer::loginUrl(get_permalink($ruvelo_post) ?: '')); ?>" aria-label="<?php
    /* translators: %s: "12 votes" */
    echo esc_attr(sprintf(__('Sign in to vote. %s so far', 'ruvelo-feedback'), $ruvelo_votes_text));
    ?>"><?php echo Html::icon('up'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.?><span><?php echo esc_html($ruvelo_count); ?></span></a>
<?php } ?>
