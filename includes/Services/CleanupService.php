<?php
/**
 * Database cleanup: revisions, drafts, trash, spam, orphaned and duplicate
 * meta, oEmbed caches, expired transients and Action Scheduler leftovers.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts, previews and deletes each cleanup type, and owns their schedules.
 *
 * Every type is described once in spec(): which rows it matches (always a
 * literal SQL fragment built here, never from the request) and how they are
 * deleted. Posts and comments go through core's delete functions so other
 * plugins' hooks fire; meta rows and logs are deleted directly in batches.
 */
class CleanupService {

	const CRON_HOOK   = 'nhrotm_cleanup';
	const BATCH       = 200;
	const TIME_BUDGET = 15; // Seconds one clean() call may run before handing back.

	/**
	 * Recurrences a schedule may use ('' = off).
	 */
	const FREQUENCIES = [ 'hourly', 'twicedaily', 'daily', 'weekly', 'nhrotm_monthly' ];

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
	 * Every cleanup type available on this site: id => [ label, group, dated, schedulable ].
	 *
	 * @return array
	 */
	public function types() {
		global $wpdb;

		$types = [
			'revisions'          => [ __( 'Post revisions', 'nhrrob-options-table-manager' ), 'posts', true, true ],
			'auto_drafts'        => [ __( 'Auto-drafts', 'nhrrob-options-table-manager' ), 'posts', true, true ],
			'trashed_posts'      => [ __( 'Trashed posts', 'nhrrob-options-table-manager' ), 'posts', true, true ],
			'orphan_post_types'  => [ __( 'Posts of unregistered post types', 'nhrrob-options-table-manager' ), 'posts', false, false ],
			'spam_comments'      => [ __( 'Spam comments', 'nhrrob-options-table-manager' ), 'comments', true, true ],
			'trashed_comments'   => [ __( 'Trashed comments', 'nhrrob-options-table-manager' ), 'comments', true, true ],
			'pending_comments'   => [ __( 'Pending comments', 'nhrrob-options-table-manager' ), 'comments', true, true ],
			'pingbacks'          => [ __( 'Pingbacks and trackbacks', 'nhrrob-options-table-manager' ), 'comments', true, true ],
			'orphan_postmeta'    => [ __( 'Orphaned post meta', 'nhrrob-options-table-manager' ), 'meta', false, true ],
			'orphan_commentmeta' => [ __( 'Orphaned comment meta', 'nhrrob-options-table-manager' ), 'meta', false, true ],
			'orphan_termmeta'    => [ __( 'Orphaned term meta', 'nhrrob-options-table-manager' ), 'meta', false, true ],
			'orphan_usermeta'    => [ __( 'Orphaned user meta', 'nhrrob-options-table-manager' ), 'meta', false, true ],
			'duplicate_postmeta' => [ __( 'Duplicate post meta', 'nhrrob-options-table-manager' ), 'meta', false, false ],
			'duplicate_usermeta' => [ __( 'Duplicate user meta', 'nhrrob-options-table-manager' ), 'meta', false, false ],
			'orphan_term_rel'    => [ __( 'Orphaned term relationships', 'nhrrob-options-table-manager' ), 'meta', false, true ],
			'oembed_cache'       => [ __( 'oEmbed caches', 'nhrrob-options-table-manager' ), 'cache', false, true ],
			'expired_transients' => [ __( 'Expired transients', 'nhrrob-options-table-manager' ), 'cache', false, true ],
			'action_scheduler'   => [ __( 'Action Scheduler: finished actions and logs', 'nhrrob-options-table-manager' ), 'cache', true, true ],
		];

		// User meta is one table for the whole network: only its managers may clean it.
		if ( ! BrowseService::can_manage_usermeta() && ! wp_doing_cron() ) {
			unset( $types['orphan_usermeta'], $types['duplicate_usermeta'] );
		}
		if ( is_multisite() && ! is_main_site() ) {
			unset( $types['orphan_usermeta'], $types['duplicate_usermeta'] );
		}

		$as_table = $wpdb->prefix . 'actionscheduler_actions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $as_table ) ) ) !== $as_table ) {
			unset( $types['action_scheduler'] );
		}

