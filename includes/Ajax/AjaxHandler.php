<?php
namespace Nhrotm\OptionsTableManager\Ajax;

use Nhrotm\OptionsTableManager\Managers\OptionsTableManager;
use Nhrotm\OptionsTableManager\Services\ValidationService;

class AjaxHandler {
    private $options_manager;

    public function __construct() {
        $this->options_manager = new OptionsTableManager();
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
}