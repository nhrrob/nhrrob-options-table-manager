<?php
namespace Nhrotm\OptionsTableManager\Rest;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Services\BrowseService;

/**
 * REST endpoints for the unified data browser.
 *
 * GET    /nhrotm/v1/browse           → paginated options|usermeta|postmeta|commentmeta|termmeta|transients
 * GET    /nhrotm/v1/browse/lookup    → search-as-you-type post/user list for the postmeta/usermeta id filter
 * DELETE /nhrotm/v1/browse/(id)      → delete one record (core items protected)
 */
class BrowseController extends RestController
{
    /**
     * @var BrowseService
     */
    private $browse;

    public function __construct(BrowseService $browse)
    {
        $this->browse = $browse;
    }

    /**
     * @return void
     */
    public function register()
    {
        register_rest_route(self::NAMESPACE_V1, '/browse', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'list_records'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => [
                'type'     => [
                    'type'    => 'string',
                    'enum'    => BrowseService::TYPES,
                    'default' => 'options',
                ],
                'page'     => ['type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint'],
                'search'   => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'orderby'  => ['type' => 'string', 'enum' => ['name', 'size', 'autoload', 'id'], 'default' => 'size'],
                'order'    => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
                // Transients only — ignored for every other type.
                'status'   => ['type' => 'string', 'enum' => ['', 'active', 'expired', 'persistent'], 'default' => ''],
                'owner'    => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                // Usermeta/postmeta only — ignored for every other type.
                'user_id'  => ['type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint'],
                'post_id'  => ['type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/browse/lookup', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'lookup'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => [
                'target' => ['type' => 'string', 'enum' => ['post', 'user'], 'required' => true],
                'search' => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/browse/save', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'save_record'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => [
                'type'       => ['type' => 'string', 'enum' => BrowseService::TYPES, 'default' => 'options'],
                'id'         => ['type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint'],
                'name'       => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'value'      => ['type' => 'string', 'default' => ''],
                'format'     => ['type' => 'string', 'enum' => ['plain', 'json', 'serialized'], 'default' => 'plain'],
                'autoload'   => ['type' => 'string', 'enum' => ['yes', 'no'], 'default' => 'yes'],
                'user_id'    => ['type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint'],
                'post_id'    => ['type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint'],
                'comment_id' => ['type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint'],
                'term_id'    => ['type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint'],
                'expiration' => ['type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/browse/bulk-delete', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'bulk_delete'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => [
                'type' => ['type' => 'string', 'enum' => BrowseService::TYPES, 'default' => 'options'],
                'ids'  => ['type' => 'array', 'required' => true, 'items' => ['type' => 'string']],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/browse/(?P<id>[a-zA-Z0-9_\-]+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_record'],
                'permission_callback' => [$this, 'can_manage'],
                'args'                => [
                    'type' => ['type' => 'string', 'enum' => BrowseService::TYPES, 'default' => 'options'],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_record'],
                'permission_callback' => [$this, 'can_manage'],
                'args'                => [
                    'type' => ['type' => 'string', 'enum' => BrowseService::TYPES, 'default' => 'options'],
                ],
            ],
        ]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_record($request)
    {
        $record = $this->browse->get($request->get_param('type'), $request->get_param('id'));
        if (null === $record) {
            return $this->fail('nhrotm_not_found', __('Record not found.', 'nhrrob-options-table-manager'), 404);
        }
        return $this->ok($record);
    }

    /**
     * Search-as-you-type lookup backing the Browse post/user filter combobox.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function lookup($request)
    {
        return $this->ok([
            'items' => $this->browse->lookup(
                $request->get_param('target'),
                $request->get_param('search')
            ),
        ]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function save_record($request)
    {
        $saved = $this->browse->save([
            'type'       => $request->get_param('type'),
            'id'         => $request->get_param('id'),
            'name'       => $request->get_param('name'),
            'value'      => $request->get_param('value'),
            'format'     => $request->get_param('format'),
            'autoload'   => $request->get_param('autoload'),
            'user_id'    => $request->get_param('user_id'),
            'post_id'    => $request->get_param('post_id'),
            'comment_id' => $request->get_param('comment_id'),
            'term_id'    => $request->get_param('term_id'),
            'expiration' => $request->get_param('expiration'),
        ]);
        if (false === $saved) {
            return $this->fail('nhrotm_save_failed', __('This record is protected or the input was invalid.', 'nhrrob-options-table-manager'));
        }
        return $this->ok($saved);
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function bulk_delete($request)
    {
        $ids = (array) $request->get_param('ids');
        $deleted = $this->browse->bulk_delete($request->get_param('type'), $ids);
        return $this->ok(['deleted' => $deleted]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function list_records($request)
    {
        return $this->ok($this->browse->query(
            $request->get_param('type'),
            $request->get_param('page'),
            $request->get_param('per_page'),
            $request->get_param('search'),
            $request->get_param('orderby'),
            $request->get_param('order'),
            $request->get_param('status'),
            $request->get_param('owner'),
            $request->get_param('user_id'),
            $request->get_param('post_id')
        ));
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function delete_record($request)
    {
        $deleted = $this->browse->delete($request->get_param('type'), $request->get_param('id'));
        if (!$deleted) {
            return $this->fail('nhrotm_delete_failed', __('This record is protected or could not be deleted.', 'nhrrob-options-table-manager'), 400);
        }
        return $this->ok(['deleted' => true]);
    }
}
