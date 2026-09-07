<?php
namespace Nhrotm\OptionsTableManager\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Traits\GlobalTrait;
use Nhrotm\OptionsTableManager\Managers\OptimizationManager;
use Nhrotm\OptionsTableManager\Managers\UsageTracker;
use Nhrotm\OptionsTableManager\Managers\ScannerManager;
use Nhrotm\OptionsTableManager\Managers\BackupManager;

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
        $scanner   = new ScannerManager();

        $autoload_rows = $optimizer->get_heavy_autoload_options();
        $usage         = $tracker->get_unused_autoload_options();
        // O(1) "is this option in the unused set" lookup — get_unused_autoload_options()
        // already excludes protected options, so a protected-but-never-observed
        // option reads as "used" here. Deliberate: protected options are never
        // offered a "Disable autoload" action anyway, so their usage badge
        // doesn't drive a decision — defaulting to the non-alarming label avoids
        // implying a cleanup candidate that can't actually be cleaned up.
        $unused_names = array_flip(array_column($usage['options'], 'option_name'));
        $orphan_rows = $scanner->scan_orphans();
        $expired_transients = $this->count_expired_transients();

        $baseline   = $this->score_baseline(count($orphan_rows), $expired_transients);
        $health     = new HealthService($this->activity);
        $base_score = $health->score($baseline);

        // The real, displayable score — unlike $baseline above, which fixes
        // backup_age_days to null so it cancels out of every $gain() diff
        // regardless of its true value (see score_baseline() docblock). That
        // shortcut would misrepresent an absolute score (always assuming the
        // worst backup-age penalty), so it's recomputed here with the real
        // age instead of re-deriving everything via HealthService::metrics()
        // (which would redundantly repeat the orphan scan and usage lookup
        // already done above).
        $display_score = $health->score(array_merge($baseline, [
            'backup_age_days' => $this->real_backup_age_days(),
        ]));

        // Marginal score gain from taking one action, holding everything else
        // fixed — not a static per-item constant (see HealthService::score()'s
        // caps, e.g. orphan groups plateau at -20 past ~10 groups).
        $gain = function (array $overrides) use ($health, $baseline, $base_score) {
            return max(0, $health->score(array_merge($baseline, $overrides)) - $base_score);
        };

        $protected_options = $this->get_protected_options();

        $autoload = array_map(function ($row) use ($usage, $unused_names, $scanner, $protected_options) {
            if (!$usage['tracking']) {
                $used = 'untracked';
            } else {
                $used = isset($unused_names[$row['option_name']]) ? 'unused' : 'used';
            }
            return [
                'name'       => $row['option_name'],
                'size'       => $row['size_formatted'],
                'size_bytes' => $row['size_bytes'],
                'owner'      => $scanner->guess_owner($row['option_name']),
                'used'       => $used,
                'used_count' => isset($usage['used_counts'][$row['option_name']]) ? $usage['used_counts'][$row['option_name']] : 0,
                'protected'  => in_array($row['option_name'], $protected_options, true),
            ];
        }, $autoload_rows);

        $orphans = array_map(function ($row) {
            return [
                'prefix' => $row['prefix'],
                'count'  => $row['count'],
                'source' => isset($row['possible_source']) ? $row['possible_source'] : '',
                'risk'   => isset($row['risk']) ? $row['risk'] : '',
            ];
        }, $orphan_rows);

        $cleanup_gain = $gain([
            'expired_transients' => 0,
            'transient_total'    => max(0, $baseline['transient_total'] - $expired_transients),
        ]);

        // Joint gain from taking every actionable row in a table at once. Not
        // a per-row figure — the health score's capped/ratio penalties (see
        // the $gain() docblock above) mean a single row's own marginal gain
        // is often 0 even when clearing the whole table together would move
        // the score, so only this one whole-table number is ever shown; nothing
        // is computed or exposed per row. Same real, single $gain() call
        // cleanup_score_gain already uses, just with the whole table's
        // actionable rows overridden together.
        $actionable_autoload_bytes = array_sum(array_map(function ($row) {
            return $row['protected'] ? 0 : $row['size_bytes'];
        }, $autoload));
        $autoload_total_gain = $gain([
            'autoload_bytes' => max(0, $baseline['autoload_bytes'] - $actionable_autoload_bytes),
        ]);
        $orphans_total_gain = $gain(['orphan_groups' => 0]);

        return [
            'score'               => $display_score,
            'autoload_total'      => $optimizer->get_total_autoload_size(),
            'autoload'            => $autoload,
            'autoload_total_gain' => $autoload_total_gain,
            'usage_tracking'      => $usage['tracking'],
            'usage_since'         => $usage['since'],
            'usage_seen'          => $usage['seen_count'],
            'usage_loads'         => $usage['load_count'],
            'orphans'             => $orphans,
            'orphans_total_gain'  => $orphans_total_gain,
            'expired_transients'  => $expired_transients,
            'cleanup_score_gain'  => $cleanup_gain,
        ];
    }

    /**
     * Days since the most recent backup snapshot, or null if there is none —
     * mirrors HealthService::metrics()'s calculation without pulling in its
     * full (redundantly heavy) metrics pass.
     *
     * @return int|null
     */
    private function real_backup_age_days()
    {
        $snapshots   = (new BackupManager())->get_snapshots();
        $last_backup = !empty($snapshots) ? $snapshots[0]['created_at'] : null;

        return $last_backup
            ? (int) floor((time() - strtotime($last_backup . ' UTC')) / DAY_IN_SECONDS)
            : null;
    }

    /**
     * Lean metrics snapshot for score-impact math. Deliberately not
     * HealthService::metrics() — that re-derives orphans/usage from scratch
     * (duplicate scan_orphans()/get_plugins() work we've already done above).
     * `options_count` and `backup_age_days` are constant across every gain
     * diff below (no Optimize action changes a snapshot's age, and only
     * orphan deletion changes row counts by a meaningful amount) — options_count
     * is fetched for accuracy on that path; backup_age_days is a fixed `null`
     * since it cancels out of every diff regardless of value.
     *
     * @param int $orphan_groups      Orphan group count.
     * @param int $expired_transients Expired transient count.
     * @return array
     */
    private function score_baseline($orphan_groups, $expired_transients)
    {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard analytics
        $autoload_bytes = (int) $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload NOT IN ('off', 'no', 'false', '0', '')"
        );
        $transient_total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE " . $this->transient_value_where('option_name')
        );
        $options_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}");
        // phpcs:enable

        return [
            'autoload_bytes'     => $autoload_bytes,
            'transient_total'    => $transient_total,
            'expired_transients' => $expired_transients,
            'orphan_groups'      => $orphan_groups,
            'options_count'      => $options_count,
            'backup_age_days'    => null,
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

        // Expired only: remove timeouts in the past plus their value rows
        // (both the regular and network-wide scopes).
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $expired = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE " . $this->transient_timeout_where('option_name') . ' AND option_value < %d',
            time()
        ));
        $deleted = 0;
        $cleared = 0;
        foreach ($expired as $timeout_name) {
            $is_site = $this->is_site_transient($timeout_name);
            $transient = $this->transient_bare_name_from_timeout($timeout_name);
            $deleted += (int) $wpdb->delete($wpdb->options, ['option_name' => $timeout_name]);
            $deleted += (int) $wpdb->delete($wpdb->options, ['option_name' => $this->transient_value_name($transient, $is_site)]);
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
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE " . $this->transient_timeout_where('option_name') . ' AND option_value < %d',
            time()
        ));
    }
}
