<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

/**
 * The votes table. One row per voter per request, enforced by a unique index.
 * Guests vote with an email address that has to be confirmed before it counts.
 */
final class Votes
{
    public static function userKey(int $userId): string
    {
        return 'u:'.$userId;
    }

    public static function emailKey(string $email): string
    {
        return 'e:'.hash('sha256', strtolower(trim($email)));
    }

    /**
     * Adds a vote. Returns true when a row was added, false if this voter had
     * already voted (the unique index decides, so races can't double count).
     */
    public static function add(int $requestId, string $voter, int $userId = 0, string $email = '', bool $confirmed = true): bool
    {
        global $wpdb;

        $table = Install::votesTable();
        $suppress = $wpdb->suppress_errors(true);
        // One statement that only inserts when the voter hasn't voted: no
        // duplicate-key error in the normal case, and the unique index still
        // has the last word if two requests race.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; caches are cleared by recount().
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (request_id, voter, user_id, email, confirmed, notify, created_at)
             SELECT %d, %s, %d, %s, %d, 1, %s FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM {$table} WHERE request_id = %d AND voter = %s)",
            $requestId,
            $voter,
            $userId,
            $email,
            $confirmed ? 1 : 0,
            current_time('mysql', true),
            $requestId,
            $voter
        ));
        // phpcs:enable
        $wpdb->suppress_errors($suppress);

        return $inserted === 1;
    }

    public static function remove(int $requestId, string $voter): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        return (int) $wpdb->delete(Install::votesTable(), ['request_id' => $requestId, 'voter' => $voter], ['%d', '%s']) > 0;
    }

    /**
     * @return array{id: int, confirmed: bool, email: string, user_id: int}|null
     */
    public static function find(int $requestId, string $voter): ?array
    {
        global $wpdb;

        $table = Install::votesTable();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name from $wpdb->prefix.
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, confirmed, email, user_id FROM {$table} WHERE request_id = %d AND voter = %s", $requestId, $voter), ARRAY_A);

        return is_array($row) ? ['id' => (int) $row['id'], 'confirmed' => (bool) $row['confirmed'], 'email' => (string) $row['email'], 'user_id' => (int) $row['user_id']] : null;
    }

    public static function has(int $requestId, string $voter): bool
    {
        $vote = self::find($requestId, $voter);

        return $vote !== null && $vote['confirmed'];
    }

    public static function confirm(int $requestId, string $voter): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        return (int) $wpdb->update(Install::votesTable(), ['confirmed' => 1], ['request_id' => $requestId, 'voter' => $voter], ['%d'], ['%d', '%s']) > 0;
    }

    /**
     * Counts confirmed votes and stores the total on the request, where the
     * board sorts by it.
     */
    public static function recount(int $requestId): int
    {
        global $wpdb;

        $table = Install::votesTable();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; the result is stored in post meta.
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE request_id = %d AND confirmed = 1", $requestId));
        update_post_meta($requestId, '_ruvelo_votes', $count);

        return $count;
    }

    /**
     * Which of these requests the viewer has voted for, in one query.
     *
     * @param list<int> $requestIds
     * @return array<int, true>
     */
    public static function votedAmong(array $requestIds, string $voter): array
    {
        global $wpdb;

        $requestIds = array_values(array_filter(array_map('intval', $requestIds)));
        if ($requestIds === [] || $voter === '') {
            return [];
        }
        $table = Install::votesTable();
        $placeholders = implode(',', array_fill(0, count($requestIds), '%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Custom table; placeholders built above.
        $ids = $wpdb->get_col($wpdb->prepare("SELECT request_id FROM {$table} WHERE voter = %s AND confirmed = 1 AND request_id IN ({$placeholders})", $voter, ...$requestIds));

        $voted = [];
        foreach ($ids as $id) {
            $voted[(int) $id] = true;
        }

        return $voted;
    }

    /**
     * The most recent voters, for the avatars on a request.
     *
     * @return list<array{user_id: int, email: string}>
     */
    public static function recent(int $requestId, int $limit = 12): array
    {
        global $wpdb;

        $table = Install::votesTable();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT user_id, email FROM {$table} WHERE request_id = %d AND confirmed = 1 ORDER BY id DESC LIMIT %d", $requestId, $limit), ARRAY_A);

        return array_values(array_map(static fn (array $row): array => ['user_id' => (int) $row['user_id'], 'email' => (string) $row['email']], is_array($rows) ? $rows : []));
    }

    /**
     * Moves every vote on $from to $into. Someone who voted for both keeps
     * one vote: the NOT EXISTS guard and the unique index both see to that.
     *
     * @return int How many votes moved.
     */
    public static function move(int $from, int $into): int
    {
        global $wpdb;

        $table = Install::votesTable();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; counts are recounted afterwards.
        $moved = (int) $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (request_id, voter, user_id, email, confirmed, notify, created_at)
             SELECT %d, d.voter, d.user_id, d.email, d.confirmed, d.notify, d.created_at FROM {$table} d
             WHERE d.request_id = %d AND NOT EXISTS (SELECT 1 FROM {$table} x WHERE x.request_id = %d AND x.voter = d.voter)",
            $into,
            $from,
            $into
        ));
        $wpdb->delete($table, ['request_id' => $from], ['%d']);
        // phpcs:enable

        return $moved;
    }

    public static function deleteFor(int $requestId): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        $wpdb->delete(Install::votesTable(), ['request_id' => $requestId], ['%d']);
    }

    /**
     * Stops (or restarts) emails about one request for one voter.
     */
    public static function setNotify(int $requestId, string $voter, bool $notify): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        $wpdb->update(Install::votesTable(), ['notify' => $notify ? 1 : 0], ['request_id' => $requestId, 'voter' => $voter], ['%d'], ['%d', '%s']);
    }

    /**
     * Votes per week for the last $weeks weeks, oldest first.
     *
     * @return array<string, int> Monday's date => votes
     */
    public static function perWeek(int $weeks = 12): array
    {
        global $wpdb;

        $start = strtotime('monday this week', time()) - ($weeks - 1) * WEEK_IN_SECONDS;
        $table = Install::votesTable();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, admin stats only.
        $dates = $wpdb->get_col($wpdb->prepare("SELECT created_at FROM {$table} WHERE confirmed = 1 AND created_at >= %s", gmdate('Y-m-d H:i:s', $start)));

        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $buckets[gmdate('Y-m-d', $start + $i * WEEK_IN_SECONDS)] = 0;
        }
        foreach ($dates as $date) {
            $index = (int) floor((strtotime((string) $date.' UTC') - $start) / WEEK_IN_SECONDS);
            $key = gmdate('Y-m-d', $start + max(0, min($weeks - 1, $index)) * WEEK_IN_SECONDS);
            $buckets[$key]++;
        }

        return $buckets;
    }

    public static function countSince(int $timestamp): int
    {
        global $wpdb;

        $table = Install::votesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, admin stats only.
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE confirmed = 1 AND created_at >= %s", gmdate('Y-m-d H:i:s', $timestamp)));
    }
}
