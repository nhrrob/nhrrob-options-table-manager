<?php
namespace Nhrotm\OptionsTableManager\Modules;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Interfaces\ModuleInterface;
use Nhrotm\OptionsTableManager\Rest\IntegrationsController;
use Nhrotm\OptionsTableManager\Services\IntegrationsService;

/**
 * Integrations section — third-party tables (Better Payment, WPRM).
 * Only registered by Bootstrap when at least one integration table exists.
 */
class IntegrationsModule implements ModuleInterface
{
    /**
     * @var IntegrationsService
     */
    private $integrations;

    public function __construct(IntegrationsService $integrations)
    {
        $this->integrations = $integrations;
    }

    /**
     * @return string
     */
    public function id()
    {
        return 'integrations';
    }

    /**
     * @return string
     */
    public function label()
    {
        return __('Integrations', 'nhrrob-options-table-manager');
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
        (new IntegrationsController($this->integrations))->register();
    }

    /**
     * @return array
     */
    public function dashboard_cards()
    {
        return [];
    }
}
