<?php
namespace Nhrotm\OptionsTableManager\Modules;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Interfaces\ModuleInterface;
use Nhrotm\OptionsTableManager\Rest\SettingsController;
use Nhrotm\OptionsTableManager\Services\SettingsService;

/**
 * Settings section — first module wired end-to-end through the registry.
 *
 * Proves the module → REST flow: the registry calls register_routes(),
 * which stands up the SettingsController under nhrotm/v1.
 */
class SettingsModule implements ModuleInterface
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
     * @return string
     */
    public function id()
    {
        return 'settings';
    }

    /**
     * @return string
     */
    public function label()
    {
        return __('Settings', 'nhrrob-options-table-manager');
    }

    /**
     * @return string
     */
    public function capability()
    {
        return 'manage_options';
    }

    /**
     * @return void
     */
    public function register_routes()
    {
        (new SettingsController($this->settings))->register();
    }

    /**
     * Settings contributes no dashboard cards.
     *
     * @return array
     */
    public function dashboard_cards()
    {
        return [];
    }
}
