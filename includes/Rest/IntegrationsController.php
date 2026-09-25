<?php
/**
 * REST endpoints backing the Integrations section.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Services\IntegrationsService;

/**
 * REST endpoints for the Integrations section.
 *
 * GET /nhrotm/v1/integrations         → available integrations
 * GET /nhrotm/v1/integrations/(slug)  → paginated rows for one table
 */
class IntegrationsController extends RestController {

	/**
	 * Service resolving detected integrations and their table rows.
	 *
	 * @var IntegrationsService
	 */
	private $integrations;

	/**
	 * Wire up the service backing the integrations endpoints.
	 *
	 * @param IntegrationsService $integrations Detects integrations and reads their tables.
	 */
	public function __construct( IntegrationsService $integrations ) {
		$this->integrations = $integrations;
	}

	/**
	 * Register the integrations routes.
	 *
	 * @return void
	 */
	public function register() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/integrations',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_available' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/integrations/(?P<slug>[a-z0-9_]+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rows' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'page'     => [
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					],
					'per_page' => [
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Return the integrations detected on this site.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_available() {
		return $this->ok( $this->integrations->available() );
	}

	/**
	 * Return one page of rows for a single integration's table.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response
	 */
	public function rows( $request ) {
		return $this->ok(
			$this->integrations->rows(
				$request->get_param( 'slug' ),
				$request->get_param( 'page' ),
				$request->get_param( 'per_page' )
			)
		);
	}
}
