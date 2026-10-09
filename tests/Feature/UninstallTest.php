<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Tests\Feature;

use Ruvelo\WP\Feedback\Install;
use Ruvelo\WP\Feedback\Notifications;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
final class UninstallTest extends TestCase
{
    private function uninstall(): void
    {
        if (! defined('WP_UNINSTALL_PLUGIN')) {
            define('WP_UNINSTALL_PLUGIN', 'ruvelo-feedback/ruvelo-feedback.php');
        }
        // Let DROP TABLE reach the real tables.
        remove_filter('query', [$this, '_drop_temporary_tables']);
        remove_filter('query', [$this, '_create_temporary_tables']);
        include dirname(__DIR__, 2).'/uninstall.php';
    }

    private function tableExists(string $table): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public function test_by_default_content_stays_and_cron_is_cleared(): void
    {
        $id = $this->request();
        Requests::changeStatus($id, 'planned', '', 0);
        $this->assertNotFalse(wp_next_scheduled(Notifications::CRON_HOOK));

        $this->uninstall();

        $this->assertFalse(wp_next_scheduled(Notifications::CRON_HOOK));
        $this->assertNotNull(get_post($id));
        $this->assertTrue($this->tableExists(Install::votesTable()));
    }

    public function test_opting_in_removes_everything(): void
    {
        $id = $this->request();
        $this->settings(['delete_data' => true]);

        $this->uninstall();

        $this->assertNull(get_post($id));
        $this->assertFalse($this->tableExists(Install::votesTable()));
        $this->assertFalse($this->tableExists(Install::outboxTable()));
        $this->assertFalse(get_option(Settings::OPTION));
        $this->assertFalse(get_role('editor')->has_cap(Settings::CAP));

        Install::install(); // Put the tables back for the other tests.
    }
}
