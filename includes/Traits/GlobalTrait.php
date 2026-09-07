<?php

namespace Nhrotm\OptionsTableManager\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait GlobalTrait
{
    public function dd($var)
    {
        echo "<pre>";
        // phpcs:ignore:WordPress.PHP.DevelopmentFunctions.error_log_print_r
        print_r($var);
        wp_die('ok');
    }

    public function allowed_html()
    {
        $allowed_tags = wp_kses_allowed_html('post');

        $allowed_tags_extra = array(
            // 'li'   => array( 'class' => 1 ),
            // 'div'  => array( 'class' => 1 ),
            // 'span' => array( 'class' => 1 ),
            'a' => array(
                'href' => 1,
                'class' => 1,
                'id' => 1,
                'target' => 1,
            ),
            // 'img'  => array(
            //     'src'     => 1,
            //     'class'   => 1,
            //     'loading' => 1,
            // ),
            'svg' => array(
                'class' => 1,
                'xmlns' => 1,
                'aria-hidden' => 1,
                'aria-labelledby' => 1,
                'fill' => 1,
                'role' => 1,
                'width' => 1,
                'height' => 1,
                'viewbox' => 1,
                'stroke-width' => 1,
                'stroke' => 1,
            ),
            'g' => array(
                'fill' => 1,
            ),
            'title' => array(
                'title' => 1,
            ),
            'path' => array(
                'stroke-linecap' => 1,
                'stroke-linejoin' => 1,
                'd' => 1,
                'fill' => 1,
            ),
            'input' => array(
                'class' => 1,
                'type' => 1,
                'name' => 1,
                'placeholder' => 1,
                'value' => 1,
                'id' => 1,
                'required' => 1,
                'readonly' => 1,
                'disabled' => 1,
                'checked' => 1,
                'min' => 1,
            ),
            'select' => array(
                'class' => 1,
                'name' => 1,
                'id' => 1,
                'required' => 1,
            ),
            'option' => array(
                'value' => 1,
                'selected' => 1,
            ),
            'form' => array(
                'action' => 1,
                'method' => 1,
                'id' => 1,
                'class' => 1,
            ),
        );

        $allowed_tags = array_merge($allowed_tags, $allowed_tags_extra);

        return $allowed_tags;
    }

