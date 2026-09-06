<?php
namespace Nhrotm\OptionsTableManager\Rest;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Services\ToolsService;

/**
 * REST endpoints for the Tools section.
 *
 * GET    /nhrotm/v1/tools/backups
 * POST   /nhrotm/v1/tools/backups            (create)
 * POST   /nhrotm/v1/tools/backups/(id)/restore
 * DELETE /nhrotm/v1/tools/backups/(id)
 * POST   /nhrotm/v1/tools/search-replace
 * GET    /nhrotm/v1/tools/export
 */
class ToolsController extends RestController
{
    /**
     * @var ToolsService
     */
    private $tools;

    public function __construct(ToolsService $tools)
    {
        $this->tools = $tools;
    }

    /**
     * @return void
     */
    public function register()
    {
        register_rest_route(self::NAMESPACE_V1, '/tools/backups', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'list_backups'],
                'permission_callback' => [$this, 'can_manage'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_backup'],
                'permission_callback' => [$this, 'can_manage'],
                'args'                => [
                    'label' => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/tools/backups/(?P<id>\d+)/restore', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'restore_backup'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => ['id' => ['type' => 'integer', 'sanitize_callback' => 'absint']],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/tools/backups/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete_backup'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => ['id' => ['type' => 'integer', 'sanitize_callback' => 'absint']],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/tools/search-replace', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'search_replace'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => [
                'search'  => ['type' => 'string', 'required' => true],
                'replace' => ['type' => 'string', 'default' => ''],
                'dry_run' => ['type' => 'boolean', 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean'],
            ],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/tools/export', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'export'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route(self::NAMESPACE_V1, '/tools/import', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'import'],
            'permission_callback' => [$this, 'can_manage'],
            'args'                => [
                'json'      => ['type' => 'string', 'required' => true],
                'overwrite' => ['type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean'],
            ],
        ]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function list_backups()
    {
        return $this->ok($this->tools->list_backups());
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function create_backup($request)
    {
        $id = $this->tools->create_backup($request->get_param('label'));
        if (!$id) {
            return $this->fail('nhrotm_backup_failed', __('Could not create the snapshot.', 'nhrrob-options-table-manager'));
        }
        return $this->ok(['id' => $id, 'backups' => $this->tools->list_backups()]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function restore_backup($request)
    {
        try {
            $restored = $this->tools->restore_backup($request->get_param('id'));
        } catch (\Exception $e) {
            return $this->fail('nhrotm_restore_failed', $e->getMessage());
        }
        return $this->ok(['restored' => $restored]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function delete_backup($request)
    {
        return $this->ok(['deleted' => $this->tools->delete_backup($request->get_param('id'))]);
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function search_replace($request)
    {
        try {
            $result = $this->tools->search_replace(
                (string) $request->get_param('search'),
                (string) $request->get_param('replace'),
                (bool) $request->get_param('dry_run')
            );
        } catch (\Exception $e) {
            return $this->fail('nhrotm_search_replace_failed', $e->getMessage());
        }
        return $this->ok($result);
    }

    /**
     * @return \WP_REST_Response
     */
    public function export()
    {
        return $this->ok($this->tools->export_options());
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function import($request)
    {
        try {
            $result = $this->tools->import_options(
                (string) $request->get_param('json'),
                (bool) $request->get_param('overwrite')
            );
        } catch (\Exception $e) {
            return $this->fail('nhrotm_import_failed', $e->getMessage());
        }
        return $this->ok($result);
    }
}
