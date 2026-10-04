<?php
/**
 * Shared helpers mixed into the plugin's managers, services and admin pages.
 *
 * @package Nhrotm\OptionsTableManager
 */

namespace Nhrotm\OptionsTableManager\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait GlobalTrait
 *
 * Collects the helpers shared across managers and services: the protected-key
 * lists that guard core options and meta from edits/deletes, and the
 * transient_* helpers for building transient SQL fragments and option_name pairs.
 */
trait GlobalTrait {

	/**
	 * Core and well-known plugin options that must not be edited or deleted.
	 *
	 * @return array
	 */
	public function get_protected_options() {
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
			// Category hierarchy cache (wp-includes/category.php, _get_term_hierarchy())
			// — real core option, missed in the first pass.
			'category_children',
			// Automatic-core-update notification flag (wp-admin/includes/update.php,
			// automatic core update mail flow) — its 'auto_' prefix also isn't in any
			// protected-prefix list, so without this entry it can be misattributed to
			// any installed plugin whose directory name happens to contain 'auto'.
			'auto_core_update_notified',
		);

		// The roles option is named after the table prefix (wp_2_user_roles on a
		// network site, xyz_user_roles with a custom prefix).
		global $wpdb;
		if ( isset( $wpdb->prefix ) ) {
			$core_options[] = $wpdb->prefix . 'user_roles';
		}

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

