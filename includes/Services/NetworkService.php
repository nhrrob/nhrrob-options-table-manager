<?php
/**
 * Multisite network view: every site at a glance, and network-wide options.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads per-site figures and the network options table (wp_sitemeta), where
 * WordPress keeps network settings and — on multisite — site transients.
 */
class NetworkService {

	const SITE_LIMIT = 200;

	/**
	 * Network options WordPress itself owns. They can be viewed, never deleted.
	 */
	const PROTECTED_KEYS = [
		'site_name',
		'admin_email',
		'admin_user_id',
		'registration',
		'upload_filetypes',
		'blog_upload_space',
		'fileupload_maxk',
		'site_admins',
		'allowedthemes',
		'illegal_names',
		'wpmu_upgrade_site',
		'welcome_email',
		'first_post',
		'first_page',
		'first_comment',
		'first_comment_url',
		'first_comment_author',
		'first_comment_email',
		'welcome_user_email',
		'limited_email_domains',
		'banned_email_domains',
		'siteurl',
		'add_new_users',
		'upload_space_check_disabled',
		'subdomain_install',
		'ms_files_rewriting',
		'user_count',
		'blog_count',
		'initial_db_version',
		'active_sitewide_plugins',
		'WPLANG',
		'menu_items',
		'global_terms_enabled',
		'registrationnotification',
		'new_admin_email',
		'can_compress_scripts',
		'main_site',
		'site_meta_supported',
		'wp_force_deactivated_plugins',
		'user_roles',
		'recently_activated',
		'auth_key',
		'auth_salt',
		'logged_in_key',
		'logged_in_salt',
		'nonce_key',
		'nonce_salt',
		'secure_auth_key',
		'secure_auth_salt',
	];

	/**
	 * Every site with its option count, autoload size and database size.
	 *
	 * @return array
	 */
	public function sites() {
		global $wpdb;

		// One pass over the table list, grouped by each site's prefix.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = (array) $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );

		$out = [];
		foreach ( get_sites(
			[
				'number' => self::SITE_LIMIT,
				'fields' => 'ids',
			]
		) as $site_id ) {
			$site_id = (int) $site_id;
			switch_to_blog( $site_id );

			$prefix   = $wpdb->prefix;
			$is_main  = is_main_site( $site_id );
			$db_bytes = 0;
			foreach ( $tables as $table ) {
				$name = $table['Name'];
				if ( 0 !== strpos( $name, $prefix ) ) {
					continue;
				}
				// The main site's prefix is also the start of every other site's.
				if ( $is_main && preg_match( '/^' . preg_quote( $wpdb->base_prefix, '/' ) . '\d+_/', $name ) ) {
					continue;
				}
				$db_bytes += (int) $table['Data_length'] + (int) $table['Index_length'];
			}

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( "SELECT COUNT(*) AS c, COALESCE(SUM(CASE WHEN autoload IN ('yes','on','auto','auto-on') THEN LENGTH(option_value) ELSE 0 END), 0) AS a FROM {$wpdb->options}", ARRAY_A );

			$out[] = [
				'id'       => $site_id,
				'name'     => get_bloginfo( 'name' ),
				'url'      => home_url( '/' ),
				'open'     => admin_url( 'tools.php?page=nhrotm-options-table-manager' ),
				'options'  => (int) $row['c'],
				'autoload' => (int) $row['a'],
				'db_bytes' => $db_bytes,
				'main'     => $is_main,
			];
			restore_current_blog();
		}
		return $out;
	}

	/**
	 * One page of network options (wp_sitemeta), largest first.
	 *
	 * @param string $search Substring of the key.
	 * @param string $kind   '' = everything, 'transients' = site transients only, 'options' = the rest.
	 * @param int    $page   1-based page.
	 * @param int    $limit  Rows per page.
	 * @return array { items, total }
	 */
	public function options( $search = '', $kind = '', $page = 1, $limit = 50 ) {
		global $wpdb;
		$limit  = max( 1, min( 100, (int) $limit ) );
		$offset = ( max( 1, (int) $page ) - 1 ) * $limit;

		$where  = [ 'site_id = %d' ];
		$params = [ get_current_network_id() ];
		if ( '' !== $search ) {
			$where[]  = 'meta_key LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		if ( 'transients' === $kind ) {
			$where[]  = 'meta_key LIKE %s';
			$params[] = $wpdb->esc_like( '_site_transient_' ) . '%';
		} elseif ( 'options' === $kind ) {
			$where[]  = 'meta_key NOT LIKE %s';
			$params[] = $wpdb->esc_like( '_site_transient_' ) . '%';
		}
		$where_sql = implode( ' AND ', $where );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where_sql is assembled from the literal fragments above; every value is a placeholder
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->sitemeta} WHERE $where_sql", $params ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, meta_key, LEFT(meta_value, 200) AS preview, LENGTH(meta_value) AS size FROM {$wpdb->sitemeta} WHERE $where_sql ORDER BY LENGTH(meta_value) DESC LIMIT %d, %d",
				array_merge( $params, [ $offset, $limit ] )
			),
			ARRAY_A
		);
        // phpcs:enable

		$items = [];
		foreach ( (array) $rows as $row ) {
			$items[] = [
				'id'        => (int) $row['meta_id'],
				'name'      => $row['meta_key'],
				'preview'   => $row['preview'],
				'size'      => (int) $row['size'],
				'transient' => 0 === strpos( $row['meta_key'], '_site_transient_' ),
				'protected' => $this->is_protected( $row['meta_key'] ),
			];
		}
		return [
			'items' => $items,
			'total' => $total,
		];
	}

	/**
	 * Whether a network option must not be deleted.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public function is_protected( $key ) {
		return in_array( $key, self::PROTECTED_KEYS, true );
	}

	/**
	 * Delete one network option (or site transient) by its row id.
	 *
	 * @param int $id meta_id.
	 * @return bool False when it is protected or missing.
	 */
	public function delete( $id ) {
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$key = $wpdb->get_var( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_id = %d AND site_id = %d", (int) $id, get_current_network_id() ) );
		if ( null === $key || $this->is_protected( $key ) ) {
			return false;
		}
		// Through core, so the object cache and hooks stay consistent.
		if ( 0 === strpos( $key, '_site_transient_timeout_' ) ) {
			return delete_site_transient( substr( $key, strlen( '_site_transient_timeout_' ) ) );
		}
		if ( 0 === strpos( $key, '_site_transient_' ) ) {
			return delete_site_transient( substr( $key, strlen( '_site_transient_' ) ) );
		}
		return delete_network_option( null, $key );
	}

	/**
	 * Delete every expired site transient.
	 *
	 * @return int Transients deleted.
	 */
	public function clean_expired_transients() {
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$names   = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_key FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key LIKE %s AND meta_value < %d",
				get_current_network_id(),
				$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
				time()
			)
		);
		$deleted = 0;
		foreach ( $names as $name ) {
			$deleted += delete_site_transient( substr( $name, strlen( '_site_transient_timeout_' ) ) ) ? 1 : 0;
		}
		return $deleted;
	}
}
