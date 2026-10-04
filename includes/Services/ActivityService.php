<?php
/**
 * Records and formats the "Recent activity" change feed.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Managers\HistoryManager;

/**
 * Records and reads the change feed shown as "Recent activity".
 *
 * Writes go to the history log (HistoryManager, one capped option) so option
 * edits stay restorable — this service only adds the non-option events (autoload changes,
 * orphan sweeps, transient cleanups) and turns rows into readable sentences.
 */
class ActivityService {

	/**
	 * History manager used to store and retrieve activity rows.
	 *
	 * @var HistoryManager
	 */
	private $history;

	/**
	 * Cleanup type labels, looked up once per request (see describe()).
	 *
	 * @var array|null
	 */
	private $cleanup_types = null;

	/**
	 * Set up the activity service.
	 *
	 * @param HistoryManager|null $history Injected for tests; defaults to a real instance.
	 */
	public function __construct( ?HistoryManager $history = null ) {
		$this->history = $history ? $history : new HistoryManager();
	}

	/**
	 * Record type implied by an action slug. update/create are shared by
	 * options and meta, so callers editing meta pass the type explicitly.
	 */
	const ACTION_TYPES = [
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
		'cleanup'              => 'event',
		'table_optimize'       => 'event',
		'table_repair'         => 'event',
		'table_convert'        => 'event',
		'table_empty'          => 'event',
		'table_drop'           => 'event',
		'cron_run'             => 'event',
		'cron_delete'          => 'event',
		'delete_orphans'       => 'event',
	];

	/**
	 * Record one event.
	 *
	 * @param string      $action      Action slug (see describe()).
	 * @param string      $subject     Option name, prefix, or count — whatever the action acts on.
	 * @param mixed       $old_value   Previous value, kept so option edits stay restorable.
	 * @param string|null $record_type Record type; derived from the action when null.
	 * @return void
	 */
	public function record( $action, $subject, $old_value = '', $record_type = null ) {
		if ( null === $record_type ) {
			$record_type = isset( self::ACTION_TYPES[ $action ] ) ? self::ACTION_TYPES[ $action ] : 'options';
		}
		$this->history->log_change( (string) $subject, $old_value, $action, $record_type );
	}

	/**
	 * Restore an option to the value stored in one history row.
	 *
	 * @param int $id History row id.
	 * @return true|string True on success, error message otherwise.
	 */
	public function restore( $id ) {
		return $this->history->restore_version( (int) $id );
	}

	/**
	 * Latest events, newest first.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public function recent( $limit = 5 ) {
		return $this->format( $this->history->get_recent( $limit ) );
	}

	/**
	 * Every recorded version of one option, newest first (Browse → History).
	 * Each entry adds `value`: the value it held before that change.
	 *
	 * @param string $option_name Option name.
	 * @return array
	 */
	public function for_option( $option_name ) {
		$rows = $this->history->get_history( $option_name );
		$out  = $this->format( $rows );
		foreach ( $out as $i => $entry ) {
			$value              = (string) $rows[ $i ]['option_value'];
			$out[ $i ]['value'] = strlen( $value ) > 300 ? substr( $value, 0, 300 ) . '…' : $value;
		}
		return $out;
	}

	/**
	 * One page of the full feed.
	 *
	 * @param int $page     1-based page number.
	 * @param int $per_page Rows per page.
	 * @return array { items, total, page, per_page, total_pages }
	 */
	public function paged( $page = 1, $per_page = 20 ) {
		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$total    = $this->history->count_all();

		return [
			'items'       => $this->format( $this->history->get_page( ( $page - 1 ) * $per_page, $per_page ) ),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		];
	}

