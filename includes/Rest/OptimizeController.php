<?php
namespace Nhrotm\OptionsTableManager\Rest;

if (!defined('ABSPATH')) {
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
class OptimizeController extends RestController
{
    /**
     * @var OptimizeService
     */
    private $optimize;

    public function __construct(OptimizeService $optimize)
    {
        $this->optimize = $optimize;
    }

    /**
     * @return void
     */
    public function register()
    {
        register_rest_route(self::NAMESPACE_V1, '/optimize', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'overview'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/optimize/disable-autoload', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'disable_autoload'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => [
                'option' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/optimize/delete-orphans', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'delete_orphans'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => [
                'prefix' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/optimize/reset-usage', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'reset_usage'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/optimize/clean-transients', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'clean_transients'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => [
                'scope' => ['type' => 'string', 'enum' => ['expired', 'all'], 'default' => 'expired'],
            ],
        ]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function overview()
    {
        return $this->ok($this->optimize->overview());
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function disable_autoload($request)
    {
        $done = $this->optimize->disable_autoload($request->get_param('option'));
        if (!$done) {
            return $this->fail('nhrotm_protected', __('That option is protected and cannot be changed.', 'nhrrob-options-table-manager'));
        }
        return $this->ok(['disabled' => true]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function delete_orphans($request)
    {
        try {
            $deleted = $this->optimize->delete_orphans($request->get_param('prefix'));
        } catch (\Exception $e) {
            return $this->fail('nhrotm_orphan_error', $e->getMessage());
        }
        return $this->ok(['deleted' => $deleted]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function reset_usage()
    {
        $this->optimize->reset_usage();
        return $this->ok(['reset' => true]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function clean_transients($request)
    {
        $deleted = $this->optimize->clean_transients($request->get_param('scope'));
        return $this->ok(['deleted' => $deleted]);
    }
}
