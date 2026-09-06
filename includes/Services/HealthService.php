<?php
namespace Nhrotm\OptionsTableManager\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Managers\ScannerManager;
use Nhrotm\OptionsTableManager\Managers\BackupManager;
use Nhrotm\OptionsTableManager\Managers\UsageTracker;

/**
 * Computes the database health score and dashboard summary.
 *
 * Score is a 0–100 blend of penalties: autoload size vs a 1 MB budget,
 * expired-transient ratio, orphan groups, options count, and backup age.
 * Reuses existing managers for orphans and backups to avoid duplication.
 */
class HealthService
{
    const AUTOLOAD_BUDGET = 1048576; // 1 MB.
    const OPTIONS_BUDGET   = 1500;

    // Front-end loads needed before "never used" is a claim worth making.
    const USAGE_SAMPLE_MIN = 20;

    /**
     * @var ActivityService
     */
    private $activity;

    public function __construct(?ActivityService $activity = null)
    {
        $this->activity = $activity ? $activity : new ActivityService();
    }

    /**
     * Raw metrics for the options table.
     *
     * @return array
     */
    public function metrics()
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard analytics
        $autoload_bytes = (int) $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload NOT IN ('no', 'false', '0', '')"
        );
        $autoload_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload NOT IN ('no', 'false', '0', '')"
        );
        $options_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}");

        $transient_total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%' AND option_name NOT LIKE '\_transient\_timeout\_%'"
        );
        $expired_transients = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%' AND option_value < %d",
                time()
            )
        );
        // Total bytes held by expired transients (value rows joined to their timeout rows).
        $expired_bytes = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(LENGTH(o.option_value))
                 FROM {$wpdb->options} o
                 INNER JOIN {$wpdb->options} t
                   ON t.option_name = CONCAT('_transient_timeout_', SUBSTRING(o.option_name, 12))
                 WHERE o.option_name LIKE '\_transient\_%'
                   AND o.option_name NOT LIKE '\_transient\_timeout\_%'
                   AND t.option_value < %d",
                time()
            )
        );
        // phpcs:enable

        $orphan_groups = count((new ScannerManager())->scan_orphans());

        // Usage tracking: autoloaded options never read on a real front-end load.
        $usage = (new UsageTracker())->get_unused_autoload_options();

        $snapshots      = (new BackupManager())->get_snapshots();
        $snapshot_count = is_array($snapshots) ? count($snapshots) : 0;
        $last_backup    = !empty($snapshots) ? $snapshots[0]['created_at'] : null;
        $backup_age_days = $last_backup
            ? floor((time() - strtotime($last_backup . ' UTC')) / DAY_IN_SECONDS)
            : null;

        return [
            'autoload_bytes'     => $autoload_bytes,
            'autoload_count'     => $autoload_count,
            'options_count'      => $options_count,
            'transient_total'    => $transient_total,
            'expired_transients' => $expired_transients,
            'expired_bytes'      => $expired_bytes,
            'orphan_groups'      => $orphan_groups,
            'snapshot_count'     => $snapshot_count,
            'last_backup'        => $last_backup,
            'backup_age_days'    => $backup_age_days,
            'usage_tracking'     => (bool) $usage['tracking'],
            'usage_loads'        => (int) $usage['load_count'],
            'unused_autoload'    => count($usage['options']),
        ];
    }

    /**
     * Full dashboard summary: score, headline, cards, recommendations.
     *
     * @return array
     */
    public function summary()
    {
        $m = $this->metrics();
        $score = $this->score($m);

        $recommendations = $this->recommendations($m);

        return [
            'score'           => $score,
            'headline'        => $this->headline($score),
            'description'     => $this->description($score, $recommendations),
            'stats'           => $this->stats($m),
            'metrics'         => $m,
            'cards'           => $this->cards($m),
            'recommendations' => $recommendations,
            'activity'        => $this->activity->recent(5),
        ];
    }

    /**
     * Weighted 0–100 score.
     *
     * @param array $m Metrics.
     * @return int
     */
    private function score(array $m)
    {
        $penalty = 0;

        // Autoload size vs 1 MB budget (up to 30).
        $penalty += min(30, ($m['autoload_bytes'] / self::AUTOLOAD_BUDGET) * 30);

        // Expired-transient ratio (up to 20).
        if ($m['transient_total'] > 0) {
            $penalty += min(20, ($m['expired_transients'] / $m['transient_total']) * 20);
        }

        // Orphan groups (up to 20).
        $penalty += min(20, $m['orphan_groups'] * 2);

        // Options count over budget (up to 15).
        if ($m['options_count'] > self::OPTIONS_BUDGET) {
            $penalty += min(15, (($m['options_count'] - self::OPTIONS_BUDGET) / self::OPTIONS_BUDGET) * 15);
        }

        // Backup age (up to 15).
        if (null === $m['backup_age_days']) {
            $penalty += 15;
        } elseif ($m['backup_age_days'] > 30) {
            $penalty += 15;
        } elseif ($m['backup_age_days'] > 7) {
            $penalty += 10;
        }

        return (int) max(0, round(100 - $penalty));
    }

    /**
     * @param int $score Score.
     * @return string
     */
    private function headline($score)
    {
        if ($score >= 80) {
            return __('Good — a few easy wins available.', 'nhrrob-options-table-manager');
        }
        if ($score >= 50) {
            return __('Fair — some cleanup recommended.', 'nhrrob-options-table-manager');
        }
        return __('Needs attention — several issues to address.', 'nhrrob-options-table-manager');
    }

    /**
     * @param array $m Metrics.
     * @return array
     */
    private function cards(array $m)
    {
        // Autoload sub: how many options are autoloaded.
        $autoload_sub = sprintf(
            /* translators: %s: number of autoloaded options. */
            _n('%s autoloaded option', '%s autoloaded options', $m['autoload_count'], 'nhrrob-options-table-manager'),
            number_format_i18n($m['autoload_count'])
        );

        // Options sub: orphan hint, or a healthy note.
        $options_sub = $m['orphan_groups'] > 0
            ? sprintf(
                /* translators: %s: number of orphaned option groups. */
                _n('%s group looks orphaned', '%s groups look orphaned', $m['orphan_groups'], 'nhrrob-options-table-manager'),
                number_format_i18n($m['orphan_groups'])
            )
            : __('nothing orphaned', 'nhrrob-options-table-manager');

        // Transients sub: expired count + size, or all-clear.
        $transients_sub = $m['expired_transients'] > 0
            ? sprintf(
                /* translators: 1: expired count, 2: human-readable size. */
                __('%1$s expired · %2$s', 'nhrrob-options-table-manager'),
                number_format_i18n($m['expired_transients']),
                $this->format_bytes($m['expired_bytes'])
            )
            : __('none expired', 'nhrrob-options-table-manager');

        // Transients action names the count when there is something to clean.
        $transients_action = $m['expired_transients'] > 0
            ? sprintf(
                /* translators: %s: number of expired transients. */
                __('Clean %s →', 'nhrrob-options-table-manager'),
                number_format_i18n($m['expired_transients'])
            )
            : __('Clean →', 'nhrrob-options-table-manager');

        // Backup sub: how many snapshots are stored.
        $backup_sub = $m['snapshot_count'] > 0
            ? sprintf(
                /* translators: %s: number of saved snapshots. */
                _n('%s snapshot saved', '%s snapshots saved', $m['snapshot_count'], 'nhrrob-options-table-manager'),
                number_format_i18n($m['snapshot_count'])
            )
            : __('no snapshots yet', 'nhrrob-options-table-manager');

        return [
            [
                'id'      => 'autoload',
                'metric'  => $this->format_bytes($m['autoload_bytes']),
                'sub'     => $autoload_sub,
                'action'  => __('Review →', 'nhrrob-options-table-manager'),
                'section' => 'optimize',
                'focus'   => 'autoload',
            ],
            [
                'id'      => 'options',
                'metric'  => number_format_i18n($m['options_count']),
                'sub'     => $options_sub,
                'action'  => __('Scan →', 'nhrrob-options-table-manager'),
                'section' => 'browse',
            ],
            [
                'id'      => 'transients',
                'metric'  => number_format_i18n($m['transient_total']),
                'sub'     => $transients_sub,
                'action'  => $transients_action,
                'section' => 'optimize',
                'focus'   => 'cleanup',
            ],
            [
                'id'      => 'backup',
                'metric'  => $this->humanize_backup($m['last_backup']),
                'sub'     => $backup_sub,
                'action'  => __('New →', 'nhrrob-options-table-manager'),
                'section' => 'tools',
                'focus'   => 'backups',
            ],
        ];
    }

    /**
     * Plain-language paragraph under the headline: what the score means, and
     * which of the open recommendations would move it most.
     *
     * @param int   $score           Health score.
     * @param array $recommendations Open recommendations.
     * @return string
     */
    private function description($score, array $recommendations)
    {
        if (empty($recommendations)) {
            return __(
                'Nothing needs attention right now. Your options table is lean, backed up, and free of expired or orphaned data.',
                'nhrrob-options-table-manager'
            );
        }

        // Name the top two fixes so the sentence tells the user what to do next.
        $fixes = array_slice(wp_list_pluck($recommendations, 'lede'), 0, 2);
        $fixes = array_values(array_filter($fixes));

        if (count($fixes) > 1) {
            $next = sprintf(
                /* translators: 1: first suggested fix, 2: second suggested fix. Both are lowercase gerund phrases. */
                __('%1$s and %2$s', 'nhrrob-options-table-manager'),
                $fixes[0],
                $fixes[1]
            );
        } else {
            $next = isset($fixes[0]) ? $fixes[0] : '';
        }

        if ($score >= 80) {
            $opening = __('Your database is in decent shape.', 'nhrrob-options-table-manager');
        } elseif ($score >= 50) {
            $opening = __('Your database works, but it is carrying avoidable weight.', 'nhrrob-options-table-manager');
        } else {
            $opening = __('Your database is carrying enough dead weight to slow every page load.', 'nhrrob-options-table-manager');
        }

        if ('' === $next) {
            return $opening;
        }

        return sprintf(
            /* translators: 1: opening sentence about the score, 2: lowercase gerund phrase naming the next actions. */
            __('%1$s Start by %2$s.', 'nhrrob-options-table-manager'),
            $opening,
            $next
        );
    }

    /**
     * size_format() returns false for zero — always give the UI a string.
     *
     * @param int $bytes Byte count.
     * @return string
     */
    private function format_bytes($bytes)
    {
        $formatted = size_format((int) $bytes);
        return $formatted ? $formatted : __('0 B', 'nhrrob-options-table-manager');
    }

    /**
     * Compact figure strip under the description: value + what it measures.
     *
     * @param array $m Metrics.
     * @return array
     */
    private function stats(array $m)
    {
        return [
            [
                'value' => size_format($m['autoload_bytes']),
                'label' => __('autoload', 'nhrrob-options-table-manager'),
            ],
            [
                'value' => number_format_i18n($m['expired_transients']),
                'label' => __('expired transients', 'nhrrob-options-table-manager'),
            ],
            [
                'value' => number_format_i18n($m['orphan_groups']),
                'label' => __('orphans', 'nhrrob-options-table-manager'),
            ],
            [
                'value'  => $this->humanize_backup($m['last_backup']),
                'label'  => __('Last backup', 'nhrrob-options-table-manager'),
                'before' => true,
            ],
        ];
    }

    /**
     * @param array $m Metrics.
     * @return array
     */
    private function recommendations(array $m)
    {
        $recs = [];

        if ($m['expired_transients'] > 0) {
            $recs[] = [
                'severity' => 'warning',
                'icon'     => 'alert',
                'text'     => sprintf(
                    /* translators: 1: expired transient count, 2: human-readable size, e.g. "220 KB". */
                    _n(
                        '%1$s expired transient is taking up %2$s.',
                        '%1$s expired transients are taking up %2$s.',
                        $m['expired_transients'],
                        'nhrrob-options-table-manager'
                    ),
                    number_format_i18n($m['expired_transients']),
                    $this->format_bytes($m['expired_bytes'])
                ),
                'lede'     => __('clearing expired transients', 'nhrrob-options-table-manager'),
                'action'   => __('Delete expired', 'nhrrob-options-table-manager'),
                'section'  => 'optimize',
                'focus'    => 'cleanup',
            ];
        }

        if ($m['autoload_bytes'] > self::AUTOLOAD_BUDGET) {
            $recs[] = [
                'severity' => 'danger',
                'icon'     => 'optimize',
                'text'     => sprintf(
                    /* translators: %s: total autoloaded size, e.g. "3 MB". */
                    __('%s of autoloaded data loads on every single request.', 'nhrrob-options-table-manager'),
                    size_format($m['autoload_bytes'])
                ),
                'lede'     => __('trimming heavy autoloaded options', 'nhrrob-options-table-manager'),
                'action'   => __('Review autoload', 'nhrrob-options-table-manager'),
                'section'  => 'optimize',
                'focus'    => 'autoload',
            ];
        }

        // Only claim an option is unused once the sample is big enough to mean it.
        if ($m['usage_tracking'] && $m['unused_autoload'] > 0 && $m['usage_loads'] >= self::USAGE_SAMPLE_MIN) {
            $recs[] = [
                'severity' => 'info',
                'icon'     => 'optimize',
                'text'     => sprintf(
                    /* translators: 1: unused option count, 2: number of front-end page loads observed. */
                    _n(
                        '%1$s autoloaded option was never used across %2$s front-end loads.',
                        '%1$s autoloaded options were never used across %2$s front-end loads.',
                        $m['unused_autoload'],
                        'nhrrob-options-table-manager'
                    ),
                    number_format_i18n($m['unused_autoload']),
                    number_format_i18n($m['usage_loads'])
                ),
                'lede'     => __('reviewing unused autoloads', 'nhrrob-options-table-manager'),
                'action'   => __('Review in Optimize', 'nhrrob-options-table-manager'),
                'section'  => 'optimize',
                'focus'    => 'usage',
            ];
        }

        if ($m['orphan_groups'] > 0) {
            $recs[] = [
                'severity' => 'info',
                'icon'     => 'database',
                'text'     => sprintf(
                    /* translators: %s: orphan group count. */
                    _n(
                        '%s option group looks like a leftover from a removed plugin.',
                        '%s option groups look like leftovers from removed plugins.',
                        $m['orphan_groups'],
                        'nhrrob-options-table-manager'
                    ),
                    number_format_i18n($m['orphan_groups'])
                ),
                'lede'     => __('clearing orphaned options', 'nhrrob-options-table-manager'),
                'action'   => __('Scan orphans', 'nhrrob-options-table-manager'),
                'section'  => 'optimize',
                'focus'    => 'orphans',
            ];
        }

        if (null === $m['backup_age_days']) {
            $recs[] = [
                'severity' => 'warning',
                'icon'     => 'refresh',
                'text'     => __('No snapshot has ever been taken of this options table.', 'nhrrob-options-table-manager'),
                'lede'     => __('taking a snapshot', 'nhrrob-options-table-manager'),
                'action'   => __('Take snapshot', 'nhrrob-options-table-manager'),
                'section'  => 'tools',
                'focus'    => 'backups',
            ];
        } elseif ($m['backup_age_days'] > 7) {
            $recs[] = [
                'severity' => 'warning',
                'icon'     => 'refresh',
                'text'     => sprintf(
                    /* translators: %s: number of days since the last snapshot. */
                    _n(
                        'Your most recent snapshot is %s day old.',
                        'Your most recent snapshot is %s days old.',
                        $m['backup_age_days'],
                        'nhrrob-options-table-manager'
                    ),
                    number_format_i18n($m['backup_age_days'])
                ),
                'lede'     => __('taking a fresh snapshot', 'nhrrob-options-table-manager'),
                'action'   => __('Take snapshot', 'nhrrob-options-table-manager'),
                'section'  => 'tools',
                'focus'    => 'backups',
            ];
        }

        return $recs;
    }

    /**
     * @param int $days Days.
     * @return string
     */
    private function humanize_days($days)
    {
        if ($days < 1) {
            return __('Today', 'nhrrob-options-table-manager');
        }
        if ($days < 2) {
            return __('1 day ago', 'nhrrob-options-table-manager');
        }
        /* translators: %s: number of days. */
        return sprintf(__('%s days ago', 'nhrrob-options-table-manager'), number_format_i18n($days));
    }

    /**
     * Human-friendly "time ago" for the last backup (minutes/hours/days), or "None".
     *
     * @param string|null $last_backup Backup timestamp (UTC mysql), or null.
     * @return string
     */
    private function humanize_backup($last_backup)
    {
        if (!$last_backup) {
            return __('None', 'nhrrob-options-table-manager');
        }
        $ts = strtotime($last_backup . ' UTC');
        if (!$ts) {
            return __('None', 'nhrrob-options-table-manager');
        }
        /* translators: %s: human-readable time difference, e.g. "2 hours". */
        return sprintf(__('%s ago', 'nhrrob-options-table-manager'), human_time_diff($ts));
    }
}
