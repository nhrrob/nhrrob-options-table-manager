<?php
namespace Nhrotm\OptionsTableManager\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Managers\BetterPaymentTableManager;
use Nhrotm\OptionsTableManager\Managers\CommonTableManager;
use Nhrotm\OptionsTableManager\Managers\OptionsTableManager;
use Nhrotm\OptionsTableManager\Managers\UsermetaTableManager;
use Nhrotm\OptionsTableManager\Managers\WprmRatingsTableManager;
use Nhrotm\OptionsTableManager\Managers\OptimizationManager;
use Nhrotm\OptionsTableManager\Managers\ScannerManager;
use Nhrotm\OptionsTableManager\Managers\SearchReplaceManager;
use Nhrotm\OptionsTableManager\Managers\ImportExportManager;

class AjaxHandler
{
    private $options_manager;
    private $usermeta_manager;
    private $better_payment_manager;
    private $wprm_ratings_manager;
    private $optimization_manager;
    private $scanner_manager;
    private $search_replace_manager;
    private $import_export_manager;
    protected $wpdb;

    public function __construct()
    {
        $this->options_manager = new OptionsTableManager();
        $this->usermeta_manager = new UsermetaTableManager();
        $this->better_payment_manager = new BetterPaymentTableManager();
        $this->wprm_ratings_manager = new WprmRatingsTableManager();
        $this->optimization_manager = new OptimizationManager();
        $this->scanner_manager = new ScannerManager();
        $this->search_replace_manager = new SearchReplaceManager();
        $this->import_export_manager = new ImportExportManager();

        global $wpdb;
        $this->wpdb = $wpdb;
        $this->registerHandlers();
    }

    private function registerHandlers()
    {
        $ajax_actions = [
            'nhrotm_option_table_data' => 'options_table_data',
            'nhrotm_get_option' => 'get_option',
            'nhrotm_add_option' => 'add_option',
            'nhrotm_edit_option' => 'edit_option',
            'nhrotm_delete_option' => 'delete_option',
            'nhrotm_bulk_delete_options' => 'bulk_delete_options',
            'nhrotm_delete_expired_transients' => 'delete_expired_transients',
            'nhrotm_option_usage_analytics' => 'option_usage_analytics',
            //
            'nhrotm_usermeta_table_data' => 'usermeta_table_data',
            'nhrotm_edit_usermeta' => 'edit_usermeta',
            'nhrotm_delete_usermeta' => 'delete_usermeta',
            //
            'nhrotm_better_payment_table_data' => 'better_payment_table_data',
            //
            'nhrotm_wprm_ratings_table_data' => 'wprm_ratings_table_data',
            'nhrotm_wprm_analytics_table_data' => 'wprm_analytics_table_data',
            'nhrotm_wprm_changelog_table_data' => 'wprm_changelog_table_data',
            // Option History
            'nhrotm_get_option_history' => 'get_option_history',
            'nhrotm_restore_option_version' => 'restore_option_version',
            // Autoload Optimization
            'nhrotm_get_heavy_autoload_options' => 'get_heavy_autoload_options',
            'nhrotm_toggle_autoload' => 'toggle_autoload',
            'nhrotm_get_total_autoload_size' => 'get_total_autoload_size',
            // Auto Cleanup
            'nhrotm_update_auto_cleanup_setting' => 'update_auto_cleanup_setting',
            // Orphan Scanner
            'nhrotm_scan_orphans' => 'scan_orphans',
            'nhrotm_delete_orphaned_prefix' => 'delete_orphaned_prefix',
            // Search & Replace
            'nhrotm_search_replace_preview' => 'search_replace_preview',
            'nhrotm_search_replace_execute' => 'search_replace_execute',
            // Import / Export
            'nhrotm_search_options_for_export' => 'search_options_for_export',
            'nhrotm_export_options' => 'export_options',
            'nhrotm_preview_import' => 'preview_import',
            'nhrotm_execute_import' => 'execute_import',

            // History & Optimization
            'nhrotm_save_history_settings' => 'save_history_settings',
            'nhrotm_prune_history' => 'prune_history',
        ];

        foreach ($ajax_actions as $action => $method) {
            add_action("wp_ajax_{$action}", [$this, $method]);
        }
    }

