<?php
namespace Nhrotm\OptionsTableManager\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Services\SettingsService;
use Nhrotm\OptionsTableManager\Modules\DashboardModule;
use Nhrotm\OptionsTableManager\Modules\BrowseModule;
use Nhrotm\OptionsTableManager\Modules\OptimizeModule;
use Nhrotm\OptionsTableManager\Modules\ToolsModule;
use Nhrotm\OptionsTableManager\Modules\IntegrationsModule;
use Nhrotm\OptionsTableManager\Modules\SettingsModule;
use Nhrotm\OptionsTableManager\Services\IntegrationsService;
use Nhrotm\OptionsTableManager\Admin\AppPage;

/**
 * 2.0 architecture bootstrap.
 *
 * Boots the module registry with the free core modules and registers their
 * REST routes on rest_api_init. Additive and inert alongside the 1.5.x
 * admin-ajax path — nothing here touches the existing UI until cutover.
 */
class Bootstrap
{
    /**
     * @var ModuleRegistry
     */
    private $registry;

    /**
     * @var SettingsService
     */
    private $settings;

    public function __construct()
    {
        $this->settings = new SettingsService();
        $this->registry = new ModuleRegistry();
    }

    /**
     * Wire hooks. Called once from the main plugin's init.
     *
     * @return void
     */
    public function init()
    {
        $this->registry->boot($this->core_modules());
        add_action('rest_api_init', [$this->registry, 'register_routes']);

        if (is_admin()) {
            (new AppPage($this->registry))->init();
        }
    }

    /**
     * The registry, for callers that need module data (e.g. admin nav).
     *
     * @return ModuleRegistry
     */
    public function registry()
    {
        return $this->registry;
    }

    /**
     * Free core modules. Add-ons append via the `nhrotm_modules` filter.
     *
     * @return array
     */
    private function core_modules()
    {
        $modules = [
            new DashboardModule(),
            new BrowseModule(),
            new OptimizeModule(),
            new ToolsModule(),
        ];

        // Integrations only appears when a supported third-party table exists.
        $integrations = new IntegrationsService();
        if ($integrations->has_any()) {
            $modules[] = new IntegrationsModule($integrations);
        }

        $modules[] = new SettingsModule($this->settings);

        return $modules;
    }
}
