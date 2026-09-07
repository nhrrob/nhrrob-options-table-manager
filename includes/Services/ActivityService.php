<?php
namespace Nhrotm\OptionsTableManager\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Managers\HistoryManager;

/**
 * Records and reads the change feed shown as "Recent activity".
 *
 * Writes go to the existing wp_nhrotm_option_history table so option edits stay
 * restorable — this service only adds the non-option events (autoload changes,
 * orphan sweeps, transient cleanups) and turns rows into readable sentences.
 */
class ActivityService
{
    /**
     * @var HistoryManager
     */
    private $history;

    public function __construct(?HistoryManager $history = null)
    {
        $this->history = $history ? $history : new HistoryManager();
    }

    /**
     * Record one event.
     *
     * @param string $action    Action slug (see describe()).
     * @param string $subject   Option name, prefix, or count — whatever the action acts on.
     * @param mixed  $old_value Previous value, kept so option edits stay restorable.
     * @return void
     */
    public function record($action, $subject, $old_value = '')
    {
        $this->history->log_change((string) $subject, $old_value, $action);
    }

    /**
     * Latest events, newest first.
     *
     * @param int $limit Max rows.
     * @return array
     */
    public function recent($limit = 5)
    {
        return $this->format($this->history->get_recent($limit));
    }

    /**
     * One page of the full feed.
     *
     * @param int $page     1-based page number.
     * @param int $per_page Rows per page.
     * @return array { items, total, page, per_page, total_pages }
     */
    public function paged($page = 1, $per_page = 20)
    {
        $page     = max(1, (int) $page);
        $per_page = max(1, min(100, (int) $per_page));
        $total    = $this->history->count_all();

        return [
            'items'       => $this->format($this->history->get_page(($page - 1) * $per_page, $per_page)),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        ];
    }

    /**
     * Turn raw rows into { prefix, code, suffix, when } entries.
     *
     * @param array $rows History rows.
     * @return array
     */
    private function format(array $rows)
    {
        $out = [];

        foreach ($rows as $r) {
            list($prefix, $code, $suffix) = $this->describe($r['action'], $r['option_name']);

            $ts = strtotime($r['performed_at'] . ' UTC');
            $out[] = [
                'prefix' => $prefix,
                'code'   => $code,
                'suffix' => $suffix,
                /* translators: %s: human-readable time difference, e.g. "2 hours". */
                'when'   => $ts ? sprintf(__('%s ago', 'nhrrob-options-table-manager'), human_time_diff($ts)) : '',
            ];
        }

        return $out;
    }

    /**
     * Sentence parts for one action. The middle part is rendered as code, so
     * the verb and any trailing words stay separately translatable.
     *
     * @param string $action  Action slug.
     * @param string $subject Row subject.
     * @return array [ prefix, code, suffix ]
     */
    private function describe($action, $subject)
    {
        switch ($action) {
            case 'create':
                return [__('Added option', 'nhrrob-options-table-manager'), $subject, ''];

            case 'delete':
                return [__('Deleted option', 'nhrrob-options-table-manager'), $subject, ''];

            case 'delete_usermeta':
                return [__('Deleted user meta', 'nhrrob-options-table-manager'), $subject, ''];

            case 'delete_postmeta':
                return [__('Deleted post meta', 'nhrrob-options-table-manager'), $subject, ''];

            case 'delete_commentmeta':
                return [__('Deleted comment meta', 'nhrrob-options-table-manager'), $subject, ''];

            case 'delete_termmeta':
                return [__('Deleted term meta', 'nhrrob-options-table-manager'), $subject, ''];

            case 'delete_transient':
                return [__('Deleted transient', 'nhrrob-options-table-manager'), $subject, ''];

            case 'create_transient':
                return [__('Added transient', 'nhrrob-options-table-manager'), $subject, ''];

            case 'update_transient':
                return [__('Updated transient', 'nhrrob-options-table-manager'), $subject, ''];

            case 'disable_autoload':
                return [__('Disabled autoload on', 'nhrrob-options-table-manager'), $subject, ''];

            case 'delete_orphans':
                return [
                    __('Deleted orphaned option group', 'nhrrob-options-table-manager'),
                    $subject,
                    '',
                ];

            case 'clean_transients':
                $count = (int) $subject;
                return [
                    sprintf(
                        /* translators: %s: number of transients deleted. */
                        _n(
                            'Deleted %s expired transient',
                            'Deleted %s expired transients',
                            $count,
                            'nhrrob-options-table-manager'
                        ),
                        number_format_i18n($count)
                    ),
                    '',
                    '',
                ];

            case 'clean_transients_all':
                $count = (int) $subject;
                return [
                    sprintf(
                        /* translators: %s: number of transients deleted. */
                        _n(
                            'Deleted %s transient',
                            'Deleted %s transients',
                            $count,
                            'nhrrob-options-table-manager'
                        ),
                        number_format_i18n($count)
                    ),
                    '',
                    '',
                ];

            case 'snapshot':
                return [__('Snapshot taken', 'nhrrob-options-table-manager'), '', $subject];

            case 'restore':
            case 'restore_backup':
                return [
                    __('Restored option', 'nhrrob-options-table-manager'),
                    $subject,
                    __('to a previous value', 'nhrrob-options-table-manager'),
                ];
        }

        return [__('Updated option', 'nhrrob-options-table-manager'), $subject, ''];
    }
}
