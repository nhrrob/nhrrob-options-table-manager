<?php
/**
 * Plugin Name: NHR Database Cleaner & Optimizer – Revisions, Transients, Autoload & Options Manager
 * Plugin URI: http://wordpress.org/plugins/nhrrob-options-table-manager/
 * Description: Clean, optimize and manage your database: revisions, spam, transients and leftovers. Edit options and meta, tune autoload, optimize tables.
 * Author: Nazmul Hasan Robin
 * Author URI: https://profiles.wordpress.org/nhrrob/
 * Version: 2.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: nhrrob-options-table-manager
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Nhrotm\OptionsTableManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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
	const VERSION = '2.2.0';

	/**
	 * Version of the plugin's stored data (options; it has no tables). Bump
	 * when a stored shape changes; maybe_upgrade_db() runs the migrations once
	 * per bump.
	 *
	 * @var string
	 */
	const DB_VERSION = '2.1.0';

	/**
	 * Class construcotr
	 */
	private function __construct() {
		$this->define_constants();

		// Registered before anything can schedule with it (activation included).
		// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- adds one monthly recurrence (30 days), far above the 15-minute floor
		add_filter( 'cron_schedules', [ \Nhrotm\OptionsTableManager\Services\CleanupService::class, 'cron_schedules' ] );

		add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
		register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate_plugin' ] );
	}

	/**
	 * Plugin activation hook
	 */
	public function activate_plugin() {
		// On a network activation this runs for the main site only; every
		// other site sets itself up on its first admin/cron request
		// (maybe_upgrade_db() + ensure_crons() in init_plugin()).
		$this->maybe_upgrade_db();
		$this->ensure_crons();
	}

	/**
	 * Schedule this site's daily jobs if they are missing (idempotent; two
	 * lookups in the autoloaded cron array). Covers subsites of a network
	 * activation, sites created later, and reactivation.
	 *
	 * @return void
	 */
	public function ensure_crons() {
		// Retired in 2.1 (per-type cleanup schedules replaced it).
		if ( wp_next_scheduled( 'nhrotm_daily_cleanup' ) ) {
			wp_clear_scheduled_hook( 'nhrotm_daily_cleanup' );
		}
		\Nhrotm\OptionsTableManager\Services\CleanupService::sync_cron( ( new \Nhrotm\OptionsTableManager\Services\CleanupService() )->schedules() );
		if ( ! wp_next_scheduled( 'nhrotm_daily_history_prune' ) ) {
			wp_schedule_event( time(), 'daily', 'nhrotm_daily_history_prune' );
		}
		$frequency = ( new \Nhrotm\OptionsTableManager\Services\SettingsService() )->get( 'backup_frequency' );
		if ( in_array( $frequency, [ 'daily', 'weekly' ], true ) && ! wp_next_scheduled( \Nhrotm\OptionsTableManager\Managers\BackupManager::CRON_HOOK ) ) {
			\Nhrotm\OptionsTableManager\Managers\BackupManager::reschedule( $frequency );
		}
	}

	/**
	 * Plugin deactivation hook
	 *
	 * @param bool $network_wide True when deactivated for the whole network.
	 */
	public function deactivate_plugin( $network_wide = false ) {
		$clear = function () {
			wp_clear_scheduled_hook( 'nhrotm_daily_cleanup' );
			wp_unschedule_hook( \Nhrotm\OptionsTableManager\Services\CleanupService::CRON_HOOK );
			wp_clear_scheduled_hook( 'nhrotm_daily_history_prune' );
			wp_clear_scheduled_hook( \Nhrotm\OptionsTableManager\Managers\BackupManager::CRON_HOOK );
		};

		if ( ! $network_wide || ! is_multisite() ) {
			$clear();
			return;
		}
		foreach ( get_sites(
			[
				'fields' => 'ids',
				'number' => 0,
			]
		) as $site_id ) {
			switch_to_blog( $site_id );
			$clear();
			restore_current_blog();
		}
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
		define( 'NHROTM_VERSION', self::VERSION );
		define( 'NHROTM_FILE', __FILE__ );
		define( 'NHROTM_PATH', __DIR__ );
		define( 'NHROTM_PLUGIN_DIR', plugin_dir_path( NHROTM_FILE ) );
		define( 'NHROTM_URL', plugins_url( '', NHROTM_FILE ) );
		define( 'NHROTM_ASSETS', NHROTM_URL . '/assets' );
		define( 'NHROTM_INCLUDES_PATH', NHROTM_PATH . '/includes' );
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	public function init_plugin() {
		$this->maybe_upgrade_db();
		if ( is_admin() || wp_doing_cron() ) {
			$this->ensure_crons();
		}

		// Cron Handler.
		add_action( \Nhrotm\OptionsTableManager\Services\CleanupService::CRON_HOOK, [ $this, 'run_cleanup' ] );
		add_action( 'nhrotm_daily_history_prune', [ $this, 'run_history_prune' ] );
		add_action( \Nhrotm\OptionsTableManager\Managers\BackupManager::CRON_HOOK, [ $this, 'run_scheduled_backup' ] );

		// Front-end autoload usage tracking (opt-in).
		if ( ! is_admin() ) {
			( new Nhrotm\OptionsTableManager\Managers\UsageTracker() )->maybe_track();
		}

		// Module registry + REST API behind the React admin app.
		( new Nhrotm\OptionsTableManager\Core\Bootstrap() )->init();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'nhrotm', '\Nhrotm\OptionsTableManager\Cli\CliCommands' );
			// Pre-2.1 command name, kept so existing scripts keep working.
			\WP_CLI::add_command( 'nhr-options', '\Nhrotm\OptionsTableManager\Cli\CliCommands' );
		}

		if ( is_admin() ) {
			new Nhrotm\OptionsTableManager\Admin();
		}
	}

	/**
	 * Migrate the plugin's stored data once per DB_VERSION. The plugin creates
	 * no tables; 2.1.0 moves the history and snapshot tables of earlier
	 * versions into options and drops them.
	 *
	 * Runs on activation and on the first admin, cron or WP-CLI request after
	 * an update (updates don't fire the activation hook) — never on a
	 * visitor's page load, and never twice at once. The version is kept inside
	 * the autoloaded nhrotm_settings option, so the steady-state cost is one
	 * in-memory comparison.
	 *
	 * @return void
	 */
	public function maybe_upgrade_db() {
		$settings = new \Nhrotm\OptionsTableManager\Services\SettingsService();
		if ( $settings->db_version() === self::DB_VERSION ) {
			return;
		}
		if ( ! is_admin() && ! wp_doing_cron() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		// add_option() is atomic, so only one request gets the lock. A lock
		// older than ten minutes belongs to a run that died; take it over.
		$lock = 'nhrotm_migrating';
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( time() - (int) get_option( $lock ) < 10 * MINUTE_IN_SECONDS ) {
				return;
			}
			update_option( $lock, time(), false );
		}

		( new \Nhrotm\OptionsTableManager\Managers\HistoryManager() )->upgrade();
		( new \Nhrotm\OptionsTableManager\Managers\BackupManager() )->upgrade();
		\Nhrotm\OptionsTableManager\Managers\UsageTracker::migrate();
		// Folds legacy nhrotm_* options into nhrotm_settings (see SettingsService).
		$settings->migrate();
		$settings->set_db_version( self::DB_VERSION );
		delete_option( $lock );
	}

	/**
	 * Run one scheduled cleanup type.
	 *
	 * @param string $type Cleanup type id.
	 */
	public function run_cleanup( $type = '' ) {
		( new \Nhrotm\OptionsTableManager\Services\CleanupService() )->run_scheduled( (string) $type );
	}

	/**
	 * Run history pruning
	 */
	public function run_history_prune() {
		$days            = ( new \Nhrotm\OptionsTableManager\Services\SettingsService() )->get( 'history_retention_days' );
		$history_manager = new \Nhrotm\OptionsTableManager\Managers\HistoryManager();
		$history_manager->prune_history( $days );
	}

	/**
	 * Run scheduled options-table backup
	 */
	public function run_scheduled_backup() {
		$backup_manager = new \Nhrotm\OptionsTableManager\Managers\BackupManager();
		$backup_manager->create_snapshot( __( 'Scheduled backup', 'nhrrob-options-table-manager' ), 'scheduled' );
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

// Call the plugin.
nhrotm_options_table_manager();
