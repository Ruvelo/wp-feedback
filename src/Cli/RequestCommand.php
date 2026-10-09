<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Cli;

use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Settings;
use WP_CLI;
use WP_CLI\Utils;
use WP_Error;
use WP_Post;

/**
 * Feature requests.
 */
final class RequestCommand
{
    /**
     * Lists requests.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : open (default), all, or a status key such as planned.
     *
     * [--category=<slug>]
     * : A category slug.
     *
     * [--search=<text>]
     * : Words in the title or description.
     *
     * [--sort=<sort>]
     * : top, new or trending.
     * ---
     * default: top
     * ---
     *
     * [--limit=<n>]
     * : How many.
     * ---
     * default: 50
     * ---
     *
     * [--format=<format>]
     * : table, csv, json, yaml, ids or count.
     * ---
     * default: table
     * ---
     *
     * ## EXAMPLES
     *
     *     wp ruvelo-feedback request list --status=all --format=csv
     *
     * @subcommand list
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public function list_(array $args, array $assoc): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- "list" is reserved.
    {
        $result = Requests::board([
            'status' => $assoc['status'] ?? 'open',
            'category' => $assoc['category'] ?? '',
            'search' => $assoc['search'] ?? '',
            'sort' => $assoc['sort'] ?? 'top',
            'per_page' => (int) ($assoc['limit'] ?? 50),
        ]);
        $format = $assoc['format'] ?? 'table';
        if ($format === 'ids') {
            echo implode(' ', array_map(static fn (WP_Post $post): int => $post->ID, $result['posts'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.

            return;
        }
        $rows = array_map(static function (WP_Post $post): array {
            $data = Requests::toArray($post);

            return ['id' => $data['id'], 'title' => $data['title'], 'status' => $data['status'], 'votes' => $data['votes'], 'comments' => $data['comments'], 'pinned' => $data['pinned'] ? 'yes' : '', 'author' => $data['author']['name'], 'url' => $data['url']];
        }, $result['posts']);
        Utils\format_items($format, $rows, ['id', 'title', 'status', 'votes', 'comments', 'pinned', 'author']);
    }

    /**
     * Shows one request.
     *
     * ## OPTIONS
     *
     * <id>
     * : The request id.
     *
     * [--format=<format>]
     * : json or yaml.
     * ---
     * default: yaml
     * ---
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public function get(array $args, array $assoc): void
    {
        $post = Requests::get((int) $args[0]);
        if ($post === null) {
            Command::stop('No such request.');
        }
        $data = Requests::toArray($post);
        $data['history'] = Requests::history($post->ID);
        WP_CLI::print_value($data, ['format' => $assoc['format'] ?? 'yaml']);
    }

    /**
     * Posts a request.
     *
     * ## OPTIONS
     *
     * <title>
     * : The title.
     *
     * [--body=<text>]
     * : The description, as plain text.
     *
     * [--user=<user>]
     * : Who asks: id, login or email. They vote for it too.
     *
     * [--category=<category>]
     * : A category slug or name.
     *
     * [--status=<status>]
     * : The starting status.
     * ---
     * default: under_review
     * ---
     *
     * [--porcelain]
     * : Print only the new id.
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public function create(array $args, array $assoc): void
    {
        $id = Requests::create([
            'title' => $args[0],
            'body' => $assoc['body'] ?? '',
            'category' => $assoc['category'] ?? '',
            'status' => $assoc['status'] ?? 'under_review',
        ], Command::user($assoc), null, false);
        if ($id instanceof WP_Error) {
            Command::fail($id);
        }
        if (isset($assoc['porcelain'])) {
            WP_CLI::line((string) $id);

            return;
        }
        WP_CLI::success(sprintf('Posted request %d: %s', $id, (string) get_permalink($id)));
    }

    /**
     * Changes a request's status. Its author and voters are emailed (in the background).
     *
     * ## OPTIONS
     *
     * <id>
     * : The request id.
     *
     * <status>
     * : under_review, planned, in_progress, shipped, closed, or your own.
     *
     * [--note=<text>]
     * : A public note, shown on the request and in the email.
     *
     * [--no-notify]
     * : Don't email anyone.
     *
     * [--by=<user>]
     * : Who made the change.
     *
     * ## EXAMPLES
     *
     *     wp ruvelo-feedback request status 12 planned --note="Starting in March."
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function status(array $args, array $assoc): void
    {
        $notify = ! isset($assoc['notify']) || $assoc['notify'] !== false;
        $result = Requests::changeStatus((int) $args[0], $args[1], (string) ($assoc['note'] ?? ''), Command::user(array_map('strval', $assoc), 'by'), $notify);
        if ($result instanceof WP_Error) {
            Command::fail($result);
        }
        WP_CLI::success($result ? sprintf('Request %d is now %s.', (int) $args[0], Settings::statusLabel($args[1])) : 'Nothing changed.');
    }

    /**
     * Merges a duplicate into another request. Votes and comments move over.
     *
     * ## OPTIONS
     *
     * <id>
     * : The duplicate.
     *
     * --into=<id>
     * : The request to keep.
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public function merge(array $args, array $assoc): void
    {
        $moved = Requests::merge((int) $args[0], (int) $assoc['into']);
        if ($moved instanceof WP_Error) {
            Command::fail($moved);
        }
        WP_CLI::success(sprintf('Merged %d into %d; %d votes moved.', (int) $args[0], (int) $assoc['into'], $moved));
    }

    /**
     * Pins a request to the top of the board, or unpins it.
     *
     * ## OPTIONS
     *
     * <id>
     * : The request id.
     *
     * [--off]
     * : Unpin it.
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public function pin(array $args, array $assoc): void
    {
        if (Requests::get((int) $args[0]) === null) {
            WP_CLI::error('No such request.');
        }
        Requests::pin((int) $args[0], ! isset($assoc['off']));
        WP_CLI::success(isset($assoc['off']) ? 'Unpinned.' : 'Pinned.');
    }

    /**
     * Approves a request waiting for moderation.
     *
     * ## OPTIONS
     *
     * <id>...
     * : One or more request ids.
     *
     * @param list<string> $args
     */
    public function approve(array $args): void
    {
        foreach ($args as $id) {
            Requests::approve((int) $id) ? WP_CLI::log("Approved {$id}.") : WP_CLI::warning("{$id} wasn't waiting for approval.");
        }
    }

    /**
     * Adds a vote for a user.
     *
     * ## OPTIONS
     *
     * <id>
     * : The request id.
     *
     * --user=<user>
     * : id, login or email.
     *
     * @param list<string> $args
     * @param array<string, string> $assoc
     */
    public function vote(array $args, array $assoc): void
    {
        $result = Requests::vote((int) $args[0], Command::user($assoc));
        if ($result instanceof WP_Error) {
            Command::fail($result);
        }
        WP_CLI::success($result ? sprintf('Voted. It has %d votes.', Requests::votes((int) $args[0])) : 'Already voted.');
    }
}
