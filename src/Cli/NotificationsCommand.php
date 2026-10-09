<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Cli;

use Ruvelo\WP\Feedback\Notifications;
use WP_CLI;

/**
 * Status-change emails.
 */
final class NotificationsCommand
{
    /**
     * Shows how many status changes are waiting to be emailed.
     *
     * @param list<string> $args
     */
    public function status(array $args = []): void
    {
        $next = wp_next_scheduled(Notifications::CRON_HOOK);
        WP_CLI::log(sprintf('Waiting: %d. Next run: %s.', Notifications::pending(), $next !== false ? gmdate('Y-m-d H:i:s', $next).' UTC' : 'not scheduled'));
    }

    /**
     * Sends waiting emails now, in batches, until none are left.
     *
     * ## OPTIONS
     *
     * [--batch=<n>]
     * : Emails per batch.
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public function run(array $args, array $assoc): void
    {
        $total = 0;
        do {
            $handled = Notifications::run(isset($assoc['batch']) ? (int) $assoc['batch'] : null);
            $total += $handled;
        } while ($handled > 0 && Notifications::pending() > 0);
        wp_clear_scheduled_hook(Notifications::CRON_HOOK);
        WP_CLI::success(sprintf('Handled %d recipients.', $total));
    }
}
