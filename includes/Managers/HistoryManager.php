<?php
/**
 * Manages the option history: logging changes, retrieving history, and restoring versions.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HistoryManager
 *
 * Manages the option history: logging changes, retrieving history, and restoring versions.
 */
class HistoryManager {

	/**
	 * Actions whose stored old value can be written back to wp_options.
	 */
	const RESTORABLE_ACTIONS = [ 'update', 'delete', 'restore', 'restore_backup' ];

	/**
	 * The history log: [ 'next' => id, 'rows' => [ id => row ] ], oldest
	 * first. One non-autoloaded option; the plugin creates no tables.
	 */
	const OPTION = 'nhrotm_history';

	/**
	 * Hard caps, so the log can never bloat the table it helps clean: the
	 * oldest entries are dropped first, and a value too large to keep is
	 * recorded without its content (that entry cannot be restored).
	 */
	const MAX_ROWS        = 300;
	const MAX_VALUE_BYTES = 102400;
	const MAX_TOTAL_BYTES = 1048576;

	/**
	 * Table used before 2.1 (migrated into OPTION, then dropped).
	 */
	const LEGACY_TABLE = 'nhrotm_option_history';

	/**
	 * The log held in memory while writes are deferred (see defer()), else null.
	 *
	 * @var array|null
	 */
	private static $deferred = null;

	/**
	 * Hold every write in memory until flush(). A bulk action logs one entry
	 * per row; without this each entry would rewrite the whole option.
	 *
	 * @return void
	 */
	public static function defer() {
		if ( null === self::$deferred ) {
			self::$deferred = ( new self() )->read();
		}
	}

	/**
	 * Save the deferred log in one write and stop deferring.
	 *
	 * @return void
	 */
	public static function flush() {
		if ( null !== self::$deferred ) {
			$log            = self::$deferred;
			self::$deferred = null;
			update_option( self::OPTION, $log, false );
		}
	}

	/**
	 * Read the log.
	 *
	 * @return array { next, rows }
	 */
	private function read() {
		if ( null !== self::$deferred ) {
			return self::$deferred;
		}
		$log = get_option( self::OPTION, [] );
		return [
			'next' => isset( $log['next'] ) ? max( 1, (int) $log['next'] ) : 1,
			'rows' => isset( $log['rows'] ) && is_array( $log['rows'] ) ? $log['rows'] : [],
		];
	}

	/**
	 * Enforce the caps and save the log.
	 *
	 * @param array $log { next, rows }.
	 * @return void
	 */
	private function write( array $log ) {
		$extra = count( $log['rows'] ) - self::MAX_ROWS;
		if ( $extra > 0 ) {
			$log['rows'] = array_slice( $log['rows'], $extra, null, true );
		}
		$bytes = 0;
		foreach ( $log['rows'] as $row ) {
			$bytes += strlen( (string) $row['option_value'] );
		}
		foreach ( array_keys( $log['rows'] ) as $id ) {
			if ( $bytes <= self::MAX_TOTAL_BYTES || count( $log['rows'] ) <= 1 ) {
				break;
			}
			$bytes -= strlen( (string) $log['rows'][ $id ]['option_value'] );
			unset( $log['rows'][ $id ] );
		}
		if ( null !== self::$deferred ) {
			self::$deferred = $log;
			return;
		}
		update_option( self::OPTION, $log, false );
	}

	/**
	 * Rows newest first, each with its id.
	 *
	 * @param bool $with_values Keep the stored value (lists don't need it).
	 * @return array
	 */
	private function newest_first( $with_values = false ) {
		$out = [];
		foreach ( array_reverse( $this->read()['rows'], true ) as $id => $row ) {
			if ( ! $with_values ) {
				unset( $row['option_value'] );
			}
			$out[] = [ 'id' => (int) $id ] + $row;
		}
		return $out;
	}

