<?php
/**
 * REST endpoints backing the Tools section.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Services\BrowseService;
use Nhrotm\OptionsTableManager\Services\CronService;
use Nhrotm\OptionsTableManager\Services\ToolsService;

/**
 * REST endpoints for the Tools section.
 *
 * GET    /nhrotm/v1/tools/backups
 * POST   /nhrotm/v1/tools/backups            (create)
 * POST   /nhrotm/v1/tools/backups/(id)/restore
 * DELETE /nhrotm/v1/tools/backups/(id)
 * POST   /nhrotm/v1/tools/search-replace
 * GET    /nhrotm/v1/tools/export
 */
class ToolsController extends RestController {

	/**
	 * Service performing the backup, search-replace and import/export work.
	 *
	 * @var ToolsService
	 */
	private $tools;

	/**
	 * Wire up the service backing the tools endpoints.
	 *
	 * @param ToolsService $tools Performs the backup, search-replace and import/export work.
	 */
	public function __construct( ToolsService $tools ) {
		$this->tools = $tools;
	}

	/**
	 * Register the tools routes.
	 *
	 * @return void
	 */
	public function register() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/tools/backups',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_backups' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_backup' ],
					'permission_callback' => [ $this, 'can_manage' ],
					'args'                => [
						'label' => [
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tools/backups/(?P<id>\d+)/restore',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'restore_backup' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'id' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tools/backups/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_backup' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'id' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tools/search-replace',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'search_replace' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'search'  => [
						'type'     => 'string',
						'required' => true,
					],
					'replace' => [
						'type'    => 'string',
						'default' => '',
					],
					'dry_run' => [
						'type'              => 'boolean',
						'default'           => true,
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tools/search-replace/count',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'search_count' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'search' => [
						'type'     => 'string',
						'required' => true,
					],
				],
			]
		);

		$event_args = [
			'hook'      => [
				'type'     => 'string',
				'required' => true,
			],
			'timestamp' => [
				'type'     => 'integer',
				'required' => true,
			],
			'sig'       => [
				'type'     => 'string',
				'required' => true,
			],
		];

		register_rest_route(
			self::NAMESPACE_V1,
			'/tools/cron',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'cron_events' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tools/cron/run',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cron_run' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => $event_args,
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tools/cron/delete',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cron_delete' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => $event_args + [
					'all' => [
						'type'              => 'boolean',
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tools/export',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'export' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'names' => [
						'type'    => 'array',
						'items'   => [ 'type' => 'string' ],
						'default' => [],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tools/export/search',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'export_search' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'search' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tools/import/preview',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'import_preview' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'json' => [
						'type'     => 'string',
						'required' => true,
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tools/import',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'import' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'json'      => [
						'type'     => 'string',
						'required' => true,
					],
					'overwrite' => [
						'type'              => 'boolean',
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
					'selected'  => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
				],
			]
		);
	}

	/**
	 * Return every stored snapshot.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_backups() {
		return $this->ok( $this->tools->list_backups() );
	}

	/**
	 * Take a new snapshot of the options table.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_backup( $request ) {
		$id = $this->tools->create_backup( $request->get_param( 'label' ) );
		if ( ! $id ) {
			return $this->fail( 'nhrotm_backup_failed', __( 'Could not create the snapshot.', 'nhrrob-options-table-manager' ) );
		}
		return $this->ok(
			[
				'id'      => $id,
				'backups' => $this->tools->list_backups(),
			]
		);
	}

	/**
	 * Re-apply every option stored in a snapshot.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function restore_backup( $request ) {
		try {
			$restored = $this->tools->restore_backup( $request->get_param( 'id' ) );
		} catch ( \Exception $e ) {
			return $this->fail( 'nhrotm_restore_failed', $e->getMessage() );
		}
		return $this->ok( [ 'restored' => $restored ] );
	}

	/**
	 * Permanently remove one snapshot.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response
	 */
	public function delete_backup( $request ) {
		return $this->ok( [ 'deleted' => $this->tools->delete_backup( $request->get_param( 'id' ) ) ] );
	}

	/**
	 * Run a site-wide search and replace across option values.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search_replace( $request ) {
		if ( ! $request->get_param( 'dry_run' ) && ! BrowseService::can_store_html() ) {
			return $this->raw_values_refused();
		}
		try {
			$result = $this->tools->search_replace(
				(string) $request->get_param( 'search' ),
				(string) $request->get_param( 'replace' ),
				(bool) $request->get_param( 'dry_run' )
			);
		} catch ( \Exception $e ) {
			return $this->fail( 'nhrotm_search_replace_failed', $e->getMessage() );
		}
		return $this->ok( $result );
	}

	/**
	 * Return the options table (or the named options) as a JSON export payload.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response
	 */
	public function export( $request ) {
		return $this->ok( $this->tools->export_options( (array) $request->get_param( 'names' ) ) );
	}

	/**
	 * Option names matching a search term (Export basket picker).
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response
	 */
	public function export_search( $request ) {
		return $this->ok( $this->tools->search_option_names( $request->get_param( 'search' ) ) );
	}

	/**
	 * Preview an import file against this site.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import_preview( $request ) {
		try {
			return $this->ok( $this->tools->preview_import( (string) $request->get_param( 'json' ) ) );
		} catch ( \Exception $e ) {
			return $this->fail( 'nhrotm_import_invalid', $e->getMessage() );
		}
	}

	/**
	 * Import options from a JSON export payload.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import( $request ) {
		if ( ! BrowseService::can_store_html() ) {
			return $this->raw_values_refused();
		}
		try {
			$result = $this->tools->import_options(
				(string) $request->get_param( 'json' ),
				(bool) $request->get_param( 'overwrite' ),
				$request->has_param( 'selected' ) ? (array) $request->get_param( 'selected' ) : null
			);
		} catch ( \Exception $e ) {
			return $this->fail( 'nhrotm_import_failed', $e->getMessage() );
		}
		return $this->ok( $result );
	}

	/**
	 * Import and a live Search & Replace write values exactly as given, HTML
	 * included, so they follow WordPress's `unfiltered_html` rule (a site
	 * admin on multisite, or DISALLOW_UNFILTERED_HTML, does not have it).
	 *
	 * @return \WP_Error
	 */
	private function raw_values_refused() {
		return $this->fail( 'nhrotm_unfiltered_html_required', __( 'This tool writes values exactly as given, including HTML, so it needs the permission to save unfiltered HTML. Ask a network administrator to run it.', 'nhrrob-options-table-manager' ), 403 );
	}

	/**
	 * Live match count for Search & Replace: options and occurrences.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response
	 */
	public function search_count( $request ) {
		$search = (string) $request->get_param( 'search' );
		if ( '' === $search ) {
			return $this->ok(
				[
					'options'     => 0,
					'occurrences' => 0,
				]
			);
		}
		$matches = ( new \Nhrotm\OptionsTableManager\Managers\SearchReplaceManager() )->preview_search( $search );
		return $this->ok(
			[
				'options'     => count( $matches ),
				'occurrences' => (int) array_sum( wp_list_pluck( $matches, 'occurrences' ) ),
			]
		);
	}

	/**
	 * Every scheduled event.
	 *
	 * @return \WP_REST_Response
	 */
	public function cron_events() {
		return $this->ok( ( new CronService() )->all() );
	}

	/**
	 * Run one scheduled event now.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cron_run( $request ) {
		$cron = new CronService();
		if ( ! $cron->run( (string) $request->get_param( 'hook' ), (int) $request->get_param( 'timestamp' ), (string) $request->get_param( 'sig' ) ) ) {
			return $this->fail( 'nhrotm_cron_missing', __( 'This event no longer exists.', 'nhrrob-options-table-manager' ), 404 );
		}
		return $this->ok( $cron->all() );
	}

	/**
	 * Delete one scheduled event (or every event of its hook).
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cron_delete( $request ) {
		$cron = new CronService();
		if ( ! $cron->delete( (string) $request->get_param( 'hook' ), (int) $request->get_param( 'timestamp' ), (string) $request->get_param( 'sig' ), (bool) $request->get_param( 'all' ) ) ) {
			return $this->fail( 'nhrotm_cron_protected', __( 'This event is protected or no longer exists.', 'nhrrob-options-table-manager' ) );
		}
		return $this->ok( $cron->all() );
	}
}
