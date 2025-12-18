<?php
namespace Nhrotm\OptionsTableManager\Interfaces;

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