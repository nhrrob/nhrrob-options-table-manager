<?php
namespace Nhrotm\OptionsTableManager\Rest;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Services\HealthService;
use Nhrotm\OptionsTableManager\Services\ActivityService;

/**
 * REST endpoints for the dashboard.
 *
 * GET /nhrotm/v1/dashboard          → score, headline, cards, recommendations.
 * GET /nhrotm/v1/dashboard/activity → paginated change feed ("View all").
 */
class DashboardController extends RestController
{
    /**
     * @var HealthService
     */
    private $health;

    /**
     * @var ActivityService
     */
    private $activity;

    public function __construct(HealthService $health, ?ActivityService $activity = null)
    {
        $this->health   = $health;
        $this->activity = $activity ? $activity : new ActivityService();
    }

    /**
     * @return void
     */
    public function register()
    {
        register_rest_route(self::NAMESPACE_V1, '/dashboard', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_summary'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/dashboard/activity', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_activity'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => [
                'page' => [
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
        ]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function get_summary()
    {
        return $this->ok($this->health->summary());
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function get_activity($request)
    {
        return $this->ok(
            $this->activity->paged($request->get_param('page'), $request->get_param('per_page'))
        );
    }
}
