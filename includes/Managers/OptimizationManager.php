<?php

namespace Nhrotm\OptionsTableManager\Managers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OptimizationManager
 *
 * Handles database optimization tasks, specifically analyzing and managing autoloaded options.
 */
class OptimizationManager extends BaseTableManager
{
    public function __construct()
    {
        parent::__construct();
        // Use standard wpdb property if available
        $this->table_name = !empty($this->wpdb->options) ? $this->wpdb->options : $this->wpdb->prefix . 'options';
    }

    /**
     * Get searchable columns (required by BaseTableManager)
     *
     * @return array
     */
    protected function get_searchable_columns()
    {
        return [];
    }

    public function get_data()
    {
        return [];
    }

    public function edit_record()
    {
        return false;
    }

    public function delete_record()
    {
        return false;
    }

    /**
     * Get heaviest autoloaded options
     *
     * @param int $limit Number of options to retrieve
     * @return array
     */
    public function get_heavy_autoload_options($limit = 20)
    {
        global $wpdb;
        $limit = intval($limit);
        $table = $this->table_name;

        // Query to get autoloaded options
        // Broaden the search to catch 'yes', 'true', '1', 'on' by excluding known 'no' values
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific query
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value, autoload, LENGTH(option_value) as size_bytes 
            FROM {$wpdb->options} 
            WHERE autoload NOT IN ('off', 'no', 'false', '0', '')
            ORDER BY size_bytes DESC 
            LIMIT %d",
            $limit
        ), ARRAY_A);

        return array_map(function ($row) {
            // Format size for display
            $row['size_formatted'] = size_format($row['size_bytes']);
            // Don't send full value, maybe just a snippet or nothing to save bandwidth
            $row['value_snippet'] = substr(wp_strip_all_tags($row['option_value']), 0, 100);
            unset($row['option_value']);
            return $row;
        }, $results);
    }

    /**
     * Toggle autoload status for an option
     *
     * @return bool
     */
    public function toggle_autoload()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
            throw new \Exception('Invalid nonce');
        }

        $this->validate_permissions();

        $option_name = isset($_POST['option_name']) ? sanitize_text_field(wp_unslash($_POST['option_name'])) : '';
        $new_status = isset($_POST['autoload_status']) ? sanitize_text_field(wp_unslash($_POST['autoload_status'])) : '';

        if (empty($option_name)) {
            throw new \Exception('Option name is required');
        }

        if (!in_array($new_status, ['yes', 'no'])) {
            throw new \Exception('Invalid status');
        }

        if ($this->is_protected_item($option_name)) {
            throw new \Exception('Cannot modify protected option');
        }

        // We use $wpdb update because update_option might fail if value is unchanged,
        // and we only want to change autoload.
        $result = $this->wpdb->update(
            $this->table_name,
            ['autoload' => $new_status],
            ['option_name' => $option_name],
            ['%s'],
            ['%s']
        );

        if ($result === false) {
            throw new \Exception('Database update failed');
        }

        return true;
    }

    /**
     * Get total autoload size
     *
     * @return string Formatted size
     */
    public function get_total_autoload_size()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific query
        $bytes = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload NOT IN ('no', 'false', '0', '')");
        return size_format($bytes ? $bytes : 0);
    }
}