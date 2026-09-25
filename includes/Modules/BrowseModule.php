<?php
/**
 * Class BrowseModule
 *
 * Registers the Browse feature module: REST routes for the unified
 * options / usermeta / postmeta / commentmeta / termmeta / transients data browser.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Interfaces\ModuleInterface;
use Nhrotm\OptionsTableManager\Rest\BrowseController;
use Nhrotm\OptionsTableManager\Services\BrowseService;

/**
 * Browse section — unified options / usermeta / postmeta / commentmeta / termmeta / transients data browser.
 */
class BrowseModule implements ModuleInterface {

	/**
	 * Stable machine id, used for routing and nav keys.
	 *
	 * @return string
	 */
	public function id() {
		return 'browse';
	}

	/**
	 * Human-readable nav label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Browse', 'nhrrob-options-table-manager' );
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
		( new BrowseController( new BrowseService() ) )->register();
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
