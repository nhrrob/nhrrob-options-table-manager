<?php

namespace Nhrotm\OptionsTableManager;

/**
 * Ajax handler class
 */
class Ajax extends App{

    /**
     * Class constructor
     */
    function __construct() {
        //
        add_action('wp_ajax_nhrotm_edit_option', [ $this, 'edit_option' ]);
        add_action('wp_ajax_nhrotm_delete_option', [ $this, 'delete_option' ]);
    }
    
    public function edit_option() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
    
        // Ensure the user has the right capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }
    
        // Sanitize and validate input data
        $option_name = isset($_POST['option_name']) ? sanitize_text_field($_POST['option_name']) : '';
        $option_value = isset($_POST['option_value']) ? stripslashes_deep(sanitize_text_field($_POST['option_value'])) : '';
    
        if (empty($option_name)) {
            wp_send_json_error('Option name is required');
            wp_die();
        }

        if (in_array($option_name, $this->get_protected_options())) {
            wp_send_json_error('This option is protected and cannot be edited');
            wp_die();
        }
    
        // Update the option
        if (update_option($option_name, $option_value)) {
            wp_send_json_success('Option updated successfully');
        } else {
            wp_send_json_error('Failed to update option');
        }
    
        wp_die();
    }

    public function delete_option() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nhrotm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }
    
        // Ensure the user has the right capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }
    
        // Sanitize and validate input data
        $option_name = isset($_POST['option_name']) ? sanitize_text_field($_POST['option_name']) : '';
    
        if (empty($option_name)) {
            wp_send_json_error('Option name is required');
            wp_die();
        }

        if (in_array($option_name, $this->get_protected_options())) {
            wp_send_json_error('This option is protected and cannot be deleted');
            wp_die();
        }
    
        // Delete the option
        if (delete_option($option_name)) {
            wp_send_json_success('Option deleted successfully');
        } else {
            wp_send_json_error('Failed to delete option');
        }
    
        wp_die();
    }
}
