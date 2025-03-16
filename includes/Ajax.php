<?php

namespace Nhrotm\OptionsTableManager;

/**
 * Ajax handler class
 */
class Ajax extends App {

    /**
     * Class constructor
     */
    function __construct() {
        add_action('wp_ajax_nhrotm_option_table_data', [ $this, 'option_table_data' ]);
        add_action('wp_ajax_nhrotm_get_option', [ $this, 'get_option' ]);
        add_action('wp_ajax_nhrotm_add_option', [ $this, 'add_option' ]);
        add_action('wp_ajax_nhrotm_edit_option', [ $this, 'edit_option' ]);
        add_action('wp_ajax_nhrotm_delete_option', [ $this, 'delete_option' ]);
        add_action('wp_ajax_nhrotm_option_usage_analytics', [ $this, 'option_usage_analytics' ]);

        add_action('wp_ajax_nhrotm_usermeta_table_data', [ $this, 'usermeta_table_data' ]);
        add_action('wp_ajax_nhrotm_edit_usermeta', [ $this, 'edit_usermeta' ]);
        add_action('wp_ajax_nhrotm_delete_usermeta', [ $this, 'delete_usermeta' ]);
        
        add_action('wp_ajax_nhrotm_better_payment_table_data', [ $this, 'better_payment_table_data' ]);
        // add_action('wp_ajax_nhrotm_edit_usermeta', [ $this, 'edit_usermeta' ]);
        // add_action('wp_ajax_nhrotm_delete_usermeta', [ $this, 'delete_usermeta' ]);
    }

    public function option_table_data() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'options';
        
        // Pagination parameters
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        
        // Search parameter
        $search = isset($_GET['search']['value']) ? sanitize_text_field(wp_unslash($_GET['search']['value'])) : '';
        
        // Sorting parameters
        $order_column_index = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
        $order_direction = isset($_GET['order'][0]['dir']) && in_array($_GET['order'][0]['dir'], ['asc', 'desc']) ? strtolower( sanitize_text_field( wp_unslash( $_GET['order'][0]['dir'] ) ) ) : 'asc';
    
        // Define columns in the correct order for sorting
        $columns = ['option_id', 'option_name', 'option_value', 'autoload'];
        
        // Ensure order column is valid using whitelist approach
        if ($order_column_index < 0 || $order_column_index >= count($columns)) {
            $order_column_index = 0; // Default to first column
        }
        $order_column = $columns[$order_column_index];
        
