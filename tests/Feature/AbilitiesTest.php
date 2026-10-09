<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Tests\Feature;

use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Tests\TestCase;
use WP_Error;

final class AbilitiesTest extends TestCase
{
    public function test_the_abilities_are_registered(): void
    {
        foreach (['list-requests', 'search-requests', 'get-request', 'get-roadmap', 'get-changelog', 'submit-request', 'change-status'] as $name) {
            $this->assertNotNull(wp_get_ability('ruvelo-feedback/'.$name), $name);
        }
        $this->assertTrue(wp_get_ability('ruvelo-feedback/list-requests')->get_meta_item('show_in_rest'));
    }

    public function test_reading_the_board(): void
    {
        $this->request('Dark mode');
        $this->request('QuickBooks Online integration');

        $list = wp_get_ability('ruvelo-feedback/list-requests')->execute(['limit' => 5]);
        $this->assertCount(2, $list);

        $found = wp_get_ability('ruvelo-feedback/search-requests')->execute(['query' => 'quickbooks sync']);
        $this->assertSame('QuickBooks Online integration', $found[0]['title']);

        $roadmap = wp_get_ability('ruvelo-feedback/get-roadmap')->execute([]);
        $this->assertSame(['planned', 'in_progress', 'shipped'], array_column($roadmap, 'status'));
    }

    public function test_changing_status_needs_the_capability(): void
    {
        $id = $this->request();
        $ability = wp_get_ability('ruvelo-feedback/change-status');

        wp_set_current_user($this->user());
        $this->assertInstanceOf(WP_Error::class, $ability->execute(['id' => $id, 'status' => 'planned']));
        $this->assertSame('under_review', Requests::status($id));

        wp_set_current_user($this->manager());
        $result = $ability->execute(['id' => $id, 'status' => 'planned', 'note' => 'Next quarter.']);
        $this->assertSame('planned', $result['status']);
    }

    public function test_submitting_needs_a_signed_in_user(): void
    {
        $ability = wp_get_ability('ruvelo-feedback/submit-request');

        $this->assertInstanceOf(WP_Error::class, $ability->execute(['title' => 'From an agent']));

        wp_set_current_user($this->user());
        $this->assertSame('From an agent', $ability->execute(['title' => 'From an agent'])['title']);
    }
}
