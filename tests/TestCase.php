<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Tests;

use Ruvelo\WP\Feedback\Install;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Settings;
use WP_UnitTestCase;

abstract class TestCase extends WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $wpdb->query('DELETE FROM '.Install::votesTable());
        $wpdb->query('DELETE FROM '.Install::outboxTable());
        // phpcs:enable
        delete_option(Settings::OPTION);
        Settings::flush();
        wp_cache_delete('counts', 'ruvelo_feedback');
        Install::grantCapabilities();
        reset_phpmailer_instance();
    }

    protected function user(string $role = 'subscriber'): int
    {
        return self::factory()->user->create(['role' => $role]);
    }

    protected function manager(): int
    {
        return $this->user('editor');
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function request(string $title = 'Dark mode', int $author = 0, array $data = []): int
    {
        $author = $author > 0 ? $author : $this->user();
        $id = Requests::create(['title' => $title] + $data, $author, null, false);
        $this->assertIsInt($id);

        return $id;
    }

    /**
     * @param array<string, mixed> $settings
     */
    protected function settings(array $settings): void
    {
        Settings::update($settings);
    }
}
