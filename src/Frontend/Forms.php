<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Frontend;

use Ruvelo\WP\Feedback\Notifications;
use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Support\RateLimit;
use Ruvelo\WP\Feedback\Support\Redirect;
use Ruvelo\WP\Feedback\Viewer;
use Ruvelo\WP\Feedback\Votes;
use WP_Error;

/**
 * The no-JavaScript path: every form posts to admin-post.php and comes back
 * with a notice. JavaScript uses the REST API for the same actions.
 */
final class Forms
{
    public static function register(): void
    {
        foreach (['vote', 'guest_vote', 'submit'] as $action) {
            add_action('admin_post_ruvelo_feedback_'.$action, [self::class, $action]);
            add_action('admin_post_nopriv_ruvelo_feedback_'.$action, [self::class, $action]);
        }
        add_action('admin_post_ruvelo_feedback_manage', [self::class, 'manage']);
        add_action('template_redirect', [self::class, 'links'], 1);
    }

    // ------------------------------------------------------------------

    public static function vote(): void
    {
        $id = self::int('request');
        self::verify('ruvelo_feedback_vote_'.$id, $id);
        if (! Viewer::canVote() || Viewer::key() === '') {
            self::back('login', $id);
        }
        if (! RateLimit::hit('vote', 60)) {
            self::back('rate', $id);
        }
        $email = is_user_logged_in() ? '' : Viewer::guestEmail();
        $result = self::int('vote') === 1
            ? Requests::vote($id, get_current_user_id(), $email)
            : Requests::unvote($id, get_current_user_id(), $email);
        if ($result instanceof WP_Error) {
            self::back($result->get_error_code() === 'ruvelo_feedback_locked' ? 'locked' : 'invalid', $id);
        }
        self::back(self::int('vote') === 1 ? 'voted' : 'unvoted', $id);
    }

    /**
     * A guest votes with an email address: the vote is stored unconfirmed
     * and counts once they click the link we send.
     */
    public static function guestVote(): void
    {
        $id = self::int('request');
        self::verify('ruvelo_feedback_guest_vote_'.$id, $id);
        if (self::str('website') !== '') {
            self::back('voted', $id); // Honeypot: pretend it worked.
        }
        if (Settings::string('vote') !== 'anyone') {
            self::back('login', $id);
        }
        if (! RateLimit::hit('guest_vote', 5)) {
            self::back('rate', $id);
        }
        $email = sanitize_email(self::str('email'));
        if (! is_email($email)) {
            self::back('email', $id);
        }
        $result = Requests::vote($id, 0, $email, false);
        if ($result instanceof WP_Error) {
            self::back($result->get_error_code() === 'ruvelo_feedback_locked' ? 'locked' : 'invalid', $id);
        }
        $vote = Votes::find($id, Votes::emailKey($email));
        if ($vote !== null && ! $vote['confirmed']) {
            self::sendConfirmation($id, $email);
        }
        self::back($vote !== null && $vote['confirmed'] ? 'voted' : 'confirm', $id);
    }

