<?php
/**
 * Boots the 2.0 module registry and its REST routes.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Services\SettingsService;
use Nhrotm\OptionsTableManager\Modules\DashboardModule;
use Nhrotm\OptionsTableManager\Modules\BrowseModule;
use Nhrotm\OptionsTableManager\Modules\CleanupModule;
use Nhrotm\OptionsTableManager\Modules\OptimizeModule;
use Nhrotm\OptionsTableManager\Modules\ToolsModule;
use Nhrotm\OptionsTableManager\Modules\IntegrationsModule;
use Nhrotm\OptionsTableManager\Modules\SettingsModule;
use Nhrotm\OptionsTableManager\Services\IntegrationsService;
use Nhrotm\OptionsTableManager\Admin\AppPage;
use Nhrotm\OptionsTableManager\Rest\NetworkController;

/**
 * 2.0 architecture bootstrap.
 *
 * Boots the module registry with the free core modules and registers their
 * REST routes on rest_api_init. Additive and inert alongside the 1.5.x
 * admin-ajax path — nothing here touches the existing UI until cutover.
 */
class Bootstrap {

	/**
	 * Registry holding every booted feature module.
	 *
	 * @var ModuleRegistry
	 */
	private $registry;

	/**
	 * Plugin settings reader.
	 *
	 * @var SettingsService
	 */
	private $settings;

	/**
	 * Construct the settings reader and module registry.
	 */
	public function __construct() {
		$this->settings = new SettingsService();
		$this->registry = new ModuleRegistry();
	}

	/**
	 * Wire hooks. Called once from the main plugin's init.
	 *
	 * @return void
	 */
	public function init() {
		// Abilities for AI agents and MCP clients (WordPress 6.9+; inert before).
		( new Abilities() )->init();

		// The module list is only needed by the admin screen and the REST API,
		// so it is built there and a front-end request pays nothing for it.
		add_action(
			'rest_api_init',
			function () {
				$this->registry->boot( $this->core_modules() );
				$this->registry->register_routes();
			}
		);
		if ( is_multisite() ) {
			// Outside the per-site module registry: gated on manage_network_options.
			add_action(
				'rest_api_init',
				function () {
					if ( current_user_can( 'manage_network_options' ) ) {
						( new NetworkController() )->register();
					}
				}
			);
		}

		if ( is_admin() ) {
			// Built when the menu is registered (not on admin-ajax/admin-post
			// requests, which never show the app). Add-ons hook `nhrotm_modules`
			// on plugins_loaded, so the filter still sees them.
			add_action(
				'admin_menu',
				function () {
					$this->registry->boot( $this->core_modules() );
				},
				1
			);
			( new AppPage( $this->registry ) )->init();
		}
	}

	/**
	 * The registry, for callers that need module data (e.g. admin nav).
	 *
	 * @return ModuleRegistry
	 */
	public function registry() {
		return $this->registry;
	}

	/**
	 * Free core modules. Add-ons append via the `nhrotm_modules` filter.
	 *
	 * @return array
	 */
	private function core_modules() {
		$modules = [
			new DashboardModule(),
			new BrowseModule(),
			new CleanupModule(),
			new OptimizeModule(),
			new ToolsModule(),
		];

		// Integrations only appears when a supported third-party table exists.
		$integrations = new IntegrationsService();
		if ( $integrations->has_any() ) {
			$modules[] = new IntegrationsModule( $integrations );
		}

		$modules[] = new SettingsModule( $this->settings );

		return $modules;
	}
}
