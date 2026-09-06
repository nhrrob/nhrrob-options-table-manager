<?php
namespace Nhrotm\OptionsTableManager\Modules;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Interfaces\ModuleInterface;
use Nhrotm\OptionsTableManager\Rest\OptimizeController;
use Nhrotm\OptionsTableManager\Services\OptimizeService;

/**
 * Optimize section — autoload health, usage tracker, orphans, cleanup.
 */
class OptimizeModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'optimize';
    }

    /**
     * @return string
     */
    public function label()
    {
        return __('Optimize', 'nhrrob-options-table-manager');
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
        (new OptimizeController(new OptimizeService()))->register();
    }

    /**
     * @return array
     */
    public function dashboard_cards()
    {
        return [];
    }
}
