<?php
namespace Nhrotm\OptionsTableManager\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Managers\BackupManager;
use Nhrotm\OptionsTableManager\Managers\SearchReplaceManager;

/**
 * Action service for the Tools section: backups, search & replace, export.
 *
 * Reuses BackupManager and SearchReplaceManager. A safety snapshot is taken
 * automatically before a live (non-dry-run) search & replace.
 */
class ToolsService
{
    /**
     * @var BackupManager
     */
    private $backups;

    /**
     * @var ActivityService
     */
    private $activity;

    public function __construct(?ActivityService $activity = null)
    {
        $this->backups  = new BackupManager();
        $this->activity = $activity ? $activity : new ActivityService();
    }

    /**
     * @return array
     */
    public function list_backups()
    {
        return $this->backups->get_snapshots();
    }

    /**
     * @param string $label Optional label.
     * @return int|false
     */
    public function create_backup($label = '')
    {
        $id = $this->backups->create_snapshot($label, 'manual');

        if ($id) {
            $this->activity->record('snapshot', __('manually', 'nhrrob-options-table-manager'));
        }

        return $id;
    }

    /**
     * @param int $id Snapshot id.
     * @return int Options restored.
     */
    public function restore_backup($id)
    {
        return $this->backups->restore_snapshot($id);
    }

    /**
     * @param int $id Snapshot id.
     * @return bool
     */
    public function delete_backup($id)
    {
        return $this->backups->delete_snapshot($id);
    }

    /**
     * Run search & replace. Auto-snapshots before a live run.
     *
     * @param string $search  Needle.
     * @param string $replace Replacement.
     * @param bool   $dry_run Preview only when true.
     * @return array
     */
    public function search_replace($search, $replace, $dry_run = true)
    {
        if (!$dry_run) {
            $this->backups->create_snapshot(__('Auto: before search & replace', 'nhrrob-options-table-manager'), 'auto');
            $this->activity->record('snapshot', __('before search & replace', 'nhrrob-options-table-manager'));
        }
        return (new SearchReplaceManager())->execute_replace($search, $replace, $dry_run);
    }

    /**
     * Export all non-transient options as a portable structure.
     *
     * @return array
     */
    public function export_options()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Full export
        $rows = $wpdb->get_results(
            "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name NOT LIKE '\_transient\_%' AND option_name NOT LIKE '\_site\_transient\_%'",
            ARRAY_A
        );

        return [
            'generated_at' => gmdate('c'),
            'site'         => home_url(),
            'count'        => count($rows),
            'options'      => $rows ? $rows : [],
        ];
    }

    /**
     * Import options from a JSON export payload. Auto-snapshots first.
     *
     * @param string $json      Raw JSON (export format, or a bare options array).
     * @param bool   $overwrite Overwrite existing options when true.
     * @return array { imported, skipped }
     * @throws \Exception On invalid JSON.
     */
    public function import_options($json, $overwrite = false)
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \Exception(__('Invalid JSON.', 'nhrrob-options-table-manager'));
        }
        $options = isset($data['options']) && is_array($data['options']) ? $data['options'] : $data;
        if (empty($options)) {
            throw new \Exception(__('No options found in the payload.', 'nhrrob-options-table-manager'));
        }

        $this->backups->create_snapshot(__('Auto: before import', 'nhrrob-options-table-manager'), 'auto');
        $this->activity->record('snapshot', __('before import', 'nhrrob-options-table-manager'));

        global $wpdb;
        $imported = 0;
        $skipped = 0;
        foreach ($options as $opt) {
            if (empty($opt['option_name'])) {
                $skipped++;
                continue;
            }
            $name = $opt['option_name'];
            $value = isset($opt['option_value']) ? $opt['option_value'] : '';
            $autoload = ( isset($opt['autoload']) && in_array($opt['autoload'], ['no', 'false', '0', ''], true) ) ? 'no' : 'yes';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $exists = $wpdb->get_var($wpdb->prepare("SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $name));
            if ($exists && !$overwrite) {
                $skipped++;
                continue;
            }

            if ($exists) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update($wpdb->options, ['option_value' => $value, 'autoload' => $autoload], ['option_name' => $name], ['%s', '%s'], ['%s']);
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->insert($wpdb->options, ['option_name' => $name, 'option_value' => $value, 'autoload' => $autoload], ['%s', '%s', '%s']);
            }
            $imported++;
        }

        wp_cache_flush();
        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