    // Hooked as admin_post_..._guest_vote.
    public static function guest_vote(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- Matches the action name.
    {
        self::guestVote();
    }

    public static function sendConfirmation(int $id, string $email): void
    {
        $url = add_query_arg([
            'ruvelo_feedback_confirm' => $id,
            'email' => rawurlencode(strtolower($email)),
            'token' => self::confirmToken($email, $id),
        ], home_url('/'));
        /* translators: %s: site name */
        $subject = sprintf(__('[%s] Confirm your vote', 'ruvelo-feedback'), Settings::name());
        $body = implode("\n", [
            __('Hi,', 'ruvelo-feedback'),
            '',
            /* translators: %s: request title */
            sprintf(__('Click this link to confirm your vote for “%s”:', 'ruvelo-feedback'), get_the_title($id)),
            '',
            $url,
            '',
            __('We’ll also email you when its status changes. If you didn’t ask for this, ignore this email: nothing will be counted.', 'ruvelo-feedback'),
        ]);
        wp_mail($email, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
    }

    public static function confirmToken(string $email, int $id): string
    {
        return substr(wp_hash('confirm|'.strtolower($email).'|'.$id), 0, 24);
    }

    public static function submit(): void
    {
        $return = esc_url_raw(self::str('return'));
        $return = $return !== '' ? $return : (Urls::submit() ?: home_url('/'));
        if (! wp_verify_nonce(self::str('_ruvelo_nonce'), 'ruvelo_feedback_submit')) {
            self::go($return, 'expired');
        }
        if (! Viewer::canPost()) {
            self::go($return, 'login');
        }
        $draft = [
            'title' => self::str('title'),
            'body' => self::str('body', true),
            'category' => self::int('category'),
            'name' => self::str('name'),
            'email' => self::str('email'),
        ];
        if (self::str('website') !== '') {
            self::go(Urls::board() ?: $return, 'pending'); // Honeypot.
        }

        // Without JavaScript, nobody saw the similar requests yet: show them once.
        if (self::str('js') !== '1' && self::str('confirm_new') !== '1' && Requests::similar($draft['title']) !== []) {
            $token = wp_generate_password(12, false);
            set_transient('ruvelo_fb_draft_'.strtolower($token), $draft, 30 * MINUTE_IN_SECONDS);
            self::redirect(add_query_arg('fb_draft', strtolower($token), remove_query_arg(['ruvelo_msg', 'fb_draft', 'fb_title'], $return)).'#ruvelo-fb-title');
        }

        if (! RateLimit::hit('request', 5)) {
            self::go($return, 'rate');
        }

        $userId = get_current_user_id();
        $result = Requests::create(
            ['title' => $draft['title'], 'body' => $draft['body'], 'category' => $draft['category']],
            $userId,
            $userId === 0 ? ['name' => $draft['name'], 'email' => $draft['email']] : null
        );
        if ($result instanceof WP_Error) {
            self::go($return, $result->get_error_code() === 'ruvelo_feedback_email' ? 'email' : 'title');
        }
        if (get_post_status($result) === 'pending') {
            self::go(Urls::board() ?: $return, 'pending');
        }
        self::go((string) get_permalink($result), 'posted');
    }

    /**
     * @return array{title: string, body: string, category: int, name: string, email: string}|null
     */
    public static function draft(string $token): ?array
    {
        $draft = $token !== '' ? get_transient('ruvelo_fb_draft_'.$token) : false;
        if (! is_array($draft)) {
            return null;
        }

        return [
            'title' => sanitize_text_field((string) ($draft['title'] ?? '')),
            'body' => sanitize_textarea_field((string) ($draft['body'] ?? '')),
            'category' => (int) ($draft['category'] ?? 0),
            'name' => sanitize_text_field((string) ($draft['name'] ?? '')),
            'email' => sanitize_email((string) ($draft['email'] ?? '')),
        ];
    }

    public static function manage(): void
    {
        $id = self::int('request');
        self::verify('ruvelo_feedback_manage_'.$id, $id);
        if (! Viewer::canManage() || Requests::get($id) === null) {
            wp_die(esc_html__('You can’t manage feedback on this site.', 'ruvelo-feedback'), '', ['response' => 403]);
        }
        $by = get_current_user_id();
        switch (self::str('op')) {
            case 'status':
                Requests::changeStatus($id, sanitize_key(self::str('status')), self::str('note', true), $by, self::str('notify') === '1');
                self::back('updated', $id);
                // no break: back() exits.
            case 'pin':
                Requests::pin($id, self::str('pinned') === '1');
                self::back('updated', $id);
                // no break
            case 'approve':
                Requests::approve($id);
                self::back('updated', $id);
                // no break
            case 'merge':
                $into = self::int('into');
                $result = Requests::merge($id, $into, $by);
                if ($result instanceof WP_Error) {
                    self::back('invalid', $id);
                }
                self::go((string) get_permalink($into), 'merged');
                // no break
            default:
                self::back('invalid', $id);
        }
    }

    /**
     * GET links from emails: confirm a guest vote, unsubscribe. Also sends
     * merged requests to the request they were merged into.
     */
    public static function links(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Signed links (HMAC tokens) instead of nonces: they arrive from email.
        if (isset($_GET['ruvelo_feedback_confirm'], $_GET['email'], $_GET['token'])) {
            $id = absint($_GET['ruvelo_feedback_confirm']);
            $email = sanitize_email(wp_unslash($_GET['email']));
            $token = sanitize_key(wp_unslash($_GET['token']));
            $url = (string) get_permalink($id);
            if ($url === '' || ! hash_equals(self::confirmToken($email, $id), $token) || ! Requests::confirmGuestVote($id, $email)) {
                self::go($url !== '' ? $url : home_url('/'), 'badlink');
            }
            Viewer::rememberGuest($email);
            self::go($url, 'confirmed');
        }

        if (isset($_GET['ruvelo_feedback_unsubscribe'], $_GET['email'], $_GET['token'])) {
            $id = absint($_GET['ruvelo_feedback_unsubscribe']);
            $email = sanitize_email(wp_unslash($_GET['email']));
            $token = sanitize_key(wp_unslash($_GET['token']));
            if (! Notifications::verify($email, $id, $token)) {
                wp_die(esc_html__('That unsubscribe link didn’t work. Use the link from your most recent email.', 'ruvelo-feedback'), esc_html__('Unsubscribe', 'ruvelo-feedback'), ['response' => 400]);
            }
            // A POST (the button, or a mail client's one-click unsubscribe) does it;
            // a GET only asks, so link scanners can't unsubscribe anyone.
            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $scope = isset($_POST['scope']) && $_POST['scope'] === 'all' ? 'all' : 'request';
                Notifications::unsubscribe($email, $id, $scope);
                wp_die(
                    '<h1>'.esc_html__('You’re unsubscribed', 'ruvelo-feedback').'</h1><p>'.esc_html($scope === 'all' ? __('You won’t get any more feedback emails from this site.', 'ruvelo-feedback') : __('You won’t get more emails about this request.', 'ruvelo-feedback')).'</p><p><a href="'.esc_url(home_url('/')).'">'.esc_html(Settings::name()).'</a></p>',
                    esc_html__('Unsubscribed', 'ruvelo-feedback'),
                    ['response' => 200]
                );
            }
            $title = get_the_title($id);
            $form = '<h1>'.esc_html__('Stop these emails?', 'ruvelo-feedback').'</h1>'
                /* translators: %s: request title */
                .'<p>'.esc_html(sprintf(__('You get emails when “%s” changes status.', 'ruvelo-feedback'), $title)).'</p>'
                .'<form method="post"><p><label><input type="radio" name="scope" value="request" checked> '.esc_html__('Stop emails about this request', 'ruvelo-feedback').'</label><br>'
                .'<label><input type="radio" name="scope" value="all"> '.esc_html__('Stop all feedback emails from this site', 'ruvelo-feedback').'</label></p>'
                .'<p><button type="submit" class="button button-primary">'.esc_html__('Unsubscribe', 'ruvelo-feedback').'</button></p></form>';
            wp_die($form, esc_html__('Unsubscribe', 'ruvelo-feedback'), ['response' => 200]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
        }
        // phpcs:enable

        self::redirectMerged();
    }

    private static function redirectMerged(): void
    {
        if (! is_404() || get_query_var('post_type') !== PostTypes::REQUEST) {
            return;
        }
        $name = get_query_var('name');
        if (! is_string($name) || $name === '') {
            return;
        }
        $posts = get_posts(['post_type' => PostTypes::REQUEST, 'name' => $name, 'post_status' => PostTypes::MERGED, 'posts_per_page' => 1]);
        if ($posts === []) {
            return;
        }
        $into = (int) get_post_meta($posts[0]->ID, '_ruvelo_merged_into', true);
        $seen = 0;
        while ($into > 0 && get_post_status($into) === PostTypes::MERGED && $seen++ < 5) {
            $into = (int) get_post_meta($into, '_ruvelo_merged_into', true);
        }
        if ($into > 0 && get_post_status($into) === 'publish') {
            self::redirect((string) get_permalink($into), 301);
        }
    }

    // ------------------------------------------------------------------

    private static function verify(string $action, int $id): void
    {
        if (! wp_verify_nonce(self::str('_ruvelo_nonce'), $action)) {
            self::back('expired', $id);
        }
    }

    private static function back(string $message, int $id): never
    {
        $referer = wp_get_referer();
        $url = $referer !== false ? $referer : (string) get_permalink($id);
        self::go($url !== '' ? $url : home_url('/'), $message);
    }

    private static function go(string $url, string $message): never
    {
        $url = remove_query_arg(['ruvelo_msg', 'fb_draft'], $url);
        self::redirect(add_query_arg('ruvelo_msg', $message, $url));
    }

    private static function redirect(string $url, int $status = 302): never
    {
        if (defined('RUVELO_FEEDBACK_TESTS')) {
            throw new Redirect($url, $status); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only signal, never shown.
        }
        wp_safe_redirect($url, $status);
        exit;
    }

    private static function str(string $key, bool $multiline = false): string
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Each handler verifies its nonce first.
        if (! isset($_POST[$key]) || ! is_string($_POST[$key])) {
            return '';
        }

        return $multiline ? sanitize_textarea_field(wp_unslash($_POST[$key])) : sanitize_text_field(wp_unslash($_POST[$key]));
        // phpcs:enable
    }

    private static function int(string $key): int
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Each handler verifies its nonce first.
        return isset($_POST[$key]) && is_numeric($_POST[$key]) ? absint($_POST[$key]) : 0;
    }
}
