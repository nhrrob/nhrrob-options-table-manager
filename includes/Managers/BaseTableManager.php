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
    protected $validation_service;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->protected_items = $this->get_protected_options();
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
    protected function is_protected_item($key) {
        return in_array($key, $this->protected_items);
    }

    /**
     * Build search conditions for database query
     * 
     * @param array $params Search and filter parameters
     * @return array Query conditions
     */
    // protected function buildSearchConditions(array $params) {
    //     // using $_GET directly. params not needed. Need to test/confirm.
    //     $conditions = [];
    //     $bindParams = [];

    //     $search = isset($_GET['search']['value']) ? sanitize_text_field(wp_unslash($_GET['search']['value'])) : '';

    //     if (!empty($search)) {
    //         $searchLike = '%' . $this->wpdb->esc_like($params['search']) . '%';
    //         $searchColumns = $this->getSearchableColumns();
            
    //         $searchConditions = [];
    //         foreach ($searchColumns as $column) {
    //             $searchConditions[] = "{$column} LIKE %s";
    //             $bindParams[] = $searchLike;
    //         }

    //         $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
    //     }

    //     return [
    //         'conditions' => $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '',
    //         'params' => $bindParams
    //     ];
    // }

    /**
     * Get columns that can be searched
     * 
     * @return array
     */
    abstract protected function get_searchable_columns();
}