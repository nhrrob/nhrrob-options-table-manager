<?php
/**
 * Class CleanupModule
 *
 * Registers the Cleanup feature module: REST routes for counting, previewing,
 * running and scheduling database cleanups.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Interfaces\ModuleInterface;
use Nhrotm\OptionsTableManager\Rest\CleanupController;

/**
 * Cleanup section — revisions, drafts, trash, spam, orphaned meta, caches, and their schedules.
 */
class CleanupModule implements ModuleInterface {

	/**
	 * Stable machine id, used for routing and nav keys.
	 *
	 * @return string
	 */
	public function id() {
		return 'cleanup';
	}

	/**
	 * Human-readable nav label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Cleanup', 'nhrrob-options-table-manager' );
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
		( new CleanupController() )->register();
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
