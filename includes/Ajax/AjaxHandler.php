<?php
namespace Nhrotm\OptionsTableManager\Ajax;

use Nhrotm\OptionsTableManager\Managers\BetterPaymentTableManager;
use Nhrotm\OptionsTableManager\Managers\CommonTableManager;
use Nhrotm\OptionsTableManager\Managers\OptionsTableManager;
use Nhrotm\OptionsTableManager\Managers\UsermetaTableManager;
use Nhrotm\OptionsTableManager\Managers\WprmRatingsTableManager;

class AjaxHandler {
    private $options_manager;
    private $usermeta_manager;
    private $better_payment_manager;
    private $wprm_ratings_manager;
    protected $wpdb;

    public function __construct() {
        $this->options_manager = new OptionsTableManager();
        $this->usermeta_manager = new UsermetaTableManager();
        $this->better_payment_manager = new BetterPaymentTableManager();
        $this->wprm_ratings_manager = new WprmRatingsTableManager();

        global $wpdb;
        $this->wpdb = $wpdb;
        $this->registerHandlers();
    }

    private function registerHandlers() {
        $ajax_actions = [
            'nhrotm_option_table_data' => 'options_table_data',
            'nhrotm_get_option' => 'get_option',
            'nhrotm_add_option' => 'add_option',
            'nhrotm_edit_option' => 'edit_option',
            'nhrotm_delete_option' => 'delete_option',
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
        ];

        foreach ($ajax_actions as $action => $method) {
            add_action("wp_ajax_{$action}", [$this, $method]);
        }
    }

    public function options_table_data() {
        try {
            $data = $this->options_manager->get_data();
            wp_send_json($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function get_option() {
        try {
            $data = $this->options_manager->get_option();
            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function add_option() {
        try {
            $data = $this->options_manager->add_option();
            if ( $data ) {
                wp_send_json_success('Option added successfully');
            } else {
                wp_send_json_error('Failed to update option!');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function edit_option() {
        try {
            $result = $this->options_manager->edit_record();

            if ( $result ) {
                wp_send_json_success('Option updated successfully!');
            } else {
                wp_send_json_error('Failed to update option!');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function delete_option() {
        try {
            $result = $this->options_manager->delete_record();
            if ( $result ) {
                wp_send_json_success('Option deleted successfully!');
            } else {
                wp_send_json_error('Failed to delete option!');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function delete_expired_transients() {
        try {
            $result = $this->options_manager->delete_expired_transients();
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function option_usage_analytics() {
        try {
            $result = $this->options_manager->option_usage_analytics();
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function usermeta_table_data() {
        try {
            $data = $this->usermeta_manager->get_data();
            wp_send_json($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function edit_usermeta() {
        try {
            $result = $this->usermeta_manager->edit_record();

            if ( $result ) {
                wp_send_json_success('Meta updated successfully!');
            } else {
                wp_send_json_error('Failed to update meta!');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function delete_usermeta() {
        try {
            $result = $this->usermeta_manager->delete_record();
            if ( $result ) {
                wp_send_json_success('Meta deleted successfully!');
            } else {
                wp_send_json_error('Failed to delete meta!');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function better_payment_table_data() {
        try {
            $data = $this->better_payment_manager->get_data();
            wp_send_json($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function wprm_ratings_table_data() {
        try {
            $data = $this->wprm_ratings_manager->get_data();
            wp_send_json($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function wprm_analytics_table_data() {
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
    
    public function wprm_changelog_table_data() {
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
}