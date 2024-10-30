<?php

namespace Nhrotm\OptionsTableManager;

/**
 * Ajax handler class
 */
class Ajax extends App{

    /**
     * Class constructor
     */
    function __construct() {
        add_action('wp_ajax_nhrotm_option_table_data', [ $this, 'option_table_data' ]);
        add_action('wp_ajax_nhrotm_add_option', [ $this, 'add_option' ]);
        add_action('wp_ajax_nhrotm_edit_option', [ $this, 'edit_option' ]);
        add_action('wp_ajax_nhrotm_delete_option', [ $this, 'delete_option' ]);
        add_action('wp_ajax_nhrotm_option_usage_analytics', [ $this, 'option_usage_analytics' ]);
    }

    public function option_table_data() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'options';

        // Pagination parameters
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        
        // Search parameter
        $search = isset($_GET['search']['value']) ? sanitize_text_field($_GET['search']['value']) : '';
        
        // Sorting parameters
        $order_column_index = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
        $order_direction = isset($_GET['order'][0]['dir']) && in_array($_GET['order'][0]['dir'], ['asc', 'desc']) ? sanitize_text_field( $_GET['order'][0]['dir'] ) : 'asc';

        // Define columns in the correct order for sorting
        $columns = ['option_id', 'option_name', 'option_value', 'autoload'];
        $order_column = $columns[$order_column_index] ?? $columns[0];

        // Get total record count
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Build query with search, filtering, and sorting
        $query = "SELECT * FROM $table_name";
        if (!empty($search)) {
            $query .= $wpdb->prepare(" WHERE option_name LIKE %s OR option_value LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
        }
        $filtered_records = $wpdb->get_var("SELECT COUNT(*) FROM ($query) AS temp");
        
        $query .= " ORDER BY $order_column $order_direction LIMIT $start, $length";

        // Execute query
        $data = $wpdb->get_results($query, ARRAY_A);

        // Wrap the option_value in the scrollable-cell div
        foreach ($data as &$row) {
            $row['option_value']    = '<div class="scrollable-cell">' . esc_html($row['option_value']) . '</div>';
            $row['actions']         = '<button class="nhrotm-edit-button" data-id="' . esc_attr($row['option_id']) . '">Edit</button>
                                       <button class="nhrotm-delete-button" data-id="' . esc_attr($row['option_id']) . '">Delete</button>';
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

    public function add_option() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }

        // Ensure the user has the right capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }

        // Sanitize and validate input data
        $option_name = isset($_POST['new_option_name']) ? sanitize_text_field($_POST['new_option_name']) : '';
        $option_value = isset($_POST['new_option_value']) ? stripslashes_deep(sanitize_text_field($_POST['new_option_value'])) : '';
        $autoload = isset($_POST['new_option_autoload']) ? sanitize_text_field($_POST['new_option_autoload']) : 'no';

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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
    
        // Ensure the user has the right capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }
    
        // Sanitize and validate input data
        $option_name = isset($_POST['option_name']) ? sanitize_text_field($_POST['option_name']) : '';
        $option_value = isset($_POST['option_value']) ? stripslashes_deep(sanitize_text_field($_POST['option_value'])) : '';
    
        if (empty($option_name)) {
            wp_send_json_error('Option name is required');
            wp_die();
        }

        if (in_array($option_name, $this->get_protected_options())) {
            wp_send_json_error('This option is protected and cannot be edited');
            wp_die();
        }
    
        // Update the option
        if (update_option($option_name, $option_value)) {
            wp_send_json_success('Option updated successfully');
        } else {
            wp_send_json_error('Failed to update option');
        }
    
        wp_die();
    }

    public function delete_option() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
    
        // Ensure the user has the right capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }
    
        // Sanitize and validate input data
        $option_name = isset($_POST['option_name']) ? sanitize_text_field($_POST['option_name']) : '';
    
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
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'nhrotm-admin-nonce')) {
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
        $results = $wpdb->get_results("SELECT option_name FROM $table_name", ARRAY_A);
        
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
    
}
