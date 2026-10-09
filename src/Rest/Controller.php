<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback\Rest;

use Ruvelo\WP\Feedback\Changelog;
use Ruvelo\WP\Feedback\PostTypes;
use Ruvelo\WP\Feedback\Requests;
use Ruvelo\WP\Feedback\Settings;
use Ruvelo\WP\Feedback\Support\RateLimit;
use Ruvelo\WP\Feedback\Viewer;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The ruvelo-feedback/v1 REST API. Reads are public and only show what the
 * caller may see; writes use cookie authentication (with the wp_rest nonce)
 * or application passwords, and check capabilities.
 */
final class Controller
{
    public const NAMESPACE = 'ruvelo-feedback/v1';

    public static function register(): void
    {
        $id = ['id' => ['type' => 'integer', 'required' => true, 'minimum' => 1]];

        register_rest_route(self::NAMESPACE, '/requests', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'list'],
                'permission_callback' => '__return_true',
                'args' => [
                    'status' => ['type' => 'string', 'default' => 'open'],
                    'category' => ['type' => 'string', 'default' => ''],
                    'tag' => ['type' => 'string', 'default' => ''],
                    'search' => ['type' => 'string', 'default' => ''],
                    'sort' => ['type' => 'string', 'enum' => Requests::SORTS, 'default' => 'top'],
                    'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                ],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create'],
                'permission_callback' => [self::class, 'canPost'],
                'args' => [
                    'title' => ['type' => 'string', 'required' => true, 'minLength' => 3, 'maxLength' => 160],
                    'body' => ['type' => 'string', 'default' => '', 'maxLength' => 10000],
                    'category' => ['type' => ['integer', 'string'], 'default' => ''],
                    'name' => ['type' => 'string', 'default' => ''],
                    'email' => ['type' => 'string', 'default' => ''],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/requests/similar', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'similar'],
            'permission_callback' => '__return_true',
            'args' => ['q' => ['type' => 'string', 'required' => true, 'maxLength' => 200]],
        ]);

