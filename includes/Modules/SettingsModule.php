<?php
/**
 * Class SettingsModule
 *
 * Registers the Settings feature module: REST routes for reading and
 * writing the plugin's centralized settings store.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Interfaces\ModuleInterface;
use Nhrotm\OptionsTableManager\Rest\SettingsController;
use Nhrotm\OptionsTableManager\Services\SettingsService;

/**
 * Settings section — first module wired end-to-end through the registry.
 *
 * Proves the module → REST flow: the registry calls register_routes(),
 * which stands up the SettingsController under nhrotm/v1.
 */
class SettingsModule implements ModuleInterface {

	/**
	 * Centralized settings store.
	 *
	 * @var SettingsService
	 */
	private $settings;

	/**
	 * Bind the settings service used to read/write plugin settings.
	 *
	 * @param SettingsService $settings Centralized settings store.
	 */
	public function __construct( SettingsService $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Stable machine id, used for routing and nav keys.
	 *
	 * @return string
	 */
	public function id() {
		return 'settings';
	}

	/**
	 * Human-readable nav label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Settings', 'nhrrob-options-table-manager' );
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
		( new SettingsController( $this->settings ) )->register();
	}

	/**
	 * Settings contributes no dashboard cards.
	 *
	 * @return array
	 */
	public function dashboard_cards() {
		return [];
	}
}
