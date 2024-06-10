<?php

namespace Nhrotm\OptionsTableManager\Admin;

use Nhrotm\OptionsTableManager\Traits\GlobalTrait;

/**
 * The Menu handler class
 */
class Menu
{
    use GlobalTrait;

    /**
     * Initialize the class
     */
    function __construct()
    {
        add_action('admin_menu', [$this, 'admin_menu']);
    }

    /**
     * Register admin menu
     *
     * @return void
     */
    public function admin_menu()
    {
        $parent_slug = 'nhrotm-options-table-manager';
        $capability = apply_filters('nhrotm-options-table-manager/menu/capability', 'manage_options');

        // $hook = add_menu_page(__('Options Table', 'nhrrob-options-table-manager'), __('Options Table', 'nhrrob-options-table-manager'), $capability, $parent_slug, [$this, 'settings_page'], 'dashicons-admin-post');
        // add_submenu_page( $parent_slug, __( 'Settings', 'nhrrob-options-table-manager' ), __( 'Settings', 'nhrrob-options-table-manager' ), $capability, 'nhrotm-options-table-manager-settings', [ $this, 'settings_page' ] );
        $hook = add_submenu_page( 'tools.php', __( 'Options Table', 'nhrrob-options-table-manager' ), __( 'Options Table', 'nhrrob-options-table-manager' ), $capability, $parent_slug, [ $this, 'settings_page' ] );

        add_action('admin_head-' . $hook, [$this, 'enqueue_assets']);
    }

    /**
     * Handles the settings page
     *
     * @return void
     */
    public function settings_page()
    {
        $settings_page = new SettingsPage();
        
        ob_start();
        $settings_page->view();
        $content = ob_get_clean();
        
        echo wp_kses( $content, $this->allowed_html() );
    }

    /**
     * Enqueue scripts and styles
     *
     * @return void
     */
    public function enqueue_assets()
    {
        wp_enqueue_style('nhrotm-options-table-manager-datatable-style');
        wp_enqueue_style('nhrotm-options-table-manager-admin-style');

        wp_enqueue_script('jquery');
        wp_enqueue_script('nhrotm-options-table-manager-datatable-script');
        wp_enqueue_script('nhrotm-options-table-manager-admin-script');
    }
}