    public function options_table_data()
    {
        try {
            $data = $this->options_manager->get_data();
            wp_send_json($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function get_option()
    {
        try {
            $data = $this->options_manager->get_option();
            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function add_option()
    {
        try {
            $data = $this->options_manager->add_option();
            if ($data) {
                wp_send_json_success('Option added successfully');
            } else {
                wp_send_json_error('Failed to update option!');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function edit_option()
    {
        try {
            $result = $this->options_manager->edit_record();

            if ($result) {
                wp_send_json_success('Option updated successfully!');
            } else {
                wp_send_json_error('Failed to update option!');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function delete_option()
    {
        try {
            $result = $this->options_manager->delete_record();
            if ($result) {
                wp_send_json_success('Option deleted successfully!');
            } else {
                wp_send_json_error('Failed to delete option!');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function bulk_delete_options()
    {
        try {
            $result = $this->options_manager->bulk_delete_records();
            if ($result) {
                wp_send_json_success('Options deleted successfully!');
            } else {
                wp_send_json_error('Failed to delete options!');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function delete_expired_transients()
    {
        try {
            $result = $this->options_manager->delete_expired_transients();
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function option_usage_analytics()
    {
        try {
            $result = $this->options_manager->option_usage_analytics();
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function usermeta_table_data()
    {
        try {
            $data = $this->usermeta_manager->get_data();
            wp_send_json($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function edit_usermeta()
    {
        try {
            $result = $this->usermeta_manager->edit_record();

            if ($result) {
                wp_send_json_success('Meta updated successfully!');
            } else {
                wp_send_json_error('Failed to update meta!');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function delete_usermeta()
    {
        try {
            $result = $this->usermeta_manager->delete_record();
            if ($result) {
                wp_send_json_success('Meta deleted successfully!');
            } else {
                wp_send_json_error('Failed to delete meta!');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function better_payment_table_data()
    {
        try {
            $data = $this->better_payment_manager->get_data();
            wp_send_json($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function wprm_ratings_table_data()
    {
        try {
            $data = $this->wprm_ratings_manager->get_data();
            wp_send_json($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function wprm_analytics_table_data()
    {
        try {
            $table_name = $this->wpdb->prefix . 'wprm_analytics';
            $columns = ['id', 'type', 'meta', 'post_id', 'recipe_id', 'user_id', 'visitor_id', 'visitor', 'created_at'];

            $common_manager = new CommonTableManager($table_name, $columns);

            $data = $common_manager->get_data();
            wp_send_json($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function wprm_changelog_table_data()
    {
        try {
            $table_name = $this->wpdb->prefix . 'wprm_changelog';
            $columns = ['id', 'type', 'meta', 'object_id', 'object_meta', 'user_id', 'user_meta', 'created_at'];

            $common_manager = new CommonTableManager($table_name, $columns);

            $data = $common_manager->get_data();
            wp_send_json($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function get_option_history()
    {
        try {
            $data = $this->options_manager->get_option_history();
            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function restore_option_version()
    {
        try {
            $result = $this->options_manager->restore_option_version();
            if ($result === true) {
                wp_send_json_success('Option restored successfully!');
            } else {
                wp_send_json_error($result);
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function get_heavy_autoload_options()
    {
        try {
            if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'nhrotm-admin-nonce')) {
                throw new \Exception('Invalid nonce');
            }
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
            $data = $this->optimization_manager->get_heavy_autoload_options($limit);
            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function toggle_autoload()
    {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
                throw new \Exception('Invalid nonce');
            }
            $result = $this->optimization_manager->toggle_autoload();
            if ($result) {
                wp_send_json_success('Autoload status updated!');
            } else {
                wp_send_json_error('Failed to update autoload status');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function get_total_autoload_size()
    {
        try {
            $size = $this->optimization_manager->get_total_autoload_size();
            wp_send_json_success(['size' => $size]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function update_auto_cleanup_setting()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $enabled = isset($_POST['enabled']) && sanitize_text_field(wp_unslash($_POST['enabled'])) === 'true' ? 'true' : 'false';
        update_option('nhrotm_auto_cleanup_enabled', $enabled);

        wp_send_json_success('Settings updated');
    }

    public function scan_orphans()
    {
        try {
            $data = $this->scanner_manager->scan_orphans();
            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function delete_orphaned_prefix()
    {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
                throw new \Exception('Invalid nonce');
            }

            // check if user has permission to delete options
            if (!current_user_can('manage_options')) {
                throw new \Exception('Unauthorized');
            }

            $prefix = isset($_POST['prefix']) ? sanitize_text_field(wp_unslash($_POST['prefix'])) : '';
            if (empty($prefix)) {
                throw new \Exception('Prefix is required');
            }

            $count = $this->scanner_manager->delete_by_prefix($prefix);
            wp_send_json_success(['message' => sprintf('%d options deleted successfully', $count)]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function search_replace_preview()
    {
        try {
            if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'nhrotm-admin-nonce')) {
                throw new \Exception('Invalid nonce');
            }

            // permission check
            if (!current_user_can('manage_options')) {
                throw new \Exception('Unauthorized');
            }

            $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
            if (empty($search)) {
                throw new \Exception('Search string is required');
            }

            $data = $this->search_replace_manager->preview_search($search);
            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function search_replace_execute()
    {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
                throw new \Exception('Invalid nonce');
            }

            if (!current_user_can('manage_options')) {
                throw new \Exception('Unauthorized');
            }

            $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
            $replace = isset($_POST['replace']) ? sanitize_text_field(wp_unslash($_POST['replace'])) : '';
            $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === 'true';

            if (empty($search)) {
                throw new \Exception('Search string is required');
            }

            $result = $this->search_replace_manager->execute_replace($search, $replace, $dry_run);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function search_options_for_export()
    {
        try {
            if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'nhrotm-admin-nonce')) {
                throw new \Exception('Invalid nonce');
            }
            if (!current_user_can('manage_options')) {
                throw new \Exception('Unauthorized');
            }
            $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
            $results = $this->import_export_manager->search_options_for_export($term);
            wp_send_json_success($results);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function export_options()
    {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
                throw new \Exception('Invalid nonce');
            }
            if (!current_user_can('manage_options')) {
                throw new \Exception('Unauthorized');
            }
            $options = isset($_POST['options']) ? array_map('sanitize_text_field', wp_unslash($_POST['options'])) : [];
            $data = $this->import_export_manager->export_options($options);
            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function preview_import()
    {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
                throw new \Exception('Invalid nonce');
            }
            if (empty($_FILES['import_file'])) {
                throw new \Exception('No file uploaded');
            }
            
            // check if user has permission to import options
            if (!current_user_can('manage_options')) {
                throw new \Exception('Unauthorized');
            }
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File path validated via is_uploaded_file
            $tmp_name = isset($_FILES['import_file']['tmp_name']) ? $_FILES['import_file']['tmp_name'] : '';
            if (empty($tmp_name) || !is_uploaded_file($tmp_name)) {
                throw new \Exception('Invalid file upload');
            }
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File path validated via is_uploaded_file
            $file_content = file_get_contents(wp_unslash($_FILES['import_file']['tmp_name']));
            if (!$file_content) throw new \Exception('Failed to read file');

            $json_data = json_decode($file_content, true);
            if (!$json_data) throw new \Exception('Invalid JSON format');

            $preview = $this->import_export_manager->preview_import($json_data);
            
            // Return preview + pass full JSON back to client (or stash in transient) for diffing
            // For simplicity in this step, we return the parsed JSON structure to client to hold in memory
            wp_send_json_success(['preview' => $preview, 'raw_data' => $json_data]); 
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function execute_import()
    {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
                throw new \Exception('Invalid nonce');
            }
            if (!current_user_can('manage_options')) {
                throw new \Exception('Unauthorized');
            }

            $raw_data = isset($_POST['raw_data']) ? json_decode(stripslashes(sanitize_text_field(wp_unslash($_POST['raw_data']))), true) : null;
            $selected = isset($_POST['selected_options']) ? array_map('sanitize_text_field', wp_unslash($_POST['selected_options'])) : [];

            if (!$raw_data) throw new \Exception('Missing import data');

            $count = $this->import_export_manager->execute_import($raw_data, $selected);
                wp_send_json_success(['count' => $count]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function save_history_settings()
    {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
                throw new \Exception('Invalid nonce');
            }
            if (!current_user_can('manage_options')) {
                throw new \Exception('Unauthorized');
            }

            $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
            if ($days < 1) $days = 30;

            update_option('nhrotm_history_retention_days', $days);
            wp_send_json_success('Settings saved');
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function prune_history()
    {
        try {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
                throw new \Exception('Invalid nonce');
            }
            if (!current_user_can('manage_options')) {
                throw new \Exception('Unauthorized');
            }

            $days = get_option('nhrotm_history_retention_days', 30);
            
            $history_manager = new \Nhrotm\OptionsTableManager\Managers\HistoryManager();
            $deleted = $history_manager->prune_history($days);
            
            wp_send_json_success(['deleted' => $deleted]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}