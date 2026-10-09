<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

use WP_Error;
use WP_Post;

/**
 * Abilities API (WordPress 6.9+): what AI agents and other tools may do with
 * the feedback board. Reads are public; writes check the same permissions as
 * the site's own forms. The MCP adapter picks them up when it is installed.
 */
final class Abilities
{
    public const CATEGORY = 'ruvelo-feedback';

    public static function register(): void
    {
        add_action('wp_abilities_api_categories_init', [self::class, 'category']);
        add_action('wp_abilities_api_init', [self::class, 'abilities']);
    }

    public static function category(): void
    {
        wp_register_ability_category(self::CATEGORY, [
            'label' => __('Feedback', 'ruvelo-feedback'),
            'description' => __('Feature requests, the roadmap and the changelog.', 'ruvelo-feedback'),
        ]);
    }

    public static function abilities(): void
    {
        $public = ['public' => true, 'show_in_rest' => true, 'mcp' => ['public' => true]];
        $read = ['annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true]] + $public;
        $requestSchema = self::requestSchema();

        wp_register_ability('ruvelo-feedback/list-requests', [
            'label' => __('List feature requests', 'ruvelo-feedback'),
            'description' => __('Lists public feature requests from the feedback board, most voted first. Filter by status (under_review, planned, in_progress, shipped, closed, open for the first three, or all), category slug or tag slug.', 'ruvelo-feedback'),
            'category' => self::CATEGORY,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'default' => 'open'],
                    'category' => ['type' => 'string'],
                    'tag' => ['type' => 'string'],
                    'sort' => ['type' => 'string', 'enum' => Requests::SORTS, 'default' => 'top'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                ],
                'additionalProperties' => false,
            ],
            'output_schema' => ['type' => 'array', 'items' => $requestSchema],
            'execute_callback' => [self::class, 'listRequests'],
            'permission_callback' => '__return_true',
            'meta' => $read,
        ]);

