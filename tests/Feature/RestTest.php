<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Tests\Feature;

use Ruvelo\WP\Feedback\Changelog;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Tests\TestCase;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class RestTest extends TestCase
{
    public function set_up(): void
    {
        parent::set_up();
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server;
        do_action('rest_api_init', $wp_rest_server);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function call(string $method, string $path, array $params = []): WP_REST_Response
    {
        $request = new WP_REST_Request($method, '/ruvelo-feedback/v1'.$path);
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        return rest_get_server()->dispatch($request);
    }

    public function test_routes_are_registered(): void
    {
        $routes = rest_get_server()->get_routes('ruvelo-feedback/v1');

        foreach (['/requests', '/requests/similar', '/requests/(?P<id>\d+)', '/requests/(?P<id>\d+)/vote', '/requests/(?P<id>\d+)/status', '/requests/(?P<id>\d+)/merge', '/roadmap', '/changelog', '/changelog/unread', '/changelog/seen'] as $route) {
            $this->assertArrayHasKey('/ruvelo-feedback/v1'.$route, $routes);
        }
    }

    public function test_anyone_can_read_the_board(): void
    {
        $id = $this->request('Dark mode');

        $response = $this->call('GET', '/requests');

        $this->assertSame(200, $response->get_status());
        $this->assertSame('Dark mode', $response->get_data()[0]['title']);
        $this->assertSame(1, $response->get_data()[0]['votes']);
        $this->assertSame('1', $response->get_headers()['X-WP-Total']);
        $this->assertSame($id, $this->call('GET', '/requests/'.$id)->get_data()['id']);
    }

    public function test_pending_requests_are_hidden_from_strangers(): void
    {
        $author = $this->user();
        $id = Requests::create(['title' => 'Held for review'], $author, null, true);

        $this->assertSame(404, $this->call('GET', '/requests/'.$id)->get_status());

        wp_set_current_user($author);
        $this->assertSame(200, $this->call('GET', '/requests/'.$id)->get_status());

        wp_set_current_user($this->manager());
        $this->assertSame(200, $this->call('GET', '/requests/'.$id)->get_status());
    }

    public function test_similar_requests(): void
    {
        $this->request('QuickBooks Online integration');
        $this->request('Dark mode');

        $data = $this->call('GET', '/requests/similar', ['q' => 'Sync with QuickBooks'])->get_data();

        $this->assertCount(1, $data);
        $this->assertSame('QuickBooks Online integration', $data[0]['title']);
    }

    public function test_guests_cannot_post_or_vote(): void
    {
        $id = $this->request();

        $this->assertSame(401, $this->call('POST', '/requests', ['title' => 'Something new'])->get_status());
        $this->assertSame(401, $this->call('POST', '/requests/'.$id.'/vote')->get_status());
    }

    public function test_signed_in_people_post_and_vote(): void
    {
        wp_set_current_user($this->user());

        $created = $this->call('POST', '/requests', ['title' => 'Slack alerts', 'body' => "When a payment fails\n\n- in a channel"]);
        $this->assertSame(201, $created->get_status());
        $id = $created->get_data()['id'];
        $this->assertTrue($created->get_data()['voted']);

        wp_set_current_user($this->user());
        $vote = $this->call('POST', '/requests/'.$id.'/vote');
        $this->assertSame(['id' => $id, 'voted' => true, 'votes' => 2], $vote->get_data());

        $unvote = $this->call('DELETE', '/requests/'.$id.'/vote');
        $this->assertSame(1, $unvote->get_data()['votes']);
    }

    public function test_titles_are_validated(): void
    {
        wp_set_current_user($this->user());

        $this->assertSame(400, $this->call('POST', '/requests', ['title' => 'x'])->get_status());
    }

    public function test_only_managers_change_status_merge_and_pin(): void
    {
        $id = $this->request('Dark mode');
        $other = $this->request('Dark theme');
        wp_set_current_user($this->user());

        $this->assertSame(403, $this->call('POST', '/requests/'.$id.'/status', ['status' => 'planned'])->get_status());
        $this->assertSame(403, $this->call('POST', '/requests/'.$other.'/merge', ['into' => $id])->get_status());
        $this->assertSame(403, $this->call('POST', '/requests/'.$id.'/pin')->get_status());

        wp_set_current_user($this->manager());
        $status = $this->call('POST', '/requests/'.$id.'/status', ['status' => 'planned', 'note' => 'Soon.']);
        $this->assertSame('planned', $status->get_data()['status']);
        $this->assertSame(422, $this->call('POST', '/requests/'.$id.'/status', ['status' => 'nope'])->get_status());

        $merge = $this->call('POST', '/requests/'.$other.'/merge', ['into' => $id]);
        $this->assertSame(200, $merge->get_status());
        $this->assertSame(2, $merge->get_data()['into']['votes']);

        $this->assertTrue($this->call('POST', '/requests/'.$id.'/pin')->get_data()['pinned']);
    }

    public function test_the_roadmap(): void
    {
        $id = $this->request('Dark mode');
        Requests::changeStatus($id, 'planned', '', 0, false);

        $columns = $this->call('GET', '/roadmap')->get_data();

        $this->assertSame(['planned', 'in_progress', 'shipped'], array_column($columns, 'status'));
        $this->assertSame('Dark mode', $columns[0]['requests'][0]['title']);
    }

    public function test_the_changelog_and_whats_new(): void
    {
        $user = $this->user();
        wp_set_current_user($user);
        Changelog::create('Faster search', '<p>Ten times faster.</p>', ['improved']);

        $entries = $this->call('GET', '/changelog')->get_data();
        $this->assertSame('Faster search', $entries[0]['title']);
        $this->assertSame('improved', $entries[0]['labels'][0]['slug']);

        $this->assertSame(1, $this->call('GET', '/changelog/unread')->get_data()['unread']);
        $this->call('POST', '/changelog/seen');
        $this->assertSame(0, $this->call('GET', '/changelog/unread')->get_data()['unread']);
    }
}
