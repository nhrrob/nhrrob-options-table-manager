<?php
/**
 * Registers the plugin's abilities with the WordPress Abilities API.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Services\ActivityService;
use Nhrotm\OptionsTableManager\Services\BrowseService;
use Nhrotm\OptionsTableManager\Services\CleanupService;
use Nhrotm\OptionsTableManager\Services\CronService;
use Nhrotm\OptionsTableManager\Services\HealthService;
use Nhrotm\OptionsTableManager\Services\OptimizeService;
use Nhrotm\OptionsTableManager\Services\TablesService;
use Nhrotm\OptionsTableManager\Services\ToolsService;

/**
 * What AI agents and MCP clients may do with the plugin (WordPress 6.9+).
 *
 * Every ability is a thin wrapper over a service the admin app already uses,
 * behind the same manage_options gate as the REST API. Two rules hold for the
 * whole list and are pinned by tests/AbilitiesTest.php:
 *
 * - No ability returns an option or meta value: values often hold API keys,
 *   and whatever an ability returns is sent to the agent's AI provider.
 * - Nothing irreversible is offered: no table empty/drop, live search and
 *   replace, import, value edits, cron deletes or settings changes.
 *
 * The two hooks below only fire when something asks for the abilities
 * registry, so a visitor request pays nothing for this class.
 */
class Abilities {

	const CATEGORY = 'nhrotm';

