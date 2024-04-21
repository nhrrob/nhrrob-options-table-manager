<div class="wrap nhrrob-options-table-manager container mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h3 class="text-2xl mb-4"><?php echo esc_html(get_admin_page_title()); ?></h3>

    <table class="form-table min-w-full divide-y divide-gray-200 overflow-x-auto">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e('Name', 'nhrrob-options-table-manager'); ?></th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e('Value', 'nhrrob-options-table-manager'); ?></th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e('Autoload', 'nhrrob-options-table-manager'); ?></th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
        <?php
        foreach ((array) $options as $option) :
            $disabled = false;

            if ('' === $option->option_name) {
                continue;
            }

            if (is_serialized($option->option_value)) {
                if (is_serialized_string($option->option_value)) {
                    // This is a serialized string, so we should display it.
                    $value               = maybe_unserialize($option->option_value);
                    $options_to_update[] = $option->option_name;
                    $class               = 'all-options';
                } else {
                    $value    = 'SERIALIZED DATA';
                    $disabled = true;
                    $class    = 'all-options disabled';
                }
            } else {
                $value               = $option->option_value;
                $options_to_update[] = $option->option_name;
                $class               = 'all-options';
            }

            $name = esc_attr($option->option_name);
        ?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap nowrap text-sm text-gray-500"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html($option->option_name); ?></label></td>
                <td class="px-6 py-4 whitespace-nowrap nowrap text-sm text-gray-500">
                    <?php if (str_contains($value, "\n")) : ?>
                        <p class="<?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $name ); ?>"><?php echo esc_textarea($value); ?></p>
                    <?php else : ?>
                        <p class="regular-text <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $name ); ?>"> <?php echo esc_attr($value); ?> </p>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap nowrap text-sm text-gray-500"> <?php echo esc_html($option->autoload); ?> </td>
            </tr>
        <?php endforeach; ?>
        </tbody>

        <tfoot>
            <tr>
                <th class="px-4 py-2"><?php esc_html_e('Name', 'nhrrob-options-table-manager'); ?></th>
                <th class="px-4 py-2"><?php esc_html_e('Value', 'nhrrob-options-table-manager'); ?></th>
                <th class="px-4 py-2"><?php esc_html_e('Autoload', 'nhrrob-options-table-manager'); ?></th>
            </tr>
        </tfoot>
    </table>

    <?php
    // Count occurrences of each prefix
    $prefix_counts = array();
    foreach ($options as $option) {
        $prefix = strtok($option->option_name, '_');
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
    <h3 class="text-2xl mb-4"><?php esc_html_e( 'Prefix Count', 'nhrrob-options-table-manager'); ?></h3> 
    <?php
    echo '<ul>';
    foreach ($prefix_counts as $prefix => $count) {
        if( $count > 5 ){
            echo '<li>' . $prefix . ': ' . $count . '</li>';
        }
    }
    echo '</ul>';
    
    ?>
</div>