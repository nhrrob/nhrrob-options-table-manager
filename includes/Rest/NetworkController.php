<?php
/**
 * REST endpoints backing the Network Admin screen (multisite only).
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Services\NetworkService;

/**
 * REST endpoints for the network view.
 *
 * GET    /nhrotm/v1/network/sites                 → every site with its figures.
 * GET    /nhrotm/v1/network/options               → network options / site transients.
 * DELETE /nhrotm/v1/network/options/{id}          → delete one (protected keys refused).
 * POST   /nhrotm/v1/network/clean-transients      → delete expired site transients.
 */
class NetworkController extends RestController {

	/**
	 * Network managers only — a site admin's manage_options is not enough.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return is_multisite() && current_user_can( 'manage_network_options' );
	}

	/**
	 * Register the network routes.
	 *
	 * @return void
	 */
	public function register() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/network/sites',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'sites' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/network/options',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'options' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'search' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'kind'   => [
						'type'    => 'string',
						'enum'    => [ '', 'options', 'transients' ],
						'default' => '',
					],
					'page'   => [
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/network/options/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_option' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'id' => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/network/clean-transients',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'clean_transients' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);
	}

	/**
	 * Every site with its figures.
	 *
	 * @return \WP_REST_Response
	 */
	public function sites() {
		return $this->ok( ( new NetworkService() )->sites() );
	}

	/**
	 * One page of network options.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response
	 */
	public function options( $request ) {
		return $this->ok( ( new NetworkService() )->options( $request->get_param( 'search' ), $request->get_param( 'kind' ), $request->get_param( 'page' ) ) );
	}

	/**
	 * Delete one network option.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_option( $request ) {
		if ( ! ( new NetworkService() )->delete( $request->get_param( 'id' ) ) ) {
			return $this->fail( 'nhrotm_network_protected', __( 'This network option is protected or could not be deleted.', 'nhrrob-options-table-manager' ) );
		}
		return $this->ok( [ 'deleted' => true ] );
	}

	/**
	 * Delete expired site transients.
	 *
	 * @return \WP_REST_Response
	 */
	public function clean_transients() {
		return $this->ok( [ 'deleted' => ( new NetworkService() )->clean_expired_transients() ] );
	}
}
