<?php
/**
 * Action service for the Tools section: backups, search & replace, export.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Nhrotm\OptionsTableManager\Managers\BackupManager;
use Nhrotm\OptionsTableManager\Managers\SearchReplaceManager;
use Nhrotm\OptionsTableManager\Traits\GlobalTrait;

/**
 * Action service for the Tools section: backups, search & replace, export.
 *
 * Reuses BackupManager and SearchReplaceManager. A safety snapshot is taken
 * automatically before a live (non-dry-run) search & replace.
 */
class ToolsService {

	use GlobalTrait;

	/**
	 * Backup manager used for snapshot create/restore/delete.
	 *
	 * @var BackupManager
	 */
	private $backups;

	/**
	 * Activity feed writer for tools actions.
	 *
	 * @var ActivityService
	 */
	private $activity;

	/**
	 * Create the service, optionally injecting an ActivityService for tests.
	 *
	 * @param ActivityService|null $activity Injected for tests; defaults to a real instance.
	 */
	public function __construct( ?ActivityService $activity = null ) {
		$this->backups  = new BackupManager();
		$this->activity = $activity ? $activity : new ActivityService();
	}

	/**
	 * List all backup snapshots.
	 *
	 * @return array
	 */
	public function list_backups() {
		return $this->backups->get_snapshots();
	}

	/**
	 * Create a manual backup snapshot and record the activity.
	 *
	 * @param string $label Optional label.
	 * @return int|false
	 */
	public function create_backup( $label = '' ) {
		$id = $this->backups->create_snapshot( $label, 'manual' );

		if ( $id ) {
			$this->activity->record( 'snapshot', __( 'manually', 'nhrrob-options-table-manager' ) );
		}

		return $id;
	}

	/**
	 * Restore every option from a backup snapshot.
	 *
	 * @param int $id Snapshot id.
	 * @return int Options restored.
	 */
	public function restore_backup( $id ) {
		$restored = $this->backups->restore_snapshot( $id );

		// A snapshot can predate the current settings shape (or hold different
		// schedules): re-normalise our own settings and re-register the events
		// they describe, so nothing is left pointing at a retired hook.
		( new SettingsService() )->migrate();
		CleanupService::sync_cron( ( new CleanupService( $this->activity ) )->schedules(), true );

		return $restored;
	}

	/**
	 * Delete a backup snapshot.
	 *
	 * @param int $id Snapshot id.
	 * @return bool
	 */
	public function delete_backup( $id ) {
		return $this->backups->delete_snapshot( $id );
	}

	/**
	 * Run search & replace. Auto-snapshots before a live run.
	 *
	 * @param string $search  Needle.
	 * @param string $replace Replacement.
	 * @param bool   $dry_run Preview only when true.
	 * @return array
	 */
	public function search_replace( $search, $replace, $dry_run = true ) {
		if ( ! $dry_run ) {
			$this->backups->create_snapshot( __( 'Auto: before search & replace', 'nhrrob-options-table-manager' ), 'auto' );
			$this->activity->record( 'snapshot', __( 'before search & replace', 'nhrrob-options-table-manager' ) );
		}
		return ( new SearchReplaceManager() )->execute_replace( $search, $replace, $dry_run );
	}

