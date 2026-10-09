<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Cli;

use Ruvelo\WP\Feedback\Changelog;
use Ruvelo\WP\Feedback\Demo;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Setup;
use Ruvelo\WP\Feedback\Stats;
use WP_CLI;
use WP_CLI\Utils;
use WP_Error;

/**
 * Manage the feedback board, roadmap and changelog.
 *
 * ## EXAMPLES
 *
 *     wp ruvelo-feedback request list --status=planned
 *     wp ruvelo-feedback request status 42 shipped --note="Live for everyone today."
 *     wp ruvelo-feedback changelog publish 51
 */
final class Command
{
    public static function register(): void
    {
        WP_CLI::add_command('ruvelo-feedback request', RequestCommand::class);
        WP_CLI::add_command('ruvelo-feedback changelog', ChangelogCommand::class);
        WP_CLI::add_command('ruvelo-feedback notifications', NotificationsCommand::class);
        WP_CLI::add_command('ruvelo-feedback setup', [self::class, 'setup']);
        WP_CLI::add_command('ruvelo-feedback stats', [self::class, 'stats']);
        WP_CLI::add_command('ruvelo-feedback demo', [self::class, 'demo']);
    }

    /**
     * Creates the Feedback, Roadmap and Suggest a feature pages.
     *
     * ## EXAMPLES
     *
     *     wp ruvelo-feedback setup
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public static function setup(array $args = [], array $assoc = []): void
    {
        $ids = Setup::createPages();
        if ($ids instanceof WP_Error) {
            self::fail($ids);
        }
        foreach ($ids as $key => $id) {
            WP_CLI::log(sprintf('%s: %s', $key, (string) get_permalink($id)));
        }
        WP_CLI::success('The feedback pages are ready.');
    }

    /**
     * Shows open, pending and shipped counts, and recent votes.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : table, json or yaml.
     * ---
     * default: table
     * ---
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public static function stats(array $args = [], array $assoc = []): void
    {
        $stats = Stats::summary();
        $rows = [
            ['metric' => 'open', 'value' => $stats['open']],
            ['metric' => 'pending', 'value' => $stats['pending']],
            ['metric' => 'votes_30d', 'value' => $stats['votes_30d']],
            ['metric' => 'shipped_90d', 'value' => $stats['shipped_90d']],
        ];
        foreach ($stats['by_status'] as $status => $count) {
            $rows[] = ['metric' => 'status:'.$status, 'value' => $count];
        }
        Utils\format_items($assoc['format'] ?? 'table', $rows, ['metric', 'value']);
    }

    /**
     * Fills the site with Halyard's demo feedback portal: users, requests,
     * votes, comments and changelog entries. For trying the plugin out.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Don't ask for confirmation.
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public static function demo(array $args = [], array $assoc = []): void
    {
        WP_CLI::confirm('This adds demo users and content to this site. Continue?', $assoc);
        $summary = Demo::seed();
        WP_CLI::success(sprintf('Demo ready: %d requests, %d votes, %d comments, %d changelog entries.', $summary['requests'], $summary['votes'], $summary['comments'], $summary['entries']));
    }

    /**
     * @param array<string, string> $assoc
     */
    public static function user(array $assoc, string $key = 'user'): int
    {
        if (! isset($assoc[$key])) {
            return 0;
        }
        $value = $assoc[$key];
        $user = is_numeric($value) ? get_user_by('id', (int) $value) : (get_user_by('login', $value) ?: get_user_by('email', $value));
        if ($user === false) {
            self::stop(sprintf('No user "%s".', $value));
        }

        return $user->ID;
    }

    public static function fail(WP_Error $error): never
    {
        self::stop($error->get_error_message());
    }

    public static function stop(string $message): never
    {
        WP_CLI::error($message);
        exit(1);
    }
}
