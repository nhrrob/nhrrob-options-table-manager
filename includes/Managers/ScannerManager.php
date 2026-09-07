<?php
namespace Nhrotm\OptionsTableManager\Managers;

/**
 * Class ScannerManager
 * 
 * Handles identification of orphaned options from uninstalled plugins and themes.
 */
class ScannerManager extends BaseTableManager
{
    /**
     * Map of common plugin prefixes to their friendly names
     * 
     * @var array
     */
    private $prefix_map = [
        'akismet_' => 'Akismet',
        'autoptimize_' => 'Autoptimize',
        'contact_form_7' => 'Contact Form 7',
        'eael_' => 'Essential Addons for Elementor',
        'elementor_' => 'Elementor',
        'itsec_' => 'iThemes Security',
        'jetpack_' => 'Jetpack',
        'nhrada_' => 'AI Developer Assistant',
        // Sibling nhr-branded plugins — their abbreviated prefixes (nhrotm,
        // nhrsmm, nhrcc…) aren't substrings of the real plugin directory
        // slug (nhrrob-options-table-manager, nhrrob-smart-media-manager…),
        // so the directory-guessing fallback below can never bridge them by
        // itself; each one needs an explicit entry here, same as nhrada_.
        'nhrotm_' => 'Options Table Manager',
        'nhrsmm_' => 'Smart Media Manager',
        'nhrcc_' => 'Core Contributions',
        'nhrfm_' => 'File Manager',
        // No 'nhrrob-smart-sync'-style folder exists on disk to confirm the exact
        // display name against — inferred from the option name itself
        // ('nhrst_smartsync_table_*'); correct this if the real plugin name differs.
        'nhrst_' => 'SmartSync',
        // Pre-rename leftovers: AI Developer Assistant's menu slug is still
        // literally 'wpad-settings' from before it became 'nhrada'; current
        // code writes 'nhrada_claude_api_key', so any 'wpad_*' row is an
        // orphan from that old prefix, not something current code reads.
        'wpad_' => 'AI Developer Assistant (old prefix)',
        'rank_math_' => 'Rank Math',
        'smush_' => 'Smush',
        'updraftplus_' => 'UpdraftPlus',
        'w3tc_' => 'W3 Total Cache',
        'woocommerce_' => 'WooCommerce',
        'wordfence_' => 'Wordfence',
        'wpforms_' => 'WPForms',
        'wp_rocket_' => 'WP Rocket',
        'wprm_' => 'WP Recipe Maker',
        'wpseo_' => 'Yoast SEO',
        'wpmudev_' => 'WPMU DEV',
    ];

    /**
     * Prefixes whose option prefix shares no substring with their plugin's
     * directory slug (an abbreviated brand prefix, e.g. 'eael_' for
     * essential-addons-for-elementor-lite), so the stripos-based directory
     * match in scan_orphans() can't detect the plugin is actually installed.
     * Maps prefix to its real directory slug so those get excluded from the
     * orphan list rather than false-flagged.
     *
     * @var array
     */
    private $abbreviated_prefix_dirs = [
        'eael_' => 'essential-addons-for-elementor-lite',
        'wprm_' => 'wp-recipe-maker',
        'nhrada_' => 'nhrrob-ai-developer-assistant',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->table_name = !empty($this->wpdb->options) ? $this->wpdb->options : $this->wpdb->prefix . 'options';
    }

    /**
     * Get searchable columns (required by BaseTableManager)
     */
    protected function get_searchable_columns()
    {
        return [];
    }

    public function get_data() { return []; }
    public function edit_record() { return false; }
    public function delete_record() { return false; }

