<?php
/**
 * WP-CLI commands for inspecting and pruning options.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Cli;

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

/**
 * Manage options, cleanups and tables via WP-CLI.
 */
class CliCommands extends \WP_CLI_Command {

	/**
	 * List top autoloaded options.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Number of options to show. Default is 20.
	 *
	 * [--search=<search>]
	 * : Search term for option name.
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv, yaml). Default is table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nhrotm list --limit=10
	 *     wp nhrotm list --search=woocommerce_
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments: limit, search, format.
	 */
	public function list( $args, $assoc_args ) {

		global $wpdb;

		$limit  = isset( $assoc_args['limit'] ) ? intval( $assoc_args['limit'] ) : 20;
		$search = isset( $assoc_args['search'] ) ? sanitize_text_field( $assoc_args['search'] ) : '';
		$format = isset( $assoc_args['format'] ) ? sanitize_text_field( $assoc_args['format'] ) : 'table';

		$query = "SELECT option_name, LENGTH(option_value) as size, autoload FROM {$wpdb->options}";

		$where_clauses = [ '1=1' ];
		$query_args    = [];

		// Build SQL and execute based on search condition.
		if ( ! empty( $search ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic WHERE clause built safely
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, LENGTH(option_value) as size, autoload 
                FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                ORDER BY size DESC 
                LIMIT %d",
					'%' . $wpdb->esc_like( $search ) . '%',
					$limit
				),
				ARRAY_A
			);
		} else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- No dynamic WHERE clause
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, LENGTH(option_value) as size, autoload 
                FROM {$wpdb->options} 
                WHERE 1=1 
                ORDER BY size DESC 
                LIMIT %d",
					$limit
				),
				ARRAY_A
			);
		}

		// Format size.
		foreach ( $results as &$row ) {
			$row['size'] = \size_format( $row['size'] );
		}

		\WP_CLI\Utils\format_items( $format, $results, [ 'option_name', 'size', 'autoload' ] );
	}

	/**
	 * Delete options by prefix.
	 *
	 * ## OPTIONS
	 *
	 * <prefix>
	 * : The prefix of options to delete.
	 *
	 * [--dry-run]
	 * : Check what would be deleted without actually deleting.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nhrotm delete subheading_ --dry-run
	 *     wp nhrotm delete subheading_
	 *
	 * @param array $args       Positional arguments; $args[0] is the option name prefix.
	 * @param array $assoc_args Associative arguments: dry-run, yes.
	 */
	public function delete( $args, $assoc_args ) {

		global $wpdb;

		$prefix  = $args[0];
		$dry_run = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run' );

		if ( empty( $prefix ) ) {
			\WP_CLI::error( 'Prefix is required.' );
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI command query
		$options = $wpdb->get_col(
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' )
		);

		if ( empty( $options ) ) {
			\WP_CLI::success( "No options found with prefix '$prefix'." );
			return;
		}

		$count = count( $options );
		\WP_CLI::log( "Found $count options with prefix '$prefix'." );

		if ( $dry_run ) {
			foreach ( $options as $opt ) {
				\WP_CLI::log( "- $opt" );
			}
			\WP_CLI::success( 'Dry run complete. No options deleted.' );
			return;
		}

		\WP_CLI::confirm( "Are you sure you want to delete these $count options?", $assoc_args );

		$deleted_count = 0;
		foreach ( $options as $opt ) {
			if ( delete_option( $opt ) ) {
				++$deleted_count;
			}
		}

		\WP_CLI::success( "Deleted $deleted_count options." );
	}

	/**
	 * Count or run database cleanups.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : list (every cleanup type with its row count) or run.
	 *
	 * [<type>]
	 * : Cleanup type id from `cleanup list` (required for run).
	 *
	 * [--older-than=<days>]
	 * : Only rows older than this many days (types with a date).
	 *
	 * [--dry-run]
	 * : Report what would be deleted without deleting.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nhrotm cleanup list
	 *     wp nhrotm cleanup run revisions --older-than=30
	 *     wp nhrotm cleanup run spam_comments --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function cleanup( $args, $assoc_args ) {
		$cleanup = new \Nhrotm\OptionsTableManager\Services\CleanupService();
		$types   = $cleanup->types();
		$action  = isset( $args[0] ) ? $args[0] : 'list';
		$days    = isset( $assoc_args['older-than'] ) ? max( 0, (int) $assoc_args['older-than'] ) : 0;

		if ( 'list' === $action ) {
			$rows = [];
			foreach ( $types as $id => $type ) {
				$rows[] = [
					'type'  => $id,
					'label' => $type['label'],
					'rows'  => $cleanup->count( $id, $days ),
				];
			}
			\WP_CLI\Utils\format_items( 'table', $rows, [ 'type', 'label', 'rows' ] );
			return;
		}

		$type = isset( $args[1] ) ? $args[1] : '';
		if ( 'run' !== $action || ! isset( $types[ $type ] ) ) {
			\WP_CLI::error( 'Usage: wp nhrotm cleanup run <type> [--older-than=<days>] [--dry-run]. See `wp nhrotm cleanup list` for the types.' );
		}
		if ( isset( $assoc_args['dry-run'] ) ) {
			\WP_CLI::success( sprintf( '%d rows would be deleted (%s).', $cleanup->count( $type, $days ), $types[ $type ]['label'] ) );
			return;
		}

		$deleted = 0;
		do {
			$result   = $cleanup->clean( $type, $days );
			$deleted += $result['deleted'];
		} while ( $result['deleted'] > 0 && $result['remaining'] > 0 );

		\WP_CLI::success( sprintf( '%d rows deleted (%s).', $deleted, $types[ $type ]['label'] ) );
	}

	/**
	 * List database tables or optimize them.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : list or optimize.
	 *
	 * [--all]
	 * : With optimize: every table, not only those with overhead.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nhrotm tables list
	 *     wp nhrotm tables optimize
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function tables( $args, $assoc_args ) {
		$tables = new \Nhrotm\OptionsTableManager\Services\TablesService();
		$rows   = $tables->all();
		$action = isset( $args[0] ) ? $args[0] : 'list';

		if ( 'optimize' === $action ) {
			$done = 0;
			foreach ( $rows as $row ) {
				if ( ( isset( $assoc_args['all'] ) || $row['overhead'] > 0 ) && true === $tables->run( $row['name'], 'optimize' ) ) {
					++$done;
				}
			}
			\WP_CLI::success( sprintf( '%d tables optimized.', $done ) );
			return;
		}

		\WP_CLI\Utils\format_items(
			'table',
			array_map(
				function ( $row ) {
					return [
						'table'    => $row['name'],
						'rows'     => $row['rows'],
						'size'     => size_format( $row['size'] ),
						'overhead' => $row['overhead'] ? size_format( $row['overhead'] ) : '-',
						'engine'   => $row['engine'],
						'owner'    => $row['owner'],
						'kind'     => $row['kind'],
					];
				},
				$rows
			),
			[ 'table', 'rows', 'size', 'overhead', 'engine', 'owner', 'kind' ]
		);
	}
}
