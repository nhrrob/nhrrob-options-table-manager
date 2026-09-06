<?php
namespace Nhrotm\OptionsTableManager\Interfaces;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contract every 2.0 feature module implements.
 *
 * A module is a self-contained feature (Dashboard, Browse, Optimize, ...).
 * The ModuleRegistry collects modules through the `nhrotm_modules` filter,
 * which is the single extension point a PRO add-on hooks into — core is
 * never edited to add a feature.
 */
interface ModuleInterface
{
    /**
     * Stable machine id, e.g. 'optimize'. Used for routing and nav keys.
     *
     * @return string
     */
    public function id();

    /**
     * Human-readable nav label, e.g. 'Optimize'.
     *
     * @return string
     */
    public function label();

    /**
     * Capability required to see/use this module.
     *
     * @return string
     */
    public function capability();

    /**
     * Register this module's REST routes under the nhrotm/v1 namespace.
     *
     * @return void
     */
    public function register_routes();

    /**
     * Cards this module contributes to the Dashboard.
     *
     * @return array
     */
    public function dashboard_cards();
}
