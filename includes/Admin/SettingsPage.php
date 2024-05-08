<?php

namespace Nhrrob\NhrrobOptionsTableManager\Admin;

use Nhrrob\NhrrobOptionsTableManager\Traits\GlobalTrait;

/**
 * The Menu handler class
 */
class SettingsPage extends Page
{
    use GlobalTrait;

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
        
        ob_start();
		include NHRROB_OPTIONS_TABLE_MANAGER_VIEWS_PATH . '/admin/settings/index.php';
        $content = ob_get_clean();
        echo wp_kses( $content, $this->allowed_html() );
    }
}