<?php
namespace Nhrotm\OptionsTableManager\Managers;

/**
 * Class ScannerManager
 * 
 * Handles identification of orphaned options from uninstalled plugins and themes.
 */
class ScannerManager extends BaseTableManager
{
    /**
     * Map of common plugin prefixes to their friendly names
     * 
     * @var array
     */
    private $prefix_map = [
        'akismet_' => 'Akismet',
        'autoptimize_' => 'Autoptimize',
        'contact_form_7' => 'Contact Form 7',
        'elementor_' => 'Elementor',
        'eael_' => 'Essential Addons for Elementor',
        'itsec_' => 'iThemes Security',
        'jetpack_' => 'Jetpack',
        'rank_math_' => 'Rank Math',
        'smush_' => 'Smush',
        'updraftplus_' => 'UpdraftPlus',
        'w3tc_' => 'W3 Total Cache',
        'woocommerce_' => 'WooCommerce',
        'wordfence_' => 'Wordfence',
        'wpforms_' => 'WPForms',
        'wp_rocket_' => 'WP Rocket',
        'wpseo_' => 'Yoast SEO',
    ];

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
        return [];
    }

    public function get_data() { return []; }
    public function edit_record() { return false; }
    public function delete_record() { return false; }

    /**
     * Scan for orphaned options
     * 
     * @return array List of orphaned prefixes and their associated options
     */
    public function scan_orphans()
    {
        global $wpdb;

        // 1. Get all unique prefixes from wp_options
        // We'll look for anything followed by an underscore as a prefix candidate
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific query
        $results = $wpdb->get_results("SELECT option_name FROM {$wpdb->options}", ARRAY_A);
        
        $prefixes = [];
        foreach ($results as $row) {
            $name = sanitize_text_field($row['option_name']);
            
            // Remove _transient and _timeout to get the real prefix
            $clean_name = preg_replace('/^_transient(?:_timeout)?_/', '', $name);
            
            $parts = explode('_', $clean_name);
            if (count($parts) > 1) {
                $prefix = $parts[0] . '_';
                if (!isset($prefixes[$prefix])) {
                    $prefixes[$prefix] = 0;
                }
                $prefixes[$prefix]++;
            }
        }

        // 2. Get list of active and inactive plugins
        $all_plugins = get_plugins();
        $plugin_dirs = [];
        foreach (array_keys($all_plugins) as $plugin_file) {
            $parts = explode('/', $plugin_file);
            if (count($parts) > 1) {
                $plugin_dirs[] = $parts[0];
            } else {
                // For single file plugins like hello.php
                $plugin_dirs[] = str_replace('.php', '', $plugin_file);
            }
        }

        // 3. Identification logic
        $orphans = [];
        $protected_prefixes = ['wp_', 'user_', 'widget_', 'theme_', 'rss_', 'sticky_'];

        foreach ($prefixes as $prefix => $count) {
            $prefix_clean = rtrim($prefix, '_');

            // Skip protected
            if (in_array($prefix, $protected_prefixes)) {
                continue;
            }

            // Check if prefix matches a plugin directory
            $found = false;
            foreach ($plugin_dirs as $dir) {
                if (stripos($dir, $prefix_clean) !== false || stripos($prefix_clean, $dir) !== false) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $orphans[] = [
                    'prefix' => $prefix,
                    'count'  => $count,
                    'possible_source' => isset($this->prefix_map[$prefix]) ? $this->prefix_map[$prefix] : 'Unknown Plugin/Theme',
                    'risk' => isset($this->prefix_map[$prefix]) ? 'Medium' : 'High'
                ];
            }
        }

        // Sort by count descending
        usort($orphans, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return $orphans;
    }

    /**
     * Bulk delete options by prefix
     * 
     * @param string $prefix
     * @return int Number of rows deleted
     */
    public function delete_by_prefix($prefix)
    {
        $this->validate_permissions();
        
        if (empty($prefix) || strlen($prefix) < 3) {
            throw new \Exception('Invalid prefix for deletion');
        }

        // Safety check: Don't delete protected prefixes
        $protected = ['wp_', 'user_', 'widget_', 'theme_'];
        if (in_array($prefix, $protected)) {
            throw new \Exception('Cannot delete protected core prefixes');
        }

        $pattern = $this->wpdb->esc_like($prefix) . '%';
        
        // Also handle transients
        $transient_pattern = '_transient_' . $pattern;
        $timeout_pattern = '_transient_timeout_' . $pattern;

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific deletion
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
                $pattern,
                $transient_pattern,
                $timeout_pattern
            )
        );
    }
}