	/**
	 * Turn raw rows into { id, prefix, code, suffix, when, who, action,
	 * option_name, performed_at_ts } entries. The first five are the
	 * original Dashboard "recent 5" widget's shape (unchanged, so it keeps
	 * working as-is); the rest are additions for the dedicated Activity tab
	 * (user attribution + raw fields for search/filter/sort).
	 *
	 * @param array $rows History rows.
	 * @return array
	 */
	private function format( array $rows ) {
		$out        = [];
		$name_cache = [];

		foreach ( $rows as $r ) {
			$type                         = ! empty( $r['record_type'] ) ? $r['record_type'] : 'unknown';
			list($prefix, $code, $suffix) = $this->describe( $r['action'], $r['option_name'], $type );

			// Stored in site-local time (current_time( 'mysql' )); convert before
			// comparing with now, or every entry is off by the site's UTC offset.
			$ts = strtotime( get_gmt_from_date( $r['performed_at'] ) . ' UTC' );
			$by = isset( $r['performed_by'] ) ? (int) $r['performed_by'] : 0;
			if ( ! isset( $name_cache[ $by ] ) ) {
				$name_cache[ $by ] = $this->display_name_for( $by );
			}

			$out[] = [
				'id'              => isset( $r['id'] ) ? (int) $r['id'] : 0,
				'prefix'          => $prefix,
				'code'            => $code,
				'suffix'          => $suffix,
				/* translators: %s: human-readable time difference, e.g. "2 hours". */
				'when'            => $ts ? sprintf( __( '%s ago', 'nhrrob-options-table-manager' ), human_time_diff( $ts ) ) : '',
				'who'             => $name_cache[ $by ],
				'action'          => $r['action'],
				'option_name'     => $r['option_name'],
				'record_type'     => $type,
				'restorable'      => HistoryManager::is_restorable( $r + [ 'record_type' => $type ] ),
				'performed_at_ts' => $ts ? $ts : 0,
			];
		}

		return $out;
	}

	/**
	 * Resolve a user id to a display name for the Activity tab's attribution
	 * column. 0 covers rows logged before `performed_by` existed and
	 * automated events with no acting user (e.g. the scheduled backup Cron).
	 *
	 * @param int $user_id WP user id, or 0.
	 * @return string
	 */
	private function display_name_for( $user_id ) {
		if ( $user_id <= 0 ) {
			return __( 'Automated', 'nhrrob-options-table-manager' );
		}
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Deleted user', 'nhrrob-options-table-manager' );
	}

