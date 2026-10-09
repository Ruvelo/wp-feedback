<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Tests\Feature;

use Ruvelo\WP\Feedback\Changelog;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Tests\TestCase;
use WP_Block_Type_Registry;

final class BlocksTest extends TestCase
{
    public function test_every_block_is_registered_and_dynamic(): void
    {
        foreach (['feedback-board', 'roadmap', 'changelog', 'whats-new', 'submit-request', 'feedback-request', 'changelog-entry'] as $name) {
            $block = WP_Block_Type_Registry::get_instance()->get_registered('ruvelo/'.$name);
            $this->assertNotNull($block, $name);
            $this->assertTrue($block->is_dynamic(), $name);
        }
    }

    public function test_the_board_renders_requests_with_vote_counts(): void
    {
        $id = $this->request('Dark mode');
        Requests::vote($id, $this->user());
        Requests::pin($id);

        $html = do_blocks('<!-- wp:ruvelo/feedback-board /-->');

        $this->assertStringContainsString('ruvelo-fb ruvelo-fb--board', $html);
        $this->assertStringContainsString('Dark mode', $html);
        $this->assertStringContainsString('is-pinned', $html);
        $this->assertMatchesRegularExpression('/<span>2<\/span>/', $html);
        $this->assertStringContainsString('Tell us what to build next', $html);
    }

    public function test_the_board_shows_vote_forms_with_nonces_to_signed_in_people(): void
    {
        $id = $this->request('Dark mode');
        wp_set_current_user($this->user());

        $html = do_blocks('<!-- wp:ruvelo/feedback-board {"showHeading":false} /-->');

        $this->assertStringContainsString('data-ruvelo-vote="'.$id.'"', $html);
        $this->assertStringContainsString('name="_ruvelo_nonce"', $html);
        $this->assertStringContainsString('aria-pressed="false"', $html);
        $this->assertStringNotContainsString('Tell us what to build next', $html);
    }

    public function test_guests_get_a_sign_in_link_instead(): void
    {
        $this->request('Dark mode');

        $html = do_blocks('<!-- wp:ruvelo/feedback-board /-->');

        $this->assertStringContainsString('Sign in to vote', $html);
        $this->assertStringNotContainsString('data-ruvelo-vote', $html);
    }

    public function test_titles_are_escaped(): void
    {
        $this->request('<script>alert(1)</script> export');

        $html = do_blocks('<!-- wp:ruvelo/feedback-board /-->');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_the_roadmap_renders_columns(): void
    {
        $id = $this->request('Usage-based billing');
        Requests::changeStatus($id, 'in_progress', '', 0, false);

        $html = do_blocks('<!-- wp:ruvelo/roadmap /-->');

        $this->assertStringContainsString('Planned', $html);
        $this->assertStringContainsString('In progress', $html);
        $this->assertStringContainsString('Shipped', $html);
        $this->assertStringContainsString('Usage-based billing', $html);
    }

    public function test_the_changelog_renders_entries_and_what_they_answer(): void
    {
        $id = $this->request('Export to CSV');
        Changelog::create('Export any report', '<p>Every report has an Export button.</p>', ['new'], [$id]);

        $html = do_blocks('<!-- wp:ruvelo/changelog /-->');

        $this->assertStringContainsString('Export any report', $html);
        $this->assertStringContainsString('Every report has an Export button.', $html);
        $this->assertStringContainsString('You asked for this', $html);
        $this->assertStringContainsString('Export to CSV', $html);
    }

    public function test_whats_new_shows_the_unread_count(): void
    {
        wp_set_current_user($this->user());
        Changelog::create('One', '');
        Changelog::create('Two', '');

        $html = do_blocks('<!-- wp:ruvelo/whats-new /-->');

        $this->assertStringContainsString('<details class="ruvelo-fb-wn"', $html);
        $this->assertMatchesRegularExpression('/data-ruvelo-badge\s*>2</', $html);

        Changelog::markSeen();
        $this->assertMatchesRegularExpression('/data-ruvelo-badge hidden>0</', do_blocks('<!-- wp:ruvelo/whats-new /-->'));
    }

    public function test_the_submit_form_has_a_nonce_and_a_honeypot(): void
    {
        wp_set_current_user($this->user());

        $html = do_blocks('<!-- wp:ruvelo/submit-request /-->');

        $this->assertStringContainsString('name="action" value="ruvelo_feedback_submit"', $html);
        $this->assertStringContainsString('name="_ruvelo_nonce"', $html);
        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringNotContainsString('name="email"', $html);
    }

    public function test_the_submit_form_asks_guests_to_sign_in_unless_guests_may_post(): void
    {
        $this->assertStringContainsString('to suggest a feature', do_blocks('<!-- wp:ruvelo/submit-request /-->'));

        $this->settings(['post' => 'anyone']);
        $this->assertStringContainsString('name="email"', do_blocks('<!-- wp:ruvelo/submit-request /-->'));
    }

    public function test_the_request_block_uses_the_post_in_context(): void
    {
        $id = $this->request('Dark mode', 0, ['body' => "Late nights\n\n- **charts** too"]);
        self::factory()->comment->create(['comment_post_ID' => $id, 'comment_content' => 'Please!', 'user_id' => $this->manager()]);

        $block = new \WP_Block(['blockName' => 'ruvelo/feedback-request', 'attrs' => []], ['postId' => $id, 'postType' => 'ruvelo_request']);
        $html = $block->render();

        $this->assertStringContainsString('<h1>Dark mode</h1>', $html);
        $this->assertStringContainsString('<strong>charts</strong>', $html);
        $this->assertStringContainsString('Please!', $html);
        $this->assertStringContainsString('ruvelo-fb-chip--team', $html);
    }

    public function test_shortcodes_render_the_same_views(): void
    {
        $this->request('Dark mode');

        $this->assertStringContainsString('Dark mode', do_shortcode('[ruvelo_feedback_board]'));
        $this->assertStringContainsString('ruvelo-fb--roadmap', do_shortcode('[ruvelo_feedback_roadmap nav="no"]'));
        $this->assertStringContainsString('ruvelo-fb--changelog', do_shortcode('[ruvelo_feedback_changelog]'));
        $this->assertStringContainsString('ruvelo-fb-wn', do_shortcode('[ruvelo_feedback_whats_new]'));
        $this->assertStringContainsString('Suggest a feature', do_shortcode('[ruvelo_feedback_submit]'));
    }
}
