<?php
namespace Nhrotm\OptionsTableManager\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Traits\GlobalTrait;
use Nhrotm\OptionsTableManager\Managers\OptimizationManager;
use Nhrotm\OptionsTableManager\Managers\UsageTracker;
use Nhrotm\OptionsTableManager\Managers\ScannerManager;

/**
 * Read/action service for the Optimize section.
 *
 * Aggregates autoload health, the usage tracker, the orphan scanner, and
 * transient cleanup, reusing existing managers. Actions honor the protected
 * options list so core data is never touched.
 */
class OptimizeService
{
    use GlobalTrait;

    /**
     * @var ActivityService
     */
    private $activity;

    public function __construct(?ActivityService $activity = null)
    {
        $this->activity = $activity ? $activity : new ActivityService();
    }

    /**
     * Combined overview for the Optimize screen.
     *
     * @return array
     */
    public function overview()
    {
        $optimizer = new OptimizationManager();
        $tracker   = new UsageTracker();

        $heavy = array_map(function ($row) {
            return [
                'name'     => $row['option_name'],
                'size'     => $row['size_formatted'],
                'autoload' => in_array($row['autoload'], ['no', 'false', '0', '', 'off'], true) ? 'no' : 'yes',
            ];
        }, $optimizer->get_heavy_autoload_options(10));

        $usage = $tracker->get_unused_autoload_options();
        $unused = array_map(function ($row) {
            return ['name' => $row['option_name'], 'size' => $row['size_formatted']];
        }, array_slice($usage['options'], 0, 20));

        $orphans = array_map(function ($row) {
            return [
                'prefix' => $row['prefix'],
                'count'  => $row['count'],
                'source' => isset($row['possible_source']) ? $row['possible_source'] : '',
                'risk'   => isset($row['risk']) ? $row['risk'] : '',
            ];
        }, (new ScannerManager())->scan_orphans());

        return [
            'autoload_total'     => $optimizer->get_total_autoload_size(),
            'heavy'              => $heavy,
            'usage_tracking'     => $usage['tracking'],
            'usage_since'        => $usage['since'],
            'usage_seen'         => $usage['seen_count'],
            'unused'             => $unused,
            'orphans'            => $orphans,
            'expired_transients' => $this->count_expired_transients(),
        ];
    }

    /**
     * Turn off autoload for a single option (protected options excluded).
     *
     * @param string $option_name Option name.
     * @return bool
     */
    public function disable_autoload($option_name)
    {
        global $wpdb;
        if (in_array($option_name, $this->get_protected_options(), true)) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update($wpdb->options, ['autoload' => 'no'], ['option_name' => $option_name], ['%s'], ['%s']);
        wp_cache_delete('alloptions', 'options');

        if (false === $updated) {
            return false;
        }

        $this->activity->record('disable_autoload', $option_name);
        return true;
    }

    /**
     * Delete an orphan group by prefix.
     *
     * @param string $prefix Prefix.
     * @return int Rows deleted.
     */
    public function delete_orphans($prefix)
    {
        $deleted = (int) (new ScannerManager())->delete_by_prefix($prefix);

        if ($deleted > 0) {
            $this->activity->record('delete_orphans', $prefix);
        }

        return $deleted;
    }

    /**
     * Reset usage tracking data.
     *
     * @return void
     */
    public function reset_usage()
    {
        (new UsageTracker())->reset();
    }

    /**
     * Delete transients by scope.
     *
     * @param string $scope expired | all.
     * @return int Rows deleted.
     */
    public function clean_transients($scope = 'expired')
    {
        global $wpdb;
        if ('all' === $scope) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $removed = (int) $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%'");
            if ($removed > 0) {
                // Rows come in name/timeout pairs — report transients, not rows.
                $this->activity->record('clean_transients_all', (int) ceil($removed / 2));
            }
            return $removed;
        }

        // Expired only: remove timeouts in the past plus their value rows.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $expired = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%' AND option_value < %d",
            time()
        ));
        $deleted = 0;
        $cleared = 0;
        foreach ($expired as $timeout_name) {
            $transient = str_replace('_transient_timeout_', '', $timeout_name);
            $deleted += (int) $wpdb->delete($wpdb->options, ['option_name' => '_transient_timeout_' . $transient]);
            $deleted += (int) $wpdb->delete($wpdb->options, ['option_name' => '_transient_' . $transient]);
            $cleared++;
        }
        // phpcs:enable

        if ($cleared > 0) {
            $this->activity->record('clean_transients', $cleared);
        }

        return $deleted;
    }

    /**
     * @return int
     */
    private function count_expired_transients()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%' AND option_value < %d",
            time()
        ));
    }
}
