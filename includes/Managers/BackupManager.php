<?php
/**
 * Creates and restores JSON snapshots of the wp_options table.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BackupManager
 *
 * Creates restorable snapshots of the wp_options table. Snapshots can be
 * taken manually, on a schedule (via Cron), or automatically before
 * destructive operations. Older snapshots are pruned to bound table growth.
 *
 * Snapshots are stored in non-autoloaded options, never in a table.
 * Transients, the `cron` option and this plugin's own recorded data are left
 * out (cache, live scheduling state that must never be rewound, and data a
 * snapshot must not contain a copy of) and the JSON
 * payload is gzip-compressed, so a snapshot is a small fraction of the table
 * it protects. A scheduled snapshot is skipped when nothing changed since the
 * previous one.
 */
class BackupManager {

	const FREQUENCY_OPTION = 'nhrotm_backup_frequency';
	const CRON_HOOK        = 'nhrotm_scheduled_backup';
	const MAX_SNAPSHOTS    = 15;

	/**
	 * Marks a compressed payload: prefix + base64( gzcompress( json ) ).
	 * Base64 keeps the bytes valid text.
	 */
	const GZ_PREFIX = 'gz:';

	/**
	 * Snapshots live in options (the plugin creates no tables): an index of
	 * [ 'next' => id, 'rows' => [ id => meta ] ] plus one payload option per
	 * snapshot. None is autoloaded, and a snapshot never contains another
	 * snapshot or the history log.
	 */
	const INDEX_OPTION = 'nhrotm_snapshots';
	const DATA_PREFIX  = 'nhrotm_snapshot_';

	/**
	 * Table used before 2.1 (migrated into options, then dropped).
	 */
	const LEGACY_TABLE = 'nhrotm_option_backups';

	/**
	 * Read the snapshot index.
	 *
	 * @return array { next, rows }
	 */
	private static function index() {
		$index = get_option( self::INDEX_OPTION, [] );
		return [
			'next' => isset( $index['next'] ) ? max( 1, (int) $index['next'] ) : 1,
			'rows' => isset( $index['rows'] ) && is_array( $index['rows'] ) ? $index['rows'] : [],
		];
	}

	/**
	 * Names of the options holding this plugin's own recorded data: the
	 * history log, the snapshot index and every snapshot payload. They are
	 * protected from edit/delete/import and left out of snapshots, exports
	 * and search & replace.
	 *
	 * @return string[]
	 */
	public static function data_options() {
		$names = [ HistoryManager::OPTION, self::INDEX_OPTION ];
		foreach ( array_keys( self::index()['rows'] ) as $id ) {
			$names[] = self::DATA_PREFIX . (int) $id;
		}
		return $names;
	}

	/**
	 * Whether an option holds this plugin's own recorded data.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	public static function is_data_option( $name ) {
		return HistoryManager::OPTION === $name || 0 === strpos( (string) $name, 'nhrotm_snapshot' );
	}

	/**
	 * Store one snapshot: payload option first, then its index row.
	 *
	 * @param array  $meta    label, type, option_count, checksum, created_at.
	 * @param string $payload Encoded snapshot.
	 * @return int|false New snapshot ID, or false when the payload could not be saved.
	 */
	private function store( array $meta, $payload ) {
		$index = self::index();
		$id    = $index['next'];
		delete_option( self::DATA_PREFIX . $id );
		if ( ! add_option( self::DATA_PREFIX . $id, $payload, '', false ) ) {
			return false;
		}
		$meta['size_bytes']   = strlen( $payload );
		$index['rows'][ $id ] = $meta;
		$index['next']        = $id + 1;
		update_option( self::INDEX_OPTION, $index, false );
		return $id;
	}

	/**
	 * Snapshot the options table (transients excluded).
	 *
	 * @param string $label Human-readable label.
	 * @param string $type  manual | scheduled | auto.
	 * @return int|false Inserted snapshot ID, 0 when a scheduled snapshot was skipped (no changes), or false on failure
	 */
	public function create_snapshot( $label = '', $type = 'manual' ) {
		$options = $this->current_options();
		$json    = wp_json_encode( $options, JSON_INVALID_UTF8_SUBSTITUTE );
		if ( false === $json ) {
			return false;
		}

		$checksum = $this->checksum( $options );
		if ( 'scheduled' === $type ) {
			$rows = self::index()['rows'];
			$last = $rows ? end( $rows ) : null;
			if ( $last && $last['checksum'] === $checksum ) {
				return 0;
			}
		}

		$id = $this->store(
			[
				'label'        => $label ? $label : gmdate( 'Y-m-d H:i' ),
				'type'         => $type,
				'option_count' => count( $options ),
				'checksum'     => $checksum,
				'created_at'   => current_time( 'mysql' ),
			],
			$this->encode( $json )
		);
		$this->prune();

		return $id;
	}

