<?php
namespace Nhrotm\OptionsTableManager\Rest;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Services\SettingsService;
use Nhrotm\OptionsTableManager\Managers\BackupManager;

/**
 * REST endpoints for the centralized settings store.
 *
 * GET  /nhrotm/v1/settings  → current settings
 * POST /nhrotm/v1/settings  → update settings (partial merge)
 */
class SettingsController extends RestController
{
    /**
     * @var SettingsService
     */
    private $settings;

    public function __construct(SettingsService $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return void
     */
    public function register()
    {
        register_rest_route(self::NAMESPACE_V1, '/settings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_settings'],
                'permission_callback' => [$this, 'can_manage'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'update_settings'],
                'permission_callback' => [$this, 'can_manage'],
                'args'                => $this->schema_args(),
            ],
        ]);
    }

    /**
     * @return \WP_REST_Response
     */
    public function get_settings()
    {
        return $this->ok($this->settings->all());
    }

    /**
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function update_settings($request)
    {
        $incoming = [];
        foreach (array_keys($this->schema_args()) as $key) {
            if (null !== $request->get_param($key)) {
                $incoming[$key] = $request->get_param($key);
            }
        }

        $previous  = $this->settings->get('backup_frequency');
        $updated   = $this->settings->update($incoming);

        // Side effect: reschedule the backup cron if the frequency changed.
        if (isset($incoming['backup_frequency']) && $incoming['backup_frequency'] !== $previous) {
            BackupManager::reschedule($updated['backup_frequency']);
        }

        return $this->ok($updated);
    }

    /**
     * Argument schema with per-field sanitization.
     *
     * @return array
     */
    private function schema_args()
    {
        return [
            'allow_html_in_values'   => [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'auto_cleanup_enabled'   => [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'usage_tracking_enabled' => [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'backup_frequency'       => [
                'type'              => 'string',
                'enum'              => ['off', 'daily', 'weekly'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'history_retention_days' => [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ];
    }
}
