<?php
namespace Nhrotm\OptionsTableManager\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Nhrotm\OptionsTableManager\Traits\GlobalTrait;

/**
 * Read/delete service for the unified Browse data grid.
 *
 * Serves options, usermeta, and transients through one paginated interface
 * for the REST BrowseController. Core options/usermeta are flagged protected
 * so the UI can block destructive actions on them.
 */
class BrowseService
{
    use GlobalTrait;

    const TYPES = ['options', 'usermeta', 'transients'];

    /**
     * @var ActivityService
     */
    private $activity;

    public function __construct(?ActivityService $activity = null)
    {
        $this->activity = $activity ? $activity : new ActivityService();
    }

    /**
     * Paginated query for a given data type.
     *
     * @param string $type     options | usermeta | transients.
     * @param int    $page     1-based page.
     * @param int    $per_page Rows per page.
     * @param string $search   Optional search term.
     * @return array { items, total, page, per_page }
     */
    public function query($type, $page = 1, $per_page = 20, $search = '', $orderby = 'size', $order = 'desc')
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'options';
        $page = max(1, (int) $page);
        $per_page = min(100, max(1, (int) $per_page));
        $offset = ($page - 1) * $per_page;
        $order_sql = $this->resolve_order($type, $orderby, $order);

        if ('usermeta' === $type) {
            $result = $this->query_usermeta($search, $offset, $per_page, $order_sql);
        } elseif ('transients' === $type) {
            $result = $this->query_transients($search, $offset, $per_page, $order_sql);
        } else {
            $result = $this->query_options($search, $offset, $per_page, $order_sql);
        }

