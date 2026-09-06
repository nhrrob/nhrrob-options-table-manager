<?php
namespace Nhrotm\OptionsTableManager\Managers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BackupManager
 *
 * Creates restorable JSON snapshots of the wp_options table. Snapshots can be
 * taken manually, on a schedule (via Cron), or automatically before
 * destructive operations. Older snapshots are pruned to bound table growth.
 */
class BackupManager
{
    const FREQUENCY_OPTION = 'nhrotm_backup_frequency';
    const CRON_HOOK        = 'nhrotm_scheduled_backup';
    const MAX_SNAPSHOTS    = 15;

    /**
     * Backups table name.
     *
     * @var string
     */
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'nhrotm_option_backups';
    }

    /**
     * Create the backups table.
     *
     * @return void
     */
    public function create_table()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            label varchar(191) NOT NULL,
            type varchar(20) NOT NULL,
            option_count int(11) NOT NULL DEFAULT 0,
            data longtext NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Ensure the table exists (lazy creation).
     *
     * @return void
     */
    private function ensure_table()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time schema check
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)) !== $this->table_name) {
            $this->create_table();
        }
    }

    /**
     * Snapshot the entire options table.
     *
     * @param string $label Human-readable label.
     * @param string $type  manual | scheduled | auto
     * @return int|false Inserted snapshot ID or false on failure
     */
    public function create_snapshot($label = '', $type = 'manual')
    {
        global $wpdb;
        $this->ensure_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Full options snapshot
        $options = $wpdb->get_results("SELECT option_name, option_value, autoload FROM {$wpdb->options}", ARRAY_A);

        $data = wp_json_encode($options, JSON_INVALID_UTF8_SUBSTITUTE);
        if (false === $data) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific backup
        $result = $wpdb->insert(
            $this->table_name,
            [
                'label'        => $label ? $label : gmdate('Y-m-d H:i'),
                'type'         => $type,
                'option_count' => count($options),
                'data'         => $data,
                'created_at'   => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );

        $this->prune();

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * List snapshots (without the heavy data payload).
     *
     * @return array
     */
    public function get_snapshots()
    {
        global $wpdb;
        $this->ensure_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific query
        $rows = $wpdb->get_results(
            "SELECT id, label, type, option_count, LENGTH(data) AS size_bytes, created_at
            FROM {$this->table_name} ORDER BY created_at DESC",
            ARRAY_A
        );

        return array_map(function ($row) {
            $row['size_formatted'] = size_format($row['size_bytes']);
            return $row;
        }, $rows ? $rows : []);
    }

    /**
     * Restore a snapshot by re-applying each stored option.
     *
     * @param int $id Snapshot ID.
     * @return int Number of options restored
     */
    public function restore_snapshot($id)
    {
        global $wpdb;
        $id = (int) $id;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific query
        $data = $wpdb->get_var($wpdb->prepare("SELECT data FROM {$this->table_name} WHERE id = %d", $id));
        if (!$data) {
            throw new \Exception('Snapshot not found');
        }

        $options = json_decode($data, true);
        if (!is_array($options)) {
            throw new \Exception('Snapshot data is corrupt');
        }

        $restored = 0;
        foreach ($options as $option) {
            if (empty($option['option_name'])) {
                continue;
            }
            $autoload = in_array($option['autoload'], ['yes', 'on', 'auto', 'auto-on'], true) ? 'yes' : 'no';
            update_option($option['option_name'], maybe_unserialize($option['option_value']), $autoload);
            $restored++;
        }

        return $restored;
    }

    /**
     * Delete a snapshot.
     *
     * @param int $id Snapshot ID.
     * @return bool
     */
    public function delete_snapshot($id)
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific deletion
        return (bool) $wpdb->delete($this->table_name, ['id' => (int) $id], ['%d']);
    }

    /**
     * Keep only the most recent MAX_SNAPSHOTS rows.
     *
     * @return void
     */
    private function prune()
    {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d, 100000",
                self::MAX_SNAPSHOTS
            )
        );
        if (!empty($ids)) {
            $ids_sql = implode(',', array_map('intval', $ids));
            $wpdb->query("DELETE FROM {$this->table_name} WHERE id IN ($ids_sql)");
        }
        // phpcs:enable
    }

    /**
     * Reschedule the backup Cron event for a frequency: off | daily | weekly.
     *
     * @param string $frequency Backup frequency.
     * @return void
     */
    public static function reschedule($frequency)
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        if (in_array($frequency, ['daily', 'weekly'], true)) {
            wp_schedule_event(time(), $frequency, self::CRON_HOOK);
        }
    }
}