    /**
     * Scan for orphaned options
     * 
     * @return array List of orphaned prefixes and their associated options
     */
    public function scan_orphans()
    {
        global $wpdb;

        // 1. Get all unique prefixes from wp_options
        // We'll look for anything followed by an underscore as a prefix candidate
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific query
        $results = $wpdb->get_results("SELECT option_name FROM {$wpdb->options}", ARRAY_A);
        
        $prefixes = [];
        foreach ($results as $row) {
            $name = sanitize_text_field($row['option_name']);
            
            // Remove _transient/_site_transient and _timeout to get the real prefix
            $clean_name = preg_replace('/^_(?:site_)?transient(?:_timeout)?_/', '', $name);
            
            $parts = explode('_', $clean_name);
            if (count($parts) > 1) {
                $prefix = $parts[0] . '_';
                if (!isset($prefixes[$prefix])) {
                    $prefixes[$prefix] = 0;
                }
                $prefixes[$prefix]++;
            }
        }

        // 2. Get list of active and inactive plugins
        $all_plugins = get_plugins();
        $plugin_dirs = [];
        foreach (array_keys($all_plugins) as $plugin_file) {
            $parts = explode('/', $plugin_file);
            if (count($parts) > 1) {
                $plugin_dirs[] = $parts[0];
            } else {
                // For single file plugins like hello.php
                $plugin_dirs[] = str_replace('.php', '', $plugin_file);
            }
        }

        // 3. Identification logic
        // Known WP core option prefixes this heuristic would otherwise flag —
        // e.g. 'users_' (plural) guards `users_can_register`, distinct from
        // the singular 'user_' below. Not an exhaustive core-options
        // allowlist, just the false positives found in practice so far.
        $orphans = [];
        // 'connectors_' covers WP core's Connectors registry (wp-includes/connectors.php,
        // added for the built-in AI provider settings) — it stores each provider's API
        // key as 'connectors_{type}_{id}_api_key', e.g. 'connectors_ai_anthropic_api_key'.
        $protected_prefixes = ['wp_', 'user_', 'users_', 'widget_', 'theme_', 'rss_', 'sticky_', 'connectors_'];

        foreach ($prefixes as $prefix => $count) {
            $prefix_clean = rtrim($prefix, '_');

            // Skip protected
            if (in_array($prefix, $protected_prefixes)) {
                continue;
            }

            // Check if prefix matches a plugin directory. Below this length
            // the substring match is too generic to trust (e.g. 'nhr'
            // matches every 'nhrrob-*' plugin directory) — see the matching
            // guard in guess_owner().
            $found = false;
            if (strlen($prefix_clean) >= 4) {
                foreach ($plugin_dirs as $dir) {
                    if (stripos($dir, $prefix_clean) !== false || stripos($prefix_clean, $dir) !== false) {
                        $found = true;
                        break;
                    }
                }
            }

            // Known abbreviated prefixes (no textual overlap with their
            // plugin's directory slug) — check the actual installed slug.
            if (!$found && isset($this->abbreviated_prefix_dirs[$prefix])) {
                $found = in_array($this->abbreviated_prefix_dirs[$prefix], $plugin_dirs, true);
            }

            if (!$found) {
                $orphans[] = [
                    'prefix' => $prefix,
                    'count'  => $count,
                    'possible_source' => isset($this->prefix_map[$prefix]) ? $this->prefix_map[$prefix] : 'Unknown Plugin/Theme',
                    'risk' => isset($this->prefix_map[$prefix]) ? 'Medium' : 'High'
                ];
            }
        }

        // Sort by count descending
        usort($orphans, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return $orphans;
    }

    /**
     * Best-guess the plugin/theme (or WordPress core) that owns an option,
     * from its name prefix — the inverse of scan_orphans()'s "does this
     * prefix match nothing installed" check. Used to label rows in the
     * Autoload health table so a "Disable autoload" decision isn't a
     * total guess about what will break.
     *
     * @param string $option_name Option name.
     * @return string Friendly owner label ('WordPress Core', a plugin name, or 'Unknown').
     */
    public function guess_owner($option_name)
    {
        // 'connectors_' covers WP core's Connectors registry (wp-includes/connectors.php,
        // added for the built-in AI provider settings) — it stores each provider's API
        // key as 'connectors_{type}_{id}_api_key', e.g. 'connectors_ai_anthropic_api_key'.
        $protected_prefixes = ['wp_', 'user_', 'users_', 'widget_', 'theme_', 'rss_', 'sticky_', 'connectors_'];

        $clean_name = preg_replace('/^_(?:site_)?transient(?:_timeout)?_/', '', $option_name);
        $is_transient = $option_name !== $clean_name;

        // Transients are deliberately excluded from get_protected_options()
        // (they're meant to stay deletable), so a real core transient like
        // 'health-check-site-status-result' would otherwise never be
        // attributed to core at all — it isn't in that list, and even if it
        // were, that list's transient entries keep their '_transient_'
        // prefix while $clean_name here has already had it stripped, so the
        // two forms could never match anyway. This is a short, separate
        // allowlist purely for the Owner label, not for protection.
        $core_transient_names = [
            'health-check-site-status-result',
            'doing_cron',
            'random_seed',
            'rewrite_rules',
            'update_core',
            'update_plugins',
            'update_themes',
            'theme_roots',
            'timeout_theme_roots',
            'plugin_slugs',
        ];
        if ($is_transient && in_array($clean_name, $core_transient_names, true)) {
            return __('WordPress Core', 'nhrrob-options-table-manager');
        }

        // Checked unconditionally, and first: get_protected_options() is a
        // literal list of real core option *names*, most of which contain
        // an underscore (rewrite_rules, active_plugins, default_comment_status…).
        // Gating this behind "no underscore in the name" — as a prior version
        // did — meant it only ever matched single-word options like
        // 'siteurl', so nearly every real core option fell through to
        // 'Unknown' instead.
        if (in_array($clean_name, $this->get_protected_options(), true)) {
            return __('WordPress Core', 'nhrrob-options-table-manager');
        }

        $parts = explode('_', $clean_name);
        $prefix = count($parts) > 1 ? $parts[0] . '_' : '';

        if ('' !== $prefix && in_array($prefix, $protected_prefixes, true)) {
            return __('WordPress Core', 'nhrrob-options-table-manager');
        }
        if ('' !== $prefix && isset($this->prefix_map[$prefix])) {
            return $this->prefix_map[$prefix];
        }

        // One pass over installed plugins, cached for the life of the request —
        // this method runs once per autoloaded option, get_plugins() itself
        // isn't cheap enough to call that often. Dir slugs normalized to
        // underscores so they line up with underscore-delimited option prefixes.
        static $plugin_names = null;
        if (null === $plugin_names) {
            $plugin_names = [];
            foreach (get_plugins() as $plugin_file => $plugin_data) {
                $plugin_parts = explode('/', $plugin_file);
                $dir = count($plugin_parts) > 1 ? $plugin_parts[0] : str_replace('.php', '', $plugin_file);
                $plugin_names[str_replace('-', '_', $dir)] = $plugin_data['Name'];
            }
        }

        // Try the longest underscore-delimited prefix first ("nhrrob_secure")
        // before the bare first segment ("nhrrob") — sibling plugins sharing a
        // brand prefix (nhrrob-secure vs nhrrob-options-table-manager) would
        // otherwise collide on the coarser one-segment match below.
        $max_segments = min(4, count($parts) - 1);
        for ($segments = $max_segments; $segments >= 2; $segments--) {
            $candidate = implode('_', array_slice($parts, 0, $segments));
            foreach ($plugin_names as $dir => $name) {
                if ($dir === $candidate) {
                    return $name;
                }
            }
        }

        // An option name with no underscore at all (e.g. 'asdas') leaves
        // $prefix empty, so $prefix_clean is '' here too. stripos($dir, '')
        // returns 0 (not false) for every $dir — every plugin "matches" — so
        // without this guard the loop below silently attributes every such
        // option to whichever plugin happens to sort first from get_plugins()
        // (alphabetically 'aaa-...'), regardless of the option's real owner.
        if ('' === $prefix) {
            return __('Unknown', 'nhrrob-options-table-manager');
        }

        // Legacy malformed naming: some options were written as 'nhr_' plus a
        // separate abbreviation segment (e.g. 'nhr_smm_settings') instead of
        // the fused prefix every current plugin uses ('nhrsmm_settings') — see
        // the "no underscore separating nhr from the abbreviation" rule. Bare
        // 'nhr' is too short/generic for the stripos fallback below (it
        // substring-matches any 'nhrrob-*' directory, not necessarily the
        // right one), so this specific two-segment form is resolved against
        // the same brand map explicitly instead of falling through.
        $prefix_clean = rtrim($prefix, '_');
        if ('nhr' === $prefix_clean && isset($parts[1])) {
            $fused_prefix = 'nhr' . $parts[1] . '_';
            if (isset($this->prefix_map[$fused_prefix])) {
                return $this->prefix_map[$fused_prefix];
            }
        }

        // Below this length the substring match is too generic to trust
        // (e.g. 'nhr' matches every 'nhrrob-*' plugin directory) — safer to
        // fall through to Unknown than to guess.
        if (strlen($prefix_clean) < 4) {
            return __('Unknown', 'nhrrob-options-table-manager');
        }

        foreach ($plugin_names as $dir => $name) {
            if (stripos($dir, $prefix_clean) !== false || stripos($prefix_clean, $dir) !== false) {
                return $name;
            }
        }

        return __('Unknown', 'nhrrob-options-table-manager');
    }

    /**
     * Bulk delete options by prefix
     * 
     * @param string $prefix
     * @return int Number of rows deleted
     */
    public function delete_by_prefix($prefix)
    {
        $this->validate_permissions();
        
        if (empty($prefix) || strlen($prefix) < 3) {
            throw new \Exception('Invalid prefix for deletion');
        }

        // Safety check: Don't delete protected prefixes
        $protected = ['wp_', 'user_', 'users_', 'widget_', 'theme_'];
        if (in_array($prefix, $protected)) {
            throw new \Exception('Cannot delete protected core prefixes');
        }

        $pattern = $this->wpdb->esc_like($prefix) . '%';

        // Also handle transients — both the regular and network-wide scopes,
        // matching how scan_orphans() strips the prefix when counting them.
        $transient_pattern = '_transient_' . $pattern;
        $timeout_pattern = '_transient_timeout_' . $pattern;
        $site_transient_pattern = '_site_transient_' . $pattern;
        $site_timeout_pattern = '_site_transient_timeout_' . $pattern;

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific deletion
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
                $pattern,
                $transient_pattern,
                $timeout_pattern,
                $site_transient_pattern,
                $site_timeout_pattern
            )
        );
    }
}
