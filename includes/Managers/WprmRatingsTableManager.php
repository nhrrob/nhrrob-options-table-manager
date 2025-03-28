<?php
namespace Nhrotm\OptionsTableManager\Managers;

use Exception;

class WprmRatingsTableManager extends BaseTableManager {

    public function __construct() {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'wprm_ratings';
    }

    /**
     * Retrieve options data
     * 
     * @return array Options data
     */
    public function get_data() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'nhrotm-admin-nonce')) {
            throw new \Exception('Invalid nonce');
        }

        $this->validate_permissions();

        global $wpdb;
        
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        
        // Search parameter
        $search = isset($_GET['search']['value']) ? sanitize_text_field(wp_unslash($_GET['search']['value'])) : '';

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
            "SELECT COUNT(*) FROM {$wpdb->prefix}wprm_ratings"
        );

        // Build WHERE clause for search conditions
        $where_clauses = [];
        
        // Global search
        if (!empty($search)) {
            $search_like = '%' . $this->wpdb->esc_like($search) . '%';
            $search_params = [];
            $search_sql_parts = [];
            
            // Add search for each column
            foreach ($columns as $column) {
                $search_sql_parts[] = "{$column} LIKE %s";
                $search_params[] = $search_like;
            }
            
            $where_clauses[] = "(" . implode(' OR ', $search_sql_parts) . ")";
            $search_params_final = $search_params;
        }
        
        // Combine WHERE clauses
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Count filtered records
        $filtered_records_sql = "SELECT COUNT(*) FROM {$this->wpdb->prefix}wprm_ratings {$where_sql}";
        
        if (!empty($search)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $filtered_records = $this->wpdb->get_var(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $this->wpdb->prepare($filtered_records_sql, ...$search_params_final)
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $filtered_records = $this->wpdb->get_var($filtered_records_sql);
        }
        
        // SQL for ordering
        $order_sql = "ORDER BY {$order_column} {$order_direction}";
        
        // Get data with search, order, and pagination
        $data_sql = "SELECT * FROM {$this->wpdb->prefix}wprm_ratings {$where_sql} {$order_sql} LIMIT %d, %d";
        
        if (!empty($search)) {
            $query_params = array_merge($search_params_final, [$start, $length]);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $data = $this->wpdb->get_results(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $this->wpdb->prepare($data_sql, ...$query_params),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $data = $this->wpdb->get_results(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $this->wpdb->prepare($data_sql, $start, $length),
                ARRAY_A
            );
        }
        
        // Wrap the option_value in the scrollable-cell div
        foreach ($data as &$row) {
            $row['date'] = esc_html(wp_date(get_option('date_format'), strtotime($row['date'])));
            // Uncomment if you need action buttons
            // $row['actions'] = sprintf(
            //     '<button class="nhrotm-edit-record" data-id="%s">Edit</button>
            //     <button class="nhrotm-delete-record" data-id="%s">Delete</button>',
            //     esc_attr($row['id']),
            //     esc_attr($row['id'])
            // );
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

    /**
     * Edit an option
     * 
     * @return bool Success status
     */
    public function edit_record() {
        // Not planned        
    }

    /**
     * Delete an option
     * 
     * @param array $data Option data to delete
     * @return bool Success status
     */
    public function delete_record() {
        // Not planned
    }

    /**
     * Get searchable columns
     * 
     * @return array
     */
    protected function get_searchable_columns() {
        return ['id', 'date', 'recipe_id', 'post_id', 'comment_id', 'approved', 'has_comment', 'user_id', 'ip', 'rating'];
    }
}