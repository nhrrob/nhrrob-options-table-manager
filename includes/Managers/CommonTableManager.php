<?php
namespace Nhrotm\OptionsTableManager\Managers;

use Exception;

class CommonTableManager extends BaseTableManager {

    protected $searchable_columns;

    public function __construct($table_name, $columns) {
        parent::__construct();
        $this->table_name = $table_name;
        $this->searchable_columns = $columns;
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
            // phpcs:ignore: WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT COUNT(*) FROM {$this->table_name}"
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
        
        // Build WHERE clause parts
        $where_parts = [];
        if (!empty($search)) {
            $search_like = '%' . $this->wpdb->esc_like($search) . '%';
            $search_sql_parts = [];
            foreach ($columns as $column) {
                $search_sql_parts[] = "{$column} LIKE %s";
            }
            $where_parts[] = "(" . implode(' OR ', $search_sql_parts) . ")";
        }
        
        // Count filtered records
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
        if (!empty($search)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_parts);
            $filtered_records = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} $where_clause",
                    ...$search_params_final
                )
            );
        } else {
            $filtered_records = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        }
        
        // Get data with search, order, and pagination
        if (!empty($search)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_parts);
            $query_params = array_merge($search_params_final, [$start, $length]);
            $data = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name} $where_clause ORDER BY {$order_column} {$order_direction} LIMIT %d, %d",
                    ...$query_params
                ),
                ARRAY_A
            );
        } else {
            $data = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name} ORDER BY {$order_column} {$order_direction} LIMIT %d, %d",
                    $start,
                    $length
                ),
                ARRAY_A
            );
        }
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
        
        // Wrap the option_value in the scrollable-cell div
        foreach ($data as &$row) {
            if ( ! empty($row['date']) ) {
                $row['date'] = esc_html(wp_date(get_option('date_format'), strtotime($row['date'])));
            }
            
            if ( ! empty($row['created_at']) ) {
                $row['created_at'] = esc_html(wp_date(get_option('date_format'), strtotime($row['created_at'])));
            }

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
        return $this->searchable_columns;
    }
}