        register_rest_route(self::NAMESPACE, '/requests/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'show'],
            'permission_callback' => '__return_true',
            'args' => $id,
        ]);

        register_rest_route(self::NAMESPACE, '/requests/(?P<id>\d+)/vote', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'vote'],
                'permission_callback' => [self::class, 'canVote'],
                'args' => $id,
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'unvote'],
                'permission_callback' => [self::class, 'canVote'],
                'args' => $id,
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/requests/(?P<id>\d+)/status', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'status'],
            'permission_callback' => [self::class, 'canManage'],
            'args' => $id + [
                'status' => ['type' => 'string', 'required' => true],
                'note' => ['type' => 'string', 'default' => ''],
                'notify' => ['type' => 'boolean', 'default' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/requests/(?P<id>\d+)/merge', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'merge'],
            'permission_callback' => [self::class, 'canManage'],
            'args' => $id + ['into' => ['type' => 'integer', 'required' => true, 'minimum' => 1]],
        ]);

        register_rest_route(self::NAMESPACE, '/requests/(?P<id>\d+)/pin', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'pin'],
            'permission_callback' => [self::class, 'canManage'],
            'args' => $id + ['pinned' => ['type' => 'boolean', 'default' => true]],
        ]);

        register_rest_route(self::NAMESPACE, '/requests/(?P<id>\d+)/approve', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'approve'],
            'permission_callback' => [self::class, 'canManage'],
            'args' => $id,
        ]);

        register_rest_route(self::NAMESPACE, '/roadmap', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'roadmap'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/statuses', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'statuses'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/changelog', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'changelog'],
            'permission_callback' => '__return_true',
            'args' => [
                'label' => ['type' => 'string', 'default' => ''],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/changelog/unread', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'unread'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/changelog/seen', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'seen'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ------------------------------------------------------------------
    // Permissions

    public static function canPost(): bool|WP_Error
    {
        if (! Viewer::canPost()) {
            return new WP_Error('rest_forbidden', __('Sign in to suggest a feature.', 'ruvelo-feedback'), ['status' => rest_authorization_required_code()]);
        }

        return true;
    }

    public static function canVote(): bool|WP_Error
    {
        if (! is_user_logged_in() && ! (Viewer::guestVotes() && Viewer::guestEmail() !== '')) {
            return new WP_Error('rest_forbidden', __('Sign in to vote.', 'ruvelo-feedback'), ['status' => rest_authorization_required_code()]);
        }
        if (! Viewer::canVote()) {
            return new WP_Error('rest_forbidden', __('You can’t vote on this site.', 'ruvelo-feedback'), ['status' => 403]);
        }

        return true;
    }

    public static function canManage(): bool|WP_Error
    {
        if (! Viewer::canManage()) {
            return new WP_Error('rest_forbidden', __('Only the team can do that.', 'ruvelo-feedback'), ['status' => rest_authorization_required_code()]);
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Requests

    public static function list(WP_REST_Request $request): WP_REST_Response
    {
        $result = Requests::board([
            'status' => (string) $request['status'],
            'category' => (string) $request['category'],
            'tag' => (string) $request['tag'],
            'search' => (string) $request['search'],
            'sort' => (string) $request['sort'],
            'page' => (int) $request['page'],
            'per_page' => (int) $request['per_page'],
        ]);
        $voter = Viewer::key();
        $response = new WP_REST_Response(array_map(static fn (WP_Post $post): array => Requests::toArray($post, $voter), $result['posts']));
        $response->header('X-WP-Total', (string) $result['total']);
        $response->header('X-WP-TotalPages', (string) $result['pages']);

        return $response;
    }

    public static function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post = self::visible((int) $request['id']);
        if ($post instanceof WP_Error) {
            return $post;
        }
        $data = Requests::toArray($post, Viewer::key());
        $data['history'] = array_map(static fn (array $change): array => [
            'from' => $change['from'],
            'to' => $change['to'],
            'label' => Settings::statusLabel($change['to']),
            'note' => $change['note'],
            'at' => gmdate('c', $change['at']),
        ], Requests::history($post->ID));
        $data['can'] = ['vote' => Viewer::canVote() && ! Requests::isLocked($post->ID), 'comment' => comments_open($post), 'manage' => Viewer::canManage()];

        return new WP_REST_Response($data);
    }

    public static function similar(WP_REST_Request $request): WP_REST_Response
    {
        $posts = Requests::similar((string) $request['q']);

        return new WP_REST_Response(array_map(static fn (WP_Post $post): array => [
            'id' => $post->ID,
            'title' => get_the_title($post),
            'url' => (string) get_permalink($post),
            'votes' => Requests::votes($post->ID),
            'status' => Requests::status($post->ID),
            'status_label' => Settings::statusLabel(Requests::status($post->ID)),
        ], $posts));
    }

    public static function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (! RateLimit::hit('request', 5)) {
            return new WP_Error('ruvelo_feedback_rate', __('That’s a lot in a short time. Wait a minute and try again.', 'ruvelo-feedback'), ['status' => 429]);
        }
        $userId = get_current_user_id();
        $id = Requests::create(
            ['title' => $request['title'], 'body' => $request['body'], 'category' => $request['category']],
            $userId,
            $userId === 0 ? ['name' => (string) $request['name'], 'email' => (string) $request['email']] : null
        );
        if ($id instanceof WP_Error) {
            return $id;
        }
        $post = Requests::get($id);

        return new WP_REST_Response($post !== null ? Requests::toArray($post, Viewer::key()) : ['id' => $id], 201);
    }

    public static function vote(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::toggle($request, true);
    }

    public static function unvote(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::toggle($request, false);
    }

    private static function toggle(WP_REST_Request $request, bool $add): WP_REST_Response|WP_Error
    {
        if (! RateLimit::hit('vote', 60)) {
            return new WP_Error('ruvelo_feedback_rate', __('That’s a lot in a short time. Wait a minute and try again.', 'ruvelo-feedback'), ['status' => 429]);
        }
        $id = (int) $request['id'];
        $email = is_user_logged_in() ? '' : Viewer::guestEmail();
        $result = $add ? Requests::vote($id, get_current_user_id(), $email) : Requests::unvote($id, get_current_user_id(), $email);
        if ($result instanceof WP_Error) {
            return $result;
        }

        return new WP_REST_Response(['id' => $id, 'voted' => $add, 'votes' => Requests::votes($id)]);
    }

    public static function status(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request['id'];
        $result = Requests::changeStatus($id, (string) $request['status'], (string) $request['note'], get_current_user_id(), (bool) $request['notify']);
        if ($result instanceof WP_Error) {
            return $result;
        }
        $post = Requests::get($id);

        return new WP_REST_Response($post !== null ? Requests::toArray($post) : []);
    }

    public static function merge(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $moved = Requests::merge((int) $request['id'], (int) $request['into'], get_current_user_id());
        if ($moved instanceof WP_Error) {
            return $moved;
        }
        $into = Requests::get((int) $request['into']);

        return new WP_REST_Response(['moved' => $moved, 'into' => $into !== null ? Requests::toArray($into) : null]);
    }

    public static function pin(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post = Requests::get((int) $request['id']);
        if ($post === null) {
            return new WP_Error('ruvelo_feedback_not_found', __('That request doesn’t exist.', 'ruvelo-feedback'), ['status' => 404]);
        }
        Requests::pin($post->ID, (bool) $request['pinned']);

        return new WP_REST_Response(Requests::toArray($post));
    }

    public static function approve(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post = Requests::get((int) $request['id']);
        if ($post === null) {
            return new WP_Error('ruvelo_feedback_not_found', __('That request doesn’t exist.', 'ruvelo-feedback'), ['status' => 404]);
        }
        Requests::approve($post->ID);
        $post = Requests::get($post->ID);

        return new WP_REST_Response($post !== null ? Requests::toArray($post) : []);
    }

    public static function roadmap(): WP_REST_Response
    {
        $voter = Viewer::key();
        $columns = [];
        foreach (Requests::roadmap() as $status => $posts) {
            $columns[] = [
                'status' => $status,
                'label' => Settings::statusLabel($status),
                'requests' => array_map(static fn (WP_Post $post): array => Requests::toArray($post, $voter), $posts),
            ];
        }

        return new WP_REST_Response($columns);
    }

    public static function statuses(): WP_REST_Response
    {
        $out = [];
        foreach (Settings::statuses() as $key => $status) {
            $out[] = ['key' => $key, 'label' => $status['label'], 'color' => $status['color'], 'open' => in_array($key, Settings::openStatuses(), true), 'locked' => in_array($key, Settings::lockedStatuses(), true)];
        }

        return new WP_REST_Response($out);
    }

    // ------------------------------------------------------------------
    // Changelog

    public static function changelog(WP_REST_Request $request): WP_REST_Response
    {
        $result = Changelog::entries((int) $request['page'], (int) $request['per_page'], (string) $request['label']);
        $response = new WP_REST_Response(array_map([Changelog::class, 'toArray'], $result['posts']));
        $response->header('X-WP-Total', (string) $result['total']);
        $response->header('X-WP-TotalPages', (string) $result['pages']);

        return $response;
    }

    public static function unread(): WP_REST_Response
    {
        return new WP_REST_Response(['unread' => Changelog::unreadCount(), 'since' => gmdate('c', Changelog::since())]);
    }

    public static function seen(): WP_REST_Response
    {
        Changelog::markSeen();

        return new WP_REST_Response(['unread' => 0]);
    }

    // ------------------------------------------------------------------

    private static function visible(int $id): WP_Post|WP_Error
    {
        $post = Requests::get($id);
        if ($post === null || ($post->post_status !== 'publish' && ! Viewer::canManage() && ((int) $post->post_author === 0 || (int) $post->post_author !== get_current_user_id()))) {
            return new WP_Error('ruvelo_feedback_not_found', __('That request doesn’t exist, or isn’t public yet.', 'ruvelo-feedback'), ['status' => 404]);
        }
        if ($post->post_type !== PostTypes::REQUEST) {
            return new WP_Error('ruvelo_feedback_not_found', __('That request doesn’t exist.', 'ruvelo-feedback'), ['status' => 404]);
        }

        return $post;
    }
}