		// Plain-language caveats for the types where "unused" is a judgement call.
		$notes = [
			'orphan_post_types'  => __( 'Content whose post type no active plugin or theme registers. A deactivated plugin looks the same as a removed one, so reactivate it first if you still need this content.', 'nhrrob-options-table-manager' ),
			'pending_comments'   => __( 'Comments still awaiting moderation. Review them first.', 'nhrrob-options-table-manager' ),
			'duplicate_postmeta' => __( 'Rows with the same key and value on the same post. One copy of each is kept.', 'nhrrob-options-table-manager' ),
			'duplicate_usermeta' => __( 'Rows with the same key and value on the same user. One copy of each is kept.', 'nhrrob-options-table-manager' ),
		];

		$out = [];
		foreach ( $types as $id => $t ) {
			$out[ $id ] = [
				'id'          => $id,
				'note'        => isset( $notes[ $id ] ) ? $notes[ $id ] : '',
				'label'       => $t[0],
				'group'       => $t[1],
				'dated'       => $t[2],
				'schedulable' => $t[3],
			];
		}
		return $out;
	}

	/**
	 * How a type's rows are matched and deleted.
	 *
	 * Returns [ from, where, params, pk, label, date, delete ]; every SQL part
	 * is a literal built here. `date` is the column the "older than N days"
	 * rule applies to ('' when the type has no date).
	 *
	 * @param string $type Type id.
	 * @return array|null
	 */
	private function spec( $type ) {
		global $wpdb;

		switch ( $type ) {
			case 'revisions':
				return [ "{$wpdb->posts} t", "t.post_type = 'revision'", [], 't.ID', 't.post_title', 't.post_modified_gmt', 'revision' ];
			case 'auto_drafts':
				return [ "{$wpdb->posts} t", "t.post_status = 'auto-draft'", [], 't.ID', 't.post_title', 't.post_date_gmt', 'post' ];
			case 'trashed_posts':
				return [ "{$wpdb->posts} t", "t.post_status = 'trash'", [], 't.ID', 't.post_title', 't.post_modified_gmt', 'post' ];
			case 'orphan_post_types':
				$registered = array_values( get_post_types() );
				return [
					"{$wpdb->posts} t",
					't.post_type NOT IN (' . implode( ',', array_fill( 0, count( $registered ), '%s' ) ) . ')',
					$registered,
					't.ID',
					"CONCAT(t.post_type, ': ', t.post_title)",
					'',
					'post',
				];
			case 'spam_comments':
				return [ "{$wpdb->comments} t", "t.comment_approved = 'spam'", [], 't.comment_ID', 't.comment_author', 't.comment_date_gmt', 'comment' ];
			case 'trashed_comments':
				return [ "{$wpdb->comments} t", "t.comment_approved = 'trash'", [], 't.comment_ID', 't.comment_author', 't.comment_date_gmt', 'comment' ];
			case 'pending_comments':
				return [ "{$wpdb->comments} t", "t.comment_approved = '0'", [], 't.comment_ID', 't.comment_author', 't.comment_date_gmt', 'comment' ];
			case 'pingbacks':
				return [ "{$wpdb->comments} t", "t.comment_type IN ('pingback','trackback')", [], 't.comment_ID', 't.comment_author_url', 't.comment_date_gmt', 'comment' ];
			case 'orphan_postmeta':
				return [ "{$wpdb->postmeta} t LEFT JOIN {$wpdb->posts} p ON p.ID = t.post_id", 'p.ID IS NULL', [], 't.meta_id', 't.meta_key', '', "{$wpdb->postmeta}|meta_id" ];
			case 'orphan_commentmeta':
				return [ "{$wpdb->commentmeta} t LEFT JOIN {$wpdb->comments} p ON p.comment_ID = t.comment_id", 'p.comment_ID IS NULL', [], 't.meta_id', 't.meta_key', '', "{$wpdb->commentmeta}|meta_id" ];
			case 'orphan_termmeta':
				return [ "{$wpdb->termmeta} t LEFT JOIN {$wpdb->terms} p ON p.term_id = t.term_id", 'p.term_id IS NULL', [], 't.meta_id', 't.meta_key', '', "{$wpdb->termmeta}|meta_id" ];
			case 'orphan_usermeta':
				return [ "{$wpdb->usermeta} t LEFT JOIN {$wpdb->users} p ON p.ID = t.user_id", 'p.ID IS NULL', [], 't.umeta_id', 't.meta_key', '', "{$wpdb->usermeta}|umeta_id" ];
			case 'duplicate_postmeta':
				return [ "{$wpdb->postmeta} t INNER JOIN {$wpdb->postmeta} d ON d.post_id = t.post_id AND d.meta_key = t.meta_key AND d.meta_value = t.meta_value AND d.meta_id < t.meta_id", '1=1', [], 'DISTINCT t.meta_id', 't.meta_key', '', "{$wpdb->postmeta}|meta_id" ];
			case 'duplicate_usermeta':
				return [ "{$wpdb->usermeta} t INNER JOIN {$wpdb->usermeta} d ON d.user_id = t.user_id AND d.meta_key = t.meta_key AND d.meta_value = t.meta_value AND d.umeta_id < t.umeta_id", '1=1', [], 'DISTINCT t.umeta_id', 't.meta_key', '', "{$wpdb->usermeta}|umeta_id" ];
			case 'orphan_term_rel':
				// Only taxonomies attached to post types: a taxonomy registered for
				// users or links stores non-post ids in object_id.
				$taxonomies = [];
				foreach ( get_taxonomies( [], 'objects' ) as $tax ) {
					if ( $tax->object_type && ! array_diff( (array) $tax->object_type, get_post_types() ) ) {
						$taxonomies[] = $tax->name;
					}
				}
				if ( ! $taxonomies ) {
					return null;
				}
				return [
					"{$wpdb->term_relationships} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = t.term_taxonomy_id LEFT JOIN {$wpdb->posts} p ON p.ID = t.object_id",
					'p.ID IS NULL AND tt.taxonomy IN (' . implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) ) . ')',
					$taxonomies,
					"CONCAT(t.object_id, ':', t.term_taxonomy_id)",
					'tt.taxonomy',
					'',
					'term_rel',
				];
			case 'oembed_cache':
				return [ "{$wpdb->postmeta} t", 't.meta_key LIKE %s', [ $wpdb->esc_like( '_oembed_' ) . '%' ], 't.meta_id', 't.meta_key', '', "{$wpdb->postmeta}|meta_id" ];
			case 'action_scheduler':
				return [ "{$wpdb->prefix}actionscheduler_actions t", "t.status IN ('complete','failed','canceled')", [], 't.action_id', "CONCAT(t.hook, ' (', t.status, ')')", 't.scheduled_date_gmt', 'action_scheduler' ];
		}
		return null;
	}

	/**
	 * WHERE clause + params for a type, with the "older than N days" rule applied.
	 *
	 * @param array $spec Spec from spec().
	 * @param int   $days 0 = every matching row.
	 * @return array [ where sql, params ]
	 */
	private function where( array $spec, $days ) {
		$where  = $spec[1];
		$params = $spec[2];
		if ( $days > 0 && '' !== $spec[5] ) {
			$where   .= " AND {$spec[5]} < %s";
			$params[] = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		}
		return [ $where, $params ];
	}

	/**
	 * Run a prepared read with an optional params list (prepare() rejects an empty one).
	 *
	 * @param string $method wpdb method: get_var | get_col | get_results.
	 * @param string $sql    SQL with placeholders.
	 * @param array  $params Placeholder values.
	 * @return mixed
	 */
	private function read( $method, $sql, array $params ) {
		global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is assembled from spec() literals; every value is a placeholder
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}
		return 'get_results' === $method ? $wpdb->get_results( $sql, ARRAY_A ) : $wpdb->$method( $sql );
        // phpcs:enable
	}

	/**
	 * Rows a type currently matches.
	 *
	 * @param string $type Type id.
	 * @param int    $days Older-than rule (0 = all).
	 * @return int
	 */
	public function count( $type, $days = 0 ) {
		if ( 'expired_transients' === $type ) {
			return ( new OptimizeService( $this->activity ) )->count_expired_transients();
		}
		$spec = $this->spec( $type );
		if ( ! $spec ) {
			return 0;
		}
		list( $where, $params ) = $this->where( $spec, (int) $days );
		$count                  = 0 === strpos( $spec[3], 'DISTINCT ' ) ? "COUNT({$spec[3]})" : 'COUNT(*)';
		return (int) $this->read( 'get_var', "SELECT $count FROM {$spec[0]} WHERE $where", $params );
	}

	/**
	 * The first rows a clean would delete, for the Preview dialog.
	 *
	 * @param string $type  Type id.
	 * @param int    $days  Older-than rule.
	 * @param int    $limit Rows to return (1–100).
	 * @return array { total, items: [ { id, label, date } ] }
	 */
	public function preview( $type, $days = 0, $limit = 50 ) {
		$limit = max( 1, min( 100, (int) $limit ) );
		$spec  = $this->spec( $type );
		if ( ! $spec ) {
			return [
				'total' => $this->count( $type, $days ),
				'items' => [],
			];
		}
		list( $where, $params ) = $this->where( $spec, (int) $days );
		$date                   = '' !== $spec[5] ? $spec[5] : "''";
		$rows                   = $this->read(
			'get_results',
			"SELECT {$spec[3]} AS id, {$spec[4]} AS label, $date AS date FROM {$spec[0]} WHERE $where ORDER BY 1 DESC LIMIT $limit",
			$params
		);
		return [
			'total' => $this->count( $type, $days ),
			'items' => $rows ? $rows : [],
		];
	}

	/**
	 * Delete a type's rows in batches until done or the time budget is spent.
	 *
	 * @param string $type Type id.
	 * @param int    $days Older-than rule.
	 * @return array { deleted, remaining }
	 */
	public function clean( $type, $days = 0 ) {
		$days    = (int) $days;
		$deleted = 0;

		if ( 'expired_transients' === $type ) {
			$before  = $this->count( $type );
			$deleted = $before > 0 ? $before : 0;
			( new OptimizeService( $this->activity ) )->clean_transients( 'expired' );
			return [
				'deleted'   => $deleted,
				'remaining' => $this->count( $type ),
			];
		}

		$spec = $this->spec( $type );
		if ( ! $spec || ! isset( $this->types()[ $type ] ) ) {
			return [
				'deleted'   => 0,
				'remaining' => 0,
			];
		}
		list( $where, $params ) = $this->where( $spec, $days );
		$started                = time();

		do {
			$ids = (array) $this->read( 'get_col', "SELECT {$spec[3]} FROM {$spec[0]} WHERE $where LIMIT " . self::BATCH, $params );
			if ( ! $ids ) {
				break;
			}
			$done     = $this->delete_batch( $spec[6], $ids );
			$deleted += $done;
			// A batch that deletes nothing (another plugin vetoed it) would loop forever.
		} while ( $done > 0 && ( time() - $started ) < self::TIME_BUDGET );

		if ( $deleted > 0 ) {
			$this->activity->record( 'cleanup', $type . '|' . $deleted );
		}

		return [
			'deleted'   => $deleted,
			'remaining' => $this->count( $type, $days ),
		];
	}

	/**
	 * Delete one batch of ids with the type's delete strategy.
	 *
	 * @param string $how post | revision | comment | term_rel | action_scheduler | "{table}|{pk}".
	 * @param array  $ids Row ids.
	 * @return int Rows deleted.
	 */
	private function delete_batch( $how, array $ids ) {
		global $wpdb;
		$done = 0;

		if ( in_array( $how, [ 'post', 'revision', 'comment' ], true ) ) {
			foreach ( $ids as $id ) {
				if ( 'revision' === $how ) {
					$ok = wp_delete_post_revision( (int) $id );
				} elseif ( 'post' === $how ) {
					$ok = wp_delete_post( (int) $id, true );
				} else {
					$ok = wp_delete_comment( (int) $id, true );
				}
				$done += ( $ok && ! is_wp_error( $ok ) ) ? 1 : 0;
			}
			return $done;
		}

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/column names come from spec() literals; ids are integers from our own SELECT, bound as placeholders
		if ( 'term_rel' === $how ) {
			foreach ( $ids as $pair ) {
				list( $object_id, $tt_id ) = array_map( 'intval', explode( ':', $pair ) );
				$done                     += (int) $wpdb->delete(
					$wpdb->term_relationships,
					[
						'object_id'        => $object_id,
						'term_taxonomy_id' => $tt_id,
					],
					[ '%d', '%d' ]
				);
			}
			return $done;
		}

		$ids = array_map( 'intval', $ids );
		$in  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		if ( 'action_scheduler' === $how ) {
			$logs = $wpdb->prefix . 'actionscheduler_logs';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $logs ) ) ) === $logs ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$logs} WHERE action_id IN ($in)", $ids ) );
			}
			return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}actionscheduler_actions WHERE action_id IN ($in)", $ids ) );
		}

		list( $table, $pk ) = explode( '|', $how );

		// Meta rows are deleted directly, so drop the cached meta of the objects
		// they belong to — only those, never the whole object cache.
		$meta   = [
			$wpdb->postmeta    => [ 'post_id', 'post_meta' ],
			$wpdb->commentmeta => [ 'comment_id', 'comment_meta' ],
			$wpdb->termmeta    => [ 'term_id', 'term_meta' ],
			$wpdb->usermeta    => [ 'user_id', 'user_meta' ],
		];
		$owners = isset( $meta[ $table ] )
			? (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT {$meta[ $table ][0]} FROM {$table} WHERE {$pk} IN ($in)", $ids ) )
			: [];

		$done = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$pk} IN ($in)", $ids ) );
		foreach ( $owners as $owner_id ) {
			wp_cache_delete( (int) $owner_id, $meta[ $table ][1] );
		}
		return $done;
        // phpcs:enable
	}

	/**
	 * Every type with its live count and schedule, for the Cleanup screen.
	 *
	 * @return array
	 */
	public function overview() {
		$schedules = $this->schedules();
		$items     = [];
		foreach ( $this->types() as $id => $type ) {
			$schedule = isset( $schedules[ $id ] ) ? $schedules[ $id ] : [
				'frequency' => '',
				'days'      => 0,
			];
			$items[]  = $type + [
				'count'     => $this->count( $id ),
				'frequency' => $schedule['frequency'],
				'days'      => $schedule['days'],
			];
		}
		return $items;
	}

	/**
	 * Total rows every schedulable type would remove (Dashboard metric).
	 *
	 * @return int
	 */
	public function total_cleanable() {
		$total = 0;
		foreach ( $this->types() as $id => $type ) {
			if ( $type['schedulable'] && 'pending_comments' !== $id ) {
				$total += $this->count( $id );
			}
		}
		return $total;
	}

	/**
	 * Saved schedules: type => [ frequency, days ].
	 *
	 * @return array
	 */
	public function schedules() {
		$stored = ( new SettingsService() )->get( 'cleanup_schedules' );
		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * Validate and save schedules, then re-register the cron events.
	 *
	 * @param array $input type => [ frequency, days ].
	 * @return array Saved schedules.
	 */
	public function save_schedules( array $input ) {
		$types = $this->types();
		$clean = [];
		foreach ( $input as $type => $row ) {
			$frequency = isset( $row['frequency'] ) ? (string) $row['frequency'] : '';
			if ( ! isset( $types[ $type ] ) || ! $types[ $type ]['schedulable'] || ! in_array( $frequency, self::FREQUENCIES, true ) ) {
				continue;
			}
			$clean[ $type ] = [
				'frequency' => $frequency,
				'days'      => max( 0, min( 3650, isset( $row['days'] ) ? (int) $row['days'] : 0 ) ),
			];
		}
		( new SettingsService() )->update( [ 'cleanup_schedules' => $clean ] );
		self::sync_cron( $clean, true );
		return $clean;
	}

	/**
	 * Make the scheduled events match the saved schedules.
	 *
	 * @param array $schedules Saved schedules.
	 * @param bool  $reset     Drop every existing event first (after a save).
	 * @return void
	 */
	public static function sync_cron( array $schedules, $reset = false ) {
		if ( $reset ) {
			wp_unschedule_hook( self::CRON_HOOK );
		}
		foreach ( $schedules as $type => $row ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK, [ $type ] ) ) {
				wp_schedule_event( time() + MINUTE_IN_SECONDS, $row['frequency'], self::CRON_HOOK, [ $type ] );
			}
		}
	}

	/**
	 * Cron callback: run one scheduled type with its saved "older than" rule.
	 *
	 * @param string $type Type id.
	 * @return void
	 */
	public function run_scheduled( $type ) {
		$schedules = $this->schedules();
		if ( isset( $schedules[ $type ] ) ) {
			$this->clean( $type, $schedules[ $type ]['days'] );
		}
	}

	/**
	 * Add the monthly recurrence WordPress doesn't ship.
	 *
	 * @param array $schedules Cron schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		$schedules['nhrotm_monthly'] = [
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once a month', 'nhrrob-options-table-manager' ),
		];
		return $schedules;
	}
}
