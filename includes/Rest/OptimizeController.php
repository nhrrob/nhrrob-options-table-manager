<?php
/**
 * REST endpoints backing the Optimize section.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Services\OptimizeService;

/**
 * REST endpoints for the Optimize section.
 *
 * GET  /nhrotm/v1/optimize                → overview (autoload, usage, orphans, cleanup)
 * POST /nhrotm/v1/optimize/disable-autoload
 * POST /nhrotm/v1/optimize/delete-orphans
 * POST /nhrotm/v1/optimize/reset-usage
 * POST /nhrotm/v1/optimize/clean-transients
 */
class OptimizeController extends RestController {

	/**
	 * Service performing the optimization actions and overview.
	 *
	 * @var OptimizeService
	 */
	private $optimize;

	/**
	 * Wire up the service backing the optimize endpoints.
	 *
	 * @param OptimizeService $optimize Performs the cleanup actions and builds the overview.
	 */
	public function __construct( OptimizeService $optimize ) {
		$this->optimize = $optimize;
	}

	/**
	 * Register the optimize routes.
	 *
	 * @return void
	 */
	public function register() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/optimize',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'overview' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/optimize/disable-autoload',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'disable_autoload' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'option' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/optimize/delete-orphans',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'delete_orphans' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'prefix' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/optimize/reset-usage',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reset_usage' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/optimize/clean-transients',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'clean_transients' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'scope' => [
						'type'    => 'string',
						'enum'    => [ 'expired', 'all' ],
						'default' => 'expired',
					],
				],
			]
		);
	}

	/**
	 * Return the autoload, usage, orphan and cleanup overview.
	 *
	 * @return \WP_REST_Response
	 */
	public function overview() {
		return $this->ok( $this->optimize->overview() );
	}

	/**
	 * Turn off autoload for a single option.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function disable_autoload( $request ) {
		$done = $this->optimize->disable_autoload( $request->get_param( 'option' ) );
		if ( ! $done ) {
			return $this->fail( 'nhrotm_protected', __( 'That option is protected and cannot be changed.', 'nhrrob-options-table-manager' ) );
		}
		return $this->ok( [ 'disabled' => true ] );
	}

	/**
	 * Delete every orphaned option sharing a given prefix.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_orphans( $request ) {
		try {
			$deleted = $this->optimize->delete_orphans( $request->get_param( 'prefix' ) );
		} catch ( \Exception $e ) {
			return $this->fail( 'nhrotm_orphan_error', $e->getMessage() );
		}
		return $this->ok( [ 'deleted' => $deleted ] );
	}

	/**
	 * Clear the recorded autoload usage-tracking data.
	 *
	 * @return \WP_REST_Response
	 */
	public function reset_usage() {
		$this->optimize->reset_usage();
		return $this->ok( [ 'reset' => true ] );
	}

	/**
	 * Delete transients matching the requested scope.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response
	 */
	public function clean_transients( $request ) {
		$deleted = $this->optimize->clean_transients( $request->get_param( 'scope' ) );
		return $this->ok( [ 'deleted' => $deleted ] );
	}
}
