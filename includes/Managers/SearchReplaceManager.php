<?php
namespace Nhrotm\OptionsTableManager\Managers;

/**
 * Class SearchReplaceManager
 * 
 * Handles site-wide string replacements in the wp_options table.
 */
class SearchReplaceManager extends BaseTableManager
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
        return ['option_name', 'option_value'];
    }

    public function get_data() { return []; }
    public function edit_record() { return false; }
    public function delete_record() { return false; }

    /**
     * Preview search results
     * 
     * @param string $search
     * @return array
     */
    public function preview_search($search)
    {
        if (empty($search)) {
            throw new \Exception('Search string cannot be empty');
        }

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT option_name, option_value FROM $this->table_name WHERE option_value LIKE %s",
                '%' . $this->wpdb->esc_like($search) . '%'
            ),
            ARRAY_A
        );

        $matches = [];
        foreach ($results as $row) {
            $value = $row['option_value'];
            $count = substr_count($value, $search);
            
            if ($count > 0) {
                $matches[] = [
                    'option_name' => $row['option_name'],
                    'occurrences' => $count
                ];
            }
        }

        return $matches;
    }

    /**
     * Execute search and replace
     * 
     * @param string $search
     * @param string $replace
     * @param bool $dry_run
     * @return array
     */
    public function execute_replace($search, $replace, $dry_run = true)
    {
        if (empty($search)) {
            throw new \Exception('Search string cannot be empty');
        }

        $this->validate_permissions();

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT option_name, option_value FROM $this->table_name WHERE option_value LIKE %s",
                '%' . $this->wpdb->esc_like($search) . '%'
            ),
            ARRAY_A
        );

        $updated_options = [];
        $total_occurrences = 0;

        foreach ($results as $row) {
            $option_name = $row['option_name'];
            $original_value = $row['option_value'];
            
            // Skip protected options for safety
            if ($this->is_protected_item($option_name)) {
                continue;
            }

            $processed_value = $original_value;
            $occurrences = 0;

            if (is_serialized($original_value)) {
                $data = unserialize($original_value);
                $occurrences = $this->recursive_replace($data, $search, $replace);
                $processed_value = serialize($data);
            } elseif ($this->is_json($original_value)) {
                $data = json_decode($original_value, true);
                $occurrences = $this->recursive_replace($data, $search, $replace);
                $processed_value = json_encode($data);
            } else {
                $processed_value = str_replace($search, $replace, $original_value, $count);
                $occurrences = $count;
            }

            if ($occurrences > 0) {
                if (!$dry_run) {
                    $this->wpdb->update(
                        $this->table_name,
                        ['option_value' => $processed_value],
                        ['option_name' => $option_name]
                    );
                }

                $updated_options[] = [
                    'option_name' => $option_name,
                    'occurrences' => $occurrences
                ];
                $total_occurrences += $occurrences;
            }
        }

        return [
            'total_updated' => count($updated_options),
            'total_occurrences' => $total_occurrences,
            'details' => $updated_options,
            'dry_run' => $dry_run
        ];
    }

    /**
     * Recursively replace strings in arrays/objects
     * 
     * @param mixed $data
     * @param string $search
     * @param string $replace
     * @return int Number of replacements made
     */
    private function recursive_replace(&$data, $search, $replace)
    {
        $count = 0;
        if (is_array($data) || is_object($data)) {
            foreach ($data as &$value) {
                $count += $this->recursive_replace($value, $search, $replace);
            }
        } elseif (is_string($data)) {
            $data = str_replace($search, $replace, $data, $temp_count);
            $count = $temp_count;
        }
        return $count;
    }

    /**
     * Check if a string is valid JSON
     * 
     * @param string $string
     * @return bool
     */
    private function is_json($string)
    {
        if (!is_string($string)) return false;
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