	/**
	 * Sentence parts for one action. The middle part is rendered as code, so
	 * the verb and any trailing words stay separately translatable.
	 *
	 * @param string $action      Action slug.
	 * @param string $subject     Row subject.
	 * @param string $record_type Row record type.
	 * @return array [ prefix, code, suffix ]
	 */
	private function describe( $action, $subject, $record_type = 'options' ) {
		$meta_labels = [
			'usermeta'    => [ __( 'Added user meta', 'nhrrob-options-table-manager' ), __( 'Updated user meta', 'nhrrob-options-table-manager' ) ],
			'postmeta'    => [ __( 'Added post meta', 'nhrrob-options-table-manager' ), __( 'Updated post meta', 'nhrrob-options-table-manager' ) ],
			'commentmeta' => [ __( 'Added comment meta', 'nhrrob-options-table-manager' ), __( 'Updated comment meta', 'nhrrob-options-table-manager' ) ],
			'termmeta'    => [ __( 'Added term meta', 'nhrrob-options-table-manager' ), __( 'Updated term meta', 'nhrrob-options-table-manager' ) ],
		];
		if ( isset( $meta_labels[ $record_type ] ) && in_array( $action, [ 'create', 'update' ], true ) ) {
			return [ $meta_labels[ $record_type ][ 'create' === $action ? 0 : 1 ], $subject, '' ];
		}
		// Pre-2.1 rows whose table can't be proven (option vs meta): don't claim either.
		if ( 'unknown' === $record_type && in_array( $action, [ 'create', 'update' ], true ) ) {
			return [ 'create' === $action ? __( 'Added', 'nhrrob-options-table-manager' ) : __( 'Updated', 'nhrrob-options-table-manager' ), $subject, '' ];
		}

		switch ( $action ) {
			case 'create':
				return [ __( 'Added option', 'nhrrob-options-table-manager' ), $subject, '' ];

			case 'delete':
				return [ __( 'Deleted option', 'nhrrob-options-table-manager' ), $subject, '' ];

			case 'delete_usermeta':
				return [ __( 'Deleted user meta', 'nhrrob-options-table-manager' ), $subject, '' ];

			case 'delete_postmeta':
				return [ __( 'Deleted post meta', 'nhrrob-options-table-manager' ), $subject, '' ];

			case 'delete_commentmeta':
				return [ __( 'Deleted comment meta', 'nhrrob-options-table-manager' ), $subject, '' ];

			case 'delete_termmeta':
				return [ __( 'Deleted term meta', 'nhrrob-options-table-manager' ), $subject, '' ];

			case 'delete_transient':
				return [ __( 'Deleted transient', 'nhrrob-options-table-manager' ), $subject, '' ];

			case 'create_transient':
				return [ __( 'Added transient', 'nhrrob-options-table-manager' ), $subject, '' ];

			case 'update_transient':
				return [ __( 'Updated transient', 'nhrrob-options-table-manager' ), $subject, '' ];

			case 'disable_autoload':
				return [ __( 'Disabled autoload on', 'nhrrob-options-table-manager' ), $subject, '' ];

			case 'delete_orphans':
				return [
					__( 'Deleted orphaned option group', 'nhrrob-options-table-manager' ),
					$subject,
					'',
				];

			case 'clean_transients':
				$count = (int) $subject;
				return [
					sprintf(
						/* translators: %s: number of transients deleted. */
						_n(
							'Deleted %s expired transient',
							'Deleted %s expired transients',
							$count,
							'nhrrob-options-table-manager'
						),
						number_format_i18n( $count )
					),
					'',
					'',
				];

			case 'clean_transients_all':
				$count = (int) $subject;
				return [
					sprintf(
						/* translators: %s: number of transients deleted. */
						_n(
							'Deleted %s transient',
							'Deleted %s transients',
							$count,
							'nhrrob-options-table-manager'
						),
						number_format_i18n( $count )
					),
					'',
					'',
				];

			case 'cleanup':
				// The subject holds the cleanup type id and the row count, pipe-separated.
				list( $type, $count ) = array_pad( explode( '|', $subject, 2 ), 2, '0' );
				if ( null === $this->cleanup_types ) {
					$this->cleanup_types = ( new CleanupService( $this ) )->types();
				}
				$types = $this->cleanup_types;
				return [
					sprintf(
						/* translators: 1: number of rows removed, 2: what was cleaned, e.g. "Post revisions". */
						__( 'Cleaned %1$s rows: %2$s', 'nhrrob-options-table-manager' ),
						number_format_i18n( (int) $count ),
						isset( $types[ $type ] ) ? $types[ $type ]['label'] : $type
					),
					'',
					'',
				];

			case 'table_optimize':
				return [ __( 'Optimized table', 'nhrrob-options-table-manager' ), $subject, '' ];
			case 'table_repair':
				return [ __( 'Repaired table', 'nhrrob-options-table-manager' ), $subject, '' ];
			case 'table_convert':
				return [ __( 'Converted table to InnoDB', 'nhrrob-options-table-manager' ), $subject, '' ];
			case 'table_empty':
				return [ __( 'Emptied table', 'nhrrob-options-table-manager' ), $subject, '' ];
			case 'table_drop':
				return [ __( 'Dropped table', 'nhrrob-options-table-manager' ), $subject, '' ];
			case 'cron_run':
				return [ __( 'Ran scheduled event', 'nhrrob-options-table-manager' ), $subject, '' ];
			case 'cron_delete':
				return [ __( 'Deleted scheduled event', 'nhrrob-options-table-manager' ), $subject, '' ];

			case 'snapshot':
				return [ __( 'Snapshot taken', 'nhrrob-options-table-manager' ), '', $subject ];

			case 'restore':
			case 'restore_backup':
				return [
					__( 'Restored option', 'nhrrob-options-table-manager' ),
					$subject,
					__( 'to a previous value', 'nhrrob-options-table-manager' ),
				];
		}

		/**
		 * Describe an activity action core doesn't know (add-on actions).
		 *
		 * Return [ prefix, code, suffix ] to render the feed sentence, or null
		 * to fall back to the generic "Updated option" wording.
		 *
		 * @param array|null $parts   Sentence parts.
		 * @param string     $action  Action slug.
		 * @param string     $subject Row subject.
		 */
		$parts = apply_filters( 'nhrotm_activity_describe', null, $action, $subject );
		if ( is_array( $parts ) && 3 === count( $parts ) ) {
			return array_values( $parts );
		}

		return [ __( 'Updated option', 'nhrrob-options-table-manager' ), $subject, '' ];
	}
}
