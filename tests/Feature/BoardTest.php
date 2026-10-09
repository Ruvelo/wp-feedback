<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Tests\Feature;

use Ruvelo\WP\Feedback\Install;
use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Privacy;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Support\Text;
use Ruvelo\WP\Feedback\Tests\TestCase;
use Ruvelo\WP\Feedback\Votes;
use WP_Post;

final class BoardTest extends TestCase
{
    /**
     * @param array<string, mixed> $args
     * @return list<string>
     */
    private function titles(array $args = []): array
    {
        return array_map(static fn (WP_Post $post): string => $post->post_title, Requests::board($args)['posts']);
    }

    public function test_top_sorts_by_votes_with_pinned_first(): void
    {
        $few = $this->request('Few votes');
        $many = $this->request('Many votes');
        $pinned = $this->request('Pinned one');
        foreach (range(1, 3) as $i) {
            Requests::vote($many, $this->user());
        }
        Requests::vote($few, $this->user());
        Requests::pin($pinned);

        $this->assertSame(['Pinned one', 'Many votes', 'Few votes'], $this->titles());
    }

    public function test_the_board_shows_open_requests_unless_asked(): void
    {
        $this->request('Still open');
        Requests::changeStatus($this->request('Done already'), 'shipped', '', 0, false);

        $this->assertSame(['Still open'], $this->titles());
        $this->assertSame(['Done already'], $this->titles(['status' => 'shipped']));
        $this->assertCount(2, $this->titles(['status' => 'all']));
    }

    public function test_filters_by_category_tag_and_search(): void
    {
        $billing = self::factory()->term->create(['taxonomy' => PostTypes::CATEGORY, 'name' => 'Billing', 'slug' => 'billing']);
        $this->request('Usage-based billing', 0, ['category' => $billing, 'tags' => ['Enterprise']]);
        $this->request('Dark mode');

        $this->assertSame(['Usage-based billing'], $this->titles(['category' => 'billing']));
        $this->assertSame(['Usage-based billing'], $this->titles(['tag' => 'enterprise']));
        $this->assertSame(['Dark mode'], $this->titles(['search' => 'dark']));
    }

    public function test_trending_counts_recent_votes(): void
    {
        global $wpdb;
        $old = $this->request('Old favourite');
        $new = $this->request('Rising star');
        foreach (range(1, 4) as $i) {
            Requests::vote($old, $this->user());
        }
        $wpdb->query($wpdb->prepare('UPDATE '.Install::votesTable().' SET created_at = %s WHERE request_id = %d', gmdate('Y-m-d H:i:s', time() - 60 * DAY_IN_SECONDS), $old));
        Requests::vote($new, $this->user());

        $this->assertSame(['Old favourite', 'Rising star'], $this->titles(['sort' => 'top']));
        $this->assertSame(['Rising star', 'Old favourite'], $this->titles(['sort' => 'trending']));
    }

    public function test_counts_by_status(): void
    {
        $this->request('Request one');
        Requests::changeStatus($this->request('Request two'), 'planned', '', 0, false);

        $this->assertEqualsCanonicalizing(['under_review' => 1, 'planned' => 1], array_filter(Requests::countsByStatus()));
    }

    public function test_status_history_is_kept(): void
    {
        $id = $this->request();
        $manager = $this->manager();
        Requests::changeStatus($id, 'planned', 'Next quarter.', $manager, false);
        Requests::changeStatus($id, 'planned', 'Designs are ready.', $manager, false);

        $history = Requests::history($id);
        $this->assertSame(['under_review', 'planned'], [$history[0]['from'], $history[0]['to']]);
        $this->assertSame('Designs are ready.', $history[1]['note']);
        $this->assertFalse(Requests::changeStatus($id, 'planned'));
    }

    public function test_plain_text_becomes_blocks(): void
    {
        $blocks = Text::toBlocks("First line\nsecond line\n\n- **one**\n- two `code`\n\nSee https://example.com");

        $this->assertStringContainsString('<!-- wp:paragraph -->', $blocks);
        $this->assertStringContainsString('<p>First line<br>second line</p>', $blocks);
        $this->assertStringContainsString('<li><strong>one</strong></li>', $blocks);
        $this->assertStringContainsString('<code>code</code>', $blocks);
        $this->assertStringContainsString('<a href="https://example.com"', $blocks);
        $this->assertStringNotContainsString('<script', Text::toBlocks('<script>alert(1)</script>'));
    }

    public function test_requests_created_in_the_admin_get_defaults(): void
    {
        $id = self::factory()->post->create(['post_type' => PostTypes::REQUEST, 'post_title' => 'From the admin']);

        $this->assertSame('under_review', Requests::status($id));
        $this->assertSame(['From the admin'], $this->titles());
    }

    public function test_deleting_a_request_deletes_its_votes(): void
    {
        $voter = $this->user();
        $id = $this->request();
        Requests::vote($id, $voter);

        wp_delete_post($id, true);

        $this->assertNull(Votes::find($id, Votes::userKey($voter)));
    }

    public function test_personal_data_export_and_erasure(): void
    {
        $voter = $this->user();
        $email = get_userdata($voter)->user_email;
        $id = $this->request();
        Requests::vote($id, $voter);
        Requests::vote($id, 0, 'guest@example.com');

        $this->assertCount(1, Privacy::export($email)['data']);
        $this->assertTrue(Privacy::erase('guest@example.com')['items_removed']);
        $this->assertSame(2, Requests::votes($id));
    }
}
