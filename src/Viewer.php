<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

/**
 * Who is looking, and what they may do.
 */
final class Viewer
{
    public const GUEST_COOKIE = 'ruvelo_feedback_guest';

    public const SEEN_COOKIE = 'ruvelo_feedback_seen';

    /**
     * The voter key for the current visitor: "u:<id>", "e:<hash>" for a guest
     * who confirmed a vote on this browser, or "" for nobody.
     */
    public static function key(): string
    {
        $user = get_current_user_id();
        if ($user > 0) {
            return Votes::userKey($user);
        }
        $email = self::guestEmail();

        return $email !== '' ? Votes::emailKey($email) : '';
    }

    /**
     * The guest email remembered on this browser after a confirmed vote. The
     * cookie is signed, so it can't be forged to see someone else's votes.
     */
    public static function guestEmail(): string
    {
        if (! isset($_COOKIE[self::GUEST_COOKIE]) || ! is_string($_COOKIE[self::GUEST_COOKIE])) {
            return '';
        }
        $parts = explode('|', sanitize_text_field(wp_unslash($_COOKIE[self::GUEST_COOKIE])));
        if (count($parts) !== 2) {
            return '';
        }
        $email = ctype_xdigit($parts[0]) ? (string) hex2bin($parts[0]) : '';
        if (! is_email($email) || ! hash_equals(wp_hash('guest|'.$email), $parts[1])) {
            return '';
        }

        return $email;
    }

    public static function rememberGuest(string $email): void
    {
        if (headers_sent()) {
            return;
        }
        $value = bin2hex($email).'|'.wp_hash('guest|'.$email);
        setcookie(self::GUEST_COOKIE, $value, ['expires' => time() + YEAR_IN_SECONDS, 'path' => COOKIEPATH, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE[self::GUEST_COOKIE] = $value;
    }

    public static function canManage(): bool
    {
        return current_user_can(Settings::CAP);
    }

    public static function canPost(): bool
    {
        $allowed = is_user_logged_in() || Settings::string('post') === 'anyone';

        /**
         * Filters whether the current visitor may post a request.
         *
         * @param bool $allowed
         */
        return (bool) apply_filters('ruvelo_feedback_can_post', $allowed);
    }

    public static function canVote(): bool
    {
        $allowed = is_user_logged_in() || Settings::string('vote') === 'anyone';

        /**
         * Filters whether the current visitor may vote.
         *
         * @param bool $allowed
         */
        return (bool) apply_filters('ruvelo_feedback_can_vote', $allowed);
    }

    public static function guestVotes(): bool
    {
        return ! is_user_logged_in() && Settings::string('vote') === 'anyone';
    }

    public static function loginUrl(string $redirect = ''): string
    {
        return wp_login_url($redirect !== '' ? $redirect : self::currentUrl());
    }

    public static function currentUrl(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $base = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
        if ($base !== '/' && $base !== '' && str_starts_with($uri, rtrim($base, '/'))) {
            $uri = substr($uri, strlen(rtrim($base, '/')));
        }

        return home_url($uri);
    }
}
