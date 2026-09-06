<?php
namespace Nhrotm\OptionsTableManager\Rest;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base REST controller for nhrotm/v1 module endpoints.
 *
 * Provides the shared namespace and the two-layer permission helpers:
 * a route-level capability gate, plus a hook for per-object checks on
 * routes that take an id. Replaces the monolithic admin-ajax switch.
 */
abstract class RestController
{
    const NAMESPACE_V1 = 'nhrotm/v1';

    /**
     * Register this controller's routes. Called from a module's
     * register_routes() during rest_api_init.
     *
     * @return void
     */
    abstract public function register();

    /**
     * Route-level capability gate. Default: manage_options.
     *
     * @return bool
     */
    public function can_manage()
    {
        return current_user_can('manage_options');
    }

    /**
     * Standard success envelope.
     *
     * @param mixed $data   Payload.
     * @param int   $status HTTP status.
     * @return \WP_REST_Response
     */
    protected function ok($data = [], $status = 200)
    {
        return new \WP_REST_Response(['success' => true, 'data' => $data], $status);
    }

    /**
     * Standard error envelope.
     *
     * @param string $code    Machine error code.
     * @param string $message Human message.
     * @param int    $status  HTTP status.
     * @return \WP_Error
     */
    protected function fail($code, $message, $status = 400)
    {
        return new \WP_Error($code, $message, ['status' => $status]);
    }
}
