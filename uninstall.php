<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following:
 * - This setting should be used to clean up any database tables or settings
 *   that the plugin has created.
 * - This file is ONLY called when the plugin is DELETED, not deactivated.
 *
 * @package Nhrotm\OptionsTableManager
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove this plugin's tables and options from the current site.
 *
 * @return void
 */
function nhrotm_uninstall_site() {
	global $wpdb;

	// The plugin stores its history and snapshots in options. The two tables
	// below only exist on sites that never ran the 2.1 migration.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$history_table = $wpdb->prefix . 'nhrotm_option_history';
	$wpdb->query( "DROP TABLE IF EXISTS $history_table" );

	$backups_table = $wpdb->prefix . 'nhrotm_option_backups';
	$wpdb->query( "DROP TABLE IF EXISTS $backups_table" );

	// History log, snapshot index and every snapshot payload.
	$data_options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name = %s OR option_name LIKE %s", 'nhrotm_history', $wpdb->esc_like( 'nhrotm_snapshot' ) . '%' ) );
	// phpcs:enable
	foreach ( (array) $data_options as $data_option ) {
		delete_option( $data_option );
	}

	foreach ( [
		'nhrotm_auto_cleanup_enabled',
		'nhrotm_allow_html_in_values',
		'nhrotm_usage_tracking_enabled',
		'nhrotm_used_autoload_options',
		'nhrotm_usage_tracking_since',
		'nhrotm_usage_load_count',
		'nhrotm_backup_frequency',
		'nhrotm_history_retention_days',
		'nhrotm_settings',
		'nhrotm_usage',
		'nhrotm_db_version',
		'nhrotm_migrating',
	] as $option ) {
		delete_option( $option );
	}

	wp_clear_scheduled_hook( 'nhrotm_daily_cleanup' );
	wp_unschedule_hook( 'nhrotm_cleanup' );
	wp_clear_scheduled_hook( 'nhrotm_daily_history_prune' );
	wp_clear_scheduled_hook( 'nhrotm_scheduled_backup' );
}

// Every site in a network keeps its own tables and options.
if ( is_multisite() ) {
	foreach ( get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	) as $nhrotm_site_id ) {
		switch_to_blog( $nhrotm_site_id );
		nhrotm_uninstall_site();
		restore_current_blog();
	}
} else {
	nhrotm_uninstall_site();
}

// Add any other options to be deleted here, e.g. delete_option( 'nhrotm_version' ).
