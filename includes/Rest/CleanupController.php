<?php
/**
 * REST endpoints backing the Cleanup screen.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Services\CleanupService;

/**
 * REST endpoints for database cleanup.
 *
 * GET  /nhrotm/v1/cleanup            → every cleanup type with its count and schedule.
 * GET  /nhrotm/v1/cleanup/preview    → the first rows a clean would delete.
 * POST /nhrotm/v1/cleanup/run        → delete one type's rows (batched; returns what is left).
 * POST /nhrotm/v1/cleanup/schedules  → save the per-type schedules.
 */
class CleanupController extends RestController {

	/**
	 * Cleanup service.
	 *
	 * @var CleanupService
	 */
	private $cleanup;

	/**
	 * Wire up the service.
	 *
	 * @param CleanupService|null $cleanup Injected for tests; defaults to a real instance.
	 */
	public function __construct( ?CleanupService $cleanup = null ) {
		$this->cleanup = $cleanup ? $cleanup : new CleanupService();
	}

	/**
	 * Register the cleanup routes.
	 *
	 * @return void
	 */
	public function register() {
		$type_args = [
			'type' => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_key',
			],
			'days' => [
				'type'              => 'integer',
				'default'           => 0,
				'minimum'           => 0,
				'maximum'           => 3650,
				'sanitize_callback' => 'absint',
			],
		];

		register_rest_route(
			self::NAMESPACE_V1,
			'/cleanup',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'overview' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/cleanup/preview',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'preview' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => $type_args,
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/cleanup/run',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'run' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => $type_args,
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/cleanup/schedules',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_schedules' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'schedules' => [
						'type'     => 'object',
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * Every cleanup type with its live count and schedule.
	 *
	 * @return \WP_REST_Response
	 */
	public function overview() {
		return $this->ok(
			[
				'items'       => $this->cleanup->overview(),
				'frequencies' => CleanupService::FREQUENCIES,
			]
		);
	}

	/**
	 * Reject a type this site (or this user) doesn't offer.
	 *
	 * @param string $type Type id.
	 * @return \WP_Error|null
	 */
	private function unknown_type( $type ) {
		return isset( $this->cleanup->types()[ $type ] ) ? null : $this->fail( 'nhrotm_cleanup_unknown_type', __( 'Unknown cleanup type.', 'nhrrob-options-table-manager' ), 404 );
	}

	/**
	 * The first rows a clean would delete.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview( $request ) {
		$error = $this->unknown_type( $request->get_param( 'type' ) );
		return $error ? $error : $this->ok( $this->cleanup->preview( $request->get_param( 'type' ), $request->get_param( 'days' ) ) );
	}

	/**
	 * Delete one type's rows.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run( $request ) {
		$error = $this->unknown_type( $request->get_param( 'type' ) );
		return $error ? $error : $this->ok( $this->cleanup->clean( $request->get_param( 'type' ), $request->get_param( 'days' ) ) );
	}

	/**
	 * Save the per-type schedules.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_schedules( $request ) {
		return $this->ok( $this->cleanup->save_schedules( (array) $request->get_param( 'schedules' ) ) );
	}
}
