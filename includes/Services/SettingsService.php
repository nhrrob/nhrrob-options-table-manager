<?php
namespace Nhrotm\OptionsTableManager\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralized settings store.
 *
 * Collapses the scattered nhrotm_* options into a single `nhrotm_settings`
 * array. Legacy keys are read as fallbacks during the deprecation window and
 * folded in by migrate(), so 1.5.x installs upgrade cleanly.
 */
class SettingsService
{
    const OPTION = 'nhrotm_settings';

    /**
     * Default settings and the legacy option each one migrates from.
     *
     * @var array
     */
    private $defaults = [
        'allow_html_in_values'   => false,
        'auto_cleanup_enabled'   => false,
        'usage_tracking_enabled' => false,
        'backup_frequency'       => 'off',
        'history_retention_days' => 30,
    ];

    /**
     * Map of setting key => legacy standalone option name.
     *
     * @var array
     */
    private $legacy_map = [
        'allow_html_in_values'   => 'nhrotm_allow_html_in_values',
        'auto_cleanup_enabled'   => 'nhrotm_auto_cleanup_enabled',
        'usage_tracking_enabled' => 'nhrotm_usage_tracking_enabled',
        'backup_frequency'       => 'nhrotm_backup_frequency',
    ];

    /**
     * @var array|null Loaded settings cache.
     */
    private $cache = null;

    /**
     * Get all settings, merged over defaults.
     *
     * @return array
     */
    public function all()
    {
        if (null === $this->cache) {
            $stored = get_option(self::OPTION, []);
            $this->cache = wp_parse_args(is_array($stored) ? $stored : [], $this->defaults);
        }
        return $this->cache;
    }

    /**
     * Get a single setting.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Fallback if not set.
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $all = $this->all();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }
        return null === $default ? (isset($this->defaults[$key]) ? $this->defaults[$key] : null) : $default;
    }

    /**
     * Update one or more settings and persist.
     *
     * @param array $values Partial settings to merge.
     * @return array The full, updated settings.
     */
    public function update(array $values)
    {
        $merged = wp_parse_args($values, $this->all());
        // Keep only known keys to avoid unbounded growth.
        $merged = array_intersect_key($merged, $this->defaults);
        update_option(self::OPTION, $merged);
        $this->cache = $merged;
        return $merged;
    }

    /**
     * One-time migration: fold legacy standalone options into nhrotm_settings.
     * Idempotent — safe to call on every activation.
     *
     * @return void
     */
    public function migrate()
    {
        $stored = get_option(self::OPTION, null);
        $settings = is_array($stored) ? $stored : [];

        foreach ($this->legacy_map as $key => $legacy_option) {
            if (!array_key_exists($key, $settings)) {
                $legacy_value = get_option($legacy_option, null);
                if (null !== $legacy_value) {
                    $settings[$key] = $legacy_value;
                }
            }
        }

        update_option(self::OPTION, wp_parse_args($settings, $this->defaults));
        $this->cache = null;
    }
}
