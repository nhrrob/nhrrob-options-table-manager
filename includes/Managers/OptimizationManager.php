<?php
/**
 * Analyzes and manages autoloaded options.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptimizationManager
 *
 * Handles database optimization tasks, specifically analyzing and managing autoloaded options.
 */
class OptimizationManager extends BaseTableManager {

	/**
	 * Resolve the wp_options table name for the current $wpdb connection.
	 */
	public function __construct() {
		parent::__construct();
		// Use standard wpdb property if available.
		$this->table_name = ! empty( $this->wpdb->options ) ? $this->wpdb->options : $this->wpdb->prefix . 'options';
	}

	/**
	 * Get autoloaded options, heaviest first.
	 *
	 * @param int $limit Safety cap on rows returned (not a "top N" — the
	 *                    Optimize screen now lists every autoloaded option,
	 *                    this just guards against a pathological site).
	 * @return array
	 */
	public function get_heavy_autoload_options( $limit = 2000 ) {
		global $wpdb;
		$limit = intval( $limit );

		// Query to get autoloaded options.
		// Broaden the search to catch 'yes', 'true', '1', 'on' by excluding known 'no' values.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific query
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value, autoload, LENGTH(option_value) as size_bytes
            FROM {$wpdb->options}
            WHERE autoload NOT IN ('off', 'no', 'false', '0', '')
            ORDER BY size_bytes DESC
            LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				// wpdb returns every column as a string — cast back to int so
				// callers (JS sort included) get a real number, not "8192" sorting
				// lexicographically before "890".
				$row['size_bytes'] = (int) $row['size_bytes'];
				// Format size for display.
				$row['size_formatted'] = size_format( $row['size_bytes'] );
				// Don't send the full value — a snippet is enough to save bandwidth.
				$row['value_snippet'] = substr( wp_strip_all_tags( $row['option_value'] ), 0, 100 );
				unset( $row['option_value'] );
				return $row;
			},
			$results
		);
	}

	/**
	 * Get total autoload size
	 *
	 * @return string Formatted size
	 */
	public function get_total_autoload_size() {
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific query
		$bytes = $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload NOT IN ('off', 'no', 'false', '0', '')" );
		return size_format( $bytes ? $bytes : 0 );
	}
}