    public function get_protected_options()
    {
        // Verified against WordPress core's populate_options() (wp-admin/includes/schema.php)
        // and the relevant wp-includes sources (widgets, cron, theme, update, dashboard,
        // upgrade). Anything core actively deletes as obsolete (its own $unusedoptions
        // list — e.g. 'update_core', 'doing_cron', 'random_seed', 'secret',
        // 'blacklist_keys' / 'comment_whitelist' after the 5.5.0 migration) or that never
        // matched a real core option name has been dropped; the modern replacements and
        // options added in later core versions have been added instead.
        //
        // Two entries below were wrongly dropped in an earlier pass of this same
        // audit because they *also* appear in schema.php's $unusedoptions list —
        // but that list is a one-time cleanup for pre-existing installs, not proof
        // the option is dead: 'can_compress_scripts' is still read/written by
        // wp-admin/includes/ajax-actions.php and wp-includes/script-loader.php on
        // every request, and 'current_theme' is unconditionally rewritten by
        // switch_theme() (wp-includes/theme.php) on every theme change. Checked
        // against a full checkout of core, not just one file, before restoring.
        $core_options = array(
            'siteurl',
            'home',
            'blogname',
            'blogdescription',
            'admin_email',
            'users_can_register',
            'start_of_week',
            'use_balanceTags',
            'use_smilies',
            'require_name_email',
            'comments_notify',
            'posts_per_rss',
            'rss_use_excerpt',
            'mailserver_url',
            'mailserver_login',
            'mailserver_pass',
            'mailserver_port',
            'default_category',
            'default_comment_status',
            'default_ping_status',
            'default_pingback_flag',
            'posts_per_page',
            'date_format',
            'time_format',
            'links_updated_date_format',
            'comment_moderation',
            'moderation_notify',
            'permalink_structure',
            'hack_file',
            'blog_charset',
            'moderation_keys',
            'active_plugins',
            'category_base',
            'ping_sites',
            'comment_max_links',
            'gmt_offset',
            'default_email_category',
            'recently_edited',
            'template',
            'stylesheet',
            'comment_registration',
            'html_type',
            'use_trackback',
            'default_role',
            'db_version',
            'wp_user_roles',
            'uploads_use_yearmonth_folders',
            'upload_path',
            'blog_public',
            'default_link_category',
            'show_on_front',
            'cron',
            'sidebars_widgets',
            'widget_pages',
            'widget_calendar',
            'widget_archives',
            'widget_meta',
            'widget_categories',
            'widget_text',
            'widget_rss',
            'widget_search',
            'widget_tag_cloud',
            'widget_nav_menu',
            'widget_custom_html',
            'widget_block',
            'widget_links',
            'widget_media_image',
            'widget_media_audio',
            'widget_media_video',
            'widget_media_gallery',
            'tag_base',
            'page_on_front',
            'page_for_posts',
            'show_avatars',
            'avatar_rating',
            'upload_url_path',
            'thumbnail_size_w',
            'thumbnail_size_h',
            'thumbnail_crop',
            'medium_size_w',
            'medium_size_h',
            'medium_large_size_w',
            'medium_large_size_h',
            'dashboard_widget_options',
            'auth_salt',
            'avatar_default',
            'logged_in_salt',
            'recently_activated',
            'large_size_w',
            'large_size_h',
            'image_default_link_type',
            'image_default_size',
            'image_default_align',
            'close_comments_for_old_posts',
            'close_comments_days_old',
            'thread_comments',
            'thread_comments_depth',
            'page_comments',
            'comments_per_page',
            'default_comments_page',
            'comment_order',
            'use_ssl',
            'sticky_posts',
            'dismissed_update_core',
            'nonce_salt',
            'uninstall_plugins',
            'stats_options',
            'stats_cache',
            'rewrite_rules',
            'timezone_string',
            'db_upgraded',
            'default_post_format',
            'link_manager_enabled',
            'initial_db_version',
            'theme_switched',
            'disallowed_keys',
            'comment_previously_approved',
            'auto_plugin_theme_update_emails',
            'finished_splitting_shared_terms',
            'site_icon',
            'wp_page_for_privacy_policy',
            'show_comments_cookies_opt_in',
            'admin_email_lifespan',
            'auto_update_core_dev',
            'auto_update_core_minor',
            'auto_update_core_major',
            'wp_force_deactivated_plugins',
            'wp_attachment_pages_enabled',
            'wp_notes_notify',
            'can_compress_scripts',
            'current_theme',
            'recovery_mode_email_last_sent',
            // Set by wp-admin/includes/upgrade.php and actively read/written by
            // wp-includes/comment.php as part of the background comment_type
            // migration job — missed in the first pass because it's only
            // referenced in comment.php, not schema.php.
            'finished_updating_comment_type',
        );

        $default_options = array(
            '_site_transient_timeout_theme_roots',
            '_site_transient_theme_roots',
            '_site_transient_update_core',
            '_site_transient_update_plugins',
            '_site_transient_update_themes',
            '_transient_doing_cron',
            '_transient_plugins_delete_result_1',
            '_transient_plugin_slugs',
            '_transient_random_seed',
            '_transient_rewrite_rules',
            '_transient_update_core',
            '_transient_update_plugins',
            '_transient_update_themes',
            'widget_recent-posts',
            'widget_recent-comments',
        );

        return array_merge($core_options, $default_options);
    }

