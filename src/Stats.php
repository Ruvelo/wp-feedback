<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

use WP_Post;
use WP_Query;

/**
 * Simple stats for the admin screen and WP-CLI.
 */
final class Stats
{
    /**
     * @return array{open: int, pending: int, votes_30d: int, shipped_90d: int, by_status: array<string, int>, votes_per_week: array<string, int>, top: list<WP_Post>}
     */
    public static function summary(): array
    {
        $byStatus = Requests::countsByStatus();
        $open = 0;
        foreach (Settings::openStatuses() as $status) {
            $open += $byStatus[$status] ?? 0;
        }
        $counts = wp_count_posts(PostTypes::REQUEST);

        $shipped = new WP_Query([
            'post_type' => PostTypes::REQUEST,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => '_ruvelo_status', 'value' => 'shipped'],
                ['key' => '_ruvelo_status_changed', 'value' => time() - 90 * DAY_IN_SECONDS, 'compare' => '>=', 'type' => 'NUMERIC'],
            ],
        ]);

        $top = Requests::board(['status' => 'open', 'per_page' => 10, 'sort' => 'top']);

        return [
            'open' => $open,
            'pending' => (int) ($counts->pending ?? 0),
            'votes_30d' => Votes::countSince(time() - 30 * DAY_IN_SECONDS),
            'shipped_90d' => (int) $shipped->found_posts,
            'by_status' => $byStatus,
            'votes_per_week' => Votes::perWeek(12),
            'top' => $top['posts'],
        ];
    }
}
