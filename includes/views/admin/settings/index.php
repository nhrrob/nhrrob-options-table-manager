<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly 
?>

<div class="wrap">
    <!-- Page Title  -->
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <button class="button button-primary nhrotm-add-option-button"><?php esc_html_e('Add New Option', 'nhrrob-options-table-manager'); ?></button>

    <!-- Table  -->
    <table id="nhrotm-data-table" class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Option ID', 'nhrrob-options-table-manager'); ?></th>
                <th><?php esc_html_e('Option Name', 'nhrrob-options-table-manager'); ?></th>
                <th><?php esc_html_e('Option Value', 'nhrrob-options-table-manager'); ?></th>
                <th><?php esc_html_e('Autoload', 'nhrrob-options-table-manager'); ?></th>
                <th><?php esc_html_e('Action', 'nhrrob-options-table-manager'); ?></th>
            </tr>
        </thead>

        <tbody>

        </tbody>
    </table>
</div>

<!-- Modal  -->
<div class="nhrotm-add-option-modal is-hidden">
    <h2><?php esc_html_e('Add Option', 'nhrrob-options-table-manager'); ?></h2>

    <p>
        <label>
            <?php esc_html_e('Option Name:', 'nhrrob-options-table-manager'); ?>
            <input type="text" class="nhrotm-new-option-name">
        </label>
    </p>

    <p>
        <label>
            <?php esc_html_e('Option Value:', 'nhrrob-options-table-manager'); ?>
            <textarea class="nhrotm-new-option-value"></textarea>
        </label>
    </p>

    <p>
        <label>
            <?php esc_html_e('Autoload:', 'nhrrob-options-table-manager'); ?>
            <select class="nhrotm-new-option-autoload">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </label>
    </p>

    <button class="button button-primary nhrotm-save-option">Save</button>
</div>

<!-- Edit Modal  -->
<div class="nhrotm-edit-option-modal is-hidden">
    <h2><?php esc_html_e('Edit Option', 'nhrrob-options-table-manager'); ?></h2>

    <p>
        <label>
            <?php esc_html_e('Option Name:', 'nhrrob-options-table-manager'); ?> 
            <input type="text" class="nhrotm-edit-option-name" readonly>
        </label>
    </p>
    
    <p>
        <label>
            <?php esc_html_e('Option Value:', 'nhrrob-options-table-manager'); ?> 
            <textarea class="nhrotm-edit-option-value"></textarea>
        </label>
    </p>
    
    <p>
        <label><?php esc_html_e('Autoload:', 'nhrrob-options-table-manager'); ?>
            <select class="nhrotm-edit-option-autoload">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </label> 
    </p>

    <button class="button button-primary nhrotm-update-option">Update</button>
</div>