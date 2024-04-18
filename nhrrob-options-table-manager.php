<?php
/**
 * Plugin Name: NHR Options Table Manager
 * Plugin URI: http://wordpress.org/plugins/nhrrob-options-table-manager/
 * Description: Clean DataTable view of wp-options table to make decisions and boost your site performance!
 * Author: Nazmul Hasan Robin
 * Version: 1.0.0
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
final class Nhrrob_Options_Table_Manager {

    /**
     * Plugin version
     *
     * @var string
     */
    const version = '1.0.0';

    /**
     * Class construcotr
     */
    private function __construct() {
        $this->define_constants();

        register_activation_hook( __FILE__, [ $this, 'activate' ] );

        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
    }

    /**
     * Initialize a singleton instance
     *
     * @return \Nhrrob_Options_Table_Manager
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
        define( 'NHRROB_OPTIONS_TABLE_MANAGER_VERSION', self::version );
        define( 'NHRROB_OPTIONS_TABLE_MANAGER_FILE', __FILE__ );
        define( 'NHRROB_OPTIONS_TABLE_MANAGER_PATH', __DIR__ );
        define( 'NHRROB_OPTIONS_TABLE_MANAGER_PLUGIN_DIR', plugin_dir_path( NHRROB_OPTIONS_TABLE_MANAGER_FILE ) );
        define( 'NHRROB_OPTIONS_TABLE_MANAGER_URL', plugins_url('', NHRROB_OPTIONS_TABLE_MANAGER_FILE) );
        define( 'NHRROB_OPTIONS_TABLE_MANAGER_ASSETS', NHRROB_OPTIONS_TABLE_MANAGER_URL . '/assets' );
        define( 'NHRROB_OPTIONS_TABLE_MANAGER_INCLUDES_PATH', NHRROB_OPTIONS_TABLE_MANAGER_PATH . '/includes' );
        define( 'NHRROB_OPTIONS_TABLE_MANAGER_VIEWS_PATH', NHRROB_OPTIONS_TABLE_MANAGER_INCLUDES_PATH . '/views' );
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin() {

        new Nhrrob\NhrrobOptionsTableManager\Assets();

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            new Nhrrob\NhrrobOptionsTableManager\Ajax();
        }

        if ( is_admin() ) {
            new Nhrrob\NhrrobOptionsTableManager\Admin();
        }
    }
}

/**
 * Initializes the main plugin
 *
 * @return \Nhrrob_Options_Table_Manager
 */
function nhrrob_options_table_manager() {
    return Nhrrob_Options_Table_Manager::init();
}

//Call the plugin
nhrrob_options_table_manager();