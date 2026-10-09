<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Tests\Feature;

use Ruvelo\WP\Feedback\Install;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Tests\TestCase;
use Ruvelo\WP\Feedback\Votes;
use WP_Error;

final class VotesTest extends TestCase
{
    public function test_the_author_votes_for_their_own_request(): void
    {
        $author = $this->user();
        $id = $this->request('Dark mode', $author);

        $this->assertSame(1, Requests::votes($id));
        $this->assertTrue(Votes::has($id, Votes::userKey($author)));
    }

    public function test_one_vote_per_person(): void
    {
        $id = $this->request();
        $voter = $this->user();

        $this->assertTrue(Requests::vote($id, $voter));
        $this->assertFalse(Requests::vote($id, $voter));
        $this->assertFalse(Requests::vote($id, $voter));

        $this->assertSame(2, Requests::votes($id));
    }

    public function test_the_unique_index_rejects_a_duplicate_row(): void
    {
        global $wpdb;
        $id = $this->request();
        $voter = $this->user();
        Votes::add($id, Votes::userKey($voter), $voter);

        $suppress = $wpdb->suppress_errors(true);
        $result = $wpdb->insert(Install::votesTable(), ['request_id' => $id, 'voter' => Votes::userKey($voter), 'user_id' => $voter, 'created_at' => current_time('mysql', true)]);
        $wpdb->suppress_errors($suppress);

        $this->assertFalse($result);
    }

    public function test_taking_a_vote_back(): void
    {
        $id = $this->request();
        $voter = $this->user();
        Requests::vote($id, $voter);

        $this->assertTrue(Requests::unvote($id, $voter));
        $this->assertFalse(Requests::unvote($id, $voter));
        $this->assertSame(1, Requests::votes($id));
    }

    public function test_shipped_and_closed_requests_stop_taking_votes(): void
    {
        $id = $this->request();
        Requests::changeStatus($id, 'shipped', '', 0, false);

        $result = Requests::vote($id, $this->user());

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(409, $result->get_error_data()['status']);
    }

    public function test_pending_requests_cannot_be_voted_on(): void
    {
        $id = Requests::create(['title' => 'Held back'], $this->user(), null, true);
        $this->assertIsInt($id);

        $this->assertInstanceOf(WP_Error::class, Requests::vote($id, $this->user()));
    }

    public function test_a_guest_vote_counts_once_confirmed(): void
    {
        $id = $this->request();

        Requests::vote($id, 0, 'Guest@Example.com', false);
        $this->assertSame(1, Requests::votes($id));

        $this->assertTrue(Requests::confirmGuestVote($id, 'guest@example.com'));
        $this->assertSame(2, Requests::votes($id));

        // The same address can't vote twice, whatever the case.
        $this->assertFalse(Requests::vote($id, 0, 'GUEST@example.com'));
        $this->assertSame(2, Requests::votes($id));
    }

    public function test_confirming_an_unknown_guest_vote_fails(): void
    {
        $this->assertFalse(Requests::confirmGuestVote($this->request(), 'nobody@example.com'));
    }

    public function test_which_requests_someone_voted_for(): void
    {
        $voter = $this->user();
        $a = $this->request('Request A');
        $b = $this->request('Request B');
        $c = $this->request('Request C');
        Requests::vote($a, $voter);
        Requests::vote($c, $voter);

        $this->assertSame([$a => true, $c => true], Votes::votedAmong([$a, $b, $c], Votes::userKey($voter)));
        $this->assertSame([], Votes::votedAmong([$a], ''));
    }

    public function test_votes_fire_an_action(): void
    {
        $seen = [];
        add_action('ruvelo_feedback_voted', static function (int $id, string $voter, bool $added) use (&$seen): void {
            $seen[] = [$id, $voter, $added];
        }, 10, 3);
        $id = $this->request();
        $voter = $this->user();

        Requests::vote($id, $voter);
        Requests::unvote($id, $voter);

        $this->assertSame([[$id, 'u:'.$voter, true], [$id, 'u:'.$voter, false]], $seen);
    }
}
