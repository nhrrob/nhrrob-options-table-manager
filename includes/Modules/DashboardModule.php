<?php
/**
 * Class DashboardModule
 *
 * Registers the Dashboard feature module: the 2.0 app's landing view, serving
 * the live health summary (score, cards, recommendations) via DashboardController.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Modules;

if ( ! defined( 'ABSPATH' ) ) {
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
class DashboardModule implements ModuleInterface {

	/**
	 * Stable machine id, used for routing and nav keys.
	 *
	 * @return string
	 */
	public function id() {
		return 'dashboard';
	}

	/**
	 * Human-readable nav label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Dashboard', 'nhrrob-options-table-manager' );
	}

	/**
	 * Capability required to see/use this module.
	 *
	 * @return string
	 */
	public function capability() {
		return 'manage_options';
	}

	/**
	 * Register this module's REST routes under the nhrotm/v1 namespace.
	 *
	 * @return void
	 */
	public function register_routes() {
		( new DashboardController( new HealthService() ) )->register();
	}

	/**
	 * Cards this module contributes to the Dashboard.
	 *
	 * @return array
	 */
	public function dashboard_cards() {
		return [];
	}
}