        wp_register_ability('ruvelo-feedback/search-requests', [
            'label' => __('Search feature requests', 'ruvelo-feedback'),
            'description' => __('Finds existing requests that match a phrase. Use it before submitting a request, to vote for an existing one instead of creating a duplicate.', 'ruvelo-feedback'),
            'category' => self::CATEGORY,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 200],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 5],
                ],
                'required' => ['query'],
                'additionalProperties' => false,
            ],
            'output_schema' => ['type' => 'array', 'items' => $requestSchema],
            'execute_callback' => [self::class, 'searchRequests'],
            'permission_callback' => '__return_true',
            'meta' => $read,
        ]);

        wp_register_ability('ruvelo-feedback/get-request', [
            'label' => __('Get a feature request', 'ruvelo-feedback'),
            'description' => __('Returns one public request with its votes, status and status history.', 'ruvelo-feedback'),
            'category' => self::CATEGORY,
            'input_schema' => [
                'type' => 'object',
                'properties' => ['id' => ['type' => 'integer', 'minimum' => 1]],
                'required' => ['id'],
                'additionalProperties' => false,
            ],
            'output_schema' => $requestSchema,
            'execute_callback' => [self::class, 'getRequest'],
            'permission_callback' => '__return_true',
            'meta' => $read,
        ]);

        wp_register_ability('ruvelo-feedback/get-roadmap', [
            'label' => __('Get the roadmap', 'ruvelo-feedback'),
            'description' => __('Returns the public roadmap: what is planned, in progress and recently shipped, with vote counts.', 'ruvelo-feedback'),
            'category' => self::CATEGORY,
            'input_schema' => [
                'type' => 'object',
                'properties' => ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20]],
                'additionalProperties' => false,
            ],
            'output_schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'requests' => ['type' => 'array', 'items' => $requestSchema],
                    ],
                ],
            ],
            'execute_callback' => [self::class, 'getRoadmap'],
            'permission_callback' => '__return_true',
            'meta' => $read,
        ]);

        wp_register_ability('ruvelo-feedback/get-changelog', [
            'label' => __('Get the changelog', 'ruvelo-feedback'),
            'description' => __('Returns recent changelog entries (newest first) with their labels (new, improved, fixed) and the requests they shipped.', 'ruvelo-feedback'),
            'category' => self::CATEGORY,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10],
                    'label' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
            'output_schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'url' => ['type' => 'string'],
                        'date' => ['type' => 'string'],
                        'excerpt' => ['type' => 'string'],
                        'labels' => ['type' => 'array'],
                        'requests' => ['type' => 'array'],
                    ],
                ],
            ],
            'execute_callback' => [self::class, 'getChangelog'],
            'permission_callback' => '__return_true',
            'meta' => $read,
        ]);

        wp_register_ability('ruvelo-feedback/submit-request', [
            'label' => __('Submit a feature request', 'ruvelo-feedback'),
            'description' => __('Posts a new feature request as the current user. Search first: if a similar request exists, vote for it instead.', 'ruvelo-feedback'),
            'category' => self::CATEGORY,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 160],
                    'body' => ['type' => 'string', 'maxLength' => 10000],
                ],
                'required' => ['title'],
                'additionalProperties' => false,
            ],
            'output_schema' => $requestSchema,
            'execute_callback' => [self::class, 'submitRequest'],
            'permission_callback' => static fn (): bool => is_user_logged_in() && Viewer::canPost(),
            'meta' => ['annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => false]] + $public,
        ]);

        wp_register_ability('ruvelo-feedback/change-status', [
            'label' => __('Change a request’s status', 'ruvelo-feedback'),
            'description' => __('Moves a request to another status (under_review, planned, in_progress, shipped, closed) with an optional public note. Its author and voters are emailed. Needs the ruvelo_manage_feedback capability.', 'ruvelo-feedback'),
            'category' => self::CATEGORY,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'minimum' => 1],
                    'status' => ['type' => 'string', 'enum' => array_keys(Settings::statuses())],
                    'note' => ['type' => 'string', 'maxLength' => 5000],
                    'notify' => ['type' => 'boolean', 'default' => true],
                ],
                'required' => ['id', 'status'],
                'additionalProperties' => false,
            ],
            'output_schema' => $requestSchema,
            'execute_callback' => [self::class, 'changeStatus'],
            'permission_callback' => static fn (): bool => current_user_can(Settings::CAP),
            'meta' => ['annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => true]] + $public,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function requestSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'excerpt' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'status_label' => ['type' => 'string'],
                'votes' => ['type' => 'integer'],
                'comments' => ['type' => 'integer'],
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $input
     * @return list<array<string, mixed>>
     */
    public static function listRequests(?array $input = null): array
    {
        $input = $input ?? [];
        $result = Requests::board([
            'status' => is_string($input['status'] ?? null) ? $input['status'] : 'open',
            'category' => is_string($input['category'] ?? null) ? $input['category'] : '',
            'tag' => is_string($input['tag'] ?? null) ? $input['tag'] : '',
            'sort' => is_string($input['sort'] ?? null) ? $input['sort'] : 'top',
            'per_page' => (int) ($input['limit'] ?? 20),
        ]);

        return array_map(static fn (WP_Post $post): array => Requests::toArray($post), $result['posts']);
    }

    /**
     * @param array<string, mixed>|null $input
     * @return list<array<string, mixed>>
     */
    public static function searchRequests(?array $input = null): array
    {
        $query = is_string($input['query'] ?? null) ? $input['query'] : '';

        return array_map(static fn (WP_Post $post): array => Requests::toArray($post), Requests::similar($query, (int) ($input['limit'] ?? 5)));
    }

    /**
     * @param array<string, mixed>|null $input
     * @return array<string, mixed>|WP_Error
     */
    public static function getRequest(?array $input = null): array|WP_Error
    {
        $post = Requests::get((int) ($input['id'] ?? 0));
        if ($post === null || $post->post_status !== 'publish') {
            return new WP_Error('ruvelo_feedback_not_found', __('That request doesn’t exist, or isn’t public.', 'ruvelo-feedback'));
        }
        $data = Requests::toArray($post);
        $data['history'] = array_map(static fn (array $change): array => ['status' => $change['to'], 'note' => $change['note'], 'at' => gmdate('c', $change['at'])], Requests::history($post->ID));

        return $data;
    }

    /**
     * @param array<string, mixed>|null $input
     * @return list<array<string, mixed>>
     */
    public static function getRoadmap(?array $input = null): array
    {
        $columns = [];
        foreach (Requests::roadmap((int) ($input['limit'] ?? 20)) as $status => $posts) {
            $columns[] = ['status' => $status, 'label' => Settings::statusLabel($status), 'requests' => array_map(static fn (WP_Post $post): array => Requests::toArray($post), $posts)];
        }

        return $columns;
    }

    /**
     * @param array<string, mixed>|null $input
     * @return list<array<string, mixed>>
     */
    public static function getChangelog(?array $input = null): array
    {
        $result = Changelog::entries(1, (int) ($input['limit'] ?? 10), is_string($input['label'] ?? null) ? $input['label'] : '');

        return array_map(static function (WP_Post $post): array {
            $data = Changelog::toArray($post);
            unset($data['content']);

            return $data;
        }, $result['posts']);
    }

    /**
     * @param array<string, mixed>|null $input
     * @return array<string, mixed>|WP_Error
     */
    public static function submitRequest(?array $input = null): array|WP_Error
    {
        $id = Requests::create(['title' => $input['title'] ?? '', 'body' => $input['body'] ?? ''], get_current_user_id());
        if ($id instanceof WP_Error) {
            return $id;
        }
        $post = Requests::get($id);

        return $post !== null ? Requests::toArray($post) : ['id' => $id];
    }

    /**
     * @param array<string, mixed>|null $input
     * @return array<string, mixed>|WP_Error
     */
    public static function changeStatus(?array $input = null): array|WP_Error
    {
        $id = (int) ($input['id'] ?? 0);
        $result = Requests::changeStatus($id, (string) ($input['status'] ?? ''), (string) ($input['note'] ?? ''), get_current_user_id(), (bool) ($input['notify'] ?? true));
        if ($result instanceof WP_Error) {
            return $result;
        }
        $post = Requests::get($id);

        return $post !== null ? Requests::toArray($post) : [];
    }
}