		// The plugin's own recorded data (history log, snapshots) lives in
		// options too: editing or deleting one by hand would corrupt it.
		return array_values( array_unique( array_merge( $core_options, $default_options, \Nhrotm\OptionsTableManager\Managers\BackupManager::data_options() ) ) );
	}

	/**
	 * Core and well-known plugin user meta keys that must not be edited or deleted.
	 *
	 * @return array
	 */
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
			'show_welcome_panel',
			'session_tokens',
		);

		// Per-site keys carry the table prefix (wp_2_capabilities on multisite,
		// xyz_capabilities with a custom prefix). List this site's own here;
		// is_protected_usermeta() also matches every other site's.
		global $wpdb;
		foreach ( $this->prefixed_usermeta_suffixes() as $suffix ) {
			$core_usermetas[] = $wpdb->get_blog_prefix() . $suffix;
			$core_usermetas[] = $wpdb->base_prefix . $suffix;
		}

		$default_usermetas = array(
			'_last_login',
			'last_update',
			'wc_last_active',
			'_woocommerce_tracks_anon_id',
		);

		return array_values( array_unique( array_merge( $core_usermetas, $default_usermetas ) ) );
	}

	/**
	 * User meta keys WordPress stores with the site's table prefix.
	 * (A method, not a trait constant: those need PHP 8.2.)
	 *
	 * @return string[]
	 */
	public function prefixed_usermeta_suffixes() {
		return [ 'capabilities', 'user_level', 'user-settings', 'user-settings-time', 'persisted_preferences', 'dashboard_quick_press_last_post_id' ];
	}

	/**
	 * Whether a user meta key must not be edited or deleted.
	 *
	 * Also matches the role/capability keys of every site in a network
	 * ({base_prefix}{blog_id}_capabilities …): user meta is one table shared
	 * by all sites, so without this a site admin could grant themselves a
	 * role on another site.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public function is_protected_usermeta( $key ) {
		global $wpdb;
		if ( in_array( $key, $this->get_protected_usermetas(), true ) ) {
			return true;
		}
		$suffixes = implode(
			'|',
			array_map(
				function ( $suffix ) {
					return preg_quote( $suffix, '/' );
				},
				$this->prefixed_usermeta_suffixes()
			)
		);
		return (bool) preg_match( '/^' . preg_quote( $wpdb->base_prefix, '/' ) . '(\d+_)?(' . $suffixes . ')$/', (string) $key );
	}

	/**
	 * Core post meta keys that must not be edited or deleted.
	 *
	 * @return array
	 */
	public function get_protected_postmetas() {
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

	/**
	 * Core comment meta keys that must not be edited or deleted.
	 *
	 * @return array
	 */
	public function get_protected_commentmetas() {
		return array(
			'_wp_trash_meta_status',
			'_wp_trash_meta_time',
		);
	}

	/**
	 * Term meta keys that must not be edited or deleted (none at present).
	 *
	 * @return array
	 */
	public function get_protected_termmetas() {
		return array();
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
	public function transient_value_where( $column = 'option_name' ) {
		return "(($column LIKE '\_transient\_%' AND $column NOT LIKE '\_transient\_timeout\_%')"
			. " OR ($column LIKE '\_site\_transient\_%' AND $column NOT LIKE '\_site\_transient\_timeout\_%'))";
	}

	/**
	 * SQL fragment matching transient *timeout* rows only (both scopes).
	 *
	 * @param string $column Column reference, never user input.
	 * @return string
	 */
	public function transient_timeout_where( $column = 'option_name' ) {
		return "($column LIKE '\_transient\_timeout\_%' OR $column LIKE '\_site\_transient\_timeout\_%')";
	}

	/**
	 * Whether an imported option value would plant a PHP object in wp_options.
	 *
	 * WordPress unserializes every option on read (get_option() →
	 * maybe_unserialize()), so a crafted import file carrying a serialized
	 * object with magic methods becomes object injection on every page load.
	 * stdClass is inert and common in real option data, so it's allowed;
	 * any other class makes the value unsafe to import.
	 *
	 * @param mixed $value Raw imported option value.
	 * @return bool
	 */
	public function is_unsafe_import_value( $value ) {
		if ( ! is_string( $value ) || ! is_serialized( $value ) ) {
			return false;
		}
		$data = @unserialize( $value, [ 'allowed_classes' => [ 'stdClass' ] ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged -- restricted to stdClass; only inspects the value, never stores the result
		return $this->contains_foreign_object( $data );
	}

	/**
	 * Recursively look for any object PHP couldn't restore (a class outside the allow-list).
	 *
	 * @param mixed $data Unserialized data.
	 * @return bool
	 */
	private function contains_foreign_object( $data ) {
		if ( $data instanceof \__PHP_Incomplete_Class ) {
			return true;
		}
		if ( is_array( $data ) || is_object( $data ) ) {
			foreach ( $data as $item ) {
				if ( $this->contains_foreign_object( $item ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether a transient option_name belongs to the network-wide "site" scope.
	 *
	 * @param string $option_name Full option_name, e.g. "_site_transient_theme_roots".
	 * @return bool
	 */
	public function is_site_transient( $option_name ) {
		return 0 === strpos( (string) $option_name, '_site_transient_' );
	}

	/**
	 * Strip whichever transient prefix applies, returning the bare transient name.
	 *
	 * @param string $option_name Full option_name.
	 * @return string
	 */
	public function transient_bare_name( $option_name ) {
		$prefix = $this->is_site_transient( $option_name ) ? '_site_transient_' : '_transient_';
		return substr( $option_name, strlen( $prefix ) );
	}

	/**
	 * Build the value-row option_name for a bare transient name.
	 *
	 * @param string $name    Bare transient name.
	 * @param bool   $is_site Whether the network-wide scope applies.
	 * @return string
	 */
	public function transient_value_name( $name, $is_site ) {
		return ( $is_site ? '_site_transient_' : '_transient_' ) . $name;
	}

	/**
	 * Build the timeout-row option_name for a bare transient name.
	 *
	 * @param string $name    Bare transient name.
	 * @param bool   $is_site Whether the network-wide scope applies.
	 * @return string
	 */
	public function transient_timeout_name( $name, $is_site ) {
		return ( $is_site ? '_site_transient_timeout_' : '_transient_timeout_' ) . $name;
	}

	/**
	 * Strip whichever transient *timeout* prefix applies, returning the bare name.
	 *
	 * @param string $timeout_option_name Full timeout option_name.
	 * @return string
	 */
	public function transient_bare_name_from_timeout( $timeout_option_name ) {
		$prefix = $this->is_site_transient( $timeout_option_name ) ? '_site_transient_timeout_' : '_transient_timeout_';
		return substr( $timeout_option_name, strlen( $prefix ) );
	}
}
