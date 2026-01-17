<?php
namespace Nhrotm\OptionsTableManager\Managers;

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
        $this->table_name = ($this->wpdb && property_exists($this->wpdb, 'prefix'))
            ? $this->wpdb->prefix . 'options'
            : 'wp_options';
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
        $limit = intval($limit);

        // Query to get autoloaded options
        // We select name and length of value
        // Note: LENGTH() gives bytes in MySQL
        $sql = "SELECT option_name, option_value, LENGTH(option_value) as size_bytes 
                FROM $this->table_name 
                WHERE autoload = 'yes' 
                ORDER BY size_bytes DESC 
                LIMIT %d";

        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $limit), ARRAY_A);

        return array_map(function ($row) {
            // Format size for display
            $row['size_formatted'] = size_format($row['size_bytes']);
            // Don't send full value, maybe just a snippet or nothing to save bandwidth
            $row['value_snippet'] = substr(strip_tags($row['option_value']), 0, 100);
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
        $new_status = isset($_POST['autoload_status']) ? sanitize_text_field(wp_unslash($_POST['autoload_status'])) : ''; // 'yes' or 'no'

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
        // Actually update_option takes autoload as 3rd arg but it also requires value.
        // We don't want to fetch value just to update autoload if we can avoid it, 
        // to avoid memory issues with large options.

        $result = $this->wpdb->update(
            $this->table_name,
            ['autoload' => $new_status],
            ['option_name' => $option_name],
            ['%s'],
            ['%s']
        );

        // If result is 0, it might mean no change, but that's fine. 
        // If false, it's error.
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
        $sql = "SELECT SUM(LENGTH(option_value)) FROM $this->table_name WHERE autoload = 'yes'";
        $bytes = $this->wpdb->get_var($sql);
        return size_format($bytes ? $bytes : 0);
    }
}
