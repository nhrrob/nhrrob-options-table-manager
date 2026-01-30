<?php
namespace Nhrotm\OptionsTableManager\Cli;

if (!defined('WP_CLI')) {
    return;
}

/**
 * Manage options via WP-CLI.
 */
class CliCommands extends \WP_CLI_Command
{
    /**
     * List top autoloaded options.
     * 
     * ## OPTIONS
     * 
     * [--limit=<number>]
     * : Number of options to show. Default is 20.
     * 
     * [--search=<search>]
     * : Search term for option name.
     * 
     * [--format=<format>]
     * : Output format (table, json, csv, yaml). Default is table.
     * 
     * ## EXAMPLES
     * 
     *     wp nhr-options list --limit=10
     *     wp nhr-options list --search=woocommerce_
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function list($args, $assoc_args)
    {

        global $wpdb;

        $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : 20;
        $search = isset($assoc_args['search']) ? sanitize_text_field($assoc_args['search']) : '';
        $format = isset($assoc_args['format']) ? sanitize_text_field($assoc_args['format']) : 'table';

        $query = "SELECT option_name, LENGTH(option_value) as size, autoload FROM {$wpdb->options}";
        
        $where_clauses = ["1=1"];
        $query_args = [];

        // Build SQL and execute based on search condition
        if (!empty($search)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic WHERE clause built safely
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT option_name, LENGTH(option_value) as size, autoload 
                FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                ORDER BY size DESC 
                LIMIT %d",
                '%' . $wpdb->esc_like($search) . '%',
                $limit
            ), ARRAY_A);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- No dynamic WHERE clause
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT option_name, LENGTH(option_value) as size, autoload 
                FROM {$wpdb->options} 
                WHERE 1=1 
                ORDER BY size DESC 
                LIMIT %d",
                $limit
            ), ARRAY_A);
        }

        // Format size
        foreach ($results as &$row) {
            $row['size'] = \size_format($row['size']);
        }

        \WP_CLI\Utils\format_items($format, $results, ['option_name', 'size', 'autoload']);
    }

    /**
     * Delete options by prefix.
     * 
     * ## OPTIONS
     * 
     * <prefix>
     * : The prefix of options to delete.
     * 
     * [--dry-run]
     * : Check what would be deleted without actually deleting.
     * 
     * [--yes]
     * : Skip confirmation.
     * 
     * ## EXAMPLES
     * 
     *     wp nhr-options delete subheading_ --dry-run
     *     wp nhr-options delete subheading_
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function delete($args, $assoc_args)
    {


        global $wpdb;

        $prefix = $args[0];
        $dry_run = \WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run');
        
        if (empty($prefix)) {
            \WP_CLI::error("Prefix is required.");
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI command query
        $options = $wpdb->get_col(
            $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like($prefix) . '%')
        );

        if (empty($options)) {
            \WP_CLI::success("No options found with prefix '$prefix'.");
            return;
        }

        $count = count($options);
        \WP_CLI::log("Found $count options with prefix '$prefix'.");

        if ($dry_run) {
            foreach ($options as $opt) {
                \WP_CLI::log("- $opt");
            }
            \WP_CLI::success("Dry run complete. No options deleted.");
            return;
        }

        \WP_CLI::confirm("Are you sure you want to delete these $count options?", $assoc_args);

        $deleted_count = 0;
        foreach ($options as $opt) {
            if (delete_option($opt)) {
                $deleted_count++;
            }
        }

        \WP_CLI::success("Deleted $deleted_count options.");
    }
}
