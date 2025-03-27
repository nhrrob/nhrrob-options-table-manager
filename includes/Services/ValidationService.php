<?php
namespace Nhrotm\OptionsTableManager\Services;

class ValidationService {

    /**
     * Recursively sanitize an array while preserving structure
     */
    public function sanitize_array_recursive($data) {
        // If it's an object, convert to array first
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (!is_array($data)) {
            if (is_bool($data)) {
                return (bool)$data;
            } else if (is_numeric($data)) {
                return $data + 0; // Convert to proper number type
            } else if (is_string($data)) {
                return sanitize_text_field($data);
            } else {
                // For other types, convert to string and sanitize
                return sanitize_text_field((string)$data);
            }
        }    
        
        $content_keys = ['content'];
        
        $sanitized = array();
        foreach ($data as $key => $value) {
            // Sanitize the key
            $clean_key = sanitize_text_field($key);
            
            if (is_array($value)  || is_object($value)) {
                $sanitized[$clean_key] = $this->sanitize_array_recursive($value);
            }  else if (is_string($value) && in_array($clean_key, $content_keys)) {
                // Use wp_kses_post for HTML content fields
                $sanitized[$clean_key] = wp_kses_post($value);
            } else {
                // Handle different value types appropriately
                if (is_bool($value)) {
                    $sanitized[$clean_key] = (bool)$value;
                } else if (is_numeric($value)) {
                    $sanitized[$clean_key] = $value + 0; // Convert to proper number type
                } else if (is_string($value)) {
                    $sanitized[$clean_key] = sanitize_text_field($value);
                } else {
                    // For other types, convert to string and sanitize
                    $sanitized[$clean_key] = sanitize_text_field((string)$value);
                }
            }
        }
        
        return $sanitized;
    }

    // Helper function to recursively sanitize arrays and objects (Added during options column filtering feature.)
    // #ToDo Need to keep one. above or this function.
    public function sanitize_recursive($data) {
        if (is_array($data)) {
            $sanitized_array = [];
            foreach ($data as $key => $value) {
                $sanitized_key = sanitize_key($key);
                if ($sanitized_key !== '') {
                    $sanitized_array[$sanitized_key] = $this->sanitize_recursive($value);
                }
            }
            return $sanitized_array;
        } elseif (is_object($data)) {
            $sanitized_object = new \stdClass();
            $object_vars = get_object_vars($data);
            
            foreach ($object_vars as $key => $value) {
                $sanitized_key = sanitize_key($key);
                if ($sanitized_key !== '') {
                    $sanitized_object->$sanitized_key = $this->sanitize_recursive($value);
                }
            }
            return $sanitized_object;
        } else {
            return $this->sanitize_item($data);
        }
    }

    public function sanitize_item( $item ){
        $item_formatted = '';

        if ( is_numeric( $item )) {
            $item_formatted = intval( $item );
        } elseif ( is_email( $item )) {
            $item_formatted = sanitize_email( $item );
        } else {
            $item_formatted = sanitize_text_field( wp_unslash( $item ) );
        }

        return $item_formatted;
    }

    /**
     * Recursively sanitize input value
     * 
     * @param mixed $value Input value
     * @return mixed Sanitized value
     */
    // public static function sanitizeValue($value) {
    //     if (is_array($value)) {
    //         return array_map([self::class, 'sanitizeValue'], $value);
    //     }

    //     if (is_object($value)) {
    //         $value = (array)$value;
    //         return (object)self::sanitizeValue($value);
    //     }

    //     if (is_bool($value)) {
    //         return $value;
    //     }

    //     if (is_numeric($value)) {
    //         return $value + 0;
    //     }

    //     return sanitize_text_field($value);
    // }

    // /**
    //  * Validate and sanitize input against specific rules
    //  * 
    //  * @param mixed $input Input to validate
    //  * @param array $rules Validation rules
    //  * @return mixed Validated and sanitized input
    //  * @throws \Exception If validation fails
    //  */
    // public static function validateInput($input, $rules = []) {
    //     $validatedInput = [];

    //     foreach ($rules as $field => $fieldRules) {
    //         $value = $input[$field] ?? null;

    //         // Required check
    //         if (in_array('required', $fieldRules) && empty($value)) {
    //             throw new \Exception("Field {$field} is required");
    //         }

    //         // Type check
    //         if (isset($fieldRules['type'])) {
    //             switch ($fieldRules['type']) {
    //                 case 'int':
    //                     $value = intval($value);
    //                     break;
    //                 case 'float':
    //                     $value = floatval($value);
    //                     break;
    //                 case 'bool':
    //                     $value = boolval($value);
    //                     break;
    //             }
    //         }

    //         // Sanitize
    //         $validatedInput[$field] = self::sanitizeValue($value);
    //     }

    //     return $validatedInput;
    // }
}