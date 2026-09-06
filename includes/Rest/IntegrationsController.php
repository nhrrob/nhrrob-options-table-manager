<?php
namespace Nhrotm\OptionsTableManager\Rest;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Services\IntegrationsService;

/**
 * REST endpoints for the Integrations section.
 *
 * GET /nhrotm/v1/integrations         → available integrations
 * GET /nhrotm/v1/integrations/(slug)  → paginated rows for one table
 */
class IntegrationsController extends RestController
{
    /**
     * @var IntegrationsService
     */
    private $integrations;

    public function __construct(IntegrationsService $integrations)
    {
        $this->integrations = $integrations;
    }

    /**
     * @return void
     */
    public function register()
    {
        register_rest_route(self::NAMESPACE_V1, '/integrations', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'list_available'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/integrations/(?P<slug>[a-z0-9_]+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'rows'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => [
                'page'     => ['type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint'],
            ],
        ]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function list_available()
    {
        return $this->ok($this->integrations->available());
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function rows($request)
    {
        return $this->ok($this->integrations->rows(
            $request->get_param('slug'),
            $request->get_param('page'),
            $request->get_param('per_page')
        ));
    }
}
