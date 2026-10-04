<?php
/**
 * Scheduled events (WP-Cron): list, run now, delete.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Managers\ScannerManager;

/**
 * Reads the cron array and acts on single events.
 *
 * An event is addressed by hook + timestamp + args signature, and every
 * action re-reads the cron array and uses the stored event — the request only
 * ever selects one, it never supplies a hook or arguments to run.
 */
class CronService {

	/**
	 * Hooks WordPress itself schedules. They can be run, never deleted.
	 */
	const CORE_HOOKS = [
		'wp_version_check',
		'wp_update_plugins',
		'wp_update_themes',
		'wp_scheduled_delete',
		'wp_scheduled_auto_draft_delete',
		'delete_expired_transients',
		'wp_privacy_delete_old_export_files',
		'wp_privacy_personal_data_cleanup_requests',
		'wp_site_health_scheduled_check',
		'recovery_mode_clean_expired_keys',
		'wp_https_detection',
		'wp_update_user_counts',
		'wp_delete_temp_updater_backups',
		'wp_update_comment_type_batch',
		'wp_split_shared_term_batch',
		'do_pings',
		'publish_future_post',
		'update_network_counts',
		'wp_maybe_auto_update',
		'wp_scheduled_health_check',
	];

	/**
	 * Activity feed recorder.
	 *
	 * @var ActivityService
	 */
	private $activity;

	/**
	 * Set up the service.
	 *
	 * @param ActivityService|null $activity Injected for tests; defaults to a real instance.
	 */
	public function __construct( ?ActivityService $activity = null ) {
		$this->activity = $activity ? $activity : new ActivityService();
	}

	/**
	 * Every scheduled event, soonest first.
	 *
	 * `orphaned` is only a hint: nothing is listening for the hook on this
	 * request AND no installed plugin matches its name. Many plugins attach
	 * their cron callbacks only during cron requests, so a missing callback
	 * alone proves nothing.
	 *
	 * @return array
	 */
	public function all() {
		$scanner   = new ScannerManager();
		$unknown   = __( 'Unknown', 'nhrrob-options-table-manager' );
		$schedules = wp_get_schedules();
		$out       = [];

		foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
			foreach ( (array) $hooks as $hook => $events ) {
				foreach ( (array) $events as $sig => $event ) {
					$core  = in_array( $hook, self::CORE_HOOKS, true );
					$owner = $core ? __( 'WordPress Core', 'nhrrob-options-table-manager' ) : $scanner->guess_owner( $hook );
					$out[] = [
						'hook'       => $hook,
						'sig'        => (string) $sig,
						'timestamp'  => (int) $timestamp,
						'next'       => sprintf(
							/* translators: %s: human-readable time difference, e.g. "2 hours". */
							$timestamp > time() ? __( 'in %s', 'nhrrob-options-table-manager' ) : __( '%s ago (overdue)', 'nhrrob-options-table-manager' ),
							human_time_diff( $timestamp )
						),
						'recurrence' => ( ! empty( $event['schedule'] ) && isset( $schedules[ $event['schedule'] ] ) ) ? $schedules[ $event['schedule'] ]['display'] : __( 'Once', 'nhrrob-options-table-manager' ),
						'args'       => empty( $event['args'] ) ? '' : (string) wp_json_encode( $event['args'] ),
						'owner'      => $owner,
						'core'       => $core,
						'orphaned'   => ! $core && $owner === $unknown && ! has_action( $hook ),
					];
				}
			}
		}
		return $out;
	}

	/**
	 * Find one stored event.
	 *
	 * @param string $hook      Hook name.
	 * @param int    $timestamp Scheduled time.
	 * @param string $sig       Args signature (the cron array key).
	 * @return array|null The stored event, or null when it no longer exists.
	 */
	private function find( $hook, $timestamp, $sig ) {
		$cron = (array) _get_cron_array();
		return isset( $cron[ $timestamp ][ $hook ][ $sig ] ) ? $cron[ $timestamp ][ $hook ][ $sig ] : null;
	}

	/**
	 * Run an event's callbacks now (its schedule is left untouched).
	 *
	 * @param string $hook      Hook name.
	 * @param int    $timestamp Scheduled time.
	 * @param string $sig       Args signature.
	 * @return bool False when the event no longer exists.
	 */
	public function run( $hook, $timestamp, $sig ) {
		$event = $this->find( $hook, (int) $timestamp, $sig );
		if ( null === $event ) {
			return false;
		}
		do_action_ref_array( $hook, (array) $event['args'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- runs an already-scheduled event's own hook
		$this->activity->record( 'cron_run', $hook );
		return true;
	}

	/**
	 * Delete one event, or every event of its hook. Core hooks are refused.
	 *
	 * @param string $hook      Hook name.
	 * @param int    $timestamp Scheduled time.
	 * @param string $sig       Args signature.
	 * @param bool   $all       Delete every event of this hook.
	 * @return bool
	 */
	public function delete( $hook, $timestamp, $sig, $all = false ) {
		$event = $this->find( $hook, (int) $timestamp, $sig );
		if ( null === $event || in_array( $hook, self::CORE_HOOKS, true ) ) {
			return false;
		}
		if ( $all ) {
			wp_unschedule_hook( $hook );
		} else {
			wp_unschedule_event( (int) $timestamp, $hook, (array) $event['args'] );
		}
		$this->activity->record( 'cron_delete', $hook );
		return true;
	}
}
