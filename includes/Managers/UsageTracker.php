<?php
/**
 * Tracks which autoloaded options are actually read on front-end requests.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Managers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Traits\GlobalTrait;

/**
 * Class UsageTracker
 *
 * Records which autoloaded options are actually read on real front-end page
 * loads, so options that are never used can be flagged for autoload removal.
 *
 * Tracking is opt-in (adds minor per-request overhead) and runs on the
 * front-end only. Used option names are collected during the request via the
 * catch-all "all" hook and persisted on shutdown.
 */
class UsageTracker {

	use GlobalTrait;

	/**
	 * All collected data in one non-autoloaded option:
	 * [ 'since' => mysql datetime, 'loads' => int, 'used' => [ name => hits ] ].
	 */
	const DATA_OPTION = 'nhrotm_usage';

	// Stop counting once the sample proves the point. Past this the extra
	// write on every front-end request buys nothing, and an undercounted
	// "never used across N loads" is still a true statement.
	const LOADS_CAP = 10000;

	// The first loads are all recorded so results show up quickly. After that
	// one load in SAMPLE_RATE is recorded and counted SAMPLE_RATE times, so a
	// busy site is not written to on every page view.
	const SAMPLE_AFTER = 200;
	const SAMPLE_RATE  = 10;

	/**
	 * Option names read during the current request.
	 *
	 * @var array
	 */
	private $used = [];

	/**
	 * How many loads this request stands for (1, or SAMPLE_RATE when sampling).
	 *
	 * @var int
	 */
	private $weight = 1;

	/**
	 * Attach front-end tracking hooks when enabled.
	 *
	 * @return void
	 */
	public function maybe_track() {
		if ( ! self::enabled() ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$loads = self::data()['loads'];
		if ( $loads >= self::LOADS_CAP ) {
			return; // The sample is complete; nothing more to record.
		}
		if ( $loads >= self::SAMPLE_AFTER ) {
			if ( 1 !== wp_rand( 1, self::SAMPLE_RATE ) ) {
				return;
			}
			$this->weight = self::SAMPLE_RATE;
		}

		add_filter( 'all', [ $this, 'record_hook' ] );
		add_action( 'shutdown', [ $this, 'persist' ] );
	}

	/**
	 * Capture the option name behind every "option_{$name}" filter.
	 *
	 * @param mixed $value Passed-through hook value (return is ignored for "all").
	 * @return mixed
	 */
	public function record_hook( $value = null ) {
		$tag = current_filter();
		if ( strncmp( $tag, 'option_', 7 ) === 0 ) {
			$this->used[ substr( $tag, 7 ) ] = true;
		}
		return $value;
	}

	/**
	 * Merge this request's used options into the stored set (autoloaded only).
	 *
	 * @return void
	 */
	public function persist() {
		$data   = self::data();
		$before = $data;

		// Count every tracked front-end load, even one that read nothing new.
		// The sample size is what makes "never used" trustworthy, so it has to
		// be recorded independently of whether new names were seen.
		$data['loads'] = min( self::LOADS_CAP, $data['loads'] + $this->weight );

		if ( ! empty( $this->used ) ) {
			$autoloaded = wp_load_alloptions();
			// Per-option hit count, not just a seen/unseen flag — lets the UI show
			// "used 12x" instead of a flat "Used" label.
			foreach ( array_keys( $this->used ) as $name ) {
				if ( isset( $autoloaded[ $name ] ) ) {
					$data['used'][ $name ] = ( isset( $data['used'][ $name ] ) ? (int) $data['used'][ $name ] : 0 ) + $this->weight;
				}
			}
			if ( '' === $data['since'] ) {
				$data['since'] = current_time( 'mysql' );
			}
		}

		if ( $data !== $before ) {
			update_option( self::DATA_OPTION, $data, false );
		}
	}

	/**
	 * Whether tracking is switched on. Reads the autoloaded settings option,
	 * so the check costs no query on a front-end request.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$settings = get_option( \Nhrotm\OptionsTableManager\Services\SettingsService::OPTION, [] );
		return is_array( $settings ) && ! empty( $settings['usage_tracking_enabled'] ) && 'false' !== $settings['usage_tracking_enabled'];
	}

	/**
	 * The collected data, normalized.
	 *
	 * @return array { since, loads, used }
	 */
	private static function data() {
		$data = get_option( self::DATA_OPTION, [] );
		$data = is_array( $data ) ? $data : [];
		return [
			'since' => isset( $data['since'] ) ? (string) $data['since'] : '',
			'loads' => isset( $data['loads'] ) ? (int) $data['loads'] : 0,
			'used'  => isset( $data['used'] ) && is_array( $data['used'] ) ? $data['used'] : [],
		];
	}

	/**
	 * 2.1 migration: fold the three options earlier versions kept (used
	 * names, start date, load count) into DATA_OPTION.
	 *
	 * @return void
	 */
	public static function migrate() {
		$used  = get_option( 'nhrotm_used_autoload_options', null );
		$since = get_option( 'nhrotm_usage_tracking_since', null );
		$loads = get_option( 'nhrotm_usage_load_count', null );
		if ( null === $used && null === $since && null === $loads ) {
			return;
		}
		if ( false === get_option( self::DATA_OPTION, false ) ) {
			update_option(
				self::DATA_OPTION,
				[
					'since' => (string) $since,
					'loads' => (int) $loads,
					// Values written before hit counts existed are the boolean true.
					'used'  => array_map( 'intval', is_array( $used ) ? $used : [] ),
				],
				false
			);
		}
		delete_option( 'nhrotm_used_autoload_options' );
		delete_option( 'nhrotm_usage_tracking_since' );
		delete_option( 'nhrotm_usage_load_count' );
	}

	/**
	 * Get autoloaded options that have never been recorded as used.
	 *
	 * @return array
	 */
	public function get_unused_autoload_options() {
		$autoloaded = wp_load_alloptions();
		$data       = self::data();
		$used       = $data['used'];
		$protected  = $this->get_protected_options();

		$unused = [];
		foreach ( $autoloaded as $name => $value ) {
			if ( isset( $used[ $name ] ) || in_array( $name, $protected, true ) ) {
				continue;
			}

			$unused[] = [
				'option_name'    => $name,
				'size_formatted' => size_format( strlen( (string) $value ) ),
				'size_bytes'     => strlen( (string) $value ),
			];
		}

		usort(
			$unused,
			function ( $a, $b ) {
				return $b['size_bytes'] <=> $a['size_bytes'];
			}
		);

		return [
			'tracking'    => self::enabled(),
			'since'       => $data['since'],
			'seen_count'  => count( $used ),
			'load_count'  => $data['loads'],
			'used_counts' => array_map( 'intval', $used ),
			'options'     => $unused,
		];
	}

	/**
	 * Number of front-end loads observed since tracking began.
	 *
	 * @return int
	 */
	public function get_load_count() {
		return self::data()['loads'];
	}

	/**
	 * Reset all collected usage data.
	 *
	 * @return void
	 */
	public function reset() {
		delete_option( self::DATA_OPTION );
	}
}
