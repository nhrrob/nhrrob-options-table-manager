<?php
namespace Nhrotm\OptionsTableManager\Managers;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;

class BetterPaymentTableManager extends BaseTableManager
{

    public function __construct()
    {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'better_payment';
    }

    /**
     * Retrieve options data
     * 
     * @return array Options data
     */
    public function get_data()
    {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'nhrotm-admin-nonce')) {
            throw new \Exception('Invalid nonce');
        }

        $this->validate_permissions();

        global $wpdb;
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        $search = isset($_GET['search']['value']) ? sanitize_text_field(wp_unslash($_GET['search']['value'])) : '';
        $order_column_index = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
        $order_direction = isset($_GET['order'][0]['dir']) && in_array($_GET['order'][0]['dir'], ['asc', 'desc']) ? strtolower(sanitize_text_field(wp_unslash($_GET['order'][0]['dir']))) : 'asc';

        $columns = $this->get_searchable_columns();
        if ($order_column_index < 0 || $order_column_index >= count($columns)) {
            $order_column_index = 0;
        }
        $order_column = $columns[$order_column_index];
        $table = $this->table_name;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Count filtered records
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $filtered_records_sql = "SELECT COUNT(*) FROM {$this->wpdb->prefix}better_payment {$where_sql}";
        
        if (!empty($search)) {
            $filtered_records = $this->wpdb->get_var(
                $this->wpdb->prepare($filtered_records_sql, ...$search_params_final)
            );
        } else {
            $filtered_records = $this->wpdb->get_var($filtered_records_sql);
        }
        
        // SQL for ordering
        $order_sql = "ORDER BY {$order_column} {$order_direction}";
        
        // Get data with search, order, and pagination
        $data_sql = "SELECT * FROM {$this->wpdb->prefix}better_payment {$where_sql} {$order_sql} LIMIT %d, %d";
        
        if (!empty($search)) {
            $query_params = array_merge($search_params_final, [$start, $length]);
            $data = $this->wpdb->get_results(
                $this->wpdb->prepare($data_sql, ...$query_params),
                ARRAY_A
            );
        } else {
            $data = $this->wpdb->get_results(
                $this->wpdb->prepare($data_sql, $start, $length),
                ARRAY_A
            );
        }
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
        
        // Wrap the option_value in the scrollable-cell div
        foreach ($data as &$row) {
            $row['amount'] = esc_html($row['currency'] . ' ' . $row['amount']);
            $row['payment_date'] = esc_html(wp_date(get_option('date_format'), strtotime($row['payment_date'])));
            $row['form_fields_info'] = '<div class="scrollable-cell">' . esc_html($row['form_fields_info']) . '</div>';
            // Uncomment if you need action buttons
            // $row['actions'] = sprintf(
            //     '<button class="nhrotm-edit-payment" data-id="%s">Edit</button>
            //     <button class="nhrotm-delete-payment" data-id="%s">Delete</button>',
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
    public function edit_record()
    {
        // Not planned        
        return false;
    }

    /**
     * Delete an option
     * 
     * @param array $data Option data to delete
     * @return bool Success status
     */
    public function delete_record()
    {
        // Not planned
        return false;
    }

    /**
     * Get searchable columns
     * 
     * @return array
     */
    protected function get_searchable_columns()
    {
        return ['id', 'transaction_id', 'amount', 'status', 'source', 'payment_date', 'email', 'form_fields_info', 'currency'];
    }
}