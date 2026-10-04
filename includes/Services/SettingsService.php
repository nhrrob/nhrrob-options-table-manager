<?php
/**
 * Centralized settings store collapsing scattered nhrotm_* options into one array.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized settings store.
 *
 * Every setting, plus the data version, lives in the single autoloaded
 * `nhrotm_settings` option. Collapses the scattered nhrotm_* options into that
 * array. Legacy keys are read as fallbacks during the deprecation window and
 * folded in by migrate(), so 1.5.x installs upgrade cleanly.
 */
class SettingsService {

	const OPTION = 'nhrotm_settings';

	/**
	 * Default settings and the legacy option each one migrates from.
	 *
	 * @var array
	 */
	private $defaults = [
		'allow_html_in_values'   => false,
		'usage_tracking_enabled' => false,
		'cleanup_schedules'      => [],
		'backup_frequency'       => 'off',
		'history_retention_days' => 30,
	];

	/**
	 * Map of setting key => legacy standalone option name.
	 *
	 * @var array
	 */
	private $legacy_map = [
		'allow_html_in_values'   => 'nhrotm_allow_html_in_values',
		'auto_cleanup_enabled'   => 'nhrotm_auto_cleanup_enabled',
		'usage_tracking_enabled' => 'nhrotm_usage_tracking_enabled',
		'backup_frequency'       => 'nhrotm_backup_frequency',
		'history_retention_days' => 'nhrotm_history_retention_days',
	];

	/**
	 * Loaded settings cache.
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Key inside the option that records which data version the site is on
	 * (see Nhrotm_Options_Table_Manager::DB_VERSION). Internal: never part of
	 * all(), and update() cannot change it.
	 */
	const DB_VERSION_KEY = 'db_version';

	/**
	 * The stored option as an array.
	 *
	 * @return array
	 */
	private function stored() {
		$stored = get_option( self::OPTION, [] );
		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * Persist settings, carrying the data version along.
	 *
	 * The option is small and autoloaded: the front-end tracker and the
	 * per-request version check both read it, so neither costs a query and
	 * no separate flag option is needed.
	 *
	 * @param array       $settings   Known settings only.
	 * @param string|null $db_version Data version to store; null keeps the current one.
	 * @return void
	 */
	private function save( array $settings, $db_version = null ) {
		$version = null === $db_version ? $this->db_version() : (string) $db_version;
		if ( '' !== $version ) {
			$settings[ self::DB_VERSION_KEY ] = $version;
		}
		update_option( self::OPTION, $settings, true );
	}

	/**
	 * Get all settings, merged over defaults.
	 *
	 * @return array
	 */
	public function all() {
		if ( null === $this->cache ) {
			$this->cache = array_intersect_key( wp_parse_args( $this->stored(), $this->defaults ), $this->defaults );
		}
		return $this->cache;
	}

	/**
	 * Data version this site was last migrated to ('' when never).
	 *
	 * @return string
	 */
	public function db_version() {
		$stored = $this->stored();
		return isset( $stored[ self::DB_VERSION_KEY ] ) ? (string) $stored[ self::DB_VERSION_KEY ] : '';
	}

	/**
	 * Record the data version after the migrations ran.
	 *
	 * @param string $version Version.
	 * @return void
	 */
	public function set_db_version( $version ) {
		$this->save( $this->all(), $version );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Fallback if not set.
	 * @return mixed
	 */
	public function get( $key, $fallback = null ) {
		$all = $this->all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return null === $fallback ? ( isset( $this->defaults[ $key ] ) ? $this->defaults[ $key ] : null ) : $fallback;
	}

	/**
	 * Update one or more settings and persist.
	 *
	 * @param array $values Partial settings to merge.
	 * @return array The full, updated settings.
	 */
	public function update( array $values ) {
		$merged = wp_parse_args( $values, $this->all() );
		// Keep only known keys to avoid unbounded growth.
		$merged = array_intersect_key( $merged, $this->defaults );
		$this->save( $merged );
		$this->cache = $merged;
		return $merged;
	}

	/**
	 * One-time migration: fold legacy standalone options into nhrotm_settings.
	 * Idempotent — safe to call on every activation.
	 *
	 * @return void
	 */
	public function migrate() {
		$settings = $this->stored();
		unset( $settings[ self::DB_VERSION_KEY ] );

		foreach ( $this->legacy_map as $key => $legacy_option ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$legacy_value = get_option( $legacy_option, null );
				if ( null !== $legacy_value ) {
					$settings[ $key ] = $legacy_value;
				}
			}
		}

		// 2.1: the single "automated daily cleanup" switch became per-type
		// schedules (CleanupService). Carry an enabled switch over as
		// "expired transients, daily" and retire its dedicated cron hook.
		$had_auto_cleanup = isset( $settings['auto_cleanup_enabled'] ) && in_array( $settings['auto_cleanup_enabled'], [ true, 'true', 1, '1' ], true );
		if ( $had_auto_cleanup && empty( $settings['cleanup_schedules']['expired_transients'] ) ) {
			$settings['cleanup_schedules']                       = isset( $settings['cleanup_schedules'] ) && is_array( $settings['cleanup_schedules'] ) ? $settings['cleanup_schedules'] : [];
			$settings['cleanup_schedules']['expired_transients'] = [
				'frequency' => 'daily',
				'days'      => 0,
			];
		}
		wp_clear_scheduled_hook( 'nhrotm_daily_cleanup' );

		$settings = array_intersect_key( wp_parse_args( $settings, $this->defaults ), $this->defaults );
		// Legacy options stored booleans as 'true'/'false' strings, and the
		// string 'false' is truthy — cast every value to its default's type.
		foreach ( $this->defaults as $key => $default ) {
			if ( is_bool( $default ) ) {
				$settings[ $key ] = true === $settings[ $key ] || 'true' === $settings[ $key ] || 1 === $settings[ $key ] || '1' === $settings[ $key ];
			} elseif ( is_int( $default ) ) {
				$settings[ $key ] = max( 1, (int) $settings[ $key ] );
			}
		}
		$this->save( $settings );

		// Folded in above; nothing reads them any more. Before 2.1 the data
		// version and an autoloaded copy of the tracking switch were separate
		// options; both now live inside this one.
		foreach ( [ 'nhrotm_allow_html_in_values', 'nhrotm_auto_cleanup_enabled', 'nhrotm_backup_frequency', 'nhrotm_history_retention_days', 'nhrotm_usage_tracking_enabled', 'nhrotm_db_version' ] as $legacy_option ) {
			delete_option( $legacy_option );
		}
		// update_option() only changes autoload when the value changes too, so
		// flip rows created by earlier versions explicitly (WP 6.4+).
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( self::OPTION, true );
		}
		$this->cache = null;
	}
}