	/**
	 * 2.1 migration: copy the newest rows of the old history table into
	 * the option, then drop the table.
	 *
	 * Rows written by 2.0 have no record_type. Meta edits were then logged
	 * with the same update/create actions as options, so restoring one wrote a
	 * bogus wp_options row: rows whose type the action proves are labelled, and
	 * update/create rows whose name is not a current option become 'unknown'
	 * so they can never be restored.
	 *
	 * @return void
	 */
	public function upgrade() {
		global $wpdb;
		$table = $wpdb->prefix . self::LEGACY_TABLE;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- $table is the plugin's own pre-2.1 table name, never user input; one-time migration
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		if ( false === get_option( self::OPTION, false ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", self::MAX_ROWS ), ARRAY_A );
			$rows = array_reverse( $rows ? $rows : [] );
			$log  = [
				'next' => 1,
				'rows' => [],
			];
			foreach ( $rows as $row ) {
				$type = isset( $row['record_type'] ) ? (string) $row['record_type'] : '';
				if ( '' === $type ) {
					$type = self::legacy_record_type( (string) $row['action'] );
					if ( 'options' === $type && in_array( $row['action'], [ 'update', 'create' ], true )
						&& null === $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $row['option_name'] ) ) ) {
						$type = 'unknown';
					}
				}
				$log['rows'][ $log['next'] ] = $this->row( $row['option_name'], (string) $row['option_value'], $row['action'], $type, (int) $row['performed_by'], $row['performed_at'] );
				++$log['next'];
			}
			$this->write( $log );
		}

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		// phpcs:enable
	}

	/**
	 * Record type a pre-2.1 action implies.
	 *
	 * @param string $action Logged action.
	 * @return string
	 */
	private static function legacy_record_type( $action ) {
		$map = [
			'delete_usermeta'      => 'usermeta',
			'delete_postmeta'      => 'postmeta',
			'delete_commentmeta'   => 'commentmeta',
			'delete_termmeta'      => 'termmeta',
			'delete_transient'     => 'transients',
			'create_transient'     => 'transients',
			'update_transient'     => 'transients',
			'snapshot'             => 'event',
			'clean_transients'     => 'event',
			'clean_transients_all' => 'event',
			'delete_orphans'       => 'event',
		];
		return isset( $map[ $action ] ) ? $map[ $action ] : 'options';
	}

	/**
	 * Build one log row; a value over MAX_VALUE_BYTES is not kept.
	 *
	 * @param string $option_name Option name (or meta key / transient name).
	 * @param string $value       Value before the change (already a string).
	 * @param string $action      Logged action.
	 * @param string $record_type Record type.
	 * @param int    $user_id     Who made the change.
	 * @param string $when        MySQL datetime, site-local.
	 * @return array
	 */
	private function row( $option_name, $value, $action, $record_type, $user_id, $when ) {
		$row = [
			'option_name'  => (string) $option_name,
			'option_value' => $value,
			'action'       => (string) $action,
			'record_type'  => (string) $record_type,
			'performed_by' => (int) $user_id,
			'performed_at' => (string) $when,
		];
		if ( strlen( $value ) > self::MAX_VALUE_BYTES ) {
			$row['option_value']  = '';
			$row['value_dropped'] = 1;
		}
		return $row;
	}

	/**
	 * Log a change to an option
	 *
	 * @param string $option_name Option name (or meta key / transient name).
	 * @param mixed  $old_value   Value before the change.
	 * @param string $action      'update' or 'delete'.
	 * @param string $record_type options | usermeta | postmeta | commentmeta | termmeta | transients | event.
	 * @return int The new entry's ID
	 */
	public function log_change( $option_name, $old_value, $action = 'update', $record_type = 'options' ) {
		// If value is array or object, serialize it.
		if ( is_array( $old_value ) || is_object( $old_value ) ) {
			$old_value = maybe_serialize( $old_value );
		}

		$log                = $this->read();
		$id                 = $log['next'];
		$log['rows'][ $id ] = $this->row( $option_name, (string) $old_value, $action, $record_type, get_current_user_id(), current_time( 'mysql' ) );
		$log['next']        = $id + 1;
		$this->write( $log );
		return $id;
	}

	/**
	 * Get history for a specific option
	 *
	 * @param string $option_name Option name.
	 * @return array
	 */
	public function get_history( $option_name ) {
		$out = [];
		foreach ( $this->newest_first( true ) as $row ) {
			if ( (string) $option_name === $row['option_name'] && 'options' === $row['record_type'] ) {
				$out[] = $row;
				if ( count( $out ) >= 100 ) {
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Get the most recent history entries across all options (for the dashboard feed).
	 *
	 * @param int $limit Max rows to return (1–20).
	 * @return array
	 */
	public function get_recent( $limit = 5 ) {
		return array_slice( $this->newest_first(), 0, max( 1, min( 20, (int) $limit ) ) );
	}

	/**
	 * Total number of recorded history entries.
	 *
	 * @return int
	 */
	public function count_all() {
		return count( $this->read()['rows'] );
	}

	/**
	 * One page of history entries across all options, newest first.
	 *
	 * @param int $offset Rows to skip.
	 * @param int $limit  Rows to return (1–100).
	 * @return array
	 */
	public function get_page( $offset = 0, $limit = 20 ) {
		return array_slice( $this->newest_first(), max( 0, (int) $offset ), max( 1, min( 100, (int) $limit ) ) );
	}

	/**
	 * Entry count and bytes of stored values (Settings → History retention).
	 *
	 * @return array { count, bytes }
	 */
	public function stats() {
		$rows  = $this->read()['rows'];
		$bytes = 0;
		foreach ( $rows as $row ) {
			$bytes += strlen( (string) $row['option_value'] );
		}
		return [
			'count' => count( $rows ),
			'bytes' => $bytes,
		];
	}

	/**
	 * Restore a specific version
	 *
	 * @param int $history_id History row ID.
	 * @return bool|string True on success, error message string on failure
	 */
	public function restore_version( $history_id ) {
		$rows   = $this->read()['rows'];
		$record = isset( $rows[ (int) $history_id ] ) ? $rows[ (int) $history_id ] : null;

		if ( ! $record ) {
			return 'Record not found';
		}

		// Only option rows carry a value update_option() can put back; a meta
		// or transient row restored here would create a bogus wp_options row.
		if ( ! self::is_restorable( $record ) ) {
			return 'This entry cannot be restored';
		}

		$option_name  = $record['option_name'];
		$option_value = $record['option_value'];

		// Restricted unserialize: no class is instantiated (no object
		// injection); objects come back as __PHP_Incomplete_Class, which
		// update_option() re-serializes to the exact original string.
		$value_to_restore = is_serialized( $option_value ) ? unserialize( $option_value, [ 'allowed_classes' => false ] ) : $option_value; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- allowed_classes:false blocks object injection

		// We log the CURRENT state before restoring, effectively adding a new history entry for the "undo".
		$current_value = get_option( $option_name );
		if ( false !== $current_value ) {
			$this->log_change( $option_name, $current_value, 'restore_backup' );
		}

		if ( update_option( $option_name, $value_to_restore ) ) {
			return true;
		}

		// If update_option returns false, it might mean the value is unchanged.
		// But for restore, that's fine.
		// Or if option was deleted, update_option might act as add_option.
		if ( get_option( $option_name ) === $value_to_restore ) {
			return true;
		}

		// Try add_option if update failed and option doesn't exist.
		if ( get_option( $option_name ) === false ) {
			if ( add_option( $option_name, $value_to_restore ) ) {
				return true;
			}
		}

		return 'Failed to restore option';
	}

	/**
	 * Whether a history row can be restored with restore_version().
	 *
	 * @param array $row History row (needs action + record_type; value_dropped when set).
	 * @return bool
	 */
	public static function is_restorable( array $row ) {
		return isset( $row['record_type'], $row['action'] )
			&& 'options' === $row['record_type']
			&& empty( $row['value_dropped'] )
			&& in_array( $row['action'], self::RESTORABLE_ACTIONS, true );
	}

	/**
	 * Prune history logs older than X days
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of entries deleted
	 */
	public function prune_history( $days = 30 ) {
		$days = intval( $days );
		if ( $days < 1 ) {
			$days = 30;
		}
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - $days * DAY_IN_SECONDS );
		$log    = $this->read();
		$before = count( $log['rows'] );

		$log['rows'] = array_filter(
			$log['rows'],
			function ( $row ) use ( $cutoff ) {
				return (string) $row['performed_at'] >= $cutoff;
			}
		);
		$deleted     = $before - count( $log['rows'] );
		if ( $deleted ) {
			$this->write( $log );
		}
		return $deleted;
	}
}
