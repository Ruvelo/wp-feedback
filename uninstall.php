<?php

/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Scheduled emails are always cleared, so nothing is left running. Content
 * (requests, votes, changelog entries) is deleted only when the site owner
 * ticked "Also delete every request, vote, changelog entry and setting".
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

wp_clear_scheduled_hook('ruvelo_feedback_send_notifications');

$ruvelo_settings = get_option('ruvelo_feedback_settings', []);
$ruvelo_delete = is_array($ruvelo_settings) && ! empty($ruvelo_settings['delete_data']);

/**
 * Lets a site decide in code, e.g. on a staging copy.
 *
 * @param bool $delete
 */
if (! apply_filters('ruvelo_feedback_uninstall_delete_data', $ruvelo_delete)) {
    return;
}

global $wpdb;

foreach (['ruvelo_request', 'ruvelo_entry'] as $ruvelo_type) {
    do {
        $ruvelo_ids = get_posts(['post_type' => $ruvelo_type, 'post_status' => 'any', 'numberposts' => 200, 'fields' => 'ids']);
        $ruvelo_merged = get_posts(['post_type' => $ruvelo_type, 'post_status' => 'ruvelo_merged', 'numberposts' => 200, 'fields' => 'ids']);
        foreach (array_merge($ruvelo_ids, $ruvelo_merged) as $ruvelo_id) {
            wp_delete_post((int) $ruvelo_id, true);
        }
    } while ($ruvelo_ids !== [] || $ruvelo_merged !== []);
}

foreach (['ruvelo_request_category', 'ruvelo_request_tag', 'ruvelo_entry_label'] as $ruvelo_taxonomy) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The taxonomy isn't registered during uninstall.
    $ruvelo_terms = $wpdb->get_col($wpdb->prepare("SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s", $ruvelo_taxonomy));
    foreach ($ruvelo_terms as $ruvelo_term) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($wpdb->term_relationships, ['term_taxonomy_id' => (int) $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s", $ruvelo_term, $ruvelo_taxonomy))]);
        $wpdb->delete($wpdb->term_taxonomy, ['term_id' => (int) $ruvelo_term, 'taxonomy' => $ruvelo_taxonomy]);
        $wpdb->delete($wpdb->termmeta, ['term_id' => (int) $ruvelo_term]);
        $wpdb->delete($wpdb->terms, ['term_id' => (int) $ruvelo_term]);
        // phpcs:enable
    }
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing the plugin's own tables.
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ruvelo_feedback_votes");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ruvelo_feedback_outbox");
$wpdb->delete($wpdb->usermeta, ['meta_key' => 'ruvelo_feedback_seen']); // phpcs:ignore WordPress.DB.SlowDBQuery -- Runs once, on uninstall.
// phpcs:enable

$ruvelo_pages = get_option('ruvelo_feedback_pages', []);
foreach (is_array($ruvelo_pages) ? $ruvelo_pages : [] as $ruvelo_page) {
    wp_delete_post((int) $ruvelo_page, true);
}

foreach (['ruvelo_feedback_settings', 'ruvelo_feedback_db_version', 'ruvelo_feedback_pages', 'ruvelo_feedback_optouts', 'ruvelo_feedback_labels_seeded'] as $ruvelo_option) {
    delete_option($ruvelo_option);
}

foreach (wp_roles()->role_objects as $ruvelo_role) {
    $ruvelo_role->remove_cap('ruvelo_manage_feedback');
}
