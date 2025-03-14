<?php

namespace Nhrotm\OptionsTableManager\Admin;

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
         
        ob_start();
		include NHROTM_VIEWS_PATH . '/admin/settings/index.php';
        $content = ob_get_clean();
        echo wp_kses( $content, $this->allowed_html() );
    }
}