        $result['page'] = $page;
        $result['per_page'] = $per_page;
        return $result;
    }

    /**
     * Build a safe ORDER BY clause from a whitelist (never interpolates user input).
     *
     * @param string $type    Data type.
     * @param string $orderby Requested sort key (name|size|autoload).
     * @param string $order   Direction (asc|desc).
     * @return string SQL ORDER BY fragment.
     */
    private function resolve_order($type, $orderby, $order)
    {
        $dir = 'asc' === strtolower((string) $order) ? 'ASC' : 'DESC';

        $columns = [
            'options'    => ['name' => 'option_name', 'size' => 'LENGTH(option_value)', 'autoload' => 'autoload', 'id' => 'option_id'],
            'usermeta'   => ['name' => 'meta_key', 'size' => 'LENGTH(meta_value)', 'id' => 'umeta_id'],
            'transients' => ['name' => 'option_name', 'size' => 'LENGTH(option_value)', 'id' => 'option_id'],
        ];
        $map = isset($columns[$type]) ? $columns[$type] : $columns['options'];

        if (isset($map[$orderby])) {
            $col = $map[$orderby];
        } else {
            $col = isset($map['size']) ? $map['size'] : reset($map);
        }

        return "ORDER BY {$col} {$dir}";
    }

    /**
     * @param string $search Search term.
     * @param int    $offset Offset.
     * @param int    $limit  Limit.
     * @return array
     */
    private function query_options($search, $offset, $limit, $order_sql = 'ORDER BY LENGTH(option_value) DESC')
    {
        global $wpdb;
        $protected = $this->get_protected_options();

        $where = "WHERE option_name NOT LIKE '\_transient\_%' AND option_name NOT LIKE '\_site\_transient\_%'";
        $params = [];
        if ('' !== $search) {
            $where .= ' AND option_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->options} $where";
        $total = (int) ( $params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql) );

        $sql = "SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} $where $order_sql LIMIT %d, %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, [$offset, $limit])), ARRAY_A);
        // phpcs:enable

        $items = array_map(function ($row) use ($protected) {
            return [
                'id'        => (int) $row['option_id'],
                'name'      => $row['option_name'],
                'preview'   => $this->preview($row['option_value']),
                'size'      => size_format(strlen($row['option_value'])),
                'autoload'  => in_array($row['autoload'], ['no', 'false', '0', ''], true) ? 'no' : 'yes',
                'protected' => in_array($row['option_name'], $protected, true),
            ];
        }, $rows ? $rows : []);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param string $search Search term.
     * @param int    $offset Offset.
     * @param int    $limit  Limit.
     * @return array
     */
    private function query_usermeta($search, $offset, $limit, $order_sql = 'ORDER BY umeta_id DESC')
    {
        global $wpdb;
        $protected = $this->get_protected_usermetas();

        $where = '';
        $params = [];
        if ('' !== $search) {
            $where = 'WHERE meta_key LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->usermeta} $where";
        $total = (int) ( $params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql) );

        $sql = "SELECT umeta_id, user_id, meta_key, meta_value FROM {$wpdb->usermeta} $where $order_sql LIMIT %d, %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, [$offset, $limit])), ARRAY_A);
        // phpcs:enable

        $items = array_map(function ($row) use ($protected) {
            return [
                'id'        => (int) $row['umeta_id'],
                'name'      => $row['meta_key'],
                'preview'   => $this->preview($row['meta_value']),
                'size'      => size_format(strlen((string) $row['meta_value'])),
                'user_id'   => (int) $row['user_id'],
                'protected' => in_array($row['meta_key'], $protected, true),
            ];
        }, $rows ? $rows : []);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param string $search Search term.
     * @param int    $offset Offset.
     * @param int    $limit  Limit.
     * @return array
     */
    private function query_transients($search, $offset, $limit, $order_sql = 'ORDER BY option_id DESC')
    {
        global $wpdb;

        // Matches both regular ("_transient_") and network-wide ("_site_transient_")
        // scopes — the latter is where WP core actually stores update_plugins,
        // update_core, update_themes, and can hold sizable feed/browser caches.
        $where = 'WHERE ' . $this->transient_value_where('option_name');
        $params = [];
        if ('' !== $search) {
            $where .= ' AND option_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->options} $where";
        $total = (int) ( $params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql) );

        $sql = "SELECT option_id, option_name, option_value FROM {$wpdb->options} $where $order_sql LIMIT %d, %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, [$offset, $limit])), ARRAY_A);
        // phpcs:enable

        $now = time();
        $items = array_map(function ($row) use ($wpdb, $now) {
            $is_site = $this->is_site_transient($row['option_name']);
            $transient = $this->transient_bare_name($row['option_name']);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $timeout = (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->transient_timeout_name($transient, $is_site)));
            if (0 === $timeout) {
                $status = 'persistent';
            } elseif ($timeout < $now) {
                $status = 'expired';
            } else {
                $status = 'active';
            }
            return [
                'id'        => $transient,
                'name'      => $transient,
                'preview'   => $this->preview($row['option_value']),
                'size'      => size_format(strlen($row['option_value'])),
                'status'    => $status,
                'expires'   => $timeout ? gmdate('Y-m-d H:i', $timeout) : '—',
                'protected' => false,
            ];
        }, $rows ? $rows : []);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Delete a single record, honoring protection.
     *
     * @param string     $type options | usermeta | transients.
     * @param int|string $id   Record identifier (option_id / umeta_id / transient name).
     * @return bool
     */
    public function delete($type, $id)
    {
        global $wpdb;

        if ('usermeta' === $type) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $row = $wpdb->get_row($wpdb->prepare("SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE umeta_id = %d", (int) $id), ARRAY_A);
            if (!$row || in_array($row['meta_key'], $this->get_protected_usermetas(), true)) {
                return false;
            }
            if (!delete_metadata_by_mid('user', (int) $id)) {
                return false;
            }
            $this->activity->record('delete_usermeta', $row['meta_key'], $row['meta_value']);
            return true;
        }

        if ('transients' === $type) {
            $name = (string) $id;
            $deleted = $this->transient_is_site_scoped($name)
                ? delete_site_transient($name)
                : delete_transient($name);
            if (!$deleted) {
                return false;
            }
            $this->activity->record('delete_transient', $name);
            return true;
        }

        // Options.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_id = %d", (int) $id), ARRAY_A);
        if (!$row || in_array($row['option_name'], $this->get_protected_options(), true)) {
            return false;
        }
        if (!delete_option($row['option_name'])) {
            return false;
        }
        // Old value is kept so the entry stays restorable from Option History.
        $this->activity->record('delete', $row['option_name'], $row['option_value']);
        return true;
    }

    /**
     * Fetch a single record with its full value, for the edit modal.
     *
     * @param string     $type options | usermeta | transients.
     * @param int|string $id   Record id (option_id / umeta_id / transient name).
     * @return array|null
     */
    public function get($type, $id)
    {
        global $wpdb;

        if ('usermeta' === $type) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $row = $wpdb->get_row($wpdb->prepare("SELECT umeta_id, user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE umeta_id = %d", (int) $id), ARRAY_A);
            if (!$row) {
                return null;
            }
            $analysis = $this->analyze_value($row['meta_value']);
            return [
                'id'        => (int) $row['umeta_id'],
                'name'      => $row['meta_key'],
                'value'     => $analysis['value'],
                'format'    => $analysis['format'],
                'user_id'   => (int) $row['user_id'],
                'protected' => in_array($row['meta_key'], $this->get_protected_usermetas(), true),
            ];
        }

        if ('transients' === $type) {
            $name = (string) $id;
            $is_site = $this->transient_is_site_scoped($name);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->transient_value_name($name, $is_site)), ARRAY_A);
            if (!$row) {
                return null;
            }
            $timeout = (int) get_option($this->transient_timeout_name($name, $is_site), 0);
            $analysis = $this->analyze_value($row['option_value']);
            return [
                'id'         => $name,
                'name'       => $name,
                'value'      => $analysis['value'],
                'format'     => $analysis['format'],
                'expiration' => $timeout > 0 ? max(0, $timeout - time()) : 0,
                'protected'  => false,
            ];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row($wpdb->prepare("SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_id = %d", (int) $id), ARRAY_A);
        if (!$row) {
            return null;
        }
        $analysis = $this->analyze_value($row['option_value']);
        return [
            'id'        => (int) $row['option_id'],
            'name'      => $row['option_name'],
            'value'     => $analysis['value'],
            'format'    => $analysis['format'],
            'autoload'  => in_array($row['autoload'], ['no', 'false', '0', ''], true) ? 'no' : 'yes',
            'protected' => in_array($row['option_name'], $this->get_protected_options(), true),
        ];
    }

    /**
     * Detect a value's structure and return an editable form.
     *
     * Serialized arrays and JSON are pretty-printed as JSON for structured
     * editing; anything else (scalars, serialized objects) stays raw so it
     * round-trips without corruption.
     *
     * @param string $raw Stored value.
     * @return array { format: serialized|json|plain, value: string }
     */
    private function analyze_value($raw)
    {
        $raw = (string) $raw;

        if (is_serialized($raw)) {
            $data = @unserialize($raw); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
            if (is_array($data)) {
                $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (false !== $json) {
                    return ['format' => 'serialized', 'value' => $json];
                }
            }
            return ['format' => 'plain', 'value' => $raw];
        }

        $trimmed = trim($raw);
        if ('' !== $trimmed && ('{' === $trimmed[0] || '[' === $trimmed[0])) {
            $decoded = json_decode($trimmed, true);
            if (null !== $decoded) {
                $json = wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (false !== $json) {
                    return ['format' => 'json', 'value' => $json];
                }
            }
        }

        return ['format' => 'plain', 'value' => $raw];
    }

    /**
     * Convert an edited value back into its storage form.
     *
     * @param string $value  Edited value (JSON for serialized/json formats).
     * @param string $format serialized | json | plain.
     * @return string|null Storage string, or null if the JSON is invalid.
     */
    private function encode_for_storage($value, $format)
    {
        if ('serialized' === $format) {
            $data = json_decode($value, true);
            if (null === $data && 'null' !== trim($value)) {
                return null;
            }
            return serialize($data); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
        }

        if ('json' === $format) {
            $data = json_decode($value, true);
            if (null === $data && 'null' !== trim($value)) {
                return null;
            }
            return wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }

    /**
     * Create or update a record. Raw value is stored verbatim to preserve
     * serialized/JSON data exactly as entered.
     *
     * @param array $args { type, id?, name, value, autoload?, user_id?, expiration? }
     * @return array|false Saved record, or false when protected/invalid.
     */
    public function save(array $args)
    {
        global $wpdb;
        $type = isset($args['type']) && in_array($args['type'], self::TYPES, true) ? $args['type'] : 'options';
        $name = isset($args['name']) ? $args['name'] : '';
        $raw_value = isset($args['value']) ? $args['value'] : '';
        $format = isset($args['format']) ? $args['format'] : 'plain';
        $id = isset($args['id']) ? (int) $args['id'] : 0;

        $value = $this->encode_for_storage($raw_value, $format);
        if (null === $value) {
            return false; // Invalid JSON for a structured value.
        }

        if ('usermeta' === $type) {
            if ($id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $existing = $wpdb->get_row($wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE umeta_id = %d", $id), ARRAY_A);
                if (!$existing || in_array($existing['meta_key'], $this->get_protected_usermetas(), true)) {
                    return false;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update($wpdb->usermeta, ['meta_value' => $value], ['umeta_id' => $id], ['%s'], ['%d']);
                $this->activity->record('update', $existing['meta_key'], $existing['meta_value']);
                return $this->get('usermeta', $id);
            }

            // Create usermeta: requires a valid user_id and a key.
            $user_id = isset($args['user_id']) ? (int) $args['user_id'] : 0;
            if ('' === $name || !$user_id || !get_userdata($user_id)) {
                return false;
            }
            if (in_array($name, $this->get_protected_usermetas(), true)) {
                return false;
            }
            $mid = add_metadata('user', $user_id, $name, $value, false);
            if (!$mid) {
                return false;
            }
            $this->activity->record('create', $name);
            return $this->get('usermeta', $mid);
        }

        if ('transients' === $type) {
            if ('' === $name) {
                return false;
            }
            // Transients need their *native* PHP value (set_transient() serializes
            // it itself); passing the already-serialized $value above would make
            // WP's maybe_serialize() wrap it a second time. $value/$format above
            // already validated the JSON, so decoding here can't fail.
            $native = in_array($format, ['json', 'serialized'], true)
                ? json_decode($raw_value, true)
                : $raw_value;
            $expiration = isset($args['expiration']) ? max(0, (int) $args['expiration']) : 0;
            // A name with no existing row yet defaults to the regular scope —
            // there's no UI for a user to deliberately create a network-wide
            // transient; editing an existing site-scoped one preserves its scope.
            $is_site = $this->transient_is_site_scoped($name);
            $existing = $this->get('transients', $name);
            if ($is_site) {
                set_site_transient($name, $native, $expiration);
            } else {
                set_transient($name, $native, $expiration);
            }
            $this->activity->record($existing ? 'update_transient' : 'create_transient', $name);
            return $this->get('transients', $name);
        }

        if ('' === $name) {
            return false;
        }
        if (in_array($name, $this->get_protected_options(), true)) {
            return false;
        }

        $autoload = ( isset($args['autoload']) && 'no' === $args['autoload'] ) ? 'no' : 'yes';

        // Read the previous row before writing — the old value is what makes the
        // resulting history entry restorable.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $previous = $id
            ? $wpdb->get_row($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_id = %d", $id), ARRAY_A)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            : $wpdb->get_row($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name = %s", $name), ARRAY_A);

        if ($id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($wpdb->options, ['option_value' => $value, 'autoload' => $autoload], ['option_id' => $id], ['%s', '%s'], ['%d']);
        } elseif ($previous) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($wpdb->options, ['option_value' => $value, 'autoload' => $autoload], ['option_name' => $name], ['%s', '%s'], ['%s']);
            $id = (int) $this->option_id($name);
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert($wpdb->options, ['option_name' => $name, 'option_value' => $value, 'autoload' => $autoload], ['%s', '%s', '%s']);
            $id = (int) $wpdb->insert_id;
        }

        wp_cache_delete('alloptions', 'options');
        wp_cache_delete($name, 'options');

        if ($previous) {
            $this->activity->record('update', $previous['option_name'], $previous['option_value']);
        } else {
            $this->activity->record('create', $name);
        }

        return $this->get('options', $id);
    }

    /**
     * Whether a bare transient name currently exists as a network-wide
     * ("_site_transient_") row rather than the regular ("_transient_") scope.
     * Used to route get/delete/save to the matching WP transient API when a
     * name could plausibly exist in either scope.
     *
     * @param string $name Bare transient name.
     * @return bool
     */
    private function transient_is_site_scoped($name)
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$wpdb->options} WHERE option_name = %s", '_site_transient_' . $name));
    }

    /**
     * Delete many records, skipping protected ones.
     *
     * @param string $type options | usermeta | transients.
     * @param array  $ids  Record ids.
     * @return int Deleted count.
     */
    public function bulk_delete($type, array $ids)
    {
        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->delete($type, $id)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Look up an option's row id by name.
     *
     * @param string $name Option name.
     * @return int
     */
    private function option_id($name)
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var($wpdb->prepare("SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $name));
    }

    /**
     * Trim a value into a scrollable-length preview.
     *
     * Bounded (not the full raw value) so a page of rows can't balloon
     * into a multi-megabyte response for pathologically large options.
     *
     * @param string $value Raw stored value.
     * @return string
     */
    private function preview($value)
    {
        $value = (string) $value;
        $value = trim($value);
        if (strlen($value) > 4000) {
            $value = substr($value, 0, 4000) . '…';
        }
        return $value;
    }
}
