<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Support;

/**
 * A fixed-window rate limit kept in transients, per user or per IP.
 */
final class RateLimit
{
    /**
     * Counts a hit and says whether it's allowed.
     */
    public static function hit(string $action, int $limit, int $window = MINUTE_IN_SECONDS): bool
    {
        /**
         * Filters a rate limit. Return 0 to turn it off.
         *
         * @param int $limit Hits allowed per window.
         * @param string $action "request", "vote", "guest_vote" or "comment".
         */
        $limit = (int) apply_filters('ruvelo_feedback_rate_limit', $limit, $action);
        if ($limit <= 0) {
            return true;
        }
        $key = 'ruvelo_fb_rl_'.md5($action.'|'.self::who());
        $hits = (int) get_transient($key);
        if ($hits >= $limit) {
            return false;
        }
        set_transient($key, $hits + 1, $window);

        return true;
    }

    private static function who(): string
    {
        $user = get_current_user_id();
        if ($user > 0) {
            return 'u'.$user;
        }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        return 'ip'.wp_hash($ip);
    }
}
