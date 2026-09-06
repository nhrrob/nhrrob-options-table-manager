<?php
namespace Nhrotm\OptionsTableManager\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read service for third-party integration tables (Better Payment, WPRM).
 *
 * Quarantines integration browsing here so their tables never inflate the
 * core navigation. Only tables that actually exist are exposed.
 */
class IntegrationsService
{
    /**
     * Integration definitions keyed by slug: table suffix + label.
     *
     * @return array
     */
    private function definitions()
    {
        return [
            'better_payment' => [
                'label'  => 'Better Payment',
                'table'  => 'better_payment',
            ],
            'wprm_ratings'   => [
                'label'  => 'WP Recipe Maker',
                'table'  => 'wprm_ratings',
            ],
        ];
    }

    /**
     * Available integrations (only those whose table exists).
     *
     * @return array
     */
    public function available()
    {
        global $wpdb;
        $available = [];
        foreach ($this->definitions() as $slug => $def) {
            $table = $wpdb->prefix . $def['table'];
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
                $available[] = ['slug' => $slug, 'label' => $def['label'], 'count' => $count];
            }
        }
        return $available;
    }

    /**
     * Whether any integration table exists.
     *
     * @return bool
     */
    public function has_any()
    {
        return !empty($this->available());
    }

    /**
     * Paginated rows for one integration table.
     *
     * @param string $slug     Integration slug.
     * @param int    $page     1-based page.
     * @param int    $per_page Rows per page.
     * @return array { columns, items, total, page, per_page }
     */
    public function rows($slug, $page = 1, $per_page = 20)
    {
        global $wpdb;
        $defs = $this->definitions();
        if (!isset($defs[$slug])) {
            return ['columns' => [], 'items' => [], 'total' => 0, 'page' => 1, 'per_page' => $per_page];
        }

        $table = $wpdb->prefix . $defs[$slug]['table'];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return ['columns' => [], 'items' => [], 'total' => 0, 'page' => 1, 'per_page' => $per_page];
        }

        $page = max(1, (int) $page);
        $per_page = min(100, max(1, (int) $per_page));
        $offset = ($page - 1) * $per_page;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$table` ORDER BY 1 DESC LIMIT %d, %d", $offset, $per_page), ARRAY_A);
        // phpcs:enable

        $columns = !empty($rows) ? array_keys($rows[0]) : [];

        return [
            'columns'  => $columns,
            'items'    => $rows ? $rows : [],
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        ];
    }
}
