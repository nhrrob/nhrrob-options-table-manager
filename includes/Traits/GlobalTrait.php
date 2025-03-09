<?php

namespace Nhrotm\OptionsTableManager\Traits;

trait GlobalTrait
{
    public function dd($var)
    {
        echo "<pre>";
        // phpcs:ignore:WordPress.PHP.DevelopmentFunctions.error_log_print_r
        print_r($var);
        wp_die('ok');
    }

    public function allowed_html(){
        $allowed_tags = wp_kses_allowed_html('post');

        $allowed_tags_extra = array(
            // 'li'   => array( 'class' => 1 ),
            // 'div'  => array( 'class' => 1 ),
            // 'span' => array( 'class' => 1 ),
            'a' => array(
                'href'            => 1,
                'class'           => 1,
                'id'              => 1,
                'target'          => 1,
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

        $allowed_tags = array_merge( $allowed_tags, $allowed_tags_extra );

        return $allowed_tags;
    }

    public function get_protected_options() {
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
            'rss_excerpt_length',
            'rss_use_excerpt',
            'mailserver_url',
            'mailserver_login',
            'mailserver_pass',
            'mailserver_port',
            'default_category',
            'default_comment_status',
            'default_ping_status',
            'default_pingback_flag',
            'default_post_edit_rows',
            'posts_per_page',
            'what_to_show',
            'date_format',
            'time_format',
            'links_updated_date_format',
            'links_recently_updated_prepend',
            'links_recently_updated_append',
            'links_recently_updated_time',
            'comment_moderation',
            'moderation_notify',
            'permalink_structure',
            'gzipcompression',
            'hack_file',
            'blog_charset',
            'moderation_keys',
            'active_plugins',
            'home',
            'category_base',
            'ping_sites',
            'advanced_edit',
            'comment_max_links',
            'gmt_offset',
            'default_email_category',
            'recently_edited',
            'use_linksupdate',
            'template',
            'stylesheet',
            'comment_whitelist',
            'blacklist_keys',
            'comment_registration',
            'open_proxy_check',
            'rss_language',
            'html_type',
            'use_trackback',
            'default_role',
            'db_version',
            'wp_user_roles',
            'uploads_use_yearmonth_folders',
            'upload_path',
            'secret',
            'blog_public',
            'default_link_category',
            'show_on_front',
            'default_link_category',
            'cron',
            'doing_cron',
            'sidebars_widgets',
            'widget_pages',
            'widget_calendar',
            'widget_archives',
            'widget_meta',
            'widget_categories',
            'widget_recent_entries',
            'widget_text',
            'widget_rss',
            'widget_recent_comments',
            'widget_wholinked',
            'widget_polls',
            'tag_base',
            'page_on_front',
            'page_for_posts',
            'page_uris',
            'page_attachment_uris',
            'show_avatars',
            'avatar_rating',
            'upload_url_path',
            'thumbnail_size_w',
            'thumbnail_size_h',
            'thumbnail_crop',
            'medium_size_w',
            'medium_size_h',
            'dashboard_widget_options',
            'current_theme',
            'auth_salt',
            'avatar_default',
            'enable_app',
            'enable_xmlrpc',
            'logged_in_salt',
            'recently_activated',
            'random_seed',
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
            'update_themes',
            'nonce_salt',
            'update_core',
            'uninstall_plugins',
            'wporg_popular_tags',
            'stats_options',
            'stats_cache',
            'rewrite_rules',
            'update_plugins',
            'category_children',
            'timezone_string',
            'can_compress_scripts',
            'db_upgraded',
            'widget_search',
            'default_post_format',
            'link_manager_enabled',
            'initial_db_version',
            'theme_switched'
        );
    
        $default_options = array(
            '_site_transient_timeout_theme_roots',
            '_site_transient_theme_roots',
            '_transient_doing_cron',
            '_transient_plugins_delete_result_1',
            '_transient_plugin_slugs',
            '_transient_random_seed',
            '_transient_rewrite_rules',
            '_transient_update_core',
            '_transient_update_plugins',
            '_transient_update_themes',
            'widget_recent-posts',
            'widget_recent-comments'
        );
    
        return array_merge($core_options, $default_options);
    }
    
    public function get_protected_usermetas() {
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

    // Helper function to recursively sanitize arrays and objects
    public function sanitize_recursive(&$data) {
        // Handle arrays using array_walk_recursive for deep sanitization
        if (is_array($data)) {
            array_walk_recursive($data, function (&$item, $key) {
                if ($key !== 'content') {
                    $item = sanitize_text_field($item); // Only sanitize if the key is not 'content'
                }
            });
        }
        // Handle objects by converting them to arrays and applying the function recursively
        elseif (is_object($data)) {
            foreach ($data as $key => &$value) {
                if ($key === 'content') {
                    continue; // Skip sanitization for 'content' fields
                }
                
                if (is_scalar($value)) {
                    $value = sanitize_text_field($value);
                } else {
                    $this->sanitize_recursive($value); // Recurse for arrays or nested objects
                }
            }
        }
    }

    public function castValues(&$array, $option_name = '') {
        // $exceptional_option_names = $this->exceptional_option_names();

        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $this->castValues($value); // Recursive call for nested arrays
            } elseif (is_numeric($value)) {
                $value = (int) $value; // Convert numeric strings to integers
            } elseif ($value === "true") {
                $value = true; // Convert "true" string to boolean true
            } elseif ($value === "false") {
                $value = false; // Convert "false" string to boolean false
            } elseif (empty($value) && $key === "recurrence") {
                $value = false; // Ensure recurrence defaults to false if empty
            }

            // if ( ! empty( $option_name ) && in_array( $option_name, $exceptional_option_names ) ) {
            //     if ( empty($value) && $key === "recurrence" ) {
            //         $value = false;
            //     }
            // }
        }
    }

    public function exceptional_option_names() {
        return [
            'betterlinks_notices',
        ];
    }

    //

}
