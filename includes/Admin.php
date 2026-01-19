<?php
namespace Nhrotm\OptionsTableManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The admin class
 */
class Admin extends App
{

    /**
     * Initialize the class
     */
    function __construct()
    {
        parent::__construct();

        $this->dispatch_actions();
        new Admin\Menu();
    }

    /**
     * Dispatch and bind actions
     *
     * @return void
     */
    public function dispatch_actions()
    {
        add_filter('plugin_action_links', array($this, 'plugin_actions_links'), 10, 2);
    }

    /**
     * Add settings page link on plugins page
     *
     * @param array $links
     * @param string $file
     *
     * @return array
     * @since 1.0.1
     */
    public function plugin_actions_links($links, $file)
    {
        $nhrotm_plugin = plugin_basename(NHROTM_FILE);

        if ($file == $nhrotm_plugin && current_user_can('manage_options')) {
            $links[] = sprintf('<a href="%s">%s</a>', admin_url("tools.php?page={$this->page_slug}"), __('Options Table', 'nhrrob-options-table-manager'));
        }

        return $links;
    }
}