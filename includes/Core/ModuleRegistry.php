<?php
namespace Nhrotm\OptionsTableManager\Core;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Interfaces\ModuleInterface;

/**
 * Collects and exposes feature modules.
 *
 * Core registers its own modules, then opens the list to add-ons via the
 * `nhrotm_modules` filter. Everything downstream (REST routes, admin nav,
 * dashboard cards) is driven from this single collection — the free build
 * and any PRO add-on wire in the same way.
 */
class ModuleRegistry
{
    /**
     * @var ModuleInterface[] Keyed by module id.
     */
    private $modules = [];

    /**
     * @var bool
     */
    private $booted = false;

    /**
     * Build the module list once, letting add-ons extend it.
     *
     * @param ModuleInterface[] $core_modules Modules shipped by the free core.
     * @return void
     */
    public function boot(array $core_modules)
    {
        if ($this->booted) {
            return;
        }

        /**
         * Filter the registered modules.
         *
         * The single extension point for add-ons. Handlers receive the core
         * modules and return the list with their own ModuleInterface
         * instances appended.
         *
         * @param ModuleInterface[] $core_modules
         */
        $modules = apply_filters('nhrotm_modules', $core_modules);

        foreach ($modules as $module) {
            if ($module instanceof ModuleInterface) {
                $this->modules[$module->id()] = $module;
            }
        }

        $this->booted = true;
    }

    /**
     * All registered modules the current user may access.
     *
     * @return ModuleInterface[]
     */
    public function get_modules()
    {
        return array_filter($this->modules, function (ModuleInterface $module) {
            return current_user_can($module->capability());
        });
    }

    /**
     * Fetch a single module by id, or null.
     *
     * @param string $id Module id.
     * @return ModuleInterface|null
     */
    public function get_module($id)
    {
        return isset($this->modules[$id]) ? $this->modules[$id] : null;
    }

    /**
     * Register REST routes for every accessible module.
     *
     * @return void
     */
    public function register_routes()
    {
        foreach ($this->get_modules() as $module) {
            $module->register_routes();
        }
    }

    /**
     * Collect dashboard cards contributed by every accessible module.
     *
     * @return array
     */
    public function dashboard_cards()
    {
        $cards = [];
        foreach ($this->get_modules() as $module) {
            foreach ($module->dashboard_cards() as $card) {
                $cards[] = $card;
            }
        }
        return $cards;
    }
}