        // Get total record count with prepared statement
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_records = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}options"
        );
        
        // Get column search values
        $column_search = [];
        if (isset($_GET['columns']) && is_array($_GET['columns'])) {
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
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = $wpdb->prepare(
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
                    $where_clauses[] = $wpdb->prepare("option_id = %d", intval($column_search[0]));
                }
            }
            
            // option_name column (index 1)
            if (!empty($column_search[1])) {
                $where_clauses[] = $wpdb->prepare(
                    "option_name LIKE %s",
                    '%' . $wpdb->esc_like($column_search[1]) . '%'
                );
            }
            
            // option_value column (index 2)
            if (!empty($column_search[2])) {
                $where_clauses[] = $wpdb->prepare(
                    "option_value LIKE %s",
                    '%' . $wpdb->esc_like($column_search[2]) . '%'
                );
            }
            
            // autoload column (index 3)
            if (!empty($column_search[3])) {
                $where_clauses[] = $wpdb->prepare(
                    "autoload LIKE %s",
                    '%' . $wpdb->esc_like($column_search[3]) . '%'
                );
            }
        }
        
        // Combine WHERE clauses
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Count filtered records
        $filtered_records_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}options {$where_sql}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $filtered_records = $wpdb->get_var($filtered_records_sql);
        
        // SQL for ordering
        $order_sql = "ORDER BY {$order_column} {$order_direction}";
        
        // Get data with search, order, and pagination
        $data_sql = "SELECT * FROM {$wpdb->prefix}options {$where_sql} {$order_sql} LIMIT %d, %d";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $data = $wpdb->get_results(
            $wpdb->prepare($data_sql, $start, $length),
            ARRAY_A
        );
        
        // Wrap the option_value in the scrollable-cell div
        foreach ($data as &$row) {
            $is_protected = in_array($row['option_name'], $this->get_protected_options());
            $protected_attr = $is_protected ? sprintf('title="%s" disabled', esc_attr__('Protected', 'nhrrob-options-table-manager')) : '';
    
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
        
        wp_send_json($response);
    }

    public function get_option() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }

        // Ensure the user has the right capability
        if (! current_user_can('manage_options') ) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }

        // Sanitize and validate input data
        $option_name = isset($_POST['option_name']) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';
        // $option_value = isset($_POST['new_option_value']) ? stripslashes_deep(sanitize_text_field($_POST['new_option_value'])) : '';
        // $autoload = isset($_POST['new_option_autoload']) ? sanitize_text_field($_POST['new_option_autoload']) : 'no';

        if (empty($option_name)) {
            wp_send_json_error('Option name is required');
            wp_die();
        }

        // if (empty($option_value)) {
        //     wp_send_json_error('Option value is required');
        //     wp_die();
        // }

        // if (get_option($option_name) !== false) {
        //     wp_send_json_error('Option already exists');
        //     wp_die();
        // }

        // Add the option
        $option_value = get_option($option_name);

        // if ($option_value === false) {
        //     wp_send_json_error('Option not found');
        //     wp_die();
        // }

        // If it's serialized, unserialize it safely
        // if (is_serialized($option_value)) {
        //     $option_value = maybe_unserialize($option_value);
        // }

        // Convert to array if needed
        // if (!is_array($option_value)) {
        //     $option_value = array('value' => $option_value);
        // }

        $option_value = ! empty( $option_value ) && is_serialized($option_value) ? unserialize($option_value, ['allowed_classes' => false]) : $option_value;

        $response = [];
        
        if ( false !== $option_value ) {
            $response['option_name'] = $option_name;
            $response['option_value'] = $option_value;
            $response['message'] = 'Option found successfully';
            wp_send_json_success($response);
        } else {
            $response['message'] = 'Failed to find option';
            wp_send_json_error($response);
        }

        wp_die();
    }

    public function add_option() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }

        // Ensure the user has the right capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }

        // Sanitize and validate input data
        $option_name = isset($_POST['new_option_name']) ? sanitize_text_field( wp_unslash( $_POST['new_option_name'] ) ) : '';
        $option_value = isset($_POST['new_option_value']) ? stripslashes_deep(sanitize_text_field( wp_unslash( $_POST['new_option_value'] ) )) : '';
        $autoload = isset($_POST['new_option_autoload']) ? sanitize_text_field( wp_unslash( $_POST['new_option_autoload'] ) ) : 'no';

        if (empty($option_name)) {
            wp_send_json_error('Option name is required');
            wp_die();
        }

        if (empty($option_value)) {
            wp_send_json_error('Option value is required');
            wp_die();
        }

        if (get_option($option_name) !== false) {
            wp_send_json_error('Option already exists');
            wp_die();
        }

        // Add the option
        if (update_option($option_name, $option_value, $autoload)) {
            wp_send_json_success('Option added successfully');
        } else {
            wp_send_json_error('Failed to add option');
        }

        wp_die();
    }
    
    public function edit_option() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
    
        // Ensure the user has the right capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }
    
        $option_name = isset($_POST['option_name']) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';

        if (empty($option_name)) {
            wp_send_json_error('Option name is required');
            wp_die();
        }

        if (!isset($_POST['option_value'])) {
            wp_send_json_error('Option value is required');
            wp_die();
        }

        if (in_array($option_name, $this->get_protected_options())) {
            wp_send_json_error('This option is protected and cannot be edited');
            wp_die();
        }

        $raw_option_value = sanitize_text_field( wp_unslash($_POST['option_value']) );
        // $option_value = stripslashes_deep( $option_value ); 

        // if (is_serialized_string($raw_option_value)) {
        //     wp_send_json_error('Serialized objects are not allowed');
        //     wp_die();
        // }

        // if (preg_match('/O:\d+:"[^"]++":\d+:{/', $raw_option_value)) {
        //     wp_send_json_error('Object serialization is not allowed');
        //     wp_die();
        // }

        $original_value = get_option($option_name);
        $is_original_serialized = is_serialized($original_value);

        $decoded_value = json_decode($raw_option_value, true);
        $sanitized_value = '';
        
        if ($decoded_value !== null && json_last_error() === JSON_ERROR_NONE) {
            $sanitized_value = $this->sanitize_array_recursive($decoded_value);
        } else if (is_serialized($raw_option_value)) {
            try {
                $unserialized = unserialize($raw_option_value, ['allowed_classes' => false]);
                    
                if ($unserialized === false) {
                    wp_send_json_error('Invalid serialized data format');
                    wp_die();
                }
                
                if (is_array($unserialized)
                 || is_object($unserialized)
                ) {
                    $sanitized_value = $this->sanitize_array_recursive((array)$unserialized);
                } else {
                    $sanitized_value = sanitize_text_field($unserialized);
                }

            } catch (\Exception $e) {
                wp_send_json_error('Error processing serialized data: ' . $e->getMessage());
                wp_die();
            }
        } else {
            // Plain string/value
            $sanitized_value = sanitize_text_field($raw_option_value);
        }

        $autoload = isset($_POST['autoload']) ? sanitize_text_field( wp_unslash( $_POST['autoload'] ) ) : null;
        
        if (update_option($option_name, $sanitized_value, $autoload)) {
            wp_send_json_success('Option updated successfully');
        } else {
            wp_send_json_error('Failed to update option');
        }
    
        wp_die();
    }

    /**
     * Recursively sanitize an array while preserving structure
     */
    public function sanitize_array_recursive($data) {
        // If it's an object, convert to array first
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (!is_array($data)) {
            if (is_bool($data)) {
                return (bool)$data;
            } else if (is_numeric($data)) {
                return $data + 0; // Convert to proper number type
            } else if (is_string($data)) {
                return sanitize_text_field($data);
            } else {
                // For other types, convert to string and sanitize
                return sanitize_text_field((string)$data);
            }
        }    
        
        $content_keys = ['content'];
        
        $sanitized = array();
        foreach ($data as $key => $value) {
            // Sanitize the key
            $clean_key = sanitize_text_field($key);
            
            if (is_array($value)  || is_object($value)) {
                $sanitized[$clean_key] = $this->sanitize_array_recursive($value);
            }  else if (is_string($value) && in_array($clean_key, $content_keys)) {
                // Use wp_kses_post for HTML content fields
                $sanitized[$clean_key] = wp_kses_post($value);
            } else {
                // Handle different value types appropriately
                if (is_bool($value)) {
                    $sanitized[$clean_key] = (bool)$value;
                } else if (is_numeric($value)) {
                    $sanitized[$clean_key] = $value + 0; // Convert to proper number type
                } else if (is_string($value)) {
                    $sanitized[$clean_key] = sanitize_text_field($value);
                } else {
                    // For other types, convert to string and sanitize
                    $sanitized[$clean_key] = sanitize_text_field((string)$value);
                }
            }
        }
        
        return $sanitized;
    }


    public function delete_option() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
    
        // Ensure the user has the right capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }
    
        // Sanitize and validate input data
        $option_name = isset($_POST['option_name']) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';
    
        if (empty($option_name)) {
            wp_send_json_error('Option name is required');
            wp_die();
        }

        if (in_array($option_name, $this->get_protected_options())) {
            wp_send_json_error('This option is protected and cannot be deleted');
            wp_die();
        }
    
        // Delete the option
        if (delete_option($option_name)) {
            wp_send_json_success('Option deleted successfully');
        } else {
            wp_send_json_error('Failed to delete option');
        }
    
        wp_die();
    }

    public function option_usage_analytics() {
        // Verify nonce and permissions
        if (!isset($_GET['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'options';
    
        // Query to get all option names
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results("SELECT option_name FROM {$wpdb->prefix}options", ARRAY_A);
        
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
        
        wp_send_json_success($data);
        wp_die();
    }

    public function usermeta_table_data() {
        // Verify nonce first
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
    
        global $wpdb;
    
        // Pagination parameters
        $start = isset($_GET['start']) ? max(0, intval($_GET['start'])) : 0;
        $length = isset($_GET['length']) ? min(max(1, intval($_GET['length'])), 100) : 10;
    
        // Search parameter
        $search = isset($_GET['search']['value']) ? sanitize_text_field(wp_unslash($_GET['search']['value'])) : '';
    
        // Sorting parameters
        $order_column_index = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
        $order_direction = isset($_GET['order'][0]['dir']) && in_array($_GET['order'][0]['dir'], ['asc', 'desc']) ? 
            strtolower(sanitize_text_field(wp_unslash($_GET['order'][0]['dir']))) : 'asc';
        
        // Define valid columns for usermeta table
        $columns = ['umeta_id', 'user_id', 'meta_key', 'meta_value'];
        
        // Validate order column
        if ($order_column_index < 0 || $order_column_index >= count($columns)) {
            $order_column_index = 0;
        }
        $order_column = $columns[$order_column_index];
    
        // Total records count
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_records = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}usermeta"
        );
    
        // Main query logic
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $filtered_records = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}usermeta 
                    WHERE meta_key LIKE %s OR meta_value LIKE %s",
                    $search_like,
                    $search_like
                )
            );
    
            // Handle order with complete prepared statements
            if ($order_column === 'umeta_id') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            WHERE meta_key LIKE %s OR meta_value LIKE %s
                            ORDER BY umeta_id DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            WHERE meta_key LIKE %s OR meta_value LIKE %s
                            ORDER BY umeta_id ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'user_id') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            WHERE meta_key LIKE %s OR meta_value LIKE %s
                            ORDER BY user_id DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            WHERE meta_key LIKE %s OR meta_value LIKE %s
                            ORDER BY user_id ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'meta_key') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            WHERE meta_key LIKE %s OR meta_value LIKE %s
                            ORDER BY meta_key DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            WHERE meta_key LIKE %s OR meta_value LIKE %s
                            ORDER BY meta_key ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } else { // meta_value
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            WHERE meta_key LIKE %s OR meta_value LIKE %s
                            ORDER BY meta_value DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            WHERE meta_key LIKE %s OR meta_value LIKE %s
                            ORDER BY meta_value ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $start, $length
                        ),
                        ARRAY_A
                    );
                }
            }
        } else {
            $filtered_records = $total_records;
            
            // Handle order without search
            if ($order_column === 'umeta_id') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            ORDER BY umeta_id DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            ORDER BY umeta_id ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'user_id') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            ORDER BY user_id DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            ORDER BY user_id ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'meta_key') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            ORDER BY meta_key DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            ORDER BY meta_key ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } else { // meta_value
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            ORDER BY meta_value DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}usermeta 
                            ORDER BY meta_value ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            }
        }
    
        // Format output
        foreach ($data as &$row) {
            $is_protected = in_array($row['meta_key'], $this->get_protected_usermetas());
            $protected_attr = $is_protected ? sprintf('title="%s" disabled', esc_attr__('Protected', 'nhrrob-options-table-manager')) : '';
            
            // phpcs:ignore:WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            $row['meta_value'] = '<div class="scrollable-cell">' . esc_html($row['meta_value']) . '</div>';
            $row['actions'] = sprintf(
                '<button class="nhrotm-edit-button-usermeta" data-id="%s" %s>Edit</button>
                <button class="nhrotm-delete-button-usermeta" data-id="%s" %s>Delete</button>',
                esc_attr($row['umeta_id']),
                $protected_attr,
                esc_attr($row['umeta_id']),
                $protected_attr,
            );
        }
    
        $response = [
            "draw" => isset($_GET['draw']) ? intval($_GET['draw']) : 0,
            "recordsTotal" => $total_records,
            "recordsFiltered" => $filtered_records,
            "data" => $data
        ];
    
        wp_send_json($response);
        wp_die();
    }    

    public function edit_usermeta() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
    
        // Ensure the user has the right capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }
    
        // Sanitize and validate input data
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $meta_key = isset($_POST['meta_key']) ? sanitize_text_field( wp_unslash( $_POST['meta_key'] ) ) : '';
        $meta_value = isset($_POST['meta_value']) ? stripslashes_deep(sanitize_text_field( wp_unslash( $_POST['meta_value'] ) )) : '';
    
        if (empty($user_id)) {
            wp_send_json_error('User ID is invalid');
            wp_die();
        }
        
        if (empty($meta_key)) {
            wp_send_json_error('Meta key is required');
            wp_die();
        }

        if (in_array($meta_key, $this->get_protected_usermetas())) {
            wp_send_json_error('This meta is protected and cannot be edited');
            wp_die();
        }
    
        // Update the option
        if (update_user_meta( $user_id, $meta_key, $meta_value )) {
            wp_send_json_success('Meta updated successfully');
        } else {
            wp_send_json_error('Failed to update meta');
        }
    
        wp_die();
    }

    public function delete_usermeta() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
    
        // Ensure the user has the right capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }
    
        // Sanitize and validate input data
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $meta_key = isset($_POST['meta_key']) ? sanitize_text_field( wp_unslash( $_POST['meta_key'] ) ) : '';
    
        if (empty($user_id)) {
            wp_send_json_error('User id is invalid');
            wp_die();
        }
        
        if (empty($meta_key)) {
            wp_send_json_error('Meta key is required');
            wp_die();
        }

        if (in_array($meta_key, $this->get_protected_usermetas())) {
            wp_send_json_error('This meta is protected and cannot be deleted');
            wp_die();
        }
    
        // Delete the option
        if (delete_user_meta($user_id, $meta_key)) {
            wp_send_json_success('Option deleted successfully');
        } else {
            wp_send_json_error('Failed to delete option');
        }
    
        wp_die();
    }

    public function better_payment_table_data() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'better_payment';
        
        // Pagination parameters
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        
        // Search parameter
        $search = isset($_GET['search']['value']) ? sanitize_text_field( wp_unslash( $_GET['search']['value'] ) ) : '';
        
        // Sorting parameters
        $order_column_index = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
        $order_direction = isset($_GET['order'][0]['dir']) && in_array($_GET['order'][0]['dir'], ['asc', 'desc']) ? strtolower( sanitize_text_field( wp_unslash( $_GET['order'][0]['dir'] ) ) ) : 'desc';
    
        // Define columns in the correct order for sorting
        $columns = ['id', 'transaction_id', 'amount', 'status', 'source', 'payment_date', 'email', 'form_fields_info', 'currency'];
        
        // Ensure order column is valid using whitelist approach
        if ($order_column_index < 0 || $order_column_index >= count($columns)) {
            $order_column_index = 0; // Default to first column
        }
        $order_column = $columns[$order_column_index];
        
        // Get total record count with prepared statement
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_records = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}better_payment"
        );
        
        // Search and ordering logic
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            
            // Get filtered count
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $filtered_records = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}better_payment WHERE 
                    id LIKE %s OR 
                    transaction_id LIKE %s OR 
                    amount LIKE %s OR 
                    status LIKE %s OR 
                    source LIKE %s OR 
                    payment_date LIKE %s OR 
                    email LIKE %s OR 
                    form_fields_info LIKE %s OR 
                    currency LIKE %s",
                    $search_like, $search_like, $search_like, $search_like, 
                    $search_like, $search_like, $search_like, $search_like, $search_like
                )
            );
            
            // Set up search parameters for reuse
            $search_params = array(
                $search_like, $search_like, $search_like, $search_like,
                $search_like, $search_like, $search_like, $search_like, $search_like
            );
            
            // Complete hardcoded query paths for each possible order column and direction
            if ($order_column === 'id') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY id DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY id ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'transaction_id') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY transaction_id DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY transaction_id ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'amount') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY amount DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY amount ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'status') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY status DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY status ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'source') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY source DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY source ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'payment_date') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY payment_date DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY payment_date ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'email') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY email DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY email ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'form_fields_info') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY form_fields_info DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY form_fields_info ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'currency') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY currency DESC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            WHERE id LIKE %s OR 
                            transaction_id LIKE %s OR 
                            amount LIKE %s OR 
                            status LIKE %s OR 
                            source LIKE %s OR 
                            payment_date LIKE %s OR 
                            email LIKE %s OR 
                            form_fields_info LIKE %s OR 
                            currency LIKE %s
                            ORDER BY currency ASC
                            LIMIT %d, %d",
                            $search_like, $search_like, $search_like, $search_like, 
                            $search_like, $search_like, $search_like, $search_like, $search_like,
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } else {
                // Default fallback if column is not recognized
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $data = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}better_payment 
                        WHERE id LIKE %s OR 
                        transaction_id LIKE %s OR 
                        amount LIKE %s OR 
                        status LIKE %s OR 
                        source LIKE %s OR 
                        payment_date LIKE %s OR 
                        email LIKE %s OR 
                        form_fields_info LIKE %s OR 
                        currency LIKE %s
                        ORDER BY id DESC
                        LIMIT %d, %d",
                        $search_like, $search_like, $search_like, $search_like, 
                        $search_like, $search_like, $search_like, $search_like, $search_like,
                        $start, $length
                    ),
                    ARRAY_A
                );
            }
        } else {
            // No search applied, use total as filtered count
            $filtered_records = $total_records;
            
            // Complete hardcoded query paths for each possible order column and direction without search
            if ($order_column === 'id') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY id DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY id ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'transaction_id') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY transaction_id DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY transaction_id ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'amount') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY amount DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY amount ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'status') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY status DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY status ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'source') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY source DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY source ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'payment_date') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY payment_date DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY payment_date ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'email') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY email DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY email ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'form_fields_info') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY form_fields_info DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY form_fields_info ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } elseif ($order_column === 'currency') {
                if ($order_direction === 'desc') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY currency DESC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}better_payment 
                            ORDER BY currency ASC
                            LIMIT %d, %d",
                            $start, $length
                        ),
                        ARRAY_A
                    );
                }
            } else {
                // Default fallback if column is not recognized
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $data = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}better_payment 
                        ORDER BY id DESC
                        LIMIT %d, %d",
                        $start, $length
                    ),
                    ARRAY_A
                );
            }
        }
        
        // Format the data
        foreach ($data as &$row) {
            $row['amount'] = esc_html($row['currency'] . ' ' . $row['amount']);
            $row['payment_date'] = esc_html(wp_date(get_option('date_format'), strtotime($row['payment_date'])));
            $row['form_fields_info'] = '<div class="scrollable-cell">' . esc_html($row['form_fields_info']) . '</div>';
            // Uncomment if you need action buttons
            // $row['actions'] = '<button class="nhrotm-edit-payment" data-id="' . esc_attr($row['id']) . '">Edit</button>
            //     <button class="nhrotm-delete-payment" data-id="' . esc_attr($row['id']) . '">Delete</button>';
        }
        
        // Prepare response for DataTables
        $response = array(
            "draw" => isset($_GET['draw']) ? intval($_GET['draw']) : 0,
            "recordsTotal" => $total_records,
            "recordsFiltered" => $filtered_records,
            "data" => $data
        );
        
        wp_send_json($response);
    }
    
}
