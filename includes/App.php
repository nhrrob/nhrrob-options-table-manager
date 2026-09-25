<?php
/**
 * Shared base class for the plugin's controllers.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Traits\GlobalTrait;

/**
 * Controller Class
 */
class App {


	use GlobalTrait;

	/**
	 * Admin page slug shared by the plugin's screens.
	 *
	 * @var string
	 */
	protected $page_slug;

	/**
	 * Set the shared admin page slug.
	 */
	public function __construct() {
		$this->page_slug = 'nhrotm-options-table-manager';
	}
}
