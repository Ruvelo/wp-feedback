<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Tests\Feature;

use Ruvelo\WP\Feedback\Frontend\Forms;
use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Support\Redirect;
use Ruvelo\WP\Feedback\Tests\TestCase;
use Ruvelo\WP\Feedback\Votes;
use WP_Error;

final class MergeTest extends TestCase
{
    public function test_votes_carry_over_without_counting_anyone_twice(): void
    {
        $keep = $this->request('Dark mode');
        $dup = $this->request('Dark theme please');
        $both = $this->user();
        $onlyDup = $this->user();
        Requests::vote($keep, $both);
        Requests::vote($dup, $both);
        Requests::vote($dup, $onlyDup);
        $this->assertSame(2, Requests::votes($keep));
        $this->assertSame(3, Requests::votes($dup));

        $moved = Requests::merge($dup, $keep);

        // The duplicate's author and $onlyDup are new; $both already voted.
        $this->assertSame(2, $moved);
        $this->assertSame(4, Requests::votes($keep));
        $this->assertSame(0, Requests::votes($dup));
        $this->assertTrue(Votes::has($keep, Votes::userKey($onlyDup)));
    }

    public function test_the_duplicate_is_hidden_and_points_to_the_request_it_joined(): void
    {
        $keep = $this->request('Dark mode');
        $dup = $this->request('Dark theme please');

        Requests::merge($dup, $keep);

        $this->assertSame(PostTypes::MERGED, get_post_status($dup));
        $this->assertSame($keep, (int) get_post_meta($dup, '_ruvelo_merged_into', true));
        $this->assertSame([$dup], get_post_meta($keep, '_ruvelo_duplicates', true));
        $ids = array_map(static fn ($post) => $post->ID, Requests::board(['status' => 'all'])['posts']);
        $this->assertNotContains($dup, $ids);
    }

    public function test_comments_move_over(): void
    {
        $keep = $this->request('Dark mode');
        $dup = $this->request('Dark theme please');
        self::factory()->comment->create_many(2, ['comment_post_ID' => $dup]);

        Requests::merge($dup, $keep);

        $this->assertSame('2', get_post($keep)->comment_count);
        $this->assertSame('0', get_post($dup)->comment_count);
    }

    public function test_it_cannot_merge_into_itself_or_into_a_merged_request(): void
    {
        $a = $this->request('Request A');
        $b = $this->request('Request B');
        $c = $this->request('Request C');
        Requests::merge($b, $a);

        $this->assertInstanceOf(WP_Error::class, Requests::merge($a, $a));
        $this->assertInstanceOf(WP_Error::class, Requests::merge($c, $b));
        $this->assertInstanceOf(WP_Error::class, Requests::merge($b, $c));
    }

    public function test_the_merged_request_redirects(): void
    {
        $this->set_permalink_structure('/%postname%/');
        $keep = $this->request('Dark mode');
        $dup = $this->request('Dark theme please');
        $url = get_permalink($dup);
        Requests::merge($dup, $keep);

        $this->go_to($url);
        $this->assertTrue(is_404());

        try {
            Forms::links();
            $this->fail('Expected a redirect.');
        } catch (Redirect $redirect) {
            $this->assertSame(get_permalink($keep), $redirect->url);
            $this->assertSame(301, $redirect->status);
        }
    }

    public function test_merging_fires_an_action(): void
    {
        $fired = null;
        add_action('ruvelo_feedback_requests_merged', static function (int $dup, int $into, int $moved) use (&$fired): void {
            $fired = [$dup, $into, $moved];
        }, 10, 3);
        $keep = $this->request('Dark mode');
        $dup = $this->request('Dark theme please');

        Requests::merge($dup, $keep);

        $this->assertSame([$dup, $keep, 1], $fired);
    }
}
