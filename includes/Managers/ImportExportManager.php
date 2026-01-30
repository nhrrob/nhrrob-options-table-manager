<?php
namespace Nhrotm\OptionsTableManager\Managers;

/**
 * Class ImportExportManager
 * 
 * Handles the export and import of selected options to/from JSON files.
 */
class ImportExportManager extends BaseTableManager
{
    public function __construct()
    {
        parent::__construct();
        $this->table_name = !empty($this->wpdb->options) ? $this->wpdb->options : $this->wpdb->prefix . 'options';
    }

    /**
     * Get searchable columns (required by BaseTableManager)
     */
    protected function get_searchable_columns()
    {
        return ['option_name'];
    }

    public function get_data() { return []; }
    public function edit_record() { return false; }
    public function delete_record() { return false; }

    /**
     * Export selected options to JSON structure
     * 
     * @param array $option_names List of option names to export
     * @return array
     */
    public function export_options($option_names)
    {
        $this->validate_permissions();
        
        if (empty($option_names)) {
            throw new \Exception('No options selected for export');
        }

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($option_names), '%s'));
        
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ($placeholders)", ...$option_names),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        $export_data = [
            'meta' => [
                'generated_at' => current_time('mysql'),
                'source_url' => get_site_url(),
                'version' => '1.0'
            ],
            'options' => []
        ];

        foreach ($results as $row) {
            // We export the raw value mostly, but handle serialization if needed by WordPress core logic
            // Ideally we export the exact string from DB to ensure it restores exactly
            $export_data['options'][] = [
                'name' => $row['option_name'],
                'value' => $row['option_value'], // Raw DB value
                'autoload' => $row['autoload']
            ];
        }

        $export_data['checksum'] = md5(json_encode($export_data['options']));

        return $export_data;
    }

    /**
     * Preview import data
     * 
     * @param array $json_data Parsed JSON data from uploaded file
     * @return array Summary of changes
     */
    public function preview_import($json_data)
    {
        $this->validate_permissions();
        
        if (!isset($json_data['options']) || !is_array($json_data['options'])) {
            throw new \Exception('Invalid import file structure');
        }

        // Validate checksum if present
        if (isset($json_data['checksum'])) {
            $calculated = md5(json_encode($json_data['options']));
            if ($calculated !== $json_data['checksum']) {
                throw new \Exception('File integrity check failed (Checksum mismatch)');
            }
        }

        $preview = [];

        foreach ($json_data['options'] as $item) {
            $name = $item['name'];
            $new_value = $item['value'];
            
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific query
            $existing_row = $wpdb->get_row(
                $wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name),
                ARRAY_A
            );

            $status = 'new';
            $current_value_preview = null;

            if ($existing_row) {
                $status = ($existing_row['option_value'] === $new_value) ? 'unchanged' : 'modified';
                // Create a brief snippet for preview
                $current_value_preview = substr($existing_row['option_value'], 0, 100) . (strlen($existing_row['option_value']) > 100 ? '...' : '');
            }

            $preview[] = [
                'name' => $name,
                'status' => $status,
                'current_snippet' => $current_value_preview,
                'autoload' => $item['autoload']
            ];
        }

        return $preview;
    }

    /**
     * Execute import for selected options
     * 
     * @param array $json_data Full import data
     * @param array $selected_options Array of option names user confirmed to import
     * @return int Count of imported options
     */
    public function execute_import($json_data, $selected_options)
    {
        $this->validate_permissions();
        
        if (empty($selected_options)) {
            return 0;
        }

        $imported_count = 0;
        $options_map = [];

        // Index options for faster lookup
        foreach ($json_data['options'] as $item) {
            $options_map[$item['name']] = $item;
        }

        foreach ($selected_options as $option_name) {
            if (!isset($options_map[$option_name])) {
                continue;
            }

            $option_data = $options_map[$option_name];
            
            global $wpdb;
            // Allow raw SQL replace to handle exact value restoration including serialization
            // using wpdb->replace handles INSERT OR UPDATE
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific import operation
            $result = $wpdb->replace(
                $this->table_name,
                [
                    'option_name' => sanitize_text_field($option_data['name']),
                    'option_value' => sanitize_text_field($option_data['value']),
                    'autoload' => sanitize_text_field($option_data['autoload'])
                ],
                ['%s', '%s', '%s']
            );

            if ($result !== false) {
                $imported_count++;
            }
        }

        return $imported_count;
    }

    /**
     * Search available options for export builder
     * 
     * @param string $term
     * @return array
     */
    public function search_options_for_export($term)
    {
        $this->validate_permissions();
        
        global $wpdb;
        $term = '%' . $wpdb->esc_like($term) . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific query
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 20", sanitize_text_field($term)),
            ARRAY_A
        );

        return array_column($results, 'option_name');
    }
}
