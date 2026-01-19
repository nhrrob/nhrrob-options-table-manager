<?php

namespace Nhrotm\OptionsTableManager\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The Menu handler class
 */
class SettingsPage extends Page
{
    /**
     * Initialize the class
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Handles the settings page
     *
     * @return void
     */
    public function view()
    {
        global $wpdb;
        // $options = $wpdb->get_results( "SELECT * FROM $wpdb->options ORDER BY option_name" );
        $options = wp_load_alloptions();
        $protected_options = $this->get_protected_options();
        $is_better_payment_installed = $this->is_better_payment_installed();
        $is_wp_recipe_maker_installed = $this->is_plugin_installed('\WP_Recipe_Maker');

        ob_start();
        include NHROTM_VIEWS_PATH . '/admin/settings/index.php';
        $content = ob_get_clean();
        echo wp_kses($content, $this->allowed_html());
    }
}