	/**
	 * Export options as JSON-ready data.
	 *
	 * With no names, exports every non-transient option; otherwise exactly
	 * the named options (the Export basket). A checksum over `options` lets
	 * Import detect a hand-edited or truncated file.
	 *
	 * @param string[] $names Option names to export; empty = all.
	 * @return array
	 */
	public function export_options( array $names = [] ) {
		global $wpdb;
		$names = array_values( array_filter( array_map( 'strval', $names ), 'strlen' ) );

		if ( $names ) {
			$placeholders = implode( ',', array_fill( 0, count( $names ), '%s' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a run of literal %s, one per name
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ($placeholders) ORDER BY option_name", $names ), ARRAY_A );
		} else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Full export
			$rows = $wpdb->get_results(
				"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name NOT LIKE '\_transient\_%' AND option_name NOT LIKE '\_site\_transient\_%' AND option_name <> 'nhrotm_history' AND option_name NOT LIKE 'nhrotm\_snapshot%'",
				ARRAY_A
			);
		}
		$rows = $rows ? $rows : [];

		return [
			'generated_at' => gmdate( 'c' ),
			'site'         => home_url(),
			'count'        => count( $rows ),
			'options'      => $rows,
			'checksum'     => md5( (string) wp_json_encode( $rows ) ),
		];
	}

	/**
	 * Option names matching a search term, for the Export basket picker.
	 *
	 * @param string $term Substring of the option name.
	 * @return string[]
	 */
	public function search_option_names( $term ) {
		global $wpdb;
		$term = trim( (string) $term );
		if ( '' === $term ) {
			return [];
		}
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 20", '%' . $wpdb->esc_like( $term ) . '%' ) );
	}

	/**
	 * Parse an export file into [ name, value, autoload ] rows.
	 *
	 * Accepts both formats: 2.x (`option_name`/`option_value`) and 1.x /
	 * Classic (`name`/`value`), plus a bare array of rows. When the file
	 * carries a checksum it must match, so a damaged file is rejected.
	 *
	 * @param string $json Export file contents.
	 * @return array[]
	 * @throws \Exception On invalid JSON, a checksum mismatch, or no options.
	 */
	private function parse_import( $json ) {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			throw new \Exception( __( 'Invalid JSON.', 'nhrrob-options-table-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not rendered as HTML output
		}
		$options = isset( $data['options'] ) && is_array( $data['options'] ) ? $data['options'] : $data;

		if ( isset( $data['checksum'], $data['options'] ) && md5( (string) wp_json_encode( $data['options'] ) ) !== $data['checksum'] ) {
			throw new \Exception( __( 'File integrity check failed (checksum mismatch). The file may have been edited or truncated.', 'nhrrob-options-table-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not rendered as HTML output
		}

		$rows = [];
		foreach ( $options as $opt ) {
			if ( ! is_array( $opt ) ) {
				continue;
			}
			$name  = isset( $opt['option_name'] ) ? $opt['option_name'] : ( isset( $opt['name'] ) ? $opt['name'] : '' );
			$value = isset( $opt['option_value'] ) ? $opt['option_value'] : ( isset( $opt['value'] ) ? $opt['value'] : '' );
			if ( ! is_string( $name ) || '' === $name || ! is_scalar( $value ) ) {
				continue;
			}
			$rows[ $name ] = [
				'name'     => $name,
				'value'    => (string) $value,
				'autoload' => ( isset( $opt['autoload'] ) && in_array( $opt['autoload'], [ 'no', 'off', 'auto-off', 'false', '0', '' ], true ) ) ? 'no' : 'yes',
			];
		}
		if ( empty( $rows ) ) {
			throw new \Exception( __( 'No options found in the payload.', 'nhrrob-options-table-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not rendered as HTML output
		}
		return $rows;
	}

	/**
	 * Preview an import: each option's status against this site.
	 *
	 * @param string $json Export file contents.
	 * @return array[] { name, status: new|modified|unchanged|protected, current, autoload }
	 * @throws \Exception On an invalid file (see parse_import()).
	 */
	public function preview_import( $json ) {
		global $wpdb;
		$preview = [];
		foreach ( $this->parse_import( $json ) as $row ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $row['name'] ) );
			if ( in_array( $row['name'], $this->get_protected_options(), true ) || $this->is_unsafe_import_value( $row['value'] ) ) {
				$status = 'protected';
			} elseif ( null === $current ) {
				$status = 'new';
			} else {
				$status = $current === $row['value'] ? 'unchanged' : 'modified';
			}
			$preview[] = [
				'name'     => $row['name'],
				'status'   => $status,
				'current'  => null === $current ? null : ( strlen( $current ) > 100 ? substr( $current, 0, 100 ) . '…' : $current ),
				'autoload' => $row['autoload'],
			];
		}
		return $preview;
	}

	/**
	 * Import options from an export file.
	 *
	 * @param string        $json      Export file contents.
	 * @param bool          $overwrite Replace options that already exist (ignored when $selected is given).
	 * @param string[]|null $selected  Only these option names; each is written even if it exists (the preview's checked rows).
	 * @return array { imported, skipped }
	 * @throws \Exception On an invalid file (see parse_import()).
	 */
	public function import_options( $json, $overwrite = false, $selected = null ) {
		$rows = $this->parse_import( $json );
		if ( is_array( $selected ) ) {
			$rows      = array_intersect_key( $rows, array_flip( array_map( 'strval', $selected ) ) );
			$overwrite = true;
		}

		$this->backups->create_snapshot( __( 'Auto: before import', 'nhrrob-options-table-manager' ), 'auto' );
		$this->activity->record( 'snapshot', __( 'before import', 'nhrrob-options-table-manager' ) );

		global $wpdb;
		$imported = 0;
		$skipped  = 0;
		foreach ( $rows as $row ) {
			// Same core-option protection as Browse/Search & Replace, and never
			// import a value that would plant a PHP object (see is_unsafe_import_value()).
			if ( in_array( $row['name'], $this->get_protected_options(), true ) || $this->is_unsafe_import_value( $row['value'] ) ) {
				++$skipped;
				continue;
			}

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$previous = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $row['name'] ) );
			if ( null !== $previous && ! $overwrite ) {
				++$skipped;
				continue;
			}

			// Raw values are written as-is so serialized data round-trips exactly.
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- raw write so serialized values round-trip exactly; wp_cache_flush() below
			if ( null !== $previous ) {
				$ok = $wpdb->update(
					$wpdb->options,
					[
						'option_value' => $row['value'],
						'autoload'     => $row['autoload'],
					],
					[ 'option_name' => $row['name'] ],
					[ '%s', '%s' ],
					[ '%s' ]
				);
			} else {
				$ok = $wpdb->insert(
					$wpdb->options,
					[
						'option_name'  => $row['name'],
						'option_value' => $row['value'],
						'autoload'     => $row['autoload'],
					],
					[ '%s', '%s', '%s' ]
				);
			}
            // phpcs:enable
			if ( false === $ok ) {
				++$skipped;
				continue;
			}
			++$imported;
		}

		wp_cache_flush();
		return [
			'imported' => $imported,
			'skipped'  => $skipped,
		];
	}
}
