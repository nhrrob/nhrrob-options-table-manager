<?php
namespace Nhrotm\OptionsTableManager\Managers;

use Exception;

class OptionsTableManager extends BaseTableManager {

    public function __construct() {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'options';
    }

    /**
     * Retrieve options data
     * 
     * @return array Options data
     */
    public function get_data() {
        $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));

        $this->validate_nonce($nonce);
        $this->validate_permissions();

        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        
        // Search parameter
        $search = isset($_GET['search']['value']) ? sanitize_text_field(wp_unslash($_GET['search']['value'])) : '';
        $option_type_filter = isset($_GET['optionTypeFilter']) && in_array($_GET['optionTypeFilter'], ['all-options', 'all-transients', 'active-transients', 'expired-transients']) ? sanitize_text_field(wp_unslash($_GET['optionTypeFilter'])) : 'all-options';

        // Sorting parameters
        $order_column_index = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
        $order_direction = isset($_GET['order'][0]['dir']) && in_array($_GET['order'][0]['dir'], ['asc', 'desc']) ? strtolower( sanitize_text_field( wp_unslash( $_GET['order'][0]['dir'] ) ) ) : 'asc';
    
        $columns = $this->get_searchable_columns();
        
        // Ensure order column is valid using whitelist approach
        if ($order_column_index < 0 || $order_column_index >= count($columns)) {
            $order_column_index = 0; // Default to first column
        }
        $order_column = $columns[$order_column_index];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_records = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}options"
        );

        // Get column search values
        $column_search = [];
        if (isset($_GET['columns']) && is_array( $_GET['columns'] )) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $columns = $this->validation_service->sanitize_recursive( wp_unslash( $_GET['columns'] ) );

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            foreach ($_GET['columns'] as $column) {
                if (isset($column['search']['value'])) {
                    $column_search[] = sanitize_text_field(wp_unslash($column['search']['value']));
                } else {
                    $column_search[] = '';
                }
            }
        }

        // Build WHERE clause for search conditions
        $where_clauses = [];
        
        // Global search
        if (!empty($search)) {
            $search_like = '%' . $this->wpdb->esc_like($search) . '%';
            $where_clauses[] = $this->wpdb->prepare(
                "(option_name LIKE %s OR option_value LIKE %s)",
                $search_like,
                $search_like
            );
        }
        
        // Individual column searches
        if (!empty($column_search)) {
            // option_id column (index 0)
            if (!empty($column_search[0])) {
                // For numeric column, use exact match or range
                if (is_numeric($column_search[0])) {
                    $where_clauses[] = $this->wpdb->prepare("option_id = %d", intval($column_search[0]));
                }
            }
            
            // option_name column (index 1)
            if (!empty($column_search[1])) {
                $where_clauses[] = $this->wpdb->prepare(
                    "option_name LIKE %s",
                    '%' . $this->wpdb->esc_like($column_search[1]) . '%'
                );
            }
            
            // option_value column (index 2)
            if (!empty($column_search[2])) {
                $where_clauses[] = $this->wpdb->prepare(
                    "option_value LIKE %s",
                    '%' . $this->wpdb->esc_like($column_search[2]) . '%'
                );
            }
            
            // autoload column (index 3)
            if (!empty($column_search[3])) {
                $where_clauses[] = $this->wpdb->prepare(
                    "autoload LIKE %s",
                    '%' . $this->wpdb->esc_like($column_search[3]) . '%'
                );
            }
        }
        
        // Combine WHERE clauses
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Count filtered records
        $filtered_records_sql = "SELECT COUNT(*) FROM {$this->wpdb->prefix}options {$where_sql}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $filtered_records = $this->wpdb->get_var($filtered_records_sql);
        
        // SQL for ordering
        $order_sql = "ORDER BY {$order_column} {$order_direction}";
        
        // Get data with search, order, and pagination
        $data_sql = "SELECT * FROM {$this->wpdb->prefix}options {$where_sql} {$order_sql} LIMIT %d, %d";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $data = $this->wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $this->wpdb->prepare($data_sql, $start, $length),
            ARRAY_A
        );
        
        // Wrap the option_value in the scrollable-cell div
        foreach ($data as &$row) {
            $is_protected = in_array($row['option_name'], $this->get_protected_options());
            $protected_attr = $is_protected ? sprintf('title="%s" disabled', esc_attr__('Protected', 'nhrrob-options-table-manager')) : '';
        
            if ( 'all-transients' === $option_type_filter ) {
                // all options are transients
                $transient_name = str_replace('_transient_', '', $row['option_name']);
                $transient_value = get_transient($transient_name);

                $transient_status = $transient_value ? '[active]' : '[expired]';
                $row['option_name'] = esc_html($transient_status . $row['option_name']);
            }

            $row['option_value'] = '<div class="scrollable-cell">' . esc_html($row['option_value']) . '</div>';
            
            $row['actions'] = sprintf(
                '<button class="nhrotm-edit-button" data-id="%s" %s>Edit</button>
                <button class="nhrotm-delete-button" data-id="%s" %s>Delete</button>',
                esc_attr($row['option_id']),
                $protected_attr,
                esc_attr($row['option_id']),
                $protected_attr,
            );
        }        
        
        // Prepare response for DataTables
        $response = array(
            "draw" => isset($_GET['draw']) ? intval($_GET['draw']) : 0,
            "recordsTotal" => $total_records,
            "recordsFiltered" => $filtered_records,
            "data" => $data
        );
        
        return $response;
    }

    public function get_option() {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));

        $this->validate_permissions();
        $this->validate_nonce($nonce);

        // Sanitize and validate input data
        $option_name = isset($_POST['option_name']) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';

        if (empty($option_name)) {
            throw new \Exception('Option name is required');
        }

        // Add the option
        $option_value = get_option($option_name);

        $option_value = ! empty( $option_value ) && is_serialized($option_value) ? unserialize($option_value, ['allowed_classes' => false]) : $option_value;

        $response = [];

        if ( false !== $option_value ) {
            $response['option_name'] = $option_name;
            $response['option_value'] = $option_value;
            $response['message'] = 'Option found successfully';
            return $response;
        } else {
            $response['message'] = 'Failed to find option';
        }

        return $response;
    }

    public function add_option() {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));

        $this->validate_permissions();
        $this->validate_nonce($nonce);

        // Sanitize and validate input data
        $option_name = isset($_POST['new_option_name']) ? sanitize_text_field( wp_unslash( $_POST['new_option_name'] ) ) : '';
        $option_value = isset($_POST['new_option_value']) ? stripslashes_deep(sanitize_text_field( wp_unslash( $_POST['new_option_value'] ) )) : '';
        $autoload = isset($_POST['new_option_autoload']) ? sanitize_text_field( wp_unslash( $_POST['new_option_autoload'] ) ) : 'no';

        if (empty($option_name)) {
            throw new \Exception('Option name is required');
        }
        
        if (empty($option_value)) {
            throw new \Exception('Option value is required');
        }

        if (get_option($option_name) !== false) {
            throw new \Exception('Option name already exists');
        }

        return update_option($option_name, $option_value, $autoload);
    }

    /**
     * Edit an option
     * 
     * @return bool Success status
     */
    public function edit_record() {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));

        $this->validate_permissions();
        $this->validate_nonce($nonce);

        $option_name = isset($_POST['option_name']) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';

        if (empty($option_name)) {
            throw new \Exception('Option name is required');
        }

        if (!isset($_POST['option_value'])) {
            throw new \Exception('Option value is required');
        }

        if ($this->is_protected_item($option_name)) {
            throw new \Exception('This option is protected and cannot be edited');
        }

        $raw_option_value = sanitize_text_field( wp_unslash($_POST['option_value']) );
        
        try {
            $decoded_value = json_decode($raw_option_value, true);
        } catch (Exception $e) {
            throw new \Exception('Error processing serialized data: ' . $e->getMessage());
        }

        $sanitized_value = '';
        
        if ($decoded_value !== null && json_last_error() === JSON_ERROR_NONE) {
            $sanitized_value = $this->validation_service->sanitize_recursive($decoded_value);
        } else if (is_serialized($raw_option_value)) {
            try {
                $unserialized = unserialize($raw_option_value, ['allowed_classes' => false]);
                    
                if ($unserialized === false) {
                    throw new \Exception('Invalid serialized data format');
                }
                
                if (is_array($unserialized)
                 || is_object($unserialized)
                ) {
                    $sanitized_value = $this->validation_service->sanitize_recursive((array)$unserialized);
                } else {
                    $sanitized_value = sanitize_text_field($unserialized);
                }

            } catch (\Exception $e) {
                // parent method has check for thrown exception
                throw new \Exception('Error processing serialized data: ' . $e->getMessage());
            }
        } else {
            // Plain string/value
            $sanitized_value = sanitize_text_field($raw_option_value);
        }

        $autoload = isset($_POST['autoload']) ? sanitize_text_field( wp_unslash( $_POST['autoload'] ) ) : null;
        
        return update_option($option_name, $sanitized_value, $autoload);        
    }

    /**
     * Delete an option
     * 
     * @param array $data Option data to delete
     * @return bool Success status
     */
    public function delete_record() {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));

        $this->validate_permissions();
        $this->validate_nonce($nonce);

        $option_name = isset($_POST['option_name']) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';

        if (empty($option_name)) {
            throw new \Exception('Option name is required');
        }

        if ($this->is_protected_item($option_name)) {
            throw new \Exception('This option is protected and cannot be deleted');
        }

        return delete_option($option_name);
    }

    /**
     * Delete an expired transient
     * 
     * @param array $data Option data to delete
     * @return bool Success status
     */
    public function delete_expired_transients() {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));

        $this->validate_permissions();
        $this->validate_nonce($nonce);

        // Get all transient options
        // phpcs:ignore:WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $transients = $this->wpdb->get_results(
            "SELECT option_name, option_value 
            FROM {$this->wpdb->options} 
            WHERE option_name LIKE '%_transient_%' 
            AND option_name NOT LIKE '%_transient_timeout_%'",
            ARRAY_A
        );

        try {
            $deleted_transients = [];

            foreach ($transients as $transient) {

                $transient_name = str_replace('_transient_', '', $transient['option_name']);

                if (false === get_transient($transient_name)) {
                    // Transient has expired, delete it
                    $deleted_transients[] = $transient_name;
                    delete_transient(esc_sql( $transient_name ) ); // #ToDo do we need to add _transient_?
                }
            }

            $response= [
                'message' => 'Expired transients deleted successfully',
                'count' => count($deleted_transients),
                'deleted_transients' => $deleted_transients,
            ];

            return $response;
        } catch (\Exception $e) {
            throw new \Exception('Database error: ' . $e->getMessage()); // parent method has catch.
        }
    }

    public function option_usage_analytics() {
        $nonce = sanitize_text_field(wp_unslash($_GET['nonce']));

        $this->validate_permissions();
        $this->validate_nonce($nonce);
    
        // Query to get all option names
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $this->wpdb->get_results("SELECT option_name FROM {$this->wpdb->prefix}options", ARRAY_A);
        
        $prefix_count = [];
    
        foreach ($results as $row) {
            $option_name = $row['option_name'];
    
            // Remove '_transient' and '_timeout' and take the next part as the prefix
            $modified_option_name = preg_replace('/^_transient(?:_timeout)?_/', '', $option_name); // Remove _transient and _timeout
            $parts = explode('_', $modified_option_name);
    
            if (count($parts) > 0) {
                $prefix = $parts[0]; // Take the first part as the prefix
                if (!isset($prefix_count[$prefix])) {
                    $prefix_count[$prefix] = 0;
                }
                $prefix_count[$prefix]++;
            } else {
                // If no prefix detected, count it as 'others'
                if (!isset($prefix_count['others'])) {
                    $prefix_count['others'] = 0;
                }
                $prefix_count['others']++;
            }
        }
    
        // Prepare results for response
        $data = [];
        foreach ($prefix_count as $prefix => $count) {
            $data[] = ['prefix' => $prefix, 'count' => $count];
        }
    
        // Sort the data by count in descending order
        usort($data, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        
        return $data;
    }

    /**
     * Get searchable columns
     * 
     * @return array
     */
    protected function get_searchable_columns() {
        return ['option_id', 'option_name', 'option_value', 'autoload'];
    }
}