	/**
	 * Every row of wp_options except transients, the cron option and this
	 * plugin's own recorded data (history log, snapshots).
	 *
	 * @return array
	 */
	private function current_options() {
		global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Full options snapshot
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s AND option_name NOT IN ('cron', %s) ORDER BY option_id",
				$wpdb->esc_like( '_transient_' ) . '%',
				$wpdb->esc_like( '_site_transient_' ) . '%',
				$wpdb->esc_like( 'nhrotm_snapshot' ) . '%',
				HistoryManager::OPTION
			),
			ARRAY_A
		);
		return $rows ? $rows : [];
	}

	/**
	 * Fingerprint of a snapshot's content, used to skip unchanged scheduled
	 * snapshots. New snapshots never contain the cron option; the filter is
	 * for snapshots taken before 2.1, whose rows still do.
	 *
	 * @param array $options Snapshot rows.
	 * @return string
	 */
	private function checksum( array $options ) {
		$rows = array_filter(
			$options,
			function ( $row ) {
				return 'cron' !== $row['option_name'];
			}
		);
		return md5( (string) wp_json_encode( array_values( $rows ), JSON_INVALID_UTF8_SUBSTITUTE ) );
	}

	/**
	 * Compress a JSON payload for storage (falls back to plain JSON without zlib).
	 *
	 * @param string $json Snapshot JSON.
	 * @return string
	 */
	private function encode( $json ) {
		if ( ! function_exists( 'gzcompress' ) ) {
			return $json;
		}
		$gz = gzcompress( $json, 6 );
		return false === $gz ? $json : self::GZ_PREFIX . base64_encode( $gz ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- keeps compressed bytes valid in a text column
	}

	/**
	 * Decode a stored payload (compressed or legacy plain JSON) to rows.
	 *
	 * @param string $data Stored payload.
	 * @return array|null Rows, or null when the payload is corrupt.
	 */
	private function decode( $data ) {
		if ( 0 === strpos( $data, self::GZ_PREFIX ) ) {
			$raw  = base64_decode( substr( $data, strlen( self::GZ_PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- see encode()
			$data = ( false !== $raw && function_exists( 'gzuncompress' ) ) ? gzuncompress( $raw ) : false;
			if ( false === $data ) {
				return null;
			}
		}
		$rows = json_decode( $data, true );
		return is_array( $rows ) ? $rows : null;
	}

	/**
	 * 2.1 migration: copy the newest snapshots of the old table into
	 * options (compressed, without transients or the cron option), then drop
	 * the table.
	 *
	 * @return void
	 */
	public function upgrade() {
		global $wpdb;
		$table = $wpdb->prefix . self::LEGACY_TABLE;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is the plugin's own pre-2.1 table name and the ids are integers from our own SELECT, never user input; one-time migration
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		// Only the newest snapshots are kept. Each one is removed from the old
		// table as soon as it is stored, so a run that is cut short (timeout,
		// memory) resumes with the remaining ones instead of losing them.
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", self::MAX_SNAPSHOTS ) ) );
		if ( $ids ) {
			$wpdb->query( "DELETE FROM {$table} WHERE id NOT IN (" . implode( ',', $ids ) . ')' );
		}
		foreach ( array_reverse( $ids ) as $id ) {
			$old  = $wpdb->get_row( $wpdb->prepare( "SELECT label, type, created_at, data FROM {$table} WHERE id = %d", $id ), ARRAY_A );
			$rows = $old ? $this->decode( (string) $old['data'] ) : null;
			if ( null !== $rows ) {
				$rows = array_values(
					array_filter(
						$rows,
						function ( $row ) {
							$name = isset( $row['option_name'] ) ? (string) $row['option_name'] : '';
							return 'cron' !== $name && 0 !== strpos( $name, '_transient_' ) && 0 !== strpos( $name, '_site_transient_' );
						}
					)
				);
				$json = wp_json_encode( $rows, JSON_INVALID_UTF8_SUBSTITUTE );
				if ( false !== $json ) {
					$this->store(
						[
							'label'        => (string) $old['label'],
							'type'         => (string) $old['type'],
							'option_count' => count( $rows ),
							'checksum'     => $this->checksum( $rows ),
							'created_at'   => (string) $old['created_at'],
						],
						$this->encode( $json )
					);
				}
			}
			$wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
		}
		$this->prune();

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		// phpcs:enable
	}

	/**
	 * Total bytes all snapshots take up in the database.
	 *
	 * @return int
	 */
	public function total_size() {
		return (int) array_sum( array_column( self::index()['rows'], 'size_bytes' ) );
	}

	/**
	 * List snapshots (without the heavy data payload), newest first.
	 *
	 * @return array
	 */
	public function get_snapshots() {
		$rows = [];
		foreach ( array_reverse( self::index()['rows'], true ) as $id => $row ) {
			$rows[] = [
				'id'           => (int) $id,
				'label'        => $row['label'],
				'type'         => $row['type'],
				'option_count' => (int) $row['option_count'],
				'size_bytes'   => (int) $row['size_bytes'],
				'created_at'   => $row['created_at'],
			];
		}
		return array_map(
			function ( $row ) {
				$row['size_formatted'] = size_format( $row['size_bytes'] );
				// created_at is stored in site-local time (current_time('mysql'))
				// and replaced below by a date-only display string — keep an exact
				// GMT copy for age calculations (HealthService's "Last backup").
				$row['created_at_gmt'] = get_gmt_from_date( $row['created_at'] );
				// Display in the site's date format, not the raw MySQL datetime.
				$row['created_at'] = esc_html( wp_date( get_option( 'date_format' ), strtotime( $row['created_at'] ) ) );
				return $row;
			},
			$rows
		);
	}

	/**
	 * Restore a snapshot by re-applying each stored option.
	 *
	 * @param int $id Snapshot ID.
	 * @return int Number of options restored
	 * @throws \Exception When the snapshot is missing or its data is corrupt.
	 */
	public function restore_snapshot( $id ) {
		$id   = (int) $id;
		$data = isset( self::index()['rows'][ $id ] ) ? get_option( self::DATA_PREFIX . $id, '' ) : '';
		if ( ! $data ) {
			throw new \Exception( 'Snapshot not found' );
		}

		$options = $this->decode( $data );
		if ( null === $options ) {
			throw new \Exception( 'Snapshot data is corrupt' );
		}

		$restored = 0;
		$autoload = [];
		foreach ( $options as $option ) {
			// The scheduled-events list is live state, not a setting: restoring
			// it would rewind every plugin's schedule to the snapshot's moment
			// (snapshots taken before 2.1 still contain it).
			if ( empty( $option['option_name'] ) || 'cron' === $option['option_name'] || self::is_data_option( $option['option_name'] ) ) {
				continue;
			}
			$load_it                            = in_array( $option['autoload'], [ 'yes', 'on', 'auto', 'auto-on' ], true );
			$autoload[ $option['option_name'] ] = $load_it;
			// Restricted unserialize (no object injection) — objects round-trip unchanged as __PHP_Incomplete_Class.
			$value = is_serialized( $option['option_value'] ) ? unserialize( $option['option_value'], [ 'allowed_classes' => false ] ) : $option['option_value']; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- allowed_classes:false blocks object injection
			update_option( $option['option_name'], $value, $load_it );
			++$restored;
		}

		// update_option() leaves the autoload flag alone when the value did not
		// change, so put the flags back explicitly (WP 6.4+).
		if ( $autoload && function_exists( 'wp_set_option_autoload_values' ) ) {
			wp_set_option_autoload_values( $autoload );
		}

		// Same as Import: drop any persistent object-cache copies so nothing
		// keeps serving pre-restore values. Transients are deliberately left
		// alone — they are cache by contract and regenerate on their own.
		wp_cache_flush();

		return $restored;
	}

	/**
	 * Delete a snapshot.
	 *
	 * @param int $id Snapshot ID.
	 * @return bool
	 */
	public function delete_snapshot( $id ) {
		$id    = (int) $id;
		$index = self::index();
		if ( ! isset( $index['rows'][ $id ] ) ) {
			return false;
		}
		unset( $index['rows'][ $id ] );
		update_option( self::INDEX_OPTION, $index, false );
		delete_option( self::DATA_PREFIX . $id );
		return true;
	}

	/**
	 * Keep only the most recent MAX_SNAPSHOTS snapshots.
	 *
	 * @return void
	 */
	private function prune() {
		$ids   = array_keys( self::index()['rows'] );
		$extra = count( $ids ) - self::MAX_SNAPSHOTS;
		foreach ( $extra > 0 ? array_slice( $ids, 0, $extra ) : [] as $id ) {
			$this->delete_snapshot( $id );
		}
	}

	/**
	 * Reschedule the backup Cron event for a frequency: off | daily | weekly.
	 *
	 * @param string $frequency Backup frequency.
	 * @return void
	 */
	public static function reschedule( $frequency ) {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		if ( in_array( $frequency, [ 'daily', 'weekly' ], true ) ) {
			wp_schedule_event( time(), $frequency, self::CRON_HOOK );
		}
	}
}
