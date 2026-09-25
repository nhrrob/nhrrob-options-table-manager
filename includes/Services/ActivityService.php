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
 * Writes go to the existing wp_nhrotm_option_history table so option edits stay
 * restorable — this service only adds the non-option events (autoload changes,
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
	 * Set up the activity service.
	 *
	 * @param HistoryManager|null $history Injected for tests; defaults to a real instance.
	 */
	public function __construct( ?HistoryManager $history = null ) {
		$this->history = $history ? $history : new HistoryManager();
	}

	/**
	 * Record one event.
	 *
	 * @param string $action    Action slug (see describe()).
	 * @param string $subject   Option name, prefix, or count — whatever the action acts on.
	 * @param mixed  $old_value Previous value, kept so option edits stay restorable.
	 * @return void
	 */
	public function record( $action, $subject, $old_value = '' ) {
		$this->history->log_change( (string) $subject, $old_value, $action );
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
			list($prefix, $code, $suffix) = $this->describe( $r['action'], $r['option_name'] );

			$ts = strtotime( $r['performed_at'] . ' UTC' );
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
	 * @param string $action  Action slug.
	 * @param string $subject Row subject.
	 * @return array [ prefix, code, suffix ]
	 */
	private function describe( $action, $subject ) {
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

		return [ __( 'Updated option', 'nhrrob-options-table-manager' ), $subject, '' ];
	}
}
