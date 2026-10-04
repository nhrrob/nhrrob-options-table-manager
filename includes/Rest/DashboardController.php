<?php
/**
 * REST endpoints backing the dashboard screen.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Services\HealthService;
use Nhrotm\OptionsTableManager\Services\ActivityService;

/**
 * REST endpoints for the dashboard.
 *
 * GET /nhrotm/v1/dashboard          → score, headline, cards, recommendations.
 * GET /nhrotm/v1/dashboard/activity → paginated change feed ("View all").
 * POST /nhrotm/v1/dashboard/activity/{id}/restore → put an option back to the value in that entry.
 */
class DashboardController extends RestController {

	/**
	 * Health scorer supplying the dashboard summary.
	 *
	 * @var HealthService
	 */
	private $health;

	/**
	 * Activity feed reader supplying the change log.
	 *
	 * @var ActivityService
	 */
	private $activity;

	/**
	 * Wire up the services backing the dashboard endpoints.
	 *
	 * @param HealthService        $health   Health scorer supplying the summary.
	 * @param ActivityService|null $activity Injected for tests; defaults to a real instance.
	 */
	public function __construct( HealthService $health, ?ActivityService $activity = null ) {
		$this->health   = $health;
		$this->activity = $activity ? $activity : new ActivityService();
	}

	/**
	 * Register the dashboard routes.
	 *
	 * @return void
	 */
	public function register() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/dashboard',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_summary' ],
				'permission_callback' => [ $this, 'can_manage' ],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/dashboard/activity',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_activity' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [
					'page'     => [
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					],
					'per_page' => [
						'type'              => 'integer',
						'default'           => 20,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/dashboard/activity/(?P<id>\d+)/restore',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'restore_activity' ],
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
	}

	/**
	 * Return the health score, headline, cards and recommendations.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_summary() {
		return $this->ok( $this->health->summary() );
	}

	/**
	 * Return one page of the recorded change feed.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response
	 */
	public function get_activity( $request ) {
		return $this->ok(
			$this->activity->paged( $request->get_param( 'page' ), $request->get_param( 'per_page' ) )
		);
	}

	/**
	 * Restore the option recorded in one activity entry to its previous value.
	 *
	 * @param \WP_REST_Request $request Full REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function restore_activity( $request ) {
		$result = $this->activity->restore( $request->get_param( 'id' ) );
		if ( true !== $result ) {
			return $this->fail( 'nhrotm_restore_failed', __( 'This entry could not be restored.', 'nhrrob-options-table-manager' ), 400 );
		}
		return $this->ok( [ 'restored' => true ] );
	}
}
