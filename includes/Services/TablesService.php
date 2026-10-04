<?php
/**
 * Database tables: sizes, overhead, owners, and maintenance actions.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Managers\ScannerManager;

/**
 * Lists this site's tables and runs optimize / repair / convert / empty / drop.
 *
 * A table name only ever reaches SQL after an exact match against the
 * database's own table list, and only tables that no installed plugin or
 * theme claims (leftover / unknown owner) can be emptied or dropped.
 */
class TablesService {

	const ACTIONS = [ 'optimize', 'repair', 'convert', 'empty', 'drop' ];

	/**
	 * Activity feed recorder.
	 *
	 * @var ActivityService
	 */
	private $activity;

	/**
	 * Set up the service.
	 *
	 * @param ActivityService|null $activity Injected for tests; defaults to a real instance.
	 */
	public function __construct( ?ActivityService $activity = null ) {
		$this->activity = $activity ? $activity : new ActivityService();
	}

	/**
	 * Whether a table belongs to this site (multisite: other sites' numbered
	 * tables are excluded, and network-global tables show on the main site only).
	 *
	 * @param string $name Table name.
	 * @return bool
	 */
	private function is_site_table( $name ) {
		global $wpdb;
		if ( 0 !== strpos( $name, $wpdb->prefix ) ) {
			return false;
		}
		if ( is_multisite() && is_main_site() && preg_match( '/^' . preg_quote( $wpdb->base_prefix, '/' ) . '\d+_/', $name ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Every table of this site with size, overhead, engine and owner.
	 *
	 * Owner kind: core | plugin (an installed plugin or theme matches) |
	 * leftover (named after a plugin that is no longer installed) | unknown.
	 *
	 * @return array
	 */
	public function all() {
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		// Core is a fixed list: plugins (WooCommerce, Action Scheduler…) add their
		// own tables to $wpdb->tables, so that list alone can't tell core apart.
		$core = [];
		foreach ( [ 'posts', 'comments', 'links', 'options', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'termmeta', 'commentmeta' ] as $table ) {
			$core[] = $wpdb->prefix . $table;
		}
		foreach ( [ 'users', 'usermeta', 'blogs', 'blogmeta', 'signups', 'site', 'sitemeta', 'registration_log' ] as $table ) {
			$core[] = $wpdb->base_prefix . $table;
		}
		// Tables active code registered on $wpdb belong to an installed plugin.
		$registered = array_values( $wpdb->tables( 'all' ) );
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = implode( '|', array_merge( wp_list_pluck( get_plugins(), 'Name' ), [ wp_get_theme()->get( 'Name' ) ] ) );
		$scanner   = new ScannerManager();
		$unknown   = __( 'Unknown', 'nhrrob-options-table-manager' );

		$out = [];
		foreach ( (array) $rows as $row ) {
			$name = $row['Name'];
			// Views have no engine, size or overhead; OPTIMIZE/REPAIR don't apply.
			if ( ! $this->is_site_table( $name ) || null === $row['Engine'] || 'VIEW' === ( isset( $row['Comment'] ) ? $row['Comment'] : '' ) ) {
				continue;
			}
			$bare = substr( $name, strlen( $wpdb->prefix ) );
			if ( in_array( $name, $core, true ) ) {
				$kind  = 'core';
				$owner = __( 'WordPress Core', 'nhrrob-options-table-manager' );
			} elseif ( 0 === strpos( $bare, 'nhrotm' ) || in_array( $name, $registered, true ) ) {
				$kind  = 'plugin';
				$owner = $scanner->guess_owner( $bare );
				$owner = $owner === $unknown ? __( 'Active plugin', 'nhrrob-options-table-manager' ) : $owner;
			} else {
				$owner = $scanner->guess_owner( $bare );
				if ( $owner === $unknown ) {
					$kind = 'unknown';
				} else {
					$kind = false !== stripos( $installed, $owner ) ? 'plugin' : 'leftover';
				}
			}
			$out[] = [
				'name'      => $name,
				'rows'      => (int) $row['Rows'],
				'size'      => (int) $row['Data_length'] + (int) $row['Index_length'],
				'overhead'  => 'InnoDB' === $row['Engine'] ? 0 : (int) $row['Data_free'],
				'engine'    => (string) $row['Engine'],
				'owner'     => $owner,
				'kind'      => $kind,
				// Only tables no installed plugin claims can be emptied or dropped.
				'removable' => in_array( $kind, [ 'leftover', 'unknown' ], true ),
			];
		}
		return $out;
	}

	/**
	 * Total size of this site's tables in bytes (Dashboard stat).
	 *
	 * @return int
	 */
	public function total_size() {
		return (int) array_sum( wp_list_pluck( $this->all(), 'size' ) );
	}

	/**
	 * Whether the current user may run table actions here. The main site of a
	 * network lists the tables every site shares (users, sites, network-wide
	 * plugin tables), so there it takes a network administrator.
	 *
	 * @return bool
	 */
	public static function can_act() {
		if ( ! is_multisite() || ! is_main_site() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return true;
		}
		return current_user_can( 'manage_network' );
	}

	/**
	 * Run one maintenance action on one table.
	 *
	 * @param string $table   Table name (must be one of this site's tables).
	 * @param string $action  optimize | repair | convert | empty | drop.
	 * @param string $confirm For empty/drop: the table name typed by the user.
	 * @return true|string True on success, an error message otherwise.
	 */
	public function run( $table, $action, $confirm = '' ) {
		global $wpdb;

		$match = null;
		foreach ( $this->all() as $row ) {
			if ( $row['name'] === $table ) {
				$match = $row;
				break;
			}
		}
		if ( ! $match || ! in_array( $action, self::ACTIONS, true ) ) {
			return __( 'Unknown table or action.', 'nhrrob-options-table-manager' );
		}
		if ( ! self::can_act() ) {
			return __( 'On the main site of a network, tables can only be changed by a network administrator: this list includes tables every site shares.', 'nhrrob-options-table-manager' );
		}
		if ( in_array( $action, [ 'empty', 'drop' ], true ) ) {
			if ( ! $match['removable'] ) {
				return __( 'This table belongs to WordPress or to an installed plugin and cannot be emptied or dropped here.', 'nhrrob-options-table-manager' );
			}
			if ( $confirm !== $match['name'] ) {
				return __( 'Type the table name exactly to confirm.', 'nhrrob-options-table-manager' );
			}
		}

		// $name is the database's own spelling of an existing table (matched above).
		$name = '`' . str_replace( '`', '', $match['name'] ) . '`';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $name is an existing table name taken from SHOW TABLE STATUS, never the raw request value
		switch ( $action ) {
			case 'optimize':
				$result = $wpdb->query( "OPTIMIZE TABLE $name" );
				break;
			case 'repair':
				$result = $wpdb->query( "REPAIR TABLE $name" );
				break;
			case 'convert':
				$result = $wpdb->query( "ALTER TABLE $name ENGINE = InnoDB" );
				break;
			case 'empty':
				$result = $wpdb->query( "TRUNCATE TABLE $name" );
				break;
			default:
				$result = $wpdb->query( "DROP TABLE IF EXISTS $name" );
		}
        // phpcs:enable

		if ( false === $result ) {
			return __( 'The database rejected this action.', 'nhrrob-options-table-manager' );
		}
		$this->activity->record( 'table_' . $action, $match['name'] );
		return true;
	}
}
