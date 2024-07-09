<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly 
?>

<div class="wrap nhrotm-options-table-manager container mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h3 class="text-2xl mb-4"><?php echo esc_html(get_admin_page_title()); ?></h3>

    <!-- Button to Show/Hide Form -->
    <button class="bg-blue-500 text-white px-4 py-2 rounded-md shadow-sm hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mb-4 nhrotm-add-option-form-toggle-button">
        <?php esc_html_e('Add New Option', 'nhrrob-options-table-manager'); ?>
    </button>

    <!-- New Option Form -->
    <div class="mb-8 bg-white shadow-md rounded-lg p-6 max-w-lg mx-auto nhrotm-add-option-form-wrap hidden">
        <h3 class="text-xl font-semibold mb-4"><?php esc_html_e('Add New Option', 'nhrrob-options-table-manager'); ?></h3>
        <form id="nhrotm-add-option-form" method="POST" action="#">
            <div class="mb-4">
                <label for="new_option_name" class="block text-sm font-medium text-gray-700"><?php esc_html_e('Option Name', 'nhrrob-options-table-manager'); ?></label>
                <input type="text" id="new_option_name" name="new_option_name" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
            </div>
            <div class="mb-4">
                <label for="new_option_value" class="block text-sm font-medium text-gray-700"><?php esc_html_e('Option Value', 'nhrrob-options-table-manager'); ?></label>
                <input type="text" id="new_option_value" name="new_option_value" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
            </div>
            <div class="mb-4">
                <label for="new_option_autoload" class="block text-sm font-medium text-gray-700"><?php esc_html_e('Autoload', 'nhrrob-options-table-manager'); ?></label>
                <select id="new_option_autoload" name="new_option_autoload" required class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
                    <option value="yes"><?php esc_html_e('Yes', 'nhrrob-options-table-manager'); ?></option>
                    <option value="no" selected><?php esc_html_e('No', 'nhrrob-options-table-manager'); ?></option>
                </select>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-md shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <?php esc_html_e('Add Option', 'nhrrob-options-table-manager'); ?>
                </button>
            </div>
        </form>
    </div>

    <table class="nhrotm-form-table form-table min-w-full divide-y divide-gray-200 overflow-x-auto">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e('Name', 'nhrrob-options-table-manager'); ?></th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e('Value', 'nhrrob-options-table-manager'); ?></th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e('Autoload', 'nhrrob-options-table-manager'); ?></th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e('Actions', 'nhrrob-options-table-manager'); ?></th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
            foreach ((array) $options as $option_name => $option_value) :
                $disabled = false;
                if ('' === $option_name) {
                    continue;
                }

                $is_protected = in_array($option_name, $protected_options);

                if (is_serialized($option_value)) {
                    if (is_serialized_string($option_value)) {
                        // This is a serialized string, so we should display it.
                        $value               = maybe_unserialize($option_value);
                        $options_to_update[] = $option_name;
                        $class               = 'all-options';
                    } else {
                        $value    = 'SERIALIZED DATA';
                        $disabled = true;
                        $class    = 'all-options disabled';

                        // Attempt to unserialize the data
                        // $unserialized_value = maybe_unserialize($option_value);

                        // // Check if unserialization was successful
                        // if ($unserialized_value !== false) {
                        //     // If successful, use the unserialized data
                        //     $value = print_r($unserialized_value, true); // You can use print_r for debugging purposes
                        // } else {
                        //     // If unsuccessful, fallback to showing it's serialized
                        //     $value = 'Serialized Data: ' . esc_html($option_value);
                        // }
                    }
                } else {
                    $value               = $option_value;
                    $options_to_update[] = $option_name;
                    $class               = 'all-options';
                }

                $name = esc_attr($option_name);

                // Truncate value if it's too long
                $max_length = 200; // Adjust the max length as needed
                $class .= (strlen($value) > $max_length) ? ' nhrotm-scroll-y' : '';
            ?>
                <tr>
                    <td class="px-6 py-4 break-words text-sm text-gray-500 nhrotm-option-name"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($option_name); ?></label></td>
                    <td class="px-6 py-4 break-words text-sm text-gray-500 nhrotm-option-value">
                        <?php if (str_contains($value, "\n")) : ?>
                            <p class="<?php echo esc_attr($class); ?>" id="<?php echo esc_attr($name); ?>"><?php echo esc_textarea($value); ?></p>
                        <?php else : ?>
                            <p class="regular-text <?php echo esc_attr($class); ?>" id="<?php echo esc_attr($name); ?>"> <?php echo esc_attr($value); ?> </p>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 break-words text-sm text-gray-500"> Yes <?php //echo esc_html($option->autoload); 
                                                                                    ?> </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?php if (!$is_protected) : ?>
                            <button class="nhrotm-edit-option-button bg-blue-500 text-white px-4 py-2 rounded <?php echo $value === 'SERIALIZED DATA' ? esc_attr('invisible') : esc_attr('') ?>"><?php esc_html_e('Edit', 'nhrrob-options-table-manager'); ?></button>
                            <button class="nhrotm-delete-option-button bg-red-500 text-white px-4 py-2 rounded"><?php esc_html_e('Delete', 'nhrrob-options-table-manager'); ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>

        <tfoot>
            <tr>
                <th class="px-4 py-2"><?php esc_html_e('Name', 'nhrrob-options-table-manager'); ?></th>
                <th class="px-4 py-2"><?php esc_html_e('Value', 'nhrrob-options-table-manager'); ?></th>
                <th class="px-4 py-2"><?php esc_html_e('Autoload', 'nhrrob-options-table-manager'); ?></th>
                <th class="px-4 py-2"><?php esc_html_e('Actions', 'nhrrob-options-table-manager'); ?></th>
            </tr>
        </tfoot>
    </table>

    <?php
    // Count occurrences of each prefix
    $prefix_counts = array();
    foreach ($options as $option_name => $option_value) {
        $prefix = strtok($option_name, '_');
        if (!isset($prefix_counts[$prefix])) {
            $prefix_counts[$prefix] = 1;
        } else {
            $prefix_counts[$prefix]++;
        }
    }

    // Sort prefixes by count in descending order
    arsort($prefix_counts);

    // Display results
    ?>
    <div class="prefix-count-container">
        <h3 class="text-2xl mb-4"><?php esc_html_e('Prefix Count', 'nhrrob-options-table-manager'); ?></h3>
        <ul class="prefix-count-list">
            <?php foreach ($prefix_counts as $prefix => $count) : ?>
                <?php if ($count > 5) : ?>
                    <li class="prefix-count-item">
                        <span class="prefix"><?php echo esc_html($prefix); ?></span>
                        <span class="count"><?php echo esc_html($count); ?></span>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
</div>