	/**
	 * Wire hooks. Called once from Bootstrap::init().
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register' ] );
	}

	/**
	 * Register the category every ability below belongs to.
	 *
	 * @return void
	 */
	public function register_category() {
		// The hook only exists on 6.9+; the check is for Plugin Check ("Requires at least" is 6.0).
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				self::CATEGORY,
				[
					'label'       => __( 'Database Cleaner', 'nhrrob-options-table-manager' ),
					'description' => __( 'Inspect, clean and optimize the WordPress database.', 'nhrrob-options-table-manager' ),
				]
			);
		}
	}

	/**
	 * Register every ability.
	 *
	 * @return void
	 */
	public function register() {
		if ( function_exists( 'wp_register_ability' ) ) {
			foreach ( $this->definitions() as $name => $args ) {
				wp_register_ability( $name, $args );
			}
		}
	}

	/**
	 * Capability gate shared by every ability. Default: manage_options.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Every ability: name => wp_register_ability() arguments.
	 *
	 * @return array
	 */
	public function definitions() {
		$type_input = [
			'type'        => 'string',
			'description' => __( 'Cleanup type id, as returned by nhrotm/list-cleanup-types.', 'nhrrob-options-table-manager' ),
		];
		$days_input = [
			'type'        => 'integer',
			'description' => __( 'Only rows older than this many days (types with a date). 0 means every row.', 'nhrrob-options-table-manager' ),
			'minimum'     => 0,
			'maximum'     => 3650,
		];
		$page_input = [
			'page'     => [
				'type'    => 'integer',
				'minimum' => 1,
			],
			'per_page' => [
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100,
			],
		];

		$abilities = [
			'nhrotm/get-health'              => [
				'label'            => __( 'Get database health', 'nhrrob-options-table-manager' ),
				'description'      => __( 'Returns the database health score (0-100), the raw metrics behind it (autoload size, option count, expired transients, orphaned options, cleanable rows, database size) and the recommended next actions. Start here.', 'nhrrob-options-table-manager' ),
				'execute_callback' => [ $this, 'get_health' ],
				'output_schema'    => [ 'type' => 'object' ],
				'meta'             => $this->meta( true ),
			],
			'nhrotm/list-cleanup-types'      => [
				'label'            => __( 'List cleanup types', 'nhrrob-options-table-manager' ),
				'description'      => __( 'Lists every kind of unused data this site can clean (revisions, auto-drafts, spam, expired transients, orphaned meta and more) with the number of rows each one currently matches and its schedule.', 'nhrrob-options-table-manager' ),
				'execute_callback' => [ $this, 'list_cleanup_types' ],
				'output_schema'    => [ 'type' => 'array' ],
				'meta'             => $this->meta( true ),
			],
			'nhrotm/preview-cleanup'         => [
				'label'            => __( 'Preview a cleanup', 'nhrrob-options-table-manager' ),
				'description'      => __( 'Shows the total and the first 50 rows one cleanup type would delete, without deleting anything.', 'nhrrob-options-table-manager' ),
				'execute_callback' => [ $this, 'preview_cleanup' ],
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'type' => $type_input,
						'days' => $days_input,
					],
					'required'   => [ 'type' ],
				],
				'output_schema'    => [ 'type' => 'object' ],
				'meta'             => $this->meta( true ),
			],
			'nhrotm/get-autoload-report'     => [
				'label'            => __( 'Get autoload report', 'nhrrob-options-table-manager' ),
				'description'      => __( 'Returns the total autoload size, the heaviest autoloaded options (name, size, owner, whether protected), groups of options left behind by removed plugins, and the expired transient count. Names and sizes only, never values.', 'nhrrob-options-table-manager' ),
				'execute_callback' => [ $this, 'get_autoload_report' ],
				'output_schema'    => [ 'type' => 'object' ],
				'meta'             => $this->meta( true ),
			],
			'nhrotm/list-tables'             => [
				'label'            => __( 'List database tables', 'nhrrob-options-table-manager' ),
				'description'      => __( 'Lists every table of this site with its row count, size and overhead in bytes, storage engine and owner (core, plugin, leftover or unknown).', 'nhrrob-options-table-manager' ),
				'execute_callback' => [ $this, 'list_tables' ],
				'output_schema'    => [ 'type' => 'array' ],
				'meta'             => $this->meta( true ),
			],
			'nhrotm/list-cron-events'        => [
				'label'            => __( 'List cron events', 'nhrrob-options-table-manager' ),
				'description'      => __( 'Lists every scheduled WP-Cron event with its hook, next run, recurrence and owner. "orphaned" is only a hint that no installed plugin seems to own the hook.', 'nhrrob-options-table-manager' ),
				'execute_callback' => [ $this, 'list_cron_events' ],
				'output_schema'    => [ 'type' => 'array' ],
				'meta'             => $this->meta( true ),
			],
			'nhrotm/search-options'          => [
				'label'            => __( 'Search options', 'nhrrob-options-table-manager' ),
				'description'      => __( 'Searches wp_options by name, largest first. Returns each option\'s name, size, autoload flag, guessed owner and whether it is protected. Never returns option values.', 'nhrrob-options-table-manager' ),
				'execute_callback' => [ $this, 'search_options' ],
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'search' => [
							'type'        => 'string',
							'description' => __( 'Text the option name must contain. Empty lists every option.', 'nhrrob-options-table-manager' ),
						],
					] + $page_input,
					'default'    => [],
				],
				'output_schema'    => [ 'type' => 'object' ],
				'meta'             => $this->meta( true ),
			],
			'nhrotm/list-activity'           => [
				'label'            => __( 'List recent activity', 'nhrrob-options-table-manager' ),
				'description'      => __( 'Returns the log of changes made through this plugin (who, when, which action, which option), newest first.', 'nhrrob-options-table-manager' ),
				'execute_callback' => [ $this, 'list_activity' ],
				'input_schema'     => [
					'type'       => 'object',
					'properties' => $page_input,
					'default'    => [],
				],
				'output_schema'    => [ 'type' => 'object' ],
				'meta'             => $this->meta( true ),
			],
			'nhrotm/run-cleanup'             => [
				'label'            => __( 'Run a cleanup', 'nhrrob-options-table-manager' ),
				'description'      => __( 'Deletes the rows one cleanup type matches. dry_run defaults to true and only reports how many rows would be deleted; pass dry_run false to delete them. Deleted rows cannot be restored. A large cleanup stops after 15 seconds: call again while "remaining" is above 0.', 'nhrrob-options-table-manager' ),
				'execute_callback' => [ $this, 'run_cleanup' ],
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'type'    => $type_input,
						'days'    => $days_input,
						'dry_run' => [
							'type'        => 'boolean',
							'description' => __( 'true (the default) counts the rows without deleting them.', 'nhrrob-options-table-manager' ),
						],
					],
					'required'   => [ 'type' ],
				],
				'output_schema'    => [ 'type' => 'object' ],
				'meta'             => $this->meta( false, true ),
			],
			'nhrotm/disable-option-autoload' => [
				'label'            => __( 'Disable autoload for an option', 'nhrrob-options-table-manager' ),
				'description'      => __( 'Stops one option from loading on every request. The option and its value are kept. Protected core options are refused.', 'nhrrob-options-table-manager' ),
				'execute_callback' => [ $this, 'disable_option_autoload' ],
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'option' => [
							'type'        => 'string',
							'description' => __( 'Exact option name.', 'nhrrob-options-table-manager' ),
						],
					],
					'required'   => [ 'option' ],
				],
				'output_schema'    => [ 'type' => 'object' ],
				'meta'             => $this->meta( false, false, true ),
			],
			'nhrotm/optimize-table'          => [
				'label'            => __( 'Optimize a table', 'nhrrob-options-table-manager' ),
				'description'      => __( 'Runs OPTIMIZE TABLE on one table of this site to reclaim overhead. No rows are changed.', 'nhrrob-options-table-manager' ),
				'execute_callback' => [ $this, 'optimize_table' ],
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'table' => [
							'type'        => 'string',
							'description' => __( 'Exact table name, as returned by nhrotm/list-tables.', 'nhrrob-options-table-manager' ),
						],
					],
					'required'   => [ 'table' ],
				],
				'output_schema'    => [ 'type' => 'object' ],
				'meta'             => $this->meta( false, false, true ),
			],
			'nhrotm/create-snapshot'         => [
				'label'            => __( 'Create an options snapshot', 'nhrrob-options-table-manager' ),
				'description'      => __( 'Saves a restorable snapshot of the options table. Take one before a cleanup. Only the 15 newest snapshots are kept.', 'nhrrob-options-table-manager' ),
				'execute_callback' => [ $this, 'create_snapshot' ],
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'label' => [
							'type'        => 'string',
							'description' => __( 'Optional label shown in the snapshot list.', 'nhrrob-options-table-manager' ),
						],
					],
					'default'    => [],
				],
				'output_schema'    => [ 'type' => 'object' ],
				'meta'             => $this->meta( false ),
			],
		];

		foreach ( $abilities as $name => $args ) {
			$abilities[ $name ] = $args + [
				'category'            => self::CATEGORY,
				'permission_callback' => [ $this, 'can_manage' ],
			];
		}
		return $abilities;
	}

	/**
	 * Ability meta: behavior annotations plus exposure to REST and MCP clients.
	 *
	 * @param bool $is_readonly Changes nothing.
	 * @param bool $destructive Deletes data that cannot be restored.
	 * @param bool $idempotent  Repeating the call has no further effect.
	 * @return array
	 */
	private function meta( $is_readonly, $destructive = false, $idempotent = false ) {
		return [
			'annotations'  => [
				'readonly'    => $is_readonly,
				'destructive' => $destructive,
				'idempotent'  => $is_readonly || $idempotent,
			],
			'public'       => true,
			// WordPress 6.9 has no `public` flag; the MCP Adapter reads `mcp.public`.
			'show_in_rest' => true,
			'mcp'          => [ 'public' => true ],
		];
	}

	/**
	 * A cleanup type id this site (and this user) offers, or an error.
	 *
	 * @param mixed $input Ability input.
	 * @return string|\WP_Error
	 */
	private function cleanup_type( $input ) {
		$type = sanitize_key( isset( $input['type'] ) ? $input['type'] : '' );
		if ( ! isset( ( new CleanupService() )->types()[ $type ] ) ) {
			return new \WP_Error( 'nhrotm_cleanup_unknown_type', __( 'Unknown cleanup type.', 'nhrrob-options-table-manager' ) );
		}
		return $type;
	}

	/**
	 * The "older than" rule from the input, within the range REST allows.
	 *
	 * @param mixed $input Ability input.
	 * @return int
	 */
	private function days( $input ) {
		return min( 3650, absint( isset( $input['days'] ) ? $input['days'] : 0 ) );
	}

	/**
	 * Health score, metrics and recommendations.
	 *
	 * @return array
	 */
	public function get_health() {
		return array_intersect_key(
			( new HealthService() )->summary(),
			array_flip( [ 'score', 'headline', 'description', 'metrics', 'recommendations' ] )
		);
	}

	/**
	 * Every cleanup type with its live count and schedule.
	 *
	 * @return array
	 */
	public function list_cleanup_types() {
		return ( new CleanupService() )->overview();
	}

	/**
	 * The first rows a cleanup would delete.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public function preview_cleanup( $input ) {
		$type = $this->cleanup_type( $input );
		return is_wp_error( $type ) ? $type : ( new CleanupService() )->preview( $type, $this->days( $input ) );
	}

	/**
	 * Autoload size, heaviest autoloaded options and orphaned option groups.
	 *
	 * @return array
	 */
	public function get_autoload_report() {
		return array_intersect_key(
			( new OptimizeService() )->overview(),
			array_flip( [ 'score', 'autoload_total', 'autoload', 'usage_tracking', 'orphans', 'expired_transients' ] )
		);
	}

	/**
	 * Every table of this site.
	 *
	 * @return array
	 */
	public function list_tables() {
		return ( new TablesService() )->all();
	}

	/**
	 * Every scheduled cron event, without its arguments (they can hold data).
	 *
	 * @return array
	 */
	public function list_cron_events() {
		return array_map(
			function ( $event ) {
				unset( $event['args'] );
				return $event;
			},
			( new CronService() )->all()
		);
	}

	/**
	 * Options matching a name search, without their values.
	 *
	 * @param mixed $input Ability input.
	 * @return array
	 */
	public function search_options( $input ) {
		$result = ( new BrowseService() )->query(
			'options',
			isset( $input['page'] ) ? $input['page'] : 1,
			isset( $input['per_page'] ) ? $input['per_page'] : 20,
			sanitize_text_field( isset( $input['search'] ) ? $input['search'] : '' )
		);
		foreach ( $result['items'] as $i => $item ) {
			unset( $result['items'][ $i ]['preview'] );
		}
		unset( $result['owners'] );
		return $result;
	}

	/**
	 * One page of the change log.
	 *
	 * @param mixed $input Ability input.
	 * @return array
	 */
	public function list_activity( $input ) {
		return ( new ActivityService() )->paged(
			isset( $input['page'] ) ? $input['page'] : 1,
			isset( $input['per_page'] ) ? $input['per_page'] : 20
		);
	}

	/**
	 * Count (dry run, the default) or delete one cleanup type's rows.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public function run_cleanup( $input ) {
		$type = $this->cleanup_type( $input );
		if ( is_wp_error( $type ) ) {
			return $type;
		}
		$cleanup = new CleanupService();
		$days    = $this->days( $input );
		if ( ! isset( $input['dry_run'] ) || false !== $input['dry_run'] ) {
			return [
				'dry_run'   => true,
				'deleted'   => 0,
				'remaining' => $cleanup->count( $type, $days ),
			];
		}
		return [ 'dry_run' => false ] + $cleanup->clean( $type, $days );
	}

	/**
	 * Turn off autoload for one option.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public function disable_option_autoload( $input ) {
		if ( ! ( new OptimizeService() )->disable_autoload( sanitize_text_field( $input['option'] ) ) ) {
			return new \WP_Error( 'nhrotm_protected', __( 'That option is protected and cannot be changed.', 'nhrrob-options-table-manager' ) );
		}
		return [ 'disabled' => true ];
	}

	/**
	 * Optimize one table.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public function optimize_table( $input ) {
		$result = ( new TablesService() )->run( sanitize_text_field( $input['table'] ), 'optimize' );
		if ( true !== $result ) {
			return new \WP_Error( 'nhrotm_table_action_failed', $result );
		}
		return [ 'optimized' => true ];
	}

	/**
	 * Create a snapshot of the options table.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public function create_snapshot( $input ) {
		$id = ( new ToolsService() )->create_backup( sanitize_text_field( isset( $input['label'] ) ? $input['label'] : '' ) );
		if ( ! $id ) {
			return new \WP_Error( 'nhrotm_snapshot_failed', __( 'The snapshot could not be created.', 'nhrrob-options-table-manager' ) );
		}
		return [ 'id' => (int) $id ];
	}
}
