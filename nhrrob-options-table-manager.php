<?php
/**
 * Plugin Name: NHR Options Table Manager
 * Plugin URI: http://wordpress.org/plugins/nhrrob-options-table-manager/
 * Description: Clean DataTable view of wp-options table to make decisions and boost your site performance!
 * Author: Nazmul Hasan Robin
 * Author URI: https://profiles.wordpress.org/nhrrob/
 * Version: 1.1.2
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
    const nhrotm_version = '1.1.2';

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