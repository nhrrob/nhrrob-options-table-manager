<?php
/**
 * Class IntegrationsModule
 *
 * Registers the Integrations feature module: REST routes for browsing
 * third-party plugin tables (e.g. WPRM) detected on this site.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Interfaces\ModuleInterface;
use Nhrotm\OptionsTableManager\Rest\IntegrationsController;
use Nhrotm\OptionsTableManager\Services\IntegrationsService;

/**
 * Integrations section — third-party tables (e.g. WPRM).
 * Only registered by Bootstrap when at least one integration table exists.
 */
class IntegrationsModule implements ModuleInterface {

	/**
	 * Detects/queries third-party integration tables.
	 *
	 * @var IntegrationsService
	 */
	private $integrations;

	/**
	 * Bind the integrations service used to discover/query third-party tables.
	 *
	 * @param IntegrationsService $integrations Detects/queries third-party integration tables.
	 */
	public function __construct( IntegrationsService $integrations ) {
		$this->integrations = $integrations;
	}

	/**
	 * Stable machine id, used for routing and nav keys.
	 *
	 * @return string
	 */
	public function id() {
		return 'integrations';
	}

	/**
	 * Human-readable nav label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Integrations', 'nhrrob-options-table-manager' );
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
		( new IntegrationsController( $this->integrations ) )->register();
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
