<?php
/**
 * Plugin Name: NHR Advanced Options Table Manager & Autoload Optimizer
 * Plugin URI: http://wordpress.org/plugins/nhrrob-options-table-manager/
 * Description: Optimize WordPress with Advanced Option History, Autoload Health Checks, and Automated Cleanup. Boost performance by reducing database bloat.
 * Author: Nazmul Hasan Robin
 * Author URI: https://profiles.wordpress.org/nhrrob/
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: nhrrob-options-table-manager
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main plugin class
 */
final class Nhrotm_Options_Table_Manager
{

    /**
     * Plugin version
     *
     * @var string
     */
    const nhrotm_version = '2.0.0';

    /**
     * Class construcotr
     */
    private function __construct()
    {
        $this->define_constants();

        add_action('plugins_loaded', [$this, 'init_plugin']);
        register_activation_hook(__FILE__, [$this, 'activate_plugin']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate_plugin']);
    }

    /**
     * Plugin activation hook
     */
    public function activate_plugin()
    {
        $history_manager = new \Nhrotm\OptionsTableManager\Managers\HistoryManager();
        $history_manager->create_table();

        $backup_manager = new \Nhrotm\OptionsTableManager\Managers\BackupManager();
        $backup_manager->create_table();

        if (!wp_next_scheduled('nhrotm_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'nhrotm_daily_cleanup');
        }

        if (!wp_next_scheduled('nhrotm_daily_history_prune')) {
            wp_schedule_event(time(), 'daily', 'nhrotm_daily_history_prune');
        }

        \Nhrotm\OptionsTableManager\Managers\BackupManager::reschedule(
            get_option(\Nhrotm\OptionsTableManager\Managers\BackupManager::FREQUENCY_OPTION, 'off')
        );

        // 2.0: fold legacy nhrotm_* options into the centralized nhrotm_settings store.
        (new \Nhrotm\OptionsTableManager\Services\SettingsService())->migrate();
    }

    /**
     * Plugin deactivation hook
     */
    public function deactivate_plugin()
    {
        wp_clear_scheduled_hook('nhrotm_daily_cleanup');
        wp_clear_scheduled_hook('nhrotm_daily_history_prune');
        wp_clear_scheduled_hook(\Nhrotm\OptionsTableManager\Managers\BackupManager::CRON_HOOK);
    }

    /**
     * Initialize a singleton instance
     *
     * @return \Nhrotm_Options_Table_Manager
     */
    public static function init()
    {
        static $instance = false;

        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Define the required plugin constants
     *
     * @return void
     */
    public function define_constants()
    {
        define('NHROTM_VERSION', self::nhrotm_version);
        define('NHROTM_FILE', __FILE__);
        define('NHROTM_PATH', __DIR__);
        define('NHROTM_PLUGIN_DIR', plugin_dir_path(NHROTM_FILE));
        define('NHROTM_URL', plugins_url('', NHROTM_FILE));
        define('NHROTM_ASSETS', NHROTM_URL . '/assets');
        define('NHROTM_INCLUDES_PATH', NHROTM_PATH . '/includes');
        define('NHROTM_VIEWS_PATH', NHROTM_INCLUDES_PATH . '/views');
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin()
    {
        // Cron Handler
        add_action('nhrotm_daily_cleanup', [$this, 'run_cleanup']);
        add_action('nhrotm_daily_history_prune', [$this, 'run_history_prune']);
        add_action(\Nhrotm\OptionsTableManager\Managers\BackupManager::CRON_HOOK, [$this, 'run_scheduled_backup']);

        // Front-end autoload usage tracking (opt-in)
        if (!is_admin()) {
            (new Nhrotm\OptionsTableManager\Managers\UsageTracker())->maybe_track();
        }

        // 2.0 architecture: module registry + REST (additive; runs alongside 1.5.x admin-ajax).
        (new Nhrotm\OptionsTableManager\Core\Bootstrap())->init();

        new Nhrotm\OptionsTableManager\Assets();

        if (defined('DOING_AJAX') && DOING_AJAX) {
            new Nhrotm\OptionsTableManager\Ajax\AjaxHandler();
        }

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('nhr-options', '\Nhrotm\OptionsTableManager\Cli\CliCommands');
        }

        if (is_admin()) {
            new Nhrotm\OptionsTableManager\Admin();
        }
    }

    /**
     * Run daily cleanup
     */
    public function run_cleanup()
    {
        // Check if enabled
        if (get_option('nhrotm_auto_cleanup_enabled', 'false') === 'true') {
            $manager = new \Nhrotm\OptionsTableManager\Managers\OptionsTableManager();
            $manager->perform_cleanup();
        }
    }

    /**
     * Run history pruning
     */
    public function run_history_prune()
    {
        $days = get_option('nhrotm_history_retention_days', 30);
        $history_manager = new \Nhrotm\OptionsTableManager\Managers\HistoryManager();
        $history_manager->prune_history($days);
    }

    /**
     * Run scheduled options-table backup
     */
    public function run_scheduled_backup()
    {
        $backup_manager = new \Nhrotm\OptionsTableManager\Managers\BackupManager();
        $backup_manager->create_snapshot('Scheduled backup', 'scheduled');
    }
}

/**
 * Initializes the main plugin
 *
 * @return \Nhrotm_Options_Table_Manager
 */
function nhrotm_options_table_manager()
{
    return Nhrotm_Options_Table_Manager::init();
}

//Call the plugin
nhrotm_options_table_manager();