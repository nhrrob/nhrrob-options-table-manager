<div class="wrap nhrrob-options-table-manager">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <table class="form-table" role="presentation">
        <thead>
            <th>Name</th>
            <th>Value</th>
        </thead>
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
                <th scope="row"><label for="<?php echo $name; ?>"><?php echo esc_html($option->option_name); ?></label></th>
                <td>
                    <?php if (str_contains($value, "\n")) : ?>
                        <p class="<?php echo $class; ?>" id="<?php echo $name; ?>"><?php echo esc_textarea($value); ?></p>
                    <?php else : ?>
                        <p class="regular-text <?php echo $class; ?>" id="<?php echo $name; ?>" > <?php echo esc_attr($value); ?> </p>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <tfoot>
            <th>Name</th>
            <th>Value</th>
        </tfoot>
    </table>
</div>