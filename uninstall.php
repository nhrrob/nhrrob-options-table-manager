<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following:
 * - This setting should be used to clean up any database tables or settings
 *   that the plugin has created.
 * - This file is ONLY called when the plugin is DELETED, not deactivated.
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

/**
 * Cleanup database table
 */
$table_name = $wpdb->prefix . 'nhrotm_option_history';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

/**
 * Cleanup settings/options
 */
delete_option('nhrotm_auto_cleanup_enabled');

// Add any other options to be deleted here
// delete_option( 'nhrotm_version' ); 
