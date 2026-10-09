<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Cli;

use Ruvelo\WP\Feedback\Changelog;
use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Requests;
use WP_CLI;
use WP_CLI\Utils;
use WP_Error;
use WP_Post;

/**
 * The changelog.
 */
final class ChangelogCommand
{
    /**
     * Lists changelog entries.
     *
     * ## OPTIONS
     *
     * [--limit=<n>]
     * : How many.
     * ---
     * default: 20
     * ---
     *
     * [--format=<format>]
     * : table, csv, json or yaml.
     * ---
     * default: table
     * ---
     *
     * @subcommand list
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public function list_(array $args, array $assoc): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- "list" is reserved.
    {
        $result = Changelog::entries(1, (int) ($assoc['limit'] ?? 20));
        $rows = array_map(static fn (WP_Post $post): array => [
            'id' => $post->ID,
            'date' => get_the_date('Y-m-d', $post),
            'title' => get_the_title($post),
            'labels' => implode(', ', array_column(Changelog::labelsOf($post->ID), 'label')),
            'requests' => implode(', ', Changelog::requestIds($post->ID)),
        ], $result['posts']);
        Utils\format_items($assoc['format'] ?? 'table', $rows, ['id', 'date', 'title', 'labels', 'requests']);
    }

    /**
     * Writes a changelog entry.
     *
     * ## OPTIONS
     *
     * <title>
     * : The title.
     *
     * [--content=<html>]
     * : The body (blocks or HTML).
     *
     * [--labels=<labels>]
     * : Comma-separated label slugs: new, improved, fixed.
     *
     * [--requests=<ids>]
     * : Comma-separated request ids this ships.
     *
     * [--draft]
     * : Save as a draft instead of publishing.
     *
     * [--porcelain]
     * : Print only the new id.
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public function create(array $args, array $assoc): void
    {
        $id = Changelog::create(
            $args[0],
            $assoc['content'] ?? '',
            self::list($assoc['labels'] ?? ''),
            array_map('intval', self::list($assoc['requests'] ?? '')),
            ! isset($assoc['draft']),
            get_current_user_id()
        );
        if ($id instanceof WP_Error) {
            Command::fail($id);
        }
        if (isset($assoc['porcelain'])) {
            WP_CLI::line((string) $id);

            return;
        }
        WP_CLI::success(sprintf('Entry %d %s.', $id, isset($assoc['draft']) ? 'saved as a draft' : 'published'));
    }

    /**
     * Publishes a draft entry. Its linked requests ship and their voters are emailed.
     *
     * ## OPTIONS
     *
     * <id>
     * : The entry id.
     *
     * [--requests=<ids>]
     * : Comma-separated request ids this ships, replacing the current list.
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public function publish(array $args, array $assoc): void
    {
        $id = (int) $args[0];
        if (get_post_type($id) !== PostTypes::ENTRY) {
            WP_CLI::error('No such changelog entry.');
        }
        if (isset($assoc['requests'])) {
            update_post_meta($id, '_ruvelo_requests', array_map('intval', self::list($assoc['requests'])));
        }
        Changelog::publish($id);
        WP_CLI::success(sprintf('Published: %s', (string) get_permalink($id)));
    }

    /**
     * @return list<string>
     */
    private static function list(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));
    }
}
