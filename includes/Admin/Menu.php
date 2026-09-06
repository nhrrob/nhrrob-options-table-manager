<?php

namespace Nhrotm\OptionsTableManager\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\App;

/**
 * The Menu handler class
 */
class Menu extends App
{
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
        // Legacy DataTables UI, kept reachable as "Classic" after the 2.0 React cutover.
        // The page stays registered (so its URL works and the React app can link to
        // it), but we hide its Tools submenu entry so only ONE menu item shows —
        // "Options Table" (the React app). Reach Classic from inside that app.
        $parent_slug = 'nhrotm-classic';
        $capability = apply_filters('nhrotm-options-table-manager/menu/capability', 'manage_options');

        $hook = add_submenu_page('tools.php', __('Options Table (Classic)', 'nhrrob-options-table-manager'), __('Options Table (Classic)', 'nhrrob-options-table-manager'), $capability, $parent_slug, [$this, 'settings_page']);

        // Registered but hidden from the menu — reachable only via tools.php?page=nhrotm-classic.
        remove_submenu_page('tools.php', $parent_slug);

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

        echo wp_kses($content, $this->allowed_html());
    }

    /**
     * Enqueue scripts and styles
     *
     * @return void
     */
    public function enqueue_assets()
    {
        wp_enqueue_style('nhrotm-datatable-style');
        wp_enqueue_style('nhrotm-admin-style');

        wp_enqueue_script('jquery');
        wp_enqueue_script('nhrotm-datatable-script');
        wp_enqueue_script('nhrotm-admin-script');
    }
}
