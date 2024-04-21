<div class="wrap nhrrob-options-table-manager container mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h2 class="text-2xl font-bold mb-4"><?php echo esc_html(get_admin_page_title()); ?></h2>

    <table class="form-table table-auto w-full px-4">
        <thead>
            <tr>
                <th class="px-4 py-2">Name</th>
                <th class="px-4 py-2">Value</th>
                <th class="px-4 py-2">Autoload</th>
            </tr>
        </thead>
        <tbody>
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
                <td class="border px-4 py-2"><label for="<?php echo $name; ?>"><?php echo esc_html($option->option_name); ?></label></td>
                <td class="border px-4 py-2">
                    <?php if (str_contains($value, "\n")) : ?>
                        <p class="<?php echo $class; ?>" id="<?php echo $name; ?>"><?php echo esc_textarea($value); ?></p>
                    <?php else : ?>
                        <p class="regular-text <?php echo $class; ?>" id="<?php echo $name; ?>"> <?php echo esc_attr($value); ?> </p>
                    <?php endif; ?>
                </td>
                <td class="border px-4 py-2"> <?php echo esc_html($option->autoload); ?> </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
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
    echo '<h3>Most Used Prefixes</h3>';
    echo '<ul>';
    foreach ($prefix_counts as $prefix => $count) {
        if( $count > 5 ){
            echo '<li>' . $prefix . ': ' . $count . '</li>';
        }
    }
    echo '</ul>';
    
    ?>
</div>