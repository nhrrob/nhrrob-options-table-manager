<?php
namespace Nhrotm\OptionsTableManager\Managers;

use Nhrotm\OptionsTableManager\Interfaces\TableManagerInterface;
use Nhrotm\OptionsTableManager\Services\ValidationService;
use Nhrotm\OptionsTableManager\Traits\GlobalTrait;

abstract class BaseTableManager implements TableManagerInterface {
    use GlobalTrait;

    protected $wpdb;
    protected $table_name;
    protected $protected_items = [];
    protected $protected_items_usermetas = [];
    protected $validation_service;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->protected_items = $this->get_protected_options();
        $this->protected_items_usermetas = $this->get_protected_usermetas();
        $this->validation_service = new ValidationService();
    }

    /**
     * Validate user permissions
     * 
     * @throws \Exception If user lacks required permissions
     */
    protected function validate_permissions() {
        if (!current_user_can('manage_options')) {
            throw new \Exception('Insufficient permissions');
        }
    }

    /**
     * Validate nonce
     * 
     * @param string $nonce Nonce to verify
     * @param string $action Nonce action
     * @throws \Exception If nonce is invalid
     */
    protected function validate_nonce($nonce, $action = 'nhrotm-admin-nonce') {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), $action)) {
            throw new \Exception('Invalid nonce');
        }
    }

    /**
     * Check if an item is protected
     * 
     * @param string $key Item key to check
     * @return bool
     */
    protected function is_protected_item($key, $table_name = '') {
        $protected_items_array = $this->wpdb->prefix . 'usermeta' === $table_name ? $this->protected_items_usermetas : $this->protected_items;
        return in_array($key, $protected_items_array);
    }

    /**
     * Get columns that can be searched
     * 
     * @return array
     */
    abstract protected function get_searchable_columns();
}