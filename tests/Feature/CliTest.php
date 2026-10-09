<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Tests\Feature;

use RuntimeException;
use Ruvelo\WP\Feedback\Changelog;
use Ruvelo\WP\Feedback\Cli\ChangelogCommand;
use Ruvelo\WP\Feedback\Cli\Command;
use Ruvelo\WP\Feedback\Cli\NotificationsCommand;
use Ruvelo\WP\Feedback\Cli\RequestCommand;
use Ruvelo\WP\Feedback\Frontend\Urls;
use Ruvelo\WP\Feedback\Notifications;
use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Tests\TestCase;
use WP_CLI;

require_once dirname(__DIR__).'/Support/FakeCli.php';

final class CliTest extends TestCase
{
    public function set_up(): void
    {
        parent::set_up();
        WP_CLI::$out = [];
    }

    public function test_request_create_and_list(): void
    {
        $user = $this->user();
        (new RequestCommand)->create(['Slack alerts'], ['user' => (string) $user, 'body' => 'In a channel', 'porcelain' => true]);
        $id = (int) WP_CLI::last();
        $this->assertSame('Slack alerts', get_the_title($id));
        $this->assertSame(1, Requests::votes($id));

        (new RequestCommand)->list_([], ['format' => 'json']);
        $items = json_decode(WP_CLI::last(), true);
        $this->assertSame('Slack alerts', $items[0]['title']);
    }

    public function test_request_status(): void
    {
        $id = $this->request();

        (new RequestCommand)->status([(string) $id, 'planned'], ['note' => 'In March.']);

        $this->assertSame('planned', Requests::status($id));
        $this->assertSame('In March.', Requests::history($id)[0]['note']);
        $this->assertSame(1, Notifications::pending());
        $this->assertStringContainsString('is now Planned', WP_CLI::last());
    }

    public function test_request_status_without_emails(): void
    {
        $id = $this->request();

        (new RequestCommand)->status([(string) $id, 'planned'], ['notify' => false]);

        $this->assertSame(0, Notifications::pending());
    }

    public function test_request_status_rejects_unknown_statuses(): void
    {
        $this->expectException(RuntimeException::class);
        (new RequestCommand)->status([(string) $this->request(), 'someday'], []);
    }

    public function test_request_merge_pin_and_vote(): void
    {
        $keep = $this->request('Dark mode');
        $dup = $this->request('Dark theme');
        $voter = $this->user();

        (new RequestCommand)->vote([(string) $keep], ['user' => (string) $voter]);
        (new RequestCommand)->merge([(string) $dup], ['into' => (string) $keep]);
        (new RequestCommand)->pin([(string) $keep], []);

        $this->assertSame(3, Requests::votes($keep));
        $this->assertSame(PostTypes::MERGED, get_post_status($dup));
        $this->assertTrue(Requests::isPinned($keep));
    }

    public function test_request_approve(): void
    {
        $id = Requests::create(['title' => 'Held back'], $this->user(), null, true);
        $this->assertIsInt($id);

        (new RequestCommand)->approve([(string) $id]);

        $this->assertSame('publish', get_post_status($id));
    }

    public function test_changelog_create_and_publish_ships_requests(): void
    {
        $id = $this->request('Dark mode');

        (new ChangelogCommand)->create(['Dark mode is here'], ['labels' => 'new', 'requests' => (string) $id, 'draft' => true, 'porcelain' => true]);
        $entry = (int) WP_CLI::last();
        $this->assertSame('draft', get_post_status($entry));
        $this->assertSame('under_review', Requests::status($id));

        (new ChangelogCommand)->publish([(string) $entry], []);

        $this->assertSame('publish', get_post_status($entry));
        $this->assertSame('shipped', Requests::status($id));
        $this->assertSame([$id], Changelog::requestIds($entry));
    }

    public function test_notifications_run_sends_everything_waiting(): void
    {
        $id = $this->request();
        Requests::changeStatus($id, 'planned', '', 0);

        (new NotificationsCommand)->run([], []);

        $this->assertSame(0, Notifications::pending());
        $this->assertNotFalse(tests_retrieve_phpmailer_instance()->get_sent());
    }

    public function test_setup_creates_the_pages_once(): void
    {
        Command::setup();
        $pages = Urls::pages();
        Command::setup();

        $this->assertSame(['board', 'roadmap', 'submit'], array_keys($pages));
        $this->assertSame($pages, Urls::pages());
        $this->assertStringContainsString('wp:ruvelo/feedback-board', get_post($pages['board'])->post_content);
        $this->assertSame($pages['board'], get_post($pages['roadmap'])->post_parent);
    }
}
