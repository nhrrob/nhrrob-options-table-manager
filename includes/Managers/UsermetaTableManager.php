<?php
namespace Nhrotm\OptionsTableManager\Managers;

if (!defined('ABSPATH')) {
    exit;
}

use Exception;

class UsermetaTableManager extends BaseTableManager
{

    public function __construct()
    {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'usermeta';
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
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            // Build WHERE clause separately to satisfy literal requirements
            $where_sql = $wpdb->prepare(" WHERE (meta_key LIKE %s OR meta_value LIKE %s)", $search_like, $search_like);
        }

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $filtered_records = $wpdb->get_var("SELECT COUNT(*) FROM $table $where_sql");

        $order_sql = " ORDER BY $order_column $order_direction";
        $data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table $where_sql $order_sql LIMIT %d, %d",
                $start,
                $length
            ),
            ARRAY_A
        );
        // phpcs:enable

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
    public function edit_record()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
            throw new \Exception('Invalid nonce');
        }

        $this->validate_permissions();

        // Sanitize and validate input data
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $meta_key = isset($_POST['meta_key']) ? sanitize_text_field(wp_unslash($_POST['meta_key'])) : '';
        $meta_value = isset($_POST['meta_value']) ? stripslashes_deep(sanitize_text_field(wp_unslash($_POST['meta_value']))) : '';

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
    public function delete_record()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
            throw new \Exception('Invalid nonce');
        }

        $this->validate_permissions();

        // Sanitize and validate input data
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $meta_key = isset($_POST['meta_key']) ? sanitize_text_field(wp_unslash($_POST['meta_key'])) : '';

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
    protected function get_searchable_columns()
    {
        return ['umeta_id', 'user_id', 'meta_key', 'meta_value'];
    }
}