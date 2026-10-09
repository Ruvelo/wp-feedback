<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Tests\Feature;

use Ruvelo\WP\Feedback\Changelog;
use Ruvelo\WP\Feedback\Notifications;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Tests\TestCase;

final class NotificationsTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function sentTo(): array
    {
        $mailer = tests_retrieve_phpmailer_instance();
        $to = [];
        foreach ($mailer->mock_sent as $mail) {
            $to[] = $mail['to'][0][0];
        }
        sort($to);

        return $to;
    }

    private function email(int $user): string
    {
        return get_userdata($user)->user_email;
    }

    public function test_a_status_change_queues_but_never_sends_inline(): void
    {
        $id = $this->request();
        Requests::vote($id, $this->user());

        Requests::changeStatus($id, 'planned', 'Starting in March.', $this->manager());

        $this->assertSame([], $this->sentTo());
        $this->assertSame(1, Notifications::pending());
        $this->assertNotFalse(wp_next_scheduled(Notifications::CRON_HOOK));
    }

    public function test_the_cron_job_tells_the_author_and_voters_but_not_whoever_changed_it(): void
    {
        $author = $this->user();
        $voter = $this->user();
        $manager = $this->manager();
        $id = $this->request('Dark mode', $author);
        Requests::vote($id, $voter);
        Requests::vote($id, $manager);

        Requests::changeStatus($id, 'planned', 'Starting in March.', $manager);
        Notifications::run();

        $expected = [$this->email($author), $this->email($voter)];
        sort($expected);
        $this->assertSame($expected, $this->sentTo());
        $this->assertSame(0, Notifications::pending());

        $mail = tests_retrieve_phpmailer_instance()->get_sent();
        $this->assertStringContainsString('Planned: Dark mode', $mail->subject);
        $this->assertStringContainsString('Starting in March.', $mail->body);
        $this->assertStringContainsString('ruvelo_feedback_unsubscribe', $mail->body);
    }

    public function test_emails_go_out_in_batches(): void
    {
        $id = $this->request();
        foreach (range(1, 7) as $i) {
            Requests::vote($id, $this->user());
        }
        Requests::changeStatus($id, 'in_progress', '', $this->manager());

        // A batch counts people considered: the author, their own vote
        // (skipped, they already heard), and one more voter.
        $this->assertSame(3, Notifications::run(3));
        $this->assertCount(2, $this->sentTo());
        $this->assertSame(1, Notifications::pending());

        Notifications::run(3);
        Notifications::run(3);
        Notifications::run(3);

        $this->assertCount(8, $this->sentTo()); // The author and seven voters.
        $this->assertSame(0, Notifications::pending());
    }

    public function test_nobody_hears_twice_about_one_change(): void
    {
        $author = $this->user();
        $id = $this->request('Dark mode', $author); // The author also voted.

        Requests::changeStatus($id, 'planned', '', $this->manager());
        Notifications::run();

        $this->assertSame([$this->email($author)], $this->sentTo());
    }

    public function test_statuses_left_out_of_the_settings_send_nothing(): void
    {
        $this->settings(['notify_statuses' => ['shipped']]);
        $id = $this->request();

        Requests::changeStatus($id, 'planned', '', $this->manager());

        $this->assertSame(0, Notifications::pending());
    }

    public function test_notify_false_skips_the_queue(): void
    {
        Requests::changeStatus($this->request(), 'planned', '', $this->manager(), false);

        $this->assertSame(0, Notifications::pending());
    }

    public function test_every_recipient_fires_the_hook_even_when_emails_are_off(): void
    {
        $this->settings(['emails' => false]);
        $recipients = [];
        add_action('ruvelo_feedback_notification', static function (array $recipient, array $message) use (&$recipients): void {
            $recipients[] = [$recipient['role'], $message['status'], $message['title']];
        }, 10, 2);
        $id = $this->request('Dark mode');
        Requests::vote($id, $this->user());

        Requests::changeStatus($id, 'planned', '', $this->manager());
        Notifications::run();

        $this->assertSame([['author', 'planned', 'Planned: Dark mode'], ['voter', 'planned', 'Planned: Dark mode']], $recipients);
        $this->assertSame([], $this->sentTo());
    }

    public function test_a_filter_can_take_over_delivery(): void
    {
        add_filter('ruvelo_feedback_send_email', '__return_false');
        $id = $this->request();
        Requests::changeStatus($id, 'planned', '', $this->manager());
        Notifications::run();

        $this->assertSame([], $this->sentTo());
    }

    public function test_unsubscribing_from_one_request(): void
    {
        $voter = $this->user();
        $id = $this->request();
        Requests::vote($id, $voter);
        $email = $this->email($voter);
        $this->assertTrue(Notifications::verify($email, $id, Notifications::token($email, $id)));
        $this->assertFalse(Notifications::verify($email, $id, 'forged'));

        Notifications::unsubscribe($email, $id, 'request');
        Requests::changeStatus($id, 'planned', '', $this->manager());
        Notifications::run();

        $this->assertNotContains($email, $this->sentTo());
    }

    public function test_unsubscribing_from_everything(): void
    {
        $voter = $this->user();
        $email = $this->email($voter);
        Notifications::unsubscribe($email, 0, 'all');
        $id = $this->request();
        Requests::vote($id, $voter);

        Requests::changeStatus($id, 'planned', '', $this->manager());
        Notifications::run();

        $this->assertTrue(Notifications::optedOut(strtoupper($email)));
        $this->assertNotContains($email, $this->sentTo());
    }

    public function test_shipping_in_a_changelog_entry_links_to_it(): void
    {
        $author = $this->user();
        $id = $this->request('Dark mode', $author);

        $entry = Changelog::create('Dark mode is here', '<p>Finally.</p>', ['new'], [$id], true, $this->manager());
        $this->assertIsInt($entry);
        $this->assertSame('shipped', Requests::status($id));
        $this->assertSame($entry, Requests::entryId($id));

        Notifications::run();
        $mail = tests_retrieve_phpmailer_instance()->get_sent();
        $this->assertStringContainsString('Shipped: Dark mode', $mail->subject);
        $this->assertStringContainsString(get_permalink($entry), $mail->body);
    }

    public function test_publishing_twice_does_not_notify_twice(): void
    {
        $id = $this->request('Dark mode');
        $entry = Changelog::create('Dark mode is here', '', [], [$id], true, $this->manager());
        $this->assertIsInt($entry);
        Notifications::run();
        reset_phpmailer_instance();

        wp_update_post(['ID' => $entry, 'post_title' => 'Dark mode is really here']);
        Notifications::run();

        $this->assertSame([], $this->sentTo());
    }

    public function test_guests_get_emails_at_the_address_they_confirmed(): void
    {
        $id = $this->request();
        Requests::vote($id, 0, 'guest@example.com', false);
        Requests::changeStatus($id, 'planned', '', $this->manager());
        Notifications::run();
        $this->assertNotContains('guest@example.com', $this->sentTo());

        reset_phpmailer_instance();
        Requests::confirmGuestVote($id, 'guest@example.com');
        Requests::changeStatus($id, 'in_progress', '', $this->manager());
        Notifications::run();
        $this->assertContains('guest@example.com', $this->sentTo());
    }
}
