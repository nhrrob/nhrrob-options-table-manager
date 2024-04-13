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

define('NHRROB_OPTIONS_TABLE_MANAGER_VERSION', '1.0.0');
define('NHRROB_OPTIONS_TABLE_MANAGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
define('NHRROB_OPTIONS_TABLE_MANAGER_FILE', __FILE__);
define('NHRROB_OPTIONS_TABLE_MANAGER_PATH', __DIR__);
define('NHRROB_OPTIONS_TABLE_MANAGER_URL', plugins_url('', NHRROB_OPTIONS_TABLE_MANAGER_FILE));
define('NHRROB_OPTIONS_TABLE_MANAGER_ASSETS', NHRROB_OPTIONS_TABLE_MANAGER_URL . '/assets');

function nhrrob_options_table_manager_init(){
    wp_register_style( 'nhrrob-options-table-manager-datatable-style','//cdn.datatables.net/2.0.3/css/dataTables.dataTables.min.css', array(), '2.0.3' );
    wp_register_style( 'nhrrob-options-table-manager-style', NHRROB_OPTIONS_TABLE_MANAGER_ASSETS . '/css/style.css', array(), filemtime( NHRROB_OPTIONS_TABLE_MANAGER_PATH . '/assets/css/style.css' ) );
    
    wp_register_script( 'nhrrob-options-table-manager-datatable-script','//cdn.datatables.net/2.0.3/js/dataTables.min.js', array('jquery'), '2.0.3' );
    wp_register_script( 'nhrrob-options-table-manager-script', NHRROB_OPTIONS_TABLE_MANAGER_ASSETS . '/js/script.js', array(), filemtime( NHRROB_OPTIONS_TABLE_MANAGER_PATH . '/assets/js/script.js' ) );
    
    wp_enqueue_style('nhrrob-options-table-manager-datatable-style');
    wp_enqueue_style('nhrrob-options-table-manager-style');
    
    wp_enqueue_script('jquery');
    wp_enqueue_script('nhrrob-options-table-manager-datatable-script');
    wp_enqueue_script('nhrrob-options-table-manager-script');
}

add_action('admin_enqueue_scripts', 'nhrrob_options_table_manager_init');