    public function get_protected_usermetas()
    {
        $core_usermetas = array(
            'nickname',
            'first_name',
            'last_name',
            'description',
            'rich_editing',
            'syntax_highlighting',
            'comment_shortcuts',
            'admin_color',
            'use_ssl',
            'show_admin_bar_front',
            'locale',
            'wp_capabilities',
            'wp_user_level',
            'show_welcome_panel',
            'session_tokens',
            'wp_user-settings',
            'wp_user-settings-time',
            'wp_persisted_preferences',
        );

        $default_usermetas = array(
            '_last_login',
            'last_update',
            'wc_last_active',
            '_woocommerce_tracks_anon_id',
        );

        return array_merge($core_usermetas, $default_usermetas);
    }

    public function get_protected_postmetas()
    {
        return array(
            '_edit_lock',
            '_edit_last',
            '_wp_page_template',
            '_thumbnail_id',
            '_wp_trash_meta_status',
            '_wp_trash_meta_time',
            '_wp_desired_post_slug',
            '_wp_old_slug',
            '_wp_old_date',
        );
    }

    public function get_protected_commentmetas()
    {
        return array(
            '_wp_trash_meta_status',
            '_wp_trash_meta_time',
        );
    }

    public function get_protected_termmetas()
    {
        return array();
    }

    public function exceptional_option_names()
    {
        return [
            'betterlinks_notices',
        ];
    }

    public function is_plugin_installed($class_name = '\WP_Recipe_Maker')
    {
        return class_exists($class_name);
    }

    /**
     * SQL fragment matching transient *value* rows only — both the regular
     * "_transient_" and network-wide "_site_transient_" scopes — excluding
     * their paired timeout rows. Literal pattern, safe to interpolate
     * directly (no user input involved).
     *
     * @param string $column Column reference, never user input.
     * @return string
     */
    public function transient_value_where($column = 'option_name')
    {
        return "(($column LIKE '\_transient\_%' AND $column NOT LIKE '\_transient\_timeout\_%')"
            . " OR ($column LIKE '\_site\_transient\_%' AND $column NOT LIKE '\_site\_transient\_timeout\_%'))";
    }

    /**
     * SQL fragment matching transient *timeout* rows only (both scopes).
     *
     * @param string $column Column reference, never user input.
     * @return string
     */
    public function transient_timeout_where($column = 'option_name')
    {
        return "($column LIKE '\_transient\_timeout\_%' OR $column LIKE '\_site\_transient\_timeout\_%')";
    }

    /**
     * Whether a transient option_name belongs to the network-wide "site" scope.
     *
     * @param string $option_name Full option_name, e.g. "_site_transient_update_plugins".
     * @return bool
     */
    public function is_site_transient($option_name)
    {
        return 0 === strpos((string) $option_name, '_site_transient_');
    }

    /**
     * Strip whichever transient prefix applies, returning the bare transient name.
     *
     * @param string $option_name Full option_name.
     * @return string
     */
    public function transient_bare_name($option_name)
    {
        $prefix = $this->is_site_transient($option_name) ? '_site_transient_' : '_transient_';
        return substr($option_name, strlen($prefix));
    }

    /**
     * Build the value-row option_name for a bare transient name.
     *
     * @param string $name    Bare transient name.
     * @param bool   $is_site Network-wide scope?
     * @return string
     */
    public function transient_value_name($name, $is_site)
    {
        return ($is_site ? '_site_transient_' : '_transient_') . $name;
    }

    /**
     * Build the timeout-row option_name for a bare transient name.
     *
     * @param string $name    Bare transient name.
     * @param bool   $is_site Network-wide scope?
     * @return string
     */
    public function transient_timeout_name($name, $is_site)
    {
        return ($is_site ? '_site_transient_timeout_' : '_transient_timeout_') . $name;
    }

    /**
     * Strip whichever transient *timeout* prefix applies, returning the bare name.
     *
     * @param string $timeout_option_name Full timeout option_name.
     * @return string
     */
    public function transient_bare_name_from_timeout($timeout_option_name)
    {
        $prefix = $this->is_site_transient($timeout_option_name) ? '_site_transient_timeout_' : '_transient_timeout_';
        return substr($timeout_option_name, strlen($prefix));
    }

    //

}
