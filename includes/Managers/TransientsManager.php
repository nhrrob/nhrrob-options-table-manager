<?php
namespace Nhrotm\OptionsTableManager\Managers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TransientsManager
 *
 * Lists and manages transients — both the regular ("_transient_") and
 * network-wide ("_site_transient_") scopes: view name, size, expiration and
 * status, delete individually or in bulk by scope.
 */
class TransientsManager extends BaseTableManager
{
    /**
     * Get searchable columns (required by BaseTableManager).
     *
     * @return array
     */
    protected function get_searchable_columns()
    {
        return [];
    }

    /**
     * List all transients (both scopes) with status and size.
     *
     * @return array
     */
    public function get_data()
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific query
        $rows = $wpdb->get_results(
            "SELECT option_name, option_value, LENGTH(option_value) AS size_bytes
            FROM {$wpdb->options}
            WHERE " . $this->transient_value_where('option_name') . '
            ORDER BY size_bytes DESC',
            ARRAY_A
        );

        $now  = time();
        $data = [];
        foreach ($rows as $row) {
            $is_site = $this->is_site_transient($row['option_name']);
            $name    = $this->transient_bare_name($row['option_name']);
            $timeout = (int) get_option($this->transient_timeout_name($name, $is_site), 0);

            if (0 === $timeout) {
                $status = 'persistent';
            } elseif ($timeout < $now) {
                $status = 'expired';
            } else {
                $status = 'active';
            }

            $data[] = [
                'name'           => $name,
                'status'         => $status,
                'expires'        => $timeout ? gmdate('Y-m-d H:i', $timeout) : '—',
                'size_formatted' => size_format($row['size_bytes']),
                'value_snippet'  => substr(wp_strip_all_tags($row['option_value']), 0, 80),
            ];
        }

        return $data;
    }

    /**
     * Whether a bare transient name currently exists as a network-wide
     * ("_site_transient_") row rather than the regular scope.
     *
     * @param string $name Bare transient name.
     * @return bool
     */
    private function is_site_scoped($name)
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-specific query
        return (bool) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT 1 FROM {$this->wpdb->options} WHERE option_name = %s",
            '_site_transient_' . $name
        ));
    }

    /**
     * Delete a single transient by name.
     *
     * @return bool Success status
     */
    public function delete_record()
    {
        $this->validate_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : ''); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- sanitized in validate_nonce
        $this->validate_permissions();

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        if (empty($name)) {
            throw new \Exception('Transient name is required');
        }

        return $this->is_site_scoped($name) ? delete_site_transient($name) : delete_transient($name);
    }

    /**
     * Delete transients in bulk by scope: expired | persistent | all.
     *
     * @return int Number of transients deleted
     */
    public function bulk_delete()
    {
        $this->validate_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : ''); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- sanitized in validate_nonce
        $this->validate_permissions();

        $scope = isset($_POST['scope']) ? sanitize_text_field(wp_unslash($_POST['scope'])) : '';
        if (!in_array($scope, ['expired', 'persistent', 'all'], true)) {
            throw new \Exception('Invalid scope');
        }

        $deleted = 0;
        foreach ($this->get_data() as $transient) {
            if ('all' === $scope || $scope === $transient['status']) {
                $removed = $this->is_site_scoped($transient['name'])
                    ? delete_site_transient($transient['name'])
                    : delete_transient($transient['name']);
                if ($removed) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Edit is not supported for transients.
     *
     * @return bool
     */
    public function edit_record()
    {
        return false;
    }
}
