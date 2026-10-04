<?php
/**
 * Shared base for the DataTables-backed table managers.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Traits\GlobalTrait;

/**
 * Class BaseTableManager
 *
 * Permission and protected-option checks shared by the wp_options managers
 * (autoload analysis, orphan scanning, search & replace).
 */
abstract class BaseTableManager {

	use GlobalTrait;

	/**
	 * WordPress database access object.
	 *
	 * @var \wpdb
	 */
	protected $wpdb;

	/**
	 * Fully-qualified name of the table this instance manages.
	 *
	 * @var string
	 */
	protected $table_name;

	/**
	 * Protected option names that cannot be edited/deleted.
	 *
	 * @var array
	 */
	protected $protected_items = [];

	/**
	 * Load the protected-option list.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb            = $wpdb;
		$this->protected_items = $this->get_protected_options();
	}

	/**
	 * Validate user permissions
	 *
	 * @throws \Exception If user lacks required permissions.
	 */
	protected function validate_permissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'Insufficient permissions' );
		}
	}

	/**
	 * Check if an option is protected
	 *
	 * @param string $key Option name.
	 * @return bool
	 */
	protected function is_protected_item( $key ) {
		return in_array( $key, $this->protected_items, true );
	}
}
