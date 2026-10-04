<?php
/**
 * Site-wide search and replace across the wp_options table.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SearchReplaceManager
 *
 * Handles site-wide string replacements in the wp_options table.
 */
class SearchReplaceManager extends BaseTableManager {

	/**
	 * Bind the manager to the wp_options table.
	 */
	public function __construct() {
		parent::__construct();
		$this->table_name = ! empty( $this->wpdb->options ) ? $this->wpdb->options : $this->wpdb->prefix . 'options';
	}

	/**
	 * Preview search results
	 *
	 * @param string $search String to look for across option names and values.
	 * @return array
	 * @throws \Exception When the search string is empty or the user lacks permission.
	 */
	public function preview_search( $search ) {
		if ( empty( $search ) ) {
			throw new \Exception( 'Search string cannot be empty' );
		}

		global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-specific search operation; $this->excluded_from_replace_where() is a literal fragment, never user input
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_value LIKE %s AND {$this->excluded_from_replace_where()}",
				'%' . $wpdb->esc_like( $search ) . '%'
			),
			ARRAY_A
		);
        // phpcs:enable

		$matches = [];
		foreach ( $results as $row ) {
			$value = $row['option_value'];
			$count = substr_count( $value, $search );

			// Protected options are never rewritten, so they don't count as matches.
			if ( $count > 0 && ! $this->is_protected_item( $row['option_name'] ) ) {
				$matches[] = [
					'option_name' => $row['option_name'],
					'occurrences' => $count,
				];
			}
		}

		return $matches;
	}

	/**
	 * Execute search and replace
	 *
	 * @param string $search  String to look for.
	 * @param string $replace Replacement string.
	 * @param bool   $dry_run Count matches without writing when true.
	 * @return array
	 * @throws \Exception When the search string is empty or the user lacks permission.
	 */
	public function execute_replace( $search, $replace, $dry_run = true ) {
		if ( empty( $search ) ) {
			throw new \Exception( 'Search string cannot be empty' );
		}

		$this->validate_permissions();

		global $wpdb;
		$search_like = '%' . $wpdb->esc_like( $search ) . '%';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-specific query; $this->excluded_from_replace_where() is a literal fragment, never user input
		// Names first, then one value at a time: every match is handled without
		// holding all the matching values in memory at once.
		$names = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE %s AND {$this->excluded_from_replace_where()}",
				$search_like
			)
		);
        // phpcs:enable

		$updated_options   = [];
		$total_occurrences = 0;

		foreach ( $names as $option_name ) {
			// Skip protected options for safety.
			if ( $this->is_protected_item( $option_name ) ) {
				continue;
			}

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$original_value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option_name ) );
			if ( null === $original_value ) {
				continue;
			}

			$processed_value = $original_value;
			$occurrences     = 0;

			if ( is_serialized( $original_value ) ) {
				// Option values can hold data other plugins took from visitors, so
				// never instantiate arbitrary classes (object injection). stdClass
				// has no magic methods and is safe to walk; any other object comes
				// back as __PHP_Incomplete_Class, is skipped by recursive_replace(),
				// and re-serializes byte-for-byte unchanged.
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- allowed_classes restricted to stdClass; see above
				$data            = unserialize( $original_value, [ 'allowed_classes' => [ 'stdClass' ] ] );
				$occurrences     = $this->recursive_replace( $data, $search, $replace );
				$processed_value = serialize( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- must match the original option's serialization format
			} elseif ( $this->is_json( $original_value ) ) {
				$data            = json_decode( $original_value, true );
				$occurrences     = $this->recursive_replace( $data, $search, $replace );
				$processed_value = wp_json_encode( $data );
			} else {
				$processed_value = str_replace( $search, $replace, $original_value, $count );
				$occurrences     = $count;
			}

			if ( $occurrences > 0 ) {
				if ( ! $dry_run ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific replace operation
					$wpdb->update(
						$this->table_name,
						[ 'option_value' => $processed_value ],
						[ 'option_name' => $option_name ]
					);
					// A raw write: drop the cached copy, or get_option() (and a
					// persistent object cache) keeps serving the old value.
					wp_cache_delete( $option_name, 'options' );
				}

				$updated_options[]  = [
					'option_name' => $option_name,
					'occurrences' => $occurrences,
				];
				$total_occurrences += $occurrences;
			}
		}

		if ( ! $dry_run && $updated_options ) {
			wp_cache_delete( 'alloptions', 'options' );
		}

		return [
			'total_updated'     => count( $updated_options ),
			'total_occurrences' => $total_occurrences,
			'details'           => $updated_options,
			'dry_run'           => $dry_run,
		];
	}

	/**
	 * Recursively replace strings in arrays/objects
	 *
	 * @param mixed  $data    Value to walk, replaced in place.
	 * @param string $search  String to look for.
	 * @param string $replace Replacement string.
	 * @return int Number of replacements made
	 */
	private function recursive_replace( &$data, $search, $replace ) {
		$count = 0;
		if ( $data instanceof \__PHP_Incomplete_Class ) {
			return 0;
		}
		if ( is_array( $data ) || is_object( $data ) ) {
			foreach ( $data as &$value ) {
				$count += $this->recursive_replace( $value, $search, $replace );
			}
		} elseif ( is_string( $data ) ) {
			$data  = str_replace( $search, $replace, $data, $temp_count );
			$count = $temp_count;
		}
		return $count;
	}

	/**
	 * Check if a string is valid JSON
	 *
	 * @param string $value Value to test.
	 * @return bool
	 */
	private function is_json( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}
		json_decode( $value );
		return ( JSON_ERROR_NONE === json_last_error() );
	}

	/**
	 * SQL fragment excluding WordPress core's own cache/transient rows from
	 * a search/replace match — a plain `option_value LIKE %search%` used to
	 * rewrite any row regardless of whether it was user data or core/plugin
	 * cache state (e.g. a cached RSS feed value happening to contain the
	 * search string), with no warning that a core cache entry — not the
	 * user's own data — was touched.
	 *
	 * @return string SQL fragment (no leading AND), safe to interpolate —
	 *                built entirely from literals, never user input.
	 */
	private function excluded_from_replace_where() {
		return 'NOT (' . $this->transient_value_where( 'option_name' )
			. ' OR ' . $this->transient_timeout_where( 'option_name' )
			. " OR option_name LIKE 'feed\_%'"
			// This plugin's own history log and snapshots: rewriting them
			// would falsify the record of what a value used to be.
			. " OR option_name = 'nhrotm_history' OR option_name LIKE 'nhrotm\_snapshot%')";
	}
}
