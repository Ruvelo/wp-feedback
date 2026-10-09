<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Frontend;

use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Support\Html;
use WP_Comment;
use WP_Post;

/**
 * Core WordPress comments on requests, in the house style. Team members
 * (anyone who can manage feedback) get a "Team" badge.
 */
final class Comments
{
    public static function render(WP_Post $post): string
    {
        $comments = get_comments([
            'post_id' => $post->ID,
            'status' => 'approve',
            'include_unapproved' => self::unapprovedFor(),
            'order' => 'ASC',
        ]);
        $comments = is_array($comments) ? array_values(array_filter($comments, static fn ($comment): bool => $comment instanceof WP_Comment)) : [];

        ob_start();
        if (comments_open($post)) {
            comment_form(self::formArgs(), $post);
        } elseif ($comments === []) {
            echo '<p class="ruvelo-fb-muted">'.esc_html__('Comments are closed.', 'ruvelo-feedback').'</p>';
        }

        if ($comments === []) {
            if (comments_open($post)) {
                echo '<p class="ruvelo-fb-muted" style="font-size: .9rem; margin-top: 1rem">'.esc_html__('No comments yet. Add a use case or an example: it helps the team understand what you need.', 'ruvelo-feedback').'</p>';
            }
        } else {
            echo '<ol class="ruvelo-fb-comments">';
            wp_list_comments([
                'style' => 'ol',
                'callback' => [self::class, 'comment'],
                'end-callback' => [self::class, 'end'],
                'max_depth' => (int) get_option('thread_comments_depth', 5),
            ], $comments);
            echo '</ol>';
        }

        return (string) ob_get_clean();
    }

    /**
     * @return array<int|string>
     */
    private static function unapprovedFor(): array
    {
        $user = get_current_user_id();
        if ($user > 0) {
            return [$user];
        }
        $commenter = wp_get_current_commenter();

        return $commenter['comment_author_email'] !== '' ? [$commenter['comment_author_email']] : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function formArgs(): array
    {
        $user = wp_get_current_user();
        $name = $user->exists() ? $user->display_name : '';

        return [
            'class_container' => 'comment-respond ruvelo-fb-respond',
            'title_reply' => __('Add a comment', 'ruvelo-feedback'),
            /* translators: %s: name of the person being replied to */
            'title_reply_to' => __('Reply to %s', 'ruvelo-feedback'),
            'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title">',
            'title_reply_after' => '</h3>',
            'cancel_reply_link' => __('Cancel', 'ruvelo-feedback'),
            'label_submit' => __('Post comment', 'ruvelo-feedback'),
            'class_submit' => 'submit ruvelo-fb-btn',
            'comment_field' => '<p class="comment-form-comment"><label for="comment">'.esc_html__('Your comment', 'ruvelo-feedback').'</label><textarea id="comment" name="comment" rows="4" maxlength="65525" required placeholder="'.esc_attr__('Add a use case, an example, or a question…', 'ruvelo-feedback').'"></textarea></p>',
            'logged_in_as' => $name !== '' ? '<p class="logged-in-as">'.Html::avatar($name, 'ruvelo-fb-avatar--small').' '.esc_html(
                /* translators: %s: display name */
                sprintf(__('Commenting as %s', 'ruvelo-feedback'), $name)
            ).'</p>' : '',
        ];
    }

    /**
     * Renders one comment (wp_list_comments callback).
     *
     * @param array<string, mixed> $args
     */
    public static function comment(WP_Comment $comment, array $args, int $depth): void
    {
        $name = get_comment_author($comment);
        $team = (int) $comment->user_id > 0 && user_can((int) $comment->user_id, Settings::CAP);
        $time = (int) strtotime($comment->comment_date_gmt.' UTC');
        ?>
        <li id="comment-<?php echo esc_attr((string) $comment->comment_ID); ?>" <?php comment_class('ruvelo-fb-comment'.($team ? ' is-official' : ''), $comment); ?>>
            <?php echo Html::avatar($name); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Html::avatar().?>
            <div>
                <div class="ruvelo-fb-comment-head">
                    <strong><?php echo esc_html($name); ?></strong>
                    <?php if ($team) { ?>
                        <span class="ruvelo-fb-chip ruvelo-fb-chip--team"><?php echo esc_html((string) apply_filters('ruvelo_feedback_team_label', __('Team', 'ruvelo-feedback'), $comment)); ?></span>
                    <?php } ?>
                    <time datetime="<?php echo esc_attr(gmdate('c', $time)); ?>" title="<?php echo esc_attr(Html::date($time)); ?>"><?php echo esc_html(Html::relative($time)); ?></time>
                </div>
                <?php if ($comment->comment_approved === '0') { ?>
                    <span class="comment-awaiting-moderation"><?php esc_html_e('Your comment is waiting for moderation.', 'ruvelo-feedback'); ?></span>
                <?php } ?>
                <div class="ruvelo-fb-prose"><?php comment_text($comment); ?></div>
                <div class="ruvelo-fb-comment-tools">
                    <?php
                    comment_reply_link(array_merge($args, [
                        'depth' => $depth,
                        'max_depth' => (int) ($args['max_depth'] ?? 5),
                        'reply_text' => __('Reply', 'ruvelo-feedback'),
                    ]), $comment);
        edit_comment_link(__('Edit', 'ruvelo-feedback'), '', '');
        ?>
                </div>
        <?php
        // The closing tags come from end().
    }

    public static function end(): void
    {
        echo '</div></li>';
    }
}
