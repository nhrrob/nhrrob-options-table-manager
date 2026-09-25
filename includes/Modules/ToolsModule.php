<?php
/**
 * Class ToolsModule
 *
 * Registers the Tools feature module: REST routes for backups, search &
 * replace, and export/import.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Interfaces\ModuleInterface;
use Nhrotm\OptionsTableManager\Rest\ToolsController;
use Nhrotm\OptionsTableManager\Services\ToolsService;

/**
 * Tools section — backups, search & replace, export.
 */
class ToolsModule implements ModuleInterface {

	/**
	 * Stable machine id, used for routing and nav keys.
	 *
	 * @return string
	 */
	public function id() {
		return 'tools';
	}

	/**
	 * Human-readable nav label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Tools', 'nhrrob-options-table-manager' );
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
		( new ToolsController( new ToolsService() ) )->register();
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
