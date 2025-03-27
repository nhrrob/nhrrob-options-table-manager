<?php
namespace Nhrotm\OptionsTableManager\Managers;

use Exception;
use Nhrotm\OptionsTableManager\Traits\GlobalTrait;

class UsermetaTableManager extends BaseTableManager {

    protected $table_name; // declared in base class. may be not needed here.

    public function __construct() {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'usermeta';
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
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}usermeta"
        );

        // Build WHERE clause for search conditions
        $where_clauses = [];
        
        // Global search
        if (!empty($search)) {
            $search_like = '%' . $this->wpdb->esc_like($search) . '%';
            $where_clauses[] = $this->wpdb->prepare(
                "(meta_key LIKE %s OR meta_value LIKE %s)",
                $search_like,
                $search_like
            );
        }
        
        // Combine WHERE clauses
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Count filtered records
        $filtered_records_sql = "SELECT COUNT(*) FROM {$this->wpdb->prefix}usermeta {$where_sql}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $filtered_records = $this->wpdb->get_var($filtered_records_sql);
        
        // SQL for ordering
        $order_sql = "ORDER BY {$order_column} {$order_direction}";
        
        // Get data with search, order, and pagination
        $data_sql = "SELECT * FROM {$this->wpdb->prefix}usermeta {$where_sql} {$order_sql} LIMIT %d, %d";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $data = $this->wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $this->wpdb->prepare($data_sql, $start, $length),
            ARRAY_A
        );
        
        // Wrap the option_value in the scrollable-cell div
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
                $protected_attr
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

    /**
     * Edit an option
     * 
     * @return bool Success status
     */
    public function edit_record() {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));

        $this->validate_permissions();
        $this->validate_nonce($nonce);

        // Sanitize and validate input data
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $meta_key = isset($_POST['meta_key']) ? sanitize_text_field( wp_unslash( $_POST['meta_key'] ) ) : '';
        $meta_value = isset($_POST['meta_value']) ? stripslashes_deep(sanitize_text_field( wp_unslash( $_POST['meta_value'] ) )) : '';
    
        if (empty($user_id)) {
            throw new \Exception('User ID is invalid');
        }

        if (empty($meta_key)) {
            throw new \Exception('Meta key is required');
        }

        if ($this->is_protected_item($meta_key, $this->table_name)) {
            throw new \Exception('This meta is protected and cannot be edited');
        }
        
        return update_user_meta($user_id, $meta_key, $meta_value);        
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

        // Sanitize and validate input data
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $meta_key = isset($_POST['meta_key']) ? sanitize_text_field( wp_unslash( $_POST['meta_key'] ) ) : '';
    
        if (empty($user_id)) {
            throw new \Exception('User id is invalid');
        }

        if (empty($meta_key)) {
            throw new \Exception('Meta key is required');
        }

        if ($this->is_protected_item($meta_key, $this->table_name)) {
            throw new \Exception('This meta is protected and cannot be deleted');
        }

        return delete_user_meta($user_id, $meta_key);
    }

    /**
     * Get searchable columns
     * 
     * @return array
     */
    protected function get_searchable_columns() {
        return ['umeta_id', 'user_id', 'meta_key', 'meta_value'];
    }
}