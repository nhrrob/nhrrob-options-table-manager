<?php
/**
 * Contract every table manager implements.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface TableManagerInterface
 *
 * The CRUD surface shared by every table manager: read a paginated/filtered
 * result set, and edit or delete a single record.
 */
interface TableManagerInterface {

	/**
	 * Retrieve table data with optional filtering and pagination
	 *
	 * @return array Table data and metadata
	 */
	public function get_data();

	/**
	 * Edit a record in the table
	 *
	 * @return bool Success status
	 */
	public function edit_record();

	/**
	 * Delete a record from the table
	 *
	 * @return bool Success status
	 */
	public function delete_record();
}
