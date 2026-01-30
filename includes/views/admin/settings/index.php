<?php if (!defined('ABSPATH'))
    exit; // Exit if accessed directly 
?>

<div class="wrap">
    <!-- Page Title  -->
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <button
        class="button button-primary nhrotm-add-option-button"><?php esc_html_e('Add New Option', 'nhrrob-options-table-manager'); ?></button>

    <!-- Table  -->
    <div class="nhrotm-data-table-wrap">
        <div class="tab mt-5 mb-3">
            <button class="tablinks active options-table" data-tab="nhrotm-options-tab">
                <?php esc_html_e('Options Table', 'nhrrob-options-table-manager'); ?>
            </button>
            <button class="tablinks usermeta-table" data-tab="nhrotm-usermeta-tab">
                <?php esc_html_e('Usermeta Table', 'nhrrob-options-table-manager'); ?>
            </button>

            <?php if ($is_better_payment_installed): ?>
                <button class="tablinks better_payment-table" data-tab="nhrotm-better-payment-tab">
                    <?php esc_html_e('Better Payment Table', 'nhrrob-options-table-manager'); ?>
                </button>
            <?php endif; ?>

            <?php if ($is_wp_recipe_maker_installed): ?>
                <button class="tablinks wprm_ratings-table" data-tab="nhrotm-wprm-ratings-tab">
                    <?php esc_html_e('WPRM Ratings Table', 'nhrrob-options-table-manager'); ?>
                </button>
                <button class="tablinks wprm_analytics-table" data-tab="nhrotm-wprm-analytics-tab">
                    <?php esc_html_e('WPRM Analytics Table', 'nhrrob-options-table-manager'); ?>
                </button>
                <button class="tablinks wprm_changelog-table" data-tab="nhrotm-wprm-changelog-tab">
                    <?php esc_html_e('WPRM Changelog Table', 'nhrrob-options-table-manager'); ?>
                </button>
            <?php endif; ?>

            <button class="tablinks optimization-tab" data-tab="nhrotm-autoload-optimizer-tab">
                <?php esc_html_e('Autoload Optimizer', 'nhrrob-options-table-manager'); ?>
            </button>

            <button class="tablinks settings-tab" data-tab="nhrotm-settings-tab">
                <?php esc_html_e('Settings', 'nhrrob-options-table-manager'); ?>
            </button>
            <button class="tablinks scanner-tab" data-tab="nhrotm-scanner-tab">
                <?php esc_html_e('Orphan Scanner', 'nhrrob-options-table-manager'); ?>
            </button>
            <button class="tablinks search-replace-tab" data-tab="nhrotm-search-replace-tab">
                <?php esc_html_e('Search & Replace', 'nhrrob-options-table-manager'); ?>
            </button>
            <button class="tablinks import-export-tab" data-tab="nhrotm-import-export-tab">
                <?php esc_html_e('Import / Export', 'nhrrob-options-table-manager'); ?>
            </button>
        </div>

        <!-- Filter starts -->
        <div class="nhrotm-filter-container">
            <div class="nhrotm-filter-row">
                <div class="nhrotm-filter-group nhrotm-bulk-action-group">
                    <select id="nhrotm-bulk-action-selector">
                        <option value="-1"><?php esc_html_e('Bulk Actions', 'nhrrob-options-table-manager'); ?></option>
                        <option value="delete"><?php esc_html_e('Delete', 'nhrrob-options-table-manager'); ?></option>
                    </select>
                    <button id="nhrotm-do-bulk-action"
                        class="button action"><?php esc_html_e('Apply', 'nhrrob-options-table-manager'); ?></button>
                </div>

                <div class="nhrotm-filter-group">
                    <select id="option-type-filter">
                        <option value="all-options"><?php esc_html_e('All Options', 'nhrrob-options-table-manager'); ?>
                        </option>
                        <option value="all-transients">
                            <?php esc_html_e('All Transients', 'nhrrob-options-table-manager'); ?>
                        </option>
                    </select>
                </div>
                <div class="nhrotm-filter-group">
                    <button id="delete-expired-transients" class="button button-danger"
                        disabled><?php esc_html_e('Delete Expired Transients', 'nhrrob-options-table-manager'); ?></button>
                </div>
            </div>
        </div>
        <!-- Filter ends  -->

        <!-- Tabs Content Start -->
        <div id="nhrotm-options-tab" class="nhrotm-tab-content">
            <table id="nhrotm-data-table" class="nhrotm-data-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="nhrotm-select-all"></td>
                        <th><?php esc_html_e('Option ID', 'nhrrob-options-table-manager'); ?></th>
                        <th><?php esc_html_e('Option Name', 'nhrrob-options-table-manager'); ?></th>
                        <th><?php esc_html_e('Option Value', 'nhrrob-options-table-manager'); ?></th>
                        <th><?php esc_html_e('Autoload', 'nhrrob-options-table-manager'); ?></th>
                        <th><?php esc_html_e('Action', 'nhrrob-options-table-manager'); ?></th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" id="nhrotm-select-all-footer"></td>
                        <th><?php esc_html_e('Option ID', 'nhrrob-options-table-manager'); ?></th>
                        <th><?php esc_html_e('Option Name', 'nhrrob-options-table-manager'); ?></th>
                        <th><?php esc_html_e('Option Value', 'nhrrob-options-table-manager'); ?></th>
                        <th><?php esc_html_e('Autoload', 'nhrrob-options-table-manager'); ?></th>
                        <th><?php esc_html_e('Action', 'nhrrob-options-table-manager'); ?></th>
                    </tr>
                </tfoot>
                <tbody></tbody>
            </table>

            <div id="nhrotm-options-analytics-section" class="mt-5">
                <h2 id="nhrotm-options-analytics-title" class="text-center mt-5 pt-5">Options Table Analytics</h2>
                <div id="nhrotm-usage-analytics-results" class="nhrotm-usage-analytics-results m-auto">
                    <!-- Data will be loaded here -->
                </div>
            </div>
        </div>

        <div id="nhrotm-usermeta-tab" class="nhrotm-tab-content" style="display:none;">
            <table id="nhrotm-data-table-usermeta" class="nhrotm-data-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('User Meta ID', 'nhrrob-options-table-manager'); ?></th>
                        <th><?php esc_html_e('User ID', 'nhrrob-options-table-manager'); ?></th>
                        <th><?php esc_html_e('Meta Key', 'nhrrob-options-table-manager'); ?></th>
                        <th><?php esc_html_e('Meta Value', 'nhrrob-options-table-manager'); ?></th>
                        <th><?php esc_html_e('Action', 'nhrrob-options-table-manager'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <?php if ($is_better_payment_installed): ?>
            <div id="nhrotm-better-payment-tab" class="nhrotm-tab-content" style="display:none;">
                <table id="nhrotm-data-table-better_payment" class="nhrotm-data-table wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Transaction ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Email', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Amount', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Form Fields', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Source', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Status', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Date', 'nhrrob-options-table-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($is_wp_recipe_maker_installed): ?>
            <div id="nhrotm-wprm-ratings-tab" class="nhrotm-tab-content" style="display:none;">
                <table id="nhrotm-data-table-wprm_ratings" class="nhrotm-data-table wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Date', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Recipe ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Post ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Comment ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Approved', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Has Comment', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('User ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('IP', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Rating', 'nhrrob-options-table-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div id="nhrotm-wprm-analytics-tab" class="nhrotm-tab-content" style="display:none;">
                <table id="nhrotm-data-table-wprm_analytics" class="nhrotm-data-table wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Type', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Meta', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Post ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Recipe ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('User ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Visitor ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Visitor', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Created At', 'nhrrob-options-table-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div id="nhrotm-wprm-changelog-tab" class="nhrotm-tab-content" style="display:none;">
                <table id="nhrotm-data-table-wprm_changelog" class="nhrotm-data-table wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Type', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Meta', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Object ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Object Meta', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('User ID', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('User Meta', 'nhrrob-options-table-manager'); ?></th>
                            <th><?php esc_html_e('Created At', 'nhrrob-options-table-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        <?php endif; ?>
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
                    <option value="true">True</option>
                    <option value="false">False</option>
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
                    <option value="true">True</option>
                    <option value="false">False</option>
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

<!-- Edit Modal Usermeta -->
<div class="nhrotm-edit-usermeta-modal is-hidden">
    <div class="nhrotm-modal-content">
        <h2><?php esc_html_e('Edit User Meta', 'nhrrob-options-table-manager'); ?></h2>

        <p>
            <label>
                <?php esc_html_e('Meta Key:', 'nhrrob-options-table-manager'); ?>
                <input type="text" class="nhrotm-edit-usermeta-key" readonly>
            </label>
        </p>

        <p>
            <label>
                <?php esc_html_e('Meta Value:', 'nhrrob-options-table-manager'); ?>
                <textarea class="nhrotm-edit-usermeta-value"></textarea>
            </label>
        </p>

        <button class="button button-primary nhrotm-update-usermeta">Update</button>
    </div>
</div>

<!-- History Modal -->
<div class="nhrotm-history-modal is-hidden">
    <div class="nhrotm-modal-content" style="max-width: 800px; width: 90%;">
        <h2><?php esc_html_e('Option History', 'nhrrob-options-table-manager'); ?>: <span
                class="nhrotm-history-option-name"></span></h2>

        <div class="nhrotm-history-loading" style="display:none; text-align: center; padding: 20px;">
            Loading...
        </div>

        <div class="nhrotm-history-list-container" style="max-height: 500px; overflow-y: auto;">
            <table class="wp-list-table widefat fixed striped" style="width:100%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Value (Preview)</th>
                        <th>By User ID</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody class="nhrotm-history-table-body">
                    <!-- History rows will be inserted here -->
                </tbody>
            </table>
        </div>

        <button class="button nhrotm-close-history-modal" style="margin-top: 15px;">Close</button>
    </div>
</div>

    <div id="nhrotm-autoload-optimizer-tab" class="nhrotm-tab-content" style="display:none;">
        <div class="card" style="max-width: 100%; margin-top: 20px; padding: 20px;">
            <h2>Autoload Health Check</h2>
            <div class="nhrotm-autoload-stats">
                <p>Total Autoload Size: <strong id="nhrotm-total-autoload-size">Loading...</strong></p>
                <p><em>Recommended limit is usually < 1MB. High autoload size slows down every page load on your site.</em>
                </p>
            </div>

            <h3>Heaviest Autoloaded Options</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Option Name</th>
                        <th>Size</th>
                        <th>Value Snippet</th>
                        <th>Autoload</th>
                    </tr>
                </thead>
                <tbody id="nhrotm-autoload-list-body">
                    <!-- Rows -->
                </tbody>
            </table>
        </div>
    </div>

    <div id="nhrotm-scanner-tab" class="nhrotm-tab-content d-none">
        <div class="card nhrotm-tab-card">
            <h2>Orphaned Options Scanner</h2>
            <p class="description">Scan your database for options left behind by uninstalled or inactive plugins. Identifying these helps reduce database bloat.</p>
            
            <div class="nhrotm-scanner-actions mb-4">
                <button id="nhrotm-start-scan" class="button button-primary">Start Deep Scan</button>
            </div>

            <div class="nhrotm-scanner-loading loader-container d-none">
                <span class="spinner is-active nhrotm-spinner-centered"></span>
                <p>Analyzing database prefixes and cross-referencing with plugin directories...</p>
            </div>

            <div id="nhrotm-scanner-results" class="d-none">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Prefix Candidate</th>
                            <th>Option Count</th>
                            <th>Likely Source</th>
                            <th>Risk Level</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="nhrotm-scanner-list-body">
                        <!-- Scan results -->
                    </tbody>
                </table>
            </div>

            <div id="nhrotm-scanner-empty" class="loader-container d-none">
                <p>No significant orphaned options detected. Your database looks clean!</p>
            </div>
        </div>
    </div>

    <div id="nhrotm-search-replace-tab" class="nhrotm-tab-content d-none">
        <div class="card nhrotm-tab-card">
            <h2>Global Search & Replace</h2>
            <p class="description">Find and replace strings across your entire options table. Supports serialized data and JSON safely.</p>
            
            <div class="nhrotm-search-replace-form mb-4 max-w-600">
                <div class="nhrotm-form-field mb-3">
                    <label class="font-semibold d-block mb-1">Search for:</label>
                    <input type="text" id="nhrotm-search-string" class="regular-text" placeholder="e.g. old-domain.com">
                </div>
                <div class="nhrotm-form-field mb-3">
                    <label class="font-semibold d-block mb-1">Replace with:</label>
                    <input type="text" id="nhrotm-replace-string" class="regular-text" placeholder="e.g. new-domain.com">
                </div>
                <div class="nhrotm-form-field mb-4">
                    <label class="nhrotm-switch-label d-flex items-center gap-10 pointer">
                        <input type="checkbox" id="nhrotm-dry-run-toggle" checked>
                        <span>Dry Run (Preview changes without saving)</span>
                    </label>
                </div>
                
                <div class="nhrotm-form-actions">
                    <button id="nhrotm-search-replace-btn" class="button button-primary">Execute Replacement</button>
                </div>
            </div>

            <div class="nhrotm-search-replace-loading loader-container d-none">
                <span class="spinner is-active nhrotm-spinner-centered"></span>
                <p>Processing database records... This may take a moment.</p>
            </div>

            <div id="nhrotm-search-replace-results" class="mt-5 d-none">
                <div class="nhrotm-sr-summary nhrotm-summary-box">
                    <p id="nhrotm-sr-summary-text" class="m-0 font-semibold"></p>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Option Name</th>
                            <th>Occurrences Found</th>
                        </tr>
                    </thead>
                    <tbody id="nhrotm-sr-list-body">
                        <!-- Results -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="nhrotm-import-export-tab" class="nhrotm-tab-content d-none">
        <div class="card nhrotm-tab-card">
            <h2>Import / Export Options</h2>
            <p class="description">Selectively export options or import a configuration file from another site.</p>

            <div class="nhrotm-ie-columns d-flex gap-20" style="gap: 40px; margin-top: 30px;">
                <!-- Export Section -->
                <div class="nhrotm-ie-column" style="flex: 1;">
                    <h3>Export Options</h3>
                    <p>Search and select options to add to your export basket.</p>
                    
                    <div class="nhrotm-form-field mb-3">
                        <input type="text" id="nhrotm-export-search" class="regular-text" placeholder="Search option names..." style="width: 100%;">
                        <div id="nhrotm-export-suggestions" class="nhrotm-suggestions-box d-none"></div>
                    </div>

                    <div class="nhrotm-export-basket">
                        <h4>Selected Options (<span id="nhrotm-basket-count">0</span>)</h4>
                        <ul id="nhrotm-basket-list">
                            <li class="empty-basket">No options selected.</li>
                        </ul>
                    </div>

                    <button id="nhrotm-do-export" class="button button-primary mt-3" disabled>Export to JSON</button>
                </div>

                <!-- Import Section -->
                <div class="nhrotm-ie-column" style="flex: 1; border-left: 1px solid #ddd; padding-left: 40px;">
                    <h3>Import Options</h3>
                    <p>Upload a previously exported JSON file.</p>

                    <div class="nhrotm-form-field mb-3">
                        <input type="file" id="nhrotm-import-file" accept=".json">
                    </div>

                    <div id="nhrotm-import-preview-area" class="d-none">
                        <h4>Import Preview</h4>
                        <div class="nhrotm-import-stats mb-2">
                             Found <span id="nhrotm-import-total">0</span> options.
                        </div>
                        
                        <div class="nhrotm-scrollable-table" style="max-height: 300px; overflow-y: auto;">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <td class="check-column"><input type="checkbox" id="nhrotm-import-select-all" checked></td>
                                        <th>Option Name</th>
                                        <th>Status</th>
                                        <th>Current Val (Snippet)</th>
                                    </tr>
                                </thead>
                                <tbody id="nhrotm-import-preview-body"></tbody>
                            </table>
                        </div>

                        <button id="nhrotm-execute-import" class="button button-primary mt-3">Execute Import</button>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <div id="nhrotm-settings-tab" class="nhrotm-tab-content d-none">
        <div class="card nhrotm-tab-card">
            <h2>Settings</h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label
                                for="nhrotm_auto_cleanup_toggle"><?php esc_html_e('Automated Daily Cleanup', 'nhrrob-options-table-manager'); ?></label>
                        </th>
                        <td>
                            <label class="nhrotm-switch">
                                <input type="checkbox" id="nhrotm_auto_cleanup_toggle">
                                <span class="nhrotm-slider nhrotm-round"></span>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Automatically delete expired transients once daily using WP Cron.', 'nhrrob-options-table-manager'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>