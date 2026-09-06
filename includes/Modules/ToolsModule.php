<?php
namespace Nhrotm\OptionsTableManager\Modules;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Interfaces\ModuleInterface;
use Nhrotm\OptionsTableManager\Rest\ToolsController;
use Nhrotm\OptionsTableManager\Services\ToolsService;

/**
 * Tools section — backups, search & replace, export.
 */
class ToolsModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'tools';
    }

    /**
     * @return string
     */
    public function label()
    {
        return __('Tools', 'nhrrob-options-table-manager');
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
        (new ToolsController(new ToolsService()))->register();
    }

    /**
     * @return array
     */
    public function dashboard_cards()
    {
        return [];
    }
}
