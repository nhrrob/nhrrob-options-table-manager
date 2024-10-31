<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly 
?>

<div class="wrap">
    <!-- Page Title  -->
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <button class="button button-primary nhrotm-add-option-button"><?php esc_html_e('Add New Option', 'nhrrob-options-table-manager'); ?></button>

    <!-- Table  -->
    <div class="nhrotm-data-table-wrap">
        <div class="tab mt-5 mb-3">
            <button class="tablinks active"><?php esc_html_e('Options Table', 'nhrrob-options-table-manager'); ?></button>
            <button class="tablinks"><?php esc_html_e('Usermeta Table', 'nhrrob-options-table-manager'); ?></button>
        </div>

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

    <div>
        <h2 class="text-center mt-5 pt-5">Options Table Analytics</h2>

        <div id="nhrotm-usage-analytics-results" class="nhrotm-usage-analytics-results m-auto">
            <!-- Data will be loaded here -->
        </div>
    </div>
</div>

<!-- Modal  -->
<div class="nhrotm-add-option-modal is-hidden">
    <div class="nhrotm-modal-content">
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
</div>

<!-- Edit Modal  -->
<div class="nhrotm-edit-option-modal is-hidden">
    <div class="nhrotm-modal-content">
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
                    <option value="on">On</option>
                    <option value="off">Off</option>
                </select>
            </label>
        </p>

        <button class="button button-primary nhrotm-update-option">Update</button>
    </div>
</div>