<?php
// First, load Composer's autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Set up WP_Mock
WP_Mock::bootstrap();

// Define WP_DIE_HANDLER constant to avoid issues with wp_die()
if (!defined('WP_DIE_HANDLER')) {
    define('WP_DIE_HANDLER', function($message, $title, $args) {
        throw new \Nhrotm_WP_Die_Exception($message, $title, $args);
    });
}

// Define the WP_Die_Exception class if not already defined
if (!class_exists('WP_Die_Exception')) {
    class Nhrotm_WP_Die_Exception extends \Exception {
        public function __construct($message, $title, $args) {
            parent::__construct($message);
        }
    }
}