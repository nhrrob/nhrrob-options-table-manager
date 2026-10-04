<?php
/**
 * Read service for third-party integration tables (e.g. WPRM).
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read service for third-party integration tables (e.g. WPRM).
 *
 * Quarantines integration browsing here so their tables never inflate the
 * core navigation. Only tables that actually exist are exposed.
 */
class IntegrationsService {

	/**
	 * Integration definitions keyed by slug: table suffix + label.
	 *
	 * @return array
	 */
	private function definitions() {
		return [
			'wprm_ratings'   => [
				'label'  => 'WPRM Ratings',
				'table'  => 'wprm_ratings',
				'plugin' => 'wp-recipe-maker/wp-recipe-maker.php',
			],
			'wprm_analytics' => [
				'label'  => 'WPRM Analytics',
				'table'  => 'wprm_analytics',
				'plugin' => 'wp-recipe-maker/wp-recipe-maker.php',
			],
			'wprm_changelog' => [
				'label'  => 'WPRM Changelog',
				'table'  => 'wprm_changelog',
				'plugin' => 'wp-recipe-maker/wp-recipe-maker.php',
			],
			'better_payment' => [
				'label'  => 'Better Payment',
				'table'  => 'better_payment',
				'plugin' => 'better-payment/better-payment.php',
			],
		];
	}

	/**
	 * Definitions whose table exists, found with one query for all of them.
	 *
	 * @return array slug => definition.
	 */
	private function existing() {
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
		return array_filter(
			$this->definitions(),
			function ( $def ) use ( $tables, $wpdb ) {
				return in_array( $wpdb->prefix . $def['table'], $tables, true );
			}
		);
	}

	/**
	 * Available integrations (only those whose table exists).
	 *
	 * @return array
	 */
	public function available() {
		global $wpdb;
		$available = [];
		foreach ( $this->existing() as $slug => $def ) {
			$table = $wpdb->prefix . $def['table'];
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a hard-coded definitions() entry, verified to exist by existing()
			$count       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
			$available[] = [
				'slug'   => $slug,
				'label'  => $def['label'],
				// False = the table is a leftover: its plugin is inactive or gone.
				'active' => in_array( $def['plugin'], (array) get_option( 'active_plugins', [] ), true ) || ( is_multisite() && array_key_exists( $def['plugin'], (array) get_site_option( 'active_sitewide_plugins', [] ) ) ),
				'count'  => $count,
			];
		}
		return $available;
	}

	/**
	 * Whether any integration table exists.
	 *
	 * @return bool
	 */
	public function has_any() {
		return ! empty( $this->existing() );
	}

	/**
	 * Paginated rows for one integration table.
	 *
	 * @param string $slug     Integration slug.
	 * @param int    $page     1-based page.
	 * @param int    $per_page Rows per page.
	 * @param string $search   Substring matched against every column.
	 * @param string $orderby  Column to sort by (must be one of the table's columns).
	 * @param string $order    asc | desc.
	 * @return array { columns, items, total, page, per_page }
	 */
	public function rows( $slug, $page = 1, $per_page = 20, $search = '', $orderby = '', $order = 'desc' ) {
		global $wpdb;
		$defs = $this->definitions();
		if ( ! isset( $defs[ $slug ] ) ) {
			return [
				'columns'  => [],
				'items'    => [],
				'total'    => 0,
				'page'     => 1,
				'per_page' => $per_page,
			];
		}

		$table = $wpdb->prefix . $defs[ $slug ]['table'];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return [
				'columns'  => [],
				'items'    => [],
				'total'    => 0,
				'page'     => 1,
				'per_page' => $per_page,
			];
		}

		$page     = max( 1, (int) $page );
		$per_page = min( 100, max( 1, (int) $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a hard-coded definitions() entry, verified to exist via SHOW TABLES; column names come from SHOW COLUMNS, never from the request; one %s per searched column, built above
		// Read the schema directly rather than inferring from $rows[0] — an
		// empty result set has no first row, so column headers would
		// otherwise disappear entirely on a genuinely empty (but real,
		// structured) table.
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table`" );

		// Sort column: the schema's own name that equals the request, else the first column.
		$order_sql = '1';
		foreach ( $columns as $index => $column ) {
			if ( $column === $orderby ) {
				$order_sql = (string) ( $index + 1 );
				break;
			}
		}
		$order_sql .= 'asc' === $order ? ' ASC' : ' DESC';

		$where  = '';
		$params = [];
		$search = trim( (string) $search );
		if ( '' !== $search && $columns ) {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$parts = [];
			foreach ( $columns as $column ) {
				$parts[]  = '`' . str_replace( '`', '', $column ) . '` LIKE %s';
				$params[] = $like;
			}
			$where = ' WHERE ' . implode( ' OR ', $parts );
		}

		$total = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$table`" . $where, $params ) )
			: $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table`" . $where . " ORDER BY $order_sql LIMIT %d, %d", array_merge( $params, [ $offset, $per_page ] ) ), ARRAY_A );
        // phpcs:enable

		return [
			'columns'  => $columns,
			'items'    => $rows ? $rows : [],
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}
