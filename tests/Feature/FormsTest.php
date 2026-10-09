<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Tests\Feature;

use Ruvelo\WP\Feedback\Frontend\Forms;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Support\Redirect;
use Ruvelo\WP\Feedback\Tests\TestCase;
use Ruvelo\WP\Feedback\Votes;
use WPDieException;

/**
 * The no-JavaScript path: nonces, permissions, honeypot and rate limits.
 */
final class FormsTest extends TestCase
{
    public function tear_down(): void
    {
        $_POST = [];
        parent::tear_down();
    }

    /**
     * @param array<string, mixed> $post
     */
    private function submit(callable $handler, array $post): string
    {
        $_POST = $post;
        try {
            $handler();
        } catch (Redirect $redirect) {
            return $redirect->url;
        }
        $this->fail('Expected a redirect.');
    }

    public function test_a_vote_needs_a_valid_nonce(): void
    {
        $id = $this->request();
        $voter = $this->user();
        wp_set_current_user($voter);

        $url = $this->submit([Forms::class, 'vote'], ['request' => $id, 'vote' => '1', '_ruvelo_nonce' => 'forged']);

        $this->assertStringContainsString('ruvelo_msg=expired', $url);
        $this->assertFalse(Votes::has($id, Votes::userKey($voter)));
    }

    public function test_a_vote_with_a_nonce_counts(): void
    {
        $id = $this->request();
        $voter = $this->user();
        wp_set_current_user($voter);

        $url = $this->submit([Forms::class, 'vote'], ['request' => $id, 'vote' => '1', '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_vote_'.$id)]);

        $this->assertStringContainsString('ruvelo_msg=voted', $url);
        $this->assertTrue(Votes::has($id, Votes::userKey($voter)));
    }

    public function test_a_nonce_for_one_request_does_not_work_for_another(): void
    {
        $a = $this->request('Request A');
        $b = $this->request('Request B');
        wp_set_current_user($this->user());

        $url = $this->submit([Forms::class, 'vote'], ['request' => $b, 'vote' => '1', '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_vote_'.$a)]);

        $this->assertStringContainsString('ruvelo_msg=expired', $url);
    }

    public function test_guests_cannot_vote_when_only_users_may(): void
    {
        $id = $this->request();
        wp_set_current_user(0);

        $url = $this->submit([Forms::class, 'vote'], ['request' => $id, 'vote' => '1', '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_vote_'.$id)]);

        $this->assertStringContainsString('ruvelo_msg=login', $url);
        $this->assertSame(1, Requests::votes($id));
    }

    public function test_guest_votes_send_a_confirmation_link(): void
    {
        $this->settings(['vote' => 'anyone']);
        $id = $this->request();
        wp_set_current_user(0);

        $url = $this->submit([Forms::class, 'guestVote'], ['request' => $id, 'email' => 'guest@example.com', '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_guest_vote_'.$id)]);

        $this->assertStringContainsString('ruvelo_msg=confirm', $url);
        $this->assertSame(1, Requests::votes($id));
        $mail = tests_retrieve_phpmailer_instance()->get_sent();
        $this->assertSame('guest@example.com', $mail->to[0][0]);
        $this->assertStringContainsString(Forms::confirmToken('guest@example.com', $id), $mail->body);
    }

    public function test_the_honeypot_drops_bots_quietly(): void
    {
        $this->settings(['vote' => 'anyone']);
        $id = $this->request();
        wp_set_current_user(0);

        $url = $this->submit([Forms::class, 'guestVote'], ['request' => $id, 'email' => 'bot@example.com', 'website' => 'http://spam.test', '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_guest_vote_'.$id)]);

        $this->assertStringContainsString('ruvelo_msg=voted', $url);
        $this->assertNull(Votes::find($id, Votes::emailKey('bot@example.com')));
        $this->assertFalse(tests_retrieve_phpmailer_instance()->get_sent());
    }

    public function test_posting_a_request(): void
    {
        wp_set_current_user($this->user());

        $url = $this->submit([Forms::class, 'submit'], ['title' => 'Slack alerts for failed payments', 'body' => 'In a channel', 'js' => '1', '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_submit')]);

        $this->assertStringContainsString('ruvelo_msg=posted', $url);
        $this->assertCount(1, Requests::board()['posts']);
    }

    public function test_without_javascript_similar_requests_are_shown_first(): void
    {
        $this->request('QuickBooks Online integration');
        wp_set_current_user($this->user());

        $url = $this->submit([Forms::class, 'submit'], ['title' => 'Sync with QuickBooks', 'return' => home_url('/new/'), '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_submit')]);

        $this->assertStringContainsString('fb_draft=', $url);
        $this->assertCount(1, Requests::board()['posts']);

        // Ticking "None of these" posts it.
        $url = $this->submit([Forms::class, 'submit'], ['title' => 'Sync with QuickBooks', 'confirm_new' => '1', '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_submit')]);
        $this->assertStringContainsString('ruvelo_msg=posted', $url);
    }

    public function test_guest_requests_need_an_email_and_wait_for_moderation(): void
    {
        $this->settings(['post' => 'anyone']);
        wp_set_current_user(0);

        $url = $this->submit([Forms::class, 'submit'], ['title' => 'SEPA Instant', 'js' => '1', '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_submit')]);
        $this->assertStringContainsString('ruvelo_msg=email', $url);

        $url = $this->submit([Forms::class, 'submit'], ['title' => 'SEPA Instant', 'name' => 'Jonas', 'email' => 'jonas@example.com', 'js' => '1', '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_submit')]);
        $this->assertStringContainsString('ruvelo_msg=pending', $url);
        $this->assertSame([], Requests::board()['posts']);
    }

    public function test_guests_cannot_post_when_only_users_may(): void
    {
        wp_set_current_user(0);

        $url = $this->submit([Forms::class, 'submit'], ['title' => 'Something', 'name' => 'A', 'email' => 'a@example.com', 'js' => '1', '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_submit')]);

        $this->assertStringContainsString('ruvelo_msg=login', $url);
    }

    public function test_posting_is_rate_limited(): void
    {
        wp_set_current_user($this->user());
        $nonce = wp_create_nonce('ruvelo_feedback_submit');
        foreach (range(1, 5) as $i) {
            $this->submit([Forms::class, 'submit'], ['title' => 'Idea number '.$i, 'js' => '1', '_ruvelo_nonce' => $nonce]);
        }

        $url = $this->submit([Forms::class, 'submit'], ['title' => 'Idea number six', 'js' => '1', '_ruvelo_nonce' => $nonce]);

        $this->assertStringContainsString('ruvelo_msg=rate', $url);
        $this->assertCount(5, Requests::board()['posts']);
    }

    public function test_only_managers_can_manage(): void
    {
        $id = $this->request();
        wp_set_current_user($this->user());
        $_POST = ['request' => $id, 'op' => 'status', 'status' => 'planned', '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_manage_'.$id)];

        $this->expectException(WPDieException::class);
        Forms::manage();
    }

    public function test_managers_change_status_from_the_request_page(): void
    {
        $id = $this->request();
        wp_set_current_user($this->manager());

        $url = $this->submit([Forms::class, 'manage'], ['request' => $id, 'op' => 'status', 'status' => 'planned', 'note' => 'Soon', '_ruvelo_nonce' => wp_create_nonce('ruvelo_feedback_manage_'.$id)]);

        $this->assertStringContainsString('ruvelo_msg=updated', $url);
        $this->assertSame('planned', Requests::status($id));
    }
}
