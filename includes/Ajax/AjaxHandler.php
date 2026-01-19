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

class AjaxHandler
{
    private $options_manager;
    private $usermeta_manager;
    private $better_payment_manager;
    private $wprm_ratings_manager;
    private $optimization_manager;
    protected $wpdb;

    public function __construct()
    {
        $this->options_manager = new OptionsTableManager();
        $this->usermeta_manager = new UsermetaTableManager();
        $this->better_payment_manager = new BetterPaymentTableManager();
        $this->wprm_ratings_manager = new WprmRatingsTableManager();
        $this->optimization_manager = new OptimizationManager();

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
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
            $data = $this->optimization_manager->get_heavy_autoload_options($limit);
            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function toggle_autoload()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {
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
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

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
}