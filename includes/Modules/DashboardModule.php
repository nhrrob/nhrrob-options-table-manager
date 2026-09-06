<?php
namespace Nhrotm\OptionsTableManager\Modules;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Interfaces\ModuleInterface;
use Nhrotm\OptionsTableManager\Rest\DashboardController;
use Nhrotm\OptionsTableManager\Services\HealthService;

/**
 * Dashboard section. Landing view of the 2.0 app.
 *
 * Serves the live health summary (score, cards, recommendations) via the
 * DashboardController, and hosts cards other modules contribute.
 */
class DashboardModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'dashboard';
    }

    /**
     * @return string
     */
    public function label()
    {
        return __('Dashboard', 'nhrrob-options-table-manager');
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
        (new DashboardController(new HealthService()))->register();
    }

    /**
     * @return array
     */
    public function dashboard_cards()
    {
        return [];
    }
}
