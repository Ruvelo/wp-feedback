<?php

declare(strict_types=1);

namespace Ruvelo\WP\Feedback;

/**
 * Tables, capabilities and the default changelog labels. Runs on activation
 * and whenever the stored schema version is behind the code.
 */
final class Install
{
    public const DB_VERSION = '1';

    public const DB_OPTION = 'ruvelo_feedback_db_version';

    public static function activate(): void
    {
        self::install();
        PostTypes::register();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(Notifications::CRON_HOOK);
        flush_rewrite_rules();
    }

    public static function maybeUpgrade(): void
    {
        if (get_option(self::DB_OPTION) !== self::DB_VERSION) {
            self::install();
        }
    }

    public static function install(): void
    {
        self::createTables();
        self::grantCapabilities();
        update_option(self::DB_OPTION, self::DB_VERSION, false);
    }

    public static function votesTable(): string
    {
        global $wpdb;

        return $wpdb->prefix.'ruvelo_feedback_votes';
    }

    public static function outboxTable(): string
    {
        global $wpdb;

        return $wpdb->prefix.'ruvelo_feedback_outbox';
    }

    public static function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH.'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $votes = self::votesTable();
        $outbox = self::outboxTable();

        // One row per voter per request. `voter` is "u:<user id>" or
        // "e:<sha256 of the email>", and the unique index is what makes
        // "one vote per person" true even under concurrent requests.
        dbDelta("CREATE TABLE {$votes} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  request_id bigint(20) unsigned NOT NULL,
  voter varchar(80) NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  email varchar(190) NOT NULL DEFAULT '',
  confirmed tinyint(1) NOT NULL DEFAULT 1,
  notify tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY request_voter (request_id,voter),
  KEY user_id (user_id),
  KEY request_confirmed (request_id,confirmed),
  KEY created_at (created_at)
) {$charset};");

        // Status changes waiting to be emailed. WP-Cron works through them in
        // batches; `last_vote_id` is the last vote already handled.
        dbDelta("CREATE TABLE {$outbox} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  request_id bigint(20) unsigned NOT NULL,
  event varchar(20) NOT NULL DEFAULT 'status',
  status_from varchar(40) NOT NULL DEFAULT '',
  status_to varchar(40) NOT NULL DEFAULT '',
  note text NOT NULL,
  entry_id bigint(20) unsigned NOT NULL DEFAULT 0,
  actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
  last_vote_id bigint(20) unsigned NOT NULL DEFAULT 0,
  author_done tinyint(1) NOT NULL DEFAULT 0,
  sent_count int(10) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  done_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY done_at (done_at)
) {$charset};");
    }

    /**
     * Administrators and editors manage feedback by default. Filter
     * `ruvelo_feedback_manager_roles` to change who gets the capability.
     */
    public static function grantCapabilities(): void
    {
        $roles = apply_filters('ruvelo_feedback_manager_roles', ['administrator', 'editor']);
        foreach (is_array($roles) ? $roles : [] as $name) {
            $role = is_string($name) ? get_role($name) : null;
            $role?->add_cap(Settings::CAP);
        }
    }

    public static function revokeCapabilities(): void
    {
        foreach (wp_roles()->role_objects as $role) {
            $role->remove_cap(Settings::CAP);
        }
    }

    /**
     * The three default changelog labels, created once.
     */
    public static function ensureLabels(): void
    {
        if (get_option('ruvelo_feedback_labels_seeded')) {
            return;
        }
        foreach (Settings::labels() as $slug => $label) {
            if (! term_exists($slug, PostTypes::LABEL)) {
                wp_insert_term($label['label'], PostTypes::LABEL, ['slug' => $slug]);
            }
        }
        update_option('ruvelo_feedback_labels_seeded', 1, false);
    }
}
