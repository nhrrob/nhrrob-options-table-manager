<?php
namespace Nhrotm\OptionsTableManager\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Core\ModuleRegistry;

/**
 * Admin page host for the 2.0 React app.
 *
 * Registers a preview submenu, renders the mount node, and enqueues the
 * compiled bundle (admin/build). Runs alongside the 1.5.x UI — additive and
 * dev-preview only until 2.0 reaches parity and takes over the main menu.
 */
class AppPage
{
    const SLUG = 'nhrotm-options-table-manager';

    /**
     * @var ModuleRegistry
     */
    private $registry;

    public function __construct(ModuleRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @return void
     */
    public function init()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    /**
     * @return void
     */
    public function register_menu()
    {
        add_management_page(
            __('Options Table', 'nhrrob-options-table-manager'),
            __('Options Table', 'nhrrob-options-table-manager'),
            'manage_options',
            self::SLUG,
            [$this, 'render']
        );
    }

    /**
     * @return void
     */
    public function render()
    {
        // The heading + wp-header-end marker is what tells core's common.js to
        // relocate admin notices inside .wrap. Without it they render above the
        // wrap at a different indent than the app. The app draws its own title
        // bar, so the h1 is for screen readers and notice placement only.
        ?>
        <div class="wrap nhrotm-wrap">
            <h1 class="screen-reader-text"><?php esc_html_e('Options Table Manager', 'nhrrob-options-table-manager'); ?></h1>
            <hr class="wp-header-end">
            <div id="nhrotm-app"></div>
        </div>
        <?php
    }

    /**
     * Enqueue the compiled app only on our page, only when a build exists.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue($hook)
    {
        if ('tools_page_' . self::SLUG !== $hook) {
            return;
        }

        $build_dir = NHROTM_PATH . '/admin/build';
        $asset_file = $build_dir . '/index.asset.php';
        if (!file_exists($asset_file)) {
            return; // Not built yet — run `npm run build`.
        }

        $asset = require $asset_file;
        $build_url = NHROTM_URL . '/admin/build';

        wp_enqueue_script(
            'nhrotm-app',
            $build_url . '/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        if (file_exists($build_dir . '/style-index.css')) {
            wp_enqueue_style(
                'nhrotm-app',
                $build_url . '/style-index.css',
                [],
                $asset['version']
            );
        }

        wp_localize_script('nhrotm-app', 'nhrotmApp', [
            'restRoot'   => esc_url_raw(rest_url()),
            'nonce'      => wp_create_nonce('wp_rest'),
            'pluginName' => __('Options Table Manager', 'nhrrob-options-table-manager'),
            'modules'    => $this->modules_payload(),
            // Legacy DataTables UI — hidden from the menu, reached via this in-app link.
            'classicUrl' => esc_url_raw(admin_url('tools.php?page=nhrotm-classic')),
            // PRO-awareness handoff: an active PRO add-on flips this true to hide
            // the free build's Upgrade item + feature tags. See PRD §0.2.
            'hasPro'     => (bool) apply_filters('nhrotm_has_pro', false),
            'upgradeUrl' => apply_filters('nhrotm_upgrade_url', 'https://wordpress.org/plugins/nhrrob-options-table-manager/'),
        ]);
    }

    /**
     * Registry-driven nav payload — mirrors the PHP ModuleRegistry so the
     * React nav and add-on modules stay in sync.
     *
     * @return array
     */
    private function modules_payload()
    {
        $payload = [];
        foreach ($this->registry->get_modules() as $module) {
            $payload[] = [
                'id'    => $module->id(),
                'label' => $module->label(),
            ];
        }
        return $payload;
    }
}
