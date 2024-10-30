<?php
/**
 * Plugin Name: NHR Options Table Manager
 * Plugin URI: http://wordpress.org/plugins/nhrrob-options-table-manager/
 * Description: Clean DataTable view of wp-options table to make decisions and boost your site performance!
 * Author: Nazmul Hasan Robin
 * Author URI: https://profiles.wordpress.org/nhrrob/
 * Version: 1.0.7
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: nhrrob-options-table-manager
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main plugin class
 */
final class Nhrotm_Options_Table_Manager {

    /**
     * Plugin version
     *
     * @var string
     */
    const nhrotm_version = '1.0.7';

    /**
     * Class construcotr
     */
    private function __construct() {
        $this->define_constants();

        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
    }

    /**
     * Initialize a singleton instance
     *
     * @return \Nhrotm_Options_Table_Manager
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Define the required plugin constants
     *
     * @return void
     */
    public function define_constants() {
        define( 'NHROTM_VERSION', self::nhrotm_version );
        define( 'NHROTM_FILE', __FILE__ );
        define( 'NHROTM_PATH', __DIR__ );
        define( 'NHROTM_PLUGIN_DIR', plugin_dir_path( NHROTM_FILE ) );
        define( 'NHROTM_URL', plugins_url('', NHROTM_FILE) );
        define( 'NHROTM_ASSETS', NHROTM_URL . '/assets' );
        define( 'NHROTM_INCLUDES_PATH', NHROTM_PATH . '/includes' );
        define( 'NHROTM_VIEWS_PATH', NHROTM_INCLUDES_PATH . '/views' );
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin() {

        new Nhrotm\OptionsTableManager\Assets();

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            new Nhrotm\OptionsTableManager\Ajax();
        }

        if ( is_admin() ) {
            new Nhrotm\OptionsTableManager\Admin();
        }
    }
}

/**
 * Initializes the main plugin
 *
 * @return \Nhrotm_Options_Table_Manager
 */
function nhrotm_options_table_manager() {
    return Nhrotm_Options_Table_Manager::init();
}

//Call the plugin
nhrotm_options_table_manager();


// new datatable
function db_table_display_data1() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'options';

    // Pagination parameters
    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
    
    // Search parameter
    $search = isset($_GET['search']['value']) ? sanitize_text_field($_GET['search']['value']) : '';
    
    // Sorting parameters
    $order_column_index = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
    $order_direction = isset($_GET['order'][0]['dir']) && in_array($_GET['order'][0]['dir'], ['asc', 'desc']) ? $_GET['order'][0]['dir'] : 'asc';

    // Define columns in the correct order for sorting
    $columns = ['option_id', 'option_name', 'option_value', 'autoload']; // Add your actual table column names here
    $order_column = $columns[$order_column_index] ?? $columns[0];

    // Get total record count
    $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    // Build query with search, filtering, and sorting
    $query = "SELECT * FROM $table_name";
    if (!empty($search)) {
        $query .= $wpdb->prepare(" WHERE option_name LIKE %s OR option_value LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
    }
    $filtered_records = $wpdb->get_var("SELECT COUNT(*) FROM ($query) AS temp");
    
    $query .= " ORDER BY $order_column $order_direction LIMIT $start, $length";

    // Execute query
    $data = $wpdb->get_results($query, ARRAY_A);

    // Wrap the option_value in the scrollable-cell div
    foreach ($data as &$row) {
        $row['option_value'] = '<div class="scrollable-cell">' . esc_html($row['option_value']) . '</div>';
        $row['actions'] = '<button class="nhrotm-edit-button" data-id="' . esc_attr($row['option_id']) . '">Edit</button>
                           <button class="nhrotm-delete-button" data-id="' . esc_attr($row['option_id']) . '">Delete</button>';
    }
    
    // Prepare response for DataTables
    $response = array(
        "draw" => isset($_GET['draw']) ? intval($_GET['draw']) : 0,
        "recordsTotal" => $total_records,
        "recordsFiltered" => $filtered_records,
        "data" => $data
    );

    wp_send_json($response);
}

add_action('wp_ajax_nhrotm_table_display_data', 'db_table_display_data1');

function db_table_edit_option1() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'options';

    $option_id = intval($_POST['option_id']);
    $option_value = sanitize_textarea_field($_POST['option_value']);
    $autoload = sanitize_text_field($_POST['autoload']);

    $updated = $wpdb->update(
        $table_name,
        [
            'option_value' => $option_value,
            'autoload' => $autoload
        ],
        ['option_id' => $option_id]
    );

    if ($updated) {
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}

add_action('wp_ajax_nhrotm_edit_option', 'db_table_edit_option1');

// Handle delete option request
function db_table_delete_option1() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'options';

    $option_id = intval($_POST['option_id']);

    $deleted = $wpdb->delete($table_name, ['option_id' => $option_id]);

    if ($deleted) {
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}
add_action('wp_ajax_nhrotm_delete_option', 'db_table_delete_option1');

function db_table_add_option1() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'options';

    // Get and sanitize data
    $option_name = sanitize_text_field($_POST['option_name']);
    $option_value = wp_unslash($_POST['option_value']);
    $option_value = sanitize_textarea_field($option_value); // for serialized/JSON data
    $autoload = sanitize_text_field($_POST['autoload']);

    // Insert new option
    $inserted = $wpdb->insert(
        $table_name,
        [
            'option_name' => $option_name,
            'option_value' => $option_value,
            'autoload' => $autoload
        ]
    );

    if ($inserted) {
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}
add_action('wp_ajax_nhrotm_add_option', 'db_table_add_option1');