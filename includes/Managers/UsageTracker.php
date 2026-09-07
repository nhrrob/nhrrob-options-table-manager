<?php
namespace Nhrotm\OptionsTableManager\Managers;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Traits\GlobalTrait;

/**
 * Class UsageTracker
 *
 * Records which autoloaded options are actually read on real front-end page
 * loads, so options that are never used can be flagged for autoload removal.
 *
 * Tracking is opt-in (adds minor per-request overhead) and runs on the
 * front-end only. Used option names are collected during the request via the
 * catch-all "all" hook and persisted on shutdown.
 */
class UsageTracker
{
    use GlobalTrait;

    const USED_OPTION    = 'nhrotm_used_autoload_options';
    const SINCE_OPTION   = 'nhrotm_usage_tracking_since';
    const ENABLED_OPTION = 'nhrotm_usage_tracking_enabled';
    const LOADS_OPTION   = 'nhrotm_usage_load_count';

    // Stop counting once the sample proves the point. Past this the extra
    // write on every front-end request buys nothing, and an undercounted
    // "never used across N loads" is still a true statement.
    const LOADS_CAP = 10000;

    /**
     * Option names read during the current request.
     *
     * @var array
     */
    private $used = [];

    /**
     * Attach front-end tracking hooks when enabled.
     *
     * @return void
     */
    public function maybe_track()
    {
        if (get_option(self::ENABLED_OPTION, 'false') !== 'true') {
            return;
        }

        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        add_filter('all', [$this, 'record_hook']);
        add_action('shutdown', [$this, 'persist']);
    }

    /**
     * Capture the option name behind every "option_{$name}" filter.
     *
     * @param mixed $value Passed-through hook value (return is ignored for "all").
     * @return mixed
     */
    public function record_hook($value = null)
    {
        $tag = current_filter();
        if (strncmp($tag, 'option_', 7) === 0) {
            $this->used[substr($tag, 7)] = true;
        }
        return $value;
    }

    /**
     * Merge this request's used options into the stored set (autoloaded only).
     *
     * @return void
     */
    public function persist()
    {
        // Count every tracked front-end load, even one that read nothing new.
        // The sample size is what makes "never used" trustworthy, so it has to
        // be recorded independently of whether new names were seen.
        $loads = $this->get_load_count();
        if ($loads < self::LOADS_CAP) {
            update_option(self::LOADS_OPTION, $loads + 1, false);
        }

        if (empty($this->used)) {
            return;
        }

        $autoloaded = wp_load_alloptions();
        // Per-option hit count, not just a seen/unseen flag — lets the UI show
        // "used 12x" instead of a flat "Used" label. Values written before this
        // change are the boolean `true`; `(int) true === 1`, so old data upgrades
        // in place with no migration.
        $stored = (array) get_option(self::USED_OPTION, []);
        $before = $stored;

        foreach (array_keys($this->used) as $name) {
            if (isset($autoloaded[$name])) {
                $stored[$name] = (isset($stored[$name]) ? (int) $stored[$name] : 0) + 1;
            }
        }

        if ($stored !== $before) {
            update_option(self::USED_OPTION, $stored, false);
        }

        if (!get_option(self::SINCE_OPTION)) {
            update_option(self::SINCE_OPTION, current_time('mysql'), false);
        }
    }

    /**
     * Get autoloaded options that have never been recorded as used.
     *
     * @return array
     */
    public function get_unused_autoload_options()
    {
        $autoloaded = wp_load_alloptions();
        $used       = (array) get_option(self::USED_OPTION, []);
        $protected  = $this->get_protected_options();

        $unused = [];
        foreach ($autoloaded as $name => $value) {
            if (isset($used[$name]) || in_array($name, $protected, true)) {
                continue;
            }

            $unused[] = [
                'option_name'    => $name,
                'size_formatted' => size_format(strlen((string) $value)),
                'size_bytes'     => strlen((string) $value),
            ];
        }

        usort($unused, function ($a, $b) {
            return $b['size_bytes'] <=> $a['size_bytes'];
        });

        return [
            'tracking'    => get_option(self::ENABLED_OPTION, 'false') === 'true',
            'since'       => get_option(self::SINCE_OPTION, ''),
            'seen_count'  => count($used),
            'load_count'  => $this->get_load_count(),
            'used_counts' => array_map('intval', $used),
            'options'     => $unused,
        ];
    }

    /**
     * Number of front-end loads observed since tracking began.
     *
     * @return int
     */
    public function get_load_count()
    {
        return (int) get_option(self::LOADS_OPTION, 0);
    }

    /**
     * Reset all collected usage data.
     *
     * @return void
     */
    public function reset()
    {
        delete_option(self::USED_OPTION);
        delete_option(self::SINCE_OPTION);
        delete_option(self::LOADS_OPTION);
    }
}
