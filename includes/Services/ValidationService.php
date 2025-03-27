<?php
namespace Nhrotm\OptionsTableManager\Services;

class ValidationService {

    /**
     * Recursively sanitize input data while preserving structure and handling different data types
     *
     * @param mixed $data Input data to sanitize
     * @return mixed Sanitized data
     */
    public function sanitize_recursive($data) {
        // Handle different input types
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (!is_array($data)) {
            return $this->sanitize_item($data);
        }

        $sanitized = [];
        $content_keys = ['content']; // Keys that should use wp_kses_post for HTML content

        foreach ($data as $key => $value) {
            // Sanitize the key
            $clean_key = sanitize_key($key);
            
            if ($clean_key === '') {
                continue; // Skip keys that become empty after sanitization
            }

            // Recursively sanitize nested arrays/objects
            if (is_array($value) || is_object($value)) {
                $sanitized[$clean_key] = $this->sanitize_recursive($value);
                continue;
            }

            // Special handling for content keys to preserve HTML
            if (is_string($value) && in_array($clean_key, $content_keys)) {
                $sanitized[$clean_key] = wp_kses_post($value);
                continue;
            }

            // Sanitize based on value type
            $sanitized[$clean_key] = $this->sanitize_item($value);
        }

        return $sanitized;
    }

    /**
     * Sanitize a single item based on its type
     *
     * @param mixed $item Item to sanitize
     * @return mixed Sanitized item
     */
    public function sanitize_item($item) {
        // Handle different data types with appropriate sanitization
        if (is_numeric($item)) {
            return is_float($item) ? floatval($item) : intval($item);
        }

        if (is_bool($item)) {
            return (bool) $item;
        }

        if (is_email($item)) {
            return sanitize_email($item);
        }

        // Default to text field sanitization for strings and other types
        return sanitize_text_field(wp_unslash($item));
    }
}