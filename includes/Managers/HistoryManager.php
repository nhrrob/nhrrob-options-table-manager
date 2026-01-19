<?php
namespace Nhrotm\OptionsTableManager\Managers;

/**
 * Class HistoryManager
 * 
 * Manages the option history: logging changes, retrieving history, and restoring versions.
 */
class HistoryManager
{

    /**
     * Table name for option history
     *
     * @var string
     */
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'nhrotm_option_history';
    }

    /**
     * Create the database table for storing history
     *
     * @return void
     */
    public function create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            option_name varchar(191) NOT NULL,
            option_value longtext NOT NULL,
            action varchar(50) NOT NULL,
            performed_by bigint(20) NOT NULL,
            performed_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY option_name (option_name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log a change to an option
     * 
     * @param string $option_name
     * @param mixed $old_value
     * @param string $action 'update' or 'delete'
     * @return int|false The inserted ID or false on error
     */
    public function log_change($option_name, $old_value, $action = 'update')
    {
        global $wpdb;

        // If value is array or object, serialize it
        if (is_array($old_value) || is_object($old_value)) {
            $old_value = maybe_serialize($old_value);
        }

        return $wpdb->insert(
            $this->table_name,
            [
                'option_name' => $option_name,
                'option_value' => $old_value,
                'action' => $action,
                'performed_by' => get_current_user_id(),
                'performed_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * Get history for a specific option
     * 
     * @param string $option_name
     * @return array
     */
    public function get_history($option_name)
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $this->table_name WHERE option_name = %s ORDER BY performed_at DESC",
                sanitize_text_field(wp_unslash($option_name))
            ),
            ARRAY_A
        );
    }

    /**
     * Restore a specific version
     * 
     * @param int $history_id
     * @return bool|string True on success, error message string on failure
     */
    public function restore_version($history_id)
    {
        global $wpdb;

        $record = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $history_id),
            ARRAY_A
        );

        if (!$record) {
            return 'Record not found';
        }

        $option_name = $record['option_name'];
        $option_value = $record['option_value'];

        // If it was serialized, it might be double serialized or just serialized string.
        // update_option expects the value as it should be used.
        // If the stored value in DB is serialized, we should probably keep it as is if update_option handles serialization,
        // BUT update_option expects the *unserialized* data if it's complex data.
        // However, we stored the raw value from DB.

        // Let's check how we retrieve it. 
        // In log_change, we did maybe_serialize.

        $value_to_restore = maybe_unserialize($option_value);

        // We log the CURRENT state before restoring, effectively adding a new history entry for the "undo"
        $current_value = get_option($option_name);
        if ($current_value !== false) {
            $this->log_change($option_name, $current_value, 'restore_backup');
        } else {
            // If option doesn't exist (it was deleted), we can't really "log change" of current value easily 
            // unless we treats "non-existent" as null/empty.
            // But simpler to just log that we are restoring.
        }

        if (update_option($option_name, $value_to_restore)) {
            return true;
        }

        // If update_option returns false, it might mean the value is unchanged. 
        // But for restore, that's fine.
        // Or if option was deleted, update_option might act as add_option.
        if (get_option($option_name) == $value_to_restore) {
            return true;
        }

        // Try add_option if update failed and option doesn't exist
        if (get_option($option_name) === false) {
            if (add_option($option_name, $value_to_restore)) {
                return true;
            }
        }

        return 'Failed to restore option';
    }
}
