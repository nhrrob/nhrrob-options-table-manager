<?php
namespace Nhrotm\OptionsTableManager\Modules;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Interfaces\ModuleInterface;
use Nhrotm\OptionsTableManager\Rest\BrowseController;
use Nhrotm\OptionsTableManager\Services\BrowseService;

/**
 * Browse section — unified options / usermeta / postmeta / commentmeta / termmeta / transients data browser.
 */
class BrowseModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'browse';
    }

    /**
     * @return string
     */
    public function label()
    {
        return __('Browse', 'nhrrob-options-table-manager');
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
        (new BrowseController(new BrowseService()))->register();
    }

    /**
     * @return array
     */
    public function dashboard_cards()
    {
        return [];
    }
}
