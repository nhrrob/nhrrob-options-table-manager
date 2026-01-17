(function ($) {
    $(document).ready(function () {
        "use strict";

        let protectedOptions = nhrotmOptionsTableManager.protected_options;
        let protectedUsermetas = nhrotmOptionsTableManager.protected_usermetas;
        let isBetterPaymentInstalled = nhrotmOptionsTableManager.is_better_payment_installed;
        let isWpRecipeMakerInstalled = nhrotmOptionsTableManager.is_wp_recipe_maker_installed;

        // Initial visibility setup
        $('.nhrotm-tab-content').hide();
        $('#nhrotm-options-tab').show(); // Show default tab

        function isProtected(optionName) {
            return protectedOptions.includes(optionName);
        }

        function isProtectedMeta(metaKey) {
            return protectedUsermetas.includes(metaKey);
        }

        // Toast notification system
        function showToast(message, type = 'success') {
            // Remove any existing toasts
            $('.nhrotm-toast').remove();

            // Create toast element
            const toast = $('<div class="nhrotm-toast ' + type + '">' + message + '</div>');
            $('body').append(toast);

            // Show and then hide after 3 seconds
            toast.fadeIn(300).delay(3000).fadeOut(300, function () {
                $(this).remove();
            });
        }



        // Datatable display
        var table = $('#nhrotm-data-table').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "type": "GET",
                "url": nhrotmOptionsTableManager.ajaxUrl + "?action=nhrotm_option_table_data&nonce=" + nhrotmOptionsTableManager.nonce,
                "data": function (d) {
                    // Add column search values to the request
                    // Remap inputs because Col 0 (checkbox) and Col 5 (Actions) have no inputs
                    // Inputs are present for Col 1, 2, 3, 4
                    d.columns[0].search.value = ''; // Checkbox
                    if ($('#nhrotm-data-table tfoot input').length >= 4) {
                        d.columns[1].search.value = $('#nhrotm-data-table tfoot input').eq(0).val();
                        d.columns[2].search.value = $('#nhrotm-data-table tfoot input').eq(1).val();
                        d.columns[3].search.value = $('#nhrotm-data-table tfoot input').eq(2).val();
                        d.columns[4].search.value = $('#nhrotm-data-table tfoot input').eq(3).val();
                        d.columns[5].search.value = '';
                    }

                    // Option type filter
                    $('#delete-expired-transients').attr('disabled', true);
                    d.optionTypeFilter = $('#option-type-filter').val();

                    if (d.optionTypeFilter === 'all-transients') {
                        $('#delete-expired-transients').attr('disabled', false);

                        // option_name is now at index 2 (0=cb, 1=id, 2=name)
                        let currentSearch = d.columns[2].search.value || '';
                        if (!currentSearch.includes('transient_')) {
                            d.columns[2].search.value = 'transient_' + currentSearch;
                        }
                    }
                }
            },
            "columns": [
                {
                    "data": null,
                    "orderable": false,
                    "visible": true,
                    "searchable": false,
                    "render": function (data, type, row) {
                        return '<input type="checkbox" class="nhrotm-checkbox" value="' + row.option_name + '">';
                    }
                },
                { "data": "option_id" },
                { "data": "option_name" },
                { "data": "option_value" },
                { "data": "autoload" },
                { "data": "actions", "orderable": false }
            ],
            "searchDelay": 500, // Delay in milliseconds (0.5 seconds)
            // "scrollY": "400px",     // Fixed height
            // "scrollCollapse": true,
            // "paging": true,
            "order": [[1, 'asc']], // Default order on the first column in ascending,
            "initComplete": function () {
                this.api()
                    .columns()
                    .every(function (index) {
                        // Skip checkbox column (index 0) and Actions column (index 5)
                        if (index === 0 || index === 5) return;

                        let column = this;
                        let title = column.footer().textContent;

                        // Create input element
                        let input = document.createElement('input');
                        input.placeholder = title;
                        column.footer().replaceChildren(input);

                        input.addEventListener('keyup', function () {
                            if (column.search() !== this.value) {
                                column.search(this.value).draw();
                            }
                        });
                    });
            }
        });

        // Add option
        $('.nhrotm-add-option-button').on('click', function () {
            $('.nhrotm-new-option-name').val('');
            $('.nhrotm-new-option-value').val('');
            $('.nhrotm-new-option-autoload').val('yes');
            $('.nhrotm-add-option-modal').show();
        });

        $('.nhrotm-add-option-modal').on('click', function (event) {
            if ($(event.target).is('.nhrotm-add-option-modal')) { // Check if the clicked target is the modal overlay
                $('.nhrotm-add-option-modal').addClass('is-hidden').fadeOut();
            }
        });

        $('.nhrotm-save-option').on('click', function () {
            const optionName = $('.nhrotm-new-option-name').val();
            const optionValue = $('.nhrotm-new-option-value').val();
            const autoload = $('.nhrotm-new-option-autoload').val();

            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: "POST",
                data: {
                    action: "nhrotm_add_option",
                    nonce: nhrotmOptionsTableManager.nonce,
                    new_option_name: optionName,
                    new_option_value: optionValue,
                    new_option_autoload: autoload
                },
                success: function (response) {
                    if (response.success) {
                        showToast("Option added successfully!", "success");
                        $('.nhrotm-add-option-modal').hide();
                        $('#nhrotm-data-table').DataTable().ajax.reload(); // Reload table data
                    } else {
                        showToast('Failed to add option: ' + response.data, "error");
                    }
                }
            });
        });

        // Edit option
        $('#nhrotm-data-table').on('click', '.nhrotm-edit-button', function () {
            const id = $(this).data('id');
            editOption(id, this);
        });

        // Close modal when clicking outside of it
        $('.nhrotm-edit-option-modal').on('click', function (event) {
            if ($(event.target).is('.nhrotm-edit-option-modal')) { // Check if the clicked target is the modal overlay
                $('.nhrotm-edit-option-modal').addClass('is-hidden').fadeOut();
            }
        });

        function editOption(id, $this) {
            const row = $('#nhrotm-data-table').DataTable().row($($this).parents('tr')).data();
            let option_value;

            $('.nhrotm-edit-option-name').val(row.option_name);
            $('.nhrotm-edit-option-value').val('');
            $('.nhrotm-edit-option-autoload').val(row.autoload);

            if (isProtected(row.option_name)) {
                showToast('This option is protected and cannot be edited.', "warning");
                return;
            }

            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: "POST",
                data: {
                    action: "nhrotm_get_option",
                    nonce: nhrotmOptionsTableManager.nonce,
                    option_name: row.option_name,
                },
                success: function (response) {
                    if (response.success) {
                        option_value = response.data.option_value ?? '';

                        if (typeof response.data.option_value === 'object') {
                            option_value = JSON.stringify(option_value, null, 2);
                            $('.nhrotm-edit-option-value').val(option_value);
                        } else {
                            $('.nhrotm-edit-option-value').val(option_value);
                        }

                        $('.nhrotm-edit-option-modal').show();
                    } else {
                        let message = response.data.message ?? 'Error: Failed to find option value';
                        // $('.nhrotm-edit-option-value').val(option_value);
                        showToast(message, "error");
                    }
                }
            });

            $('.nhrotm-update-option').off('click').on('click', function () {
                const optionName = $('.nhrotm-edit-option-name').val();
                const optionValue = $('.nhrotm-edit-option-value').val();
                const autoload = $('.nhrotm-edit-option-autoload').val();

                try {
                    optionValue = JSON.parse(optionValue);
                } catch (e) {
                    // Value remains a string if not valid JSON
                }

                // console.log(optionValue);
                // console.log(JSON.parse(optionValue));
                $.ajax({
                    url: nhrotmOptionsTableManager.ajaxUrl,
                    method: "POST",
                    data: {
                        action: "nhrotm_edit_option",
                        nonce: nhrotmOptionsTableManager.nonce,
                        option_name: optionName,
                        option_value: optionValue,
                        autoload: autoload
                    },
                    success: function (response) {
                        if (response.success) {
                            showToast("Option updated successfully!", "success");
                            $('.nhrotm-edit-option-modal').hide();
                            table.ajax.reload(null, false); // Reload table data
                        } else {
                            showToast('Error: ' + response.data, "error");
                        }
                    }
                });
            });
        }

        // Delete option
        $('#nhrotm-data-table').on('click', '.nhrotm-delete-button', function () {
            deleteOption(this);
        });

        function deleteOption($this) {
            const row = $('#nhrotm-data-table').DataTable().row($($this).parents('tr')).data();
            let optionName = row.option_name;

            if (isProtected(optionName)) {
                showToast('This option is protected and cannot be deleted.', "warning");
                return;
            }

            if (confirm("Are you sure you want to delete this option?")) {
                jQuery.ajax({
                    url: nhrotmOptionsTableManager.ajaxUrl,
                    method: "POST",
                    data: {
                        action: "nhrotm_delete_option",
                        nonce: nhrotmOptionsTableManager.nonce,
                        option_name: optionName
                    },
                    success: function (response) {
                        if (response.success) {
                            showToast("Option deleted successfully!", "success");
                            jQuery('#nhrotm-data-table').DataTable().ajax.reload(); // Reload table data
                        } else {
                            showToast("Failed to delete option.", "error");
                        }
                    }
                });
            }
        }

        // Analytics
        loadAnalyticsData();

        function loadAnalyticsData() {
            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: "GET",
                data: {
                    action: "nhrotm_option_usage_analytics",
                    nonce: nhrotmOptionsTableManager.nonce
                },
                success: function (response) {
                    if (response.success) {
                        let tableContent = "<table><tr><th>Option Prefix</th><th>Option Count</th></tr>";
                        response.data.forEach(row => {
                            if (row.prefix && row.count > 5) {
                                tableContent += `<tr><td>${row.prefix}</td><td>${row.count}</td></tr>`;
                            }
                        });
                        tableContent += "</table>";
                        $('#nhrotm-usage-analytics-results').html(tableContent);
                    } else {
                        showToast("Error: " + response.data, "error");
                    }
                }
            });
        }

        let usermetaTableAdjusted = false;
        let betterPaymentTableAdjusted = false;
        let wprmRatingsTableAdjusted = false;
        let wprmAnalyticsTableAdjusted = false;
        let wprmChangelogTableAdjusted = false;

        // Toggle
        $(document).on('click', '.nhrotm-data-table-wrap .tab .tablinks', function () {
            $('.nhrotm-data-table-wrap .tab .tablinks').removeClass('active');
            $(this).addClass('active');

            if ($(this).hasClass('options-table')) {
                $('#nhrotm-data-table-usermeta_wrapper').fadeOut();
                $('.nhrotm-data-table-wrap .logged-user-id').fadeOut();
                $('#nhrotm-data-table-better_payment_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_ratings_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_analytics_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_changelog_wrapper').fadeOut();

                $('#nhrotm-data-table_wrapper').fadeIn();
                $('.nhrotm-filter-container').fadeIn();

            } else if ($(this).hasClass('usermeta-table')) {
                $('#nhrotm-data-table_wrapper').fadeOut();
                $('.nhrotm-filter-container').fadeOut();
                $('#nhrotm-data-table-better_payment_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_ratings_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_analytics_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_changelog_wrapper').fadeOut();

                $('#nhrotm-data-table-usermeta_wrapper').fadeIn();
                $('.nhrotm-data-table-wrap .logged-user-id').fadeIn();

                if (!usermetaTableAdjusted) {
                    $('#nhrotm-data-table-usermeta').DataTable().columns.adjust().draw();
                    usermetaTableAdjusted = true;
                }
            } else if ($(this).hasClass('better_payment-table')) {
                $('#nhrotm-data-table-usermeta_wrapper').fadeOut();
                $('.nhrotm-data-table-wrap .logged-user-id').fadeOut();
                $('#nhrotm-data-table_wrapper').fadeOut();
                $('.nhrotm-filter-container').fadeOut();
                $('#nhrotm-data-table-wprm_ratings_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_analytics_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_changelog_wrapper').fadeOut();

                $('#nhrotm-data-table-better_payment_wrapper').fadeIn();

                if (!betterPaymentTableAdjusted) {
                    $('#nhrotm-data-table-better_payment').DataTable().columns.adjust().draw();
                    betterPaymentTableAdjusted = true;
                }
            } else if ($(this).hasClass('wprm_ratings-table')) {
                $('#nhrotm-data-table-usermeta_wrapper').fadeOut();
                $('.nhrotm-data-table-wrap .logged-user-id').fadeOut();
                $('#nhrotm-data-table_wrapper').fadeOut();
                $('.nhrotm-filter-container').fadeOut();
                $('#nhrotm-data-table-better_payment_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_analytics_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_changelog_wrapper').fadeOut();

                $('#nhrotm-data-table-wprm_ratings_wrapper').fadeIn();

                if (!wprmRatingsTableAdjusted) {
                    $('#nhrotm-data-table-wprm_ratings').DataTable().columns.adjust().draw();
                    wprmRatingsTableAdjusted = true;
                }
            } else if ($(this).hasClass('wprm_analytics-table')) {
                $('#nhrotm-data-table-usermeta_wrapper').fadeOut();
                $('.nhrotm-data-table-wrap .logged-user-id').fadeOut();
                $('#nhrotm-data-table_wrapper').fadeOut();
                $('.nhrotm-filter-container').fadeOut();
                $('#nhrotm-data-table-better_payment_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_ratings_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_changelog_wrapper').fadeOut();

                $('#nhrotm-data-table-wprm_analytics_wrapper').fadeIn();

                if (!wprmAnalyticsTableAdjusted) {
                    $('#nhrotm-data-table-wprm_analytics').DataTable().columns.adjust().draw();
                    wprmAnalyticsTableAdjusted = true;
                }
            } else if ($(this).hasClass('wprm_changelog-table')) {
                $('#nhrotm-data-table-usermeta_wrapper').fadeOut();
                $('.nhrotm-data-table-wrap .logged-user-id').fadeOut();
                $('#nhrotm-data-table_wrapper').fadeOut();
                $('.nhrotm-filter-container').fadeOut();
                $('#nhrotm-data-table-better_payment_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_ratings_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_changelog_wrapper').fadeOut();
                $('#nhrotm-data-table-wprm_analytics_wrapper').fadeOut();

                $('#nhrotm-data-table-wprm_changelog_wrapper').fadeIn();

                if (!wprmChangelogTableAdjusted) {
                    $('#nhrotm-data-table-wprm_changelog').DataTable().columns.adjust().draw();
                    wprmChangelogTableAdjusted = true;
                }
            }
        });

        // User Meta Table
        $('#nhrotm-data-table-usermeta').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "type": "GET",
                "url": nhrotmOptionsTableManager.ajaxUrl + "?action=nhrotm_usermeta_table_data&nonce=" + nhrotmOptionsTableManager.nonce,
            },
            "columns": [
                { "data": "umeta_id" },
                { "data": "user_id" },
                { "data": "meta_key" },
                { "data": "meta_value" },
                { "data": "actions", "orderable": false }
            ],
            "searchDelay": 500, // Delay in milliseconds (0.5 seconds)
            // "scrollY": "400px",     // Fixed height
            // "scrollCollapse": true,
            // "paging": true,
            // "order": [[0, 'asc']], // Default order on the first column in ascending
        });

        // Edit Usermeta
        $('#nhrotm-data-table-usermeta').on('click', '.nhrotm-edit-button-usermeta', function () {
            editUsermeta(this);
        });

        // Close modal when clicking outside of it
        $('.nhrotm-edit-usermeta-modal').on('click', function (event) {
            if ($(event.target).is('.nhrotm-edit-usermeta-modal')) { // Check if the clicked target is the modal overlay
                $('.nhrotm-edit-usermeta-modal').addClass('is-hidden').fadeOut();
            }
        });

        function editUsermeta($this) {
            const row = $('#nhrotm-data-table-usermeta').DataTable().row($($this).parents('tr')).data();

            let userId = parseInt(row.user_id);
            $('.nhrotm-edit-usermeta-key').val(row.meta_key);
            $('.nhrotm-edit-usermeta-value').val(row.meta_value.replace(/<div class="scrollable-cell">|<\/div>/g, ''));

            if (isProtectedMeta(row.meta_key)) {
                showToast('This meta is protected and cannot be edited.', "warning");
                return;
            }

            $('.nhrotm-edit-usermeta-modal').show();

            $('.nhrotm-update-usermeta').off('click').on('click', function () {
                const metaKey = $('.nhrotm-edit-usermeta-key').val();
                const metaValue = $('.nhrotm-edit-usermeta-value').val();

                $.ajax({
                    url: nhrotmOptionsTableManager.ajaxUrl,
                    method: "POST",
                    data: {
                        action: "nhrotm_edit_usermeta",
                        nonce: nhrotmOptionsTableManager.nonce,
                        user_id: userId,
                        meta_key: metaKey,
                        meta_value: metaValue,
                    },
                    success: function (response) {
                        if (response.success) {
                            showToast("Meta updated successfully!", "success");
                            $('.nhrotm-edit-usermeta-modal').hide();
                            $('#nhrotm-data-table-usermeta').DataTable().ajax.reload(); // Reload table data
                        } else {
                            showToast('Error: ' + response.data, "error");
                        }
                    }
                });
            });
        }

        // Delete Usermeta
        $('#nhrotm-data-table-usermeta').on('click', '.nhrotm-delete-button-usermeta', function () {
            console.log('clicked!');
            deleteUsermeta(this);
        });

        function deleteUsermeta($this) {
            const row = $('#nhrotm-data-table-usermeta').DataTable().row($($this).parents('tr')).data();
            let userId = parseInt(row.user_id);
            let metaKey = row.meta_key;

            if (isProtected(metaKey)) {
                showToast('This meta is protected and cannot be deleted.', "warning");
                return;
            }

            if (confirm("Are you sure you want to delete this meta?")) {
                jQuery.ajax({
                    url: nhrotmOptionsTableManager.ajaxUrl,
                    method: "POST",
                    data: {
                        action: "nhrotm_delete_usermeta",
                        nonce: nhrotmOptionsTableManager.nonce,
                        user_id: userId,
                        meta_key: metaKey
                    },
                    success: function (response) {
                        if (response.success) {
                            showToast("Meta deleted successfully!", "success");
                            jQuery('#nhrotm-data-table-usermeta').DataTable().ajax.reload(); // Reload table data
                        } else {
                            showToast('Error: ' + response.data, "error");
                        }
                    }
                });
            }
        }

        // Better Payment Table
        if (isBetterPaymentInstalled) {
            $('#nhrotm-data-table-better_payment').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "type": "GET",
                    "url": nhrotmOptionsTableManager.ajaxUrl + "?action=nhrotm_better_payment_table_data&nonce=" + nhrotmOptionsTableManager.nonce,
                },
                "columns": [
                    { "data": "id", 'visible': false },
                    { "data": "transaction_id" },
                    { "data": "email" },
                    { "data": "amount" },
                    { "data": "form_fields_info" },
                    { "data": "source" },
                    { "data": "status" },
                    { "data": "payment_date" },
                ],
                "searchDelay": 500, // Delay in milliseconds (0.5 seconds)
                // "scrollY": "400px",     // Fixed height
                // "scrollCollapse": true,
                // "paging": true,
                // "order": [[0, 'asc']], // Default order on the first column in ascending
            });
        }

        // WP Recipe Maker Tables
        if (isWpRecipeMakerInstalled) {
            // wprm_ratings table
            $('#nhrotm-data-table-wprm_ratings').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "type": "GET",
                    "url": nhrotmOptionsTableManager.ajaxUrl + "?action=nhrotm_wprm_ratings_table_data&nonce=" + nhrotmOptionsTableManager.nonce,
                },
                "columns": [
                    { "data": "id", 'visible': false },
                    { "data": "date" },
                    { "data": "recipe_id" },
                    { "data": "post_id" },
                    { "data": "comment_id" },
                    { "data": "approved" },
                    { "data": "has_comment" },
                    { "data": "user_id" },
                    { "data": "ip" },
                    { "data": "rating" },
                ],
                "searchDelay": 500, // Delay in milliseconds (0.5 seconds)
                // "scrollY": "400px",     // Fixed height
                // "scrollCollapse": true,
                // "paging": true,
                // "order": [[0, 'asc']], // Default order on the first column in ascending
            });

            // wprm_analytics table
            $('#nhrotm-data-table-wprm_analytics').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "type": "GET",
                    "url": nhrotmOptionsTableManager.ajaxUrl + "?action=nhrotm_wprm_analytics_table_data&nonce=" + nhrotmOptionsTableManager.nonce,
                },
                "columns": [
                    { "data": "id", 'visible': false },
                    { "data": "type" },
                    { "data": "meta" },
                    { "data": "post_id" },
                    { "data": "recipe_id" },
                    { "data": "user_id" },
                    { "data": "visitor_id" },
                    { "data": "visitor" },
                    { "data": "created_at" },
                ],
                "searchDelay": 500, // Delay in milliseconds (0.5 seconds)
                // "scrollY": "400px",     // Fixed height
                // "scrollCollapse": true,
                // "paging": true,
                // "order": [[0, 'asc']], // Default order on the first column in ascending
            });

            // wprm_changelog table
            $('#nhrotm-data-table-wprm_changelog').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "type": "GET",
                    "url": nhrotmOptionsTableManager.ajaxUrl + "?action=nhrotm_wprm_changelog_table_data&nonce=" + nhrotmOptionsTableManager.nonce,
                },
                "columns": [
                    { "data": "id", 'visible': false },
                    { "data": "type" },
                    { "data": "meta" },
                    { "data": "object_id" },
                    { "data": "object_meta" },
                    { "data": "user_id" },
                    { "data": "user_meta" },
                    { "data": "created_at" },
                ],
                "searchDelay": 500, // Delay in milliseconds (0.5 seconds)
                // "scrollY": "400px",     // Fixed height
                // "scrollCollapse": true,
                // "paging": true,
                // "order": [[0, 'asc']], // Default order on the first column in ascending
            });
        }

        // Filtering
        // Add filter dropdown handler
        $('#option-type-filter').on('change', function () {
            table.ajax.reload();
        });

        // Add "Delete All Transients" button handler
        $('#delete-expired-transients').on('click', function () {
            if (confirm('Are you sure you want to delete expired transients? This action cannot be undone.')) {
                $.ajax({
                    url: nhrotmOptionsTableManager.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'nhrotm_delete_expired_transients',
                        nonce: nhrotmOptionsTableManager.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            showToast("Expired transients deleted successfully!", "success");
                            table.ajax.reload();
                        } else {
                            showToast('Failed to delete transients: ' + response.data, "error");
                        }
                    },
                    error: function () {
                        showToast('An error occurred while deleting transients.', "error");
                    }
                });
            }
        });

        // Select All handler
        $('#nhrotm-select-all, #nhrotm-select-all-footer').on('click', function () {
            var rows = $('#nhrotm-data-table').DataTable().rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"]', rows).prop('checked', this.checked);
            $('#nhrotm-select-all, #nhrotm-select-all-footer').prop('checked', this.checked);
        });

        // Handle individual checkbox click to update Select All state
        $('#nhrotm-data-table tbody').on('change', 'input[type="checkbox"]', function () {
            if (!this.checked) {
                var el = $('#nhrotm-select-all, #nhrotm-select-all-footer').get(0);
                if (el && el.checked && ('indeterminate' in el)) {
                    el.indeterminate = true;
                }
            }
        });

        // Bulk Actions
        $('#nhrotm-do-bulk-action').on('click', function (e) {
            e.preventDefault();
            const action = $('#nhrotm-bulk-action-selector').val();

            if (action === '-1') {
                showToast("Please select an action.", "error");
                return;
            }

            const selectedOptions = [];
            $('#nhrotm-data-table tbody input.nhrotm-checkbox:checked').each(function () {
                selectedOptions.push($(this).val());
            });

            if (selectedOptions.length === 0) {
                showToast("Please select at least one option.", "warning");
                return;
            }

            if (action === 'delete') {
                if (confirm("Are you sure you want to delete selected options?")) {
                    bulkDeleteOptions(selectedOptions);
                }
            }
        });

        function bulkDeleteOptions(optionNames) {
            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: "POST",
                data: {
                    action: "nhrotm_bulk_delete_options",
                    nonce: nhrotmOptionsTableManager.nonce,
                    option_names: optionNames
                },
                success: function (response) {
                    if (response.success) {
                        showToast("Options deleted successfully!", "success");
                        table.ajax.reload(null, false);
                        $('#nhrotm-select-all, #nhrotm-select-all-footer').prop('checked', false);
                    } else {
                        showToast("Failed to delete options.", "error");
                    }
                }
            });
        }

        // --- History Feature ---

        // Open History Modal
        $('#nhrotm-data-table').on('click', '.nhrotm-history-button', function () {
            const optionName = $(this).data('option-name');
            $('.nhrotm-history-option-name').text(optionName);
            $('.nhrotm-history-table-body').empty();
            $('.nhrotm-history-modal').show();
            $('.nhrotm-history-loading').show();
            $('.nhrotm-history-list-container').hide();

            loadHistory(optionName);
        });

        // Close History Modal
        $('.nhrotm-close-history-modal, .nhrotm-history-modal').on('click', function (e) {
            if ($(e.target).is('.nhrotm-history-modal') || $(e.target).is('.nhrotm-close-history-modal')) {
                $('.nhrotm-history-modal').hide();
            }
        });

        function loadHistory(optionName) {
            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: "GET", // Changed to GET as per AjaxHandler
                data: {
                    action: "nhrotm_get_option_history",
                    nonce: nhrotmOptionsTableManager.nonce,
                    option_name: optionName
                },
                success: function (response) {
                    $('.nhrotm-history-loading').hide();
                    $('.nhrotm-history-list-container').show();

                    if (response.success) {
                        const history = response.data;
                        if (history.length === 0) {
                            $('.nhrotm-history-table-body').html('<tr><td colspan="5">No history found for this option.</td></tr>');
                            return;
                        }

                        let html = '';
                        history.forEach(item => {
                            let valuePreview = item.option_value;
                            if (valuePreview.length > 50) {
                                valuePreview = valuePreview.substring(0, 50) + '...';
                            }

                            // Safe escape for HTML content
                            valuePreview = $('<div>').text(valuePreview).html();

                            html += `<tr>
                                <td>${item.performed_at}</td>
                                <td>${item.action}</td>
                                <td>${valuePreview}</td>
                                <td>${item.performed_by}</td>
                                <td>
                                    <button class="button nhrotm-restore-button" data-history-id="${item.id}">Restore</button>
                                </td>
                            </tr>`;
                        });
                        $('.nhrotm-history-table-body').html(html);
                    } else {
                        $('.nhrotm-history-table-body').html('<tr><td colspan="5">Failed to load history.</td></tr>');
                        showToast("Error loading history: " + response.data, "error");
                    }
                },
                error: function () {
                    $('.nhrotm-history-loading').hide();
                    showToast("System error loading history.", "error");
                }
            });
        }

        // Restore Version
        $(document).on('click', '.nhrotm-restore-button', function () {
            const historyId = $(this).data('history-id');

            if (confirm("Are you sure you want to restore this version? Current value will be backed up.")) {
                $.ajax({
                    url: nhrotmOptionsTableManager.ajaxUrl,
                    method: "POST",
                    data: {
                        action: "nhrotm_restore_option_version",
                        nonce: nhrotmOptionsTableManager.nonce,
                        history_id: historyId
                    },
                    success: function (response) {
                        if (response.success) {
                            showToast("Option restored successfully!", "success");
                            $('.nhrotm-history-modal').hide();
                            table.ajax.reload(null, false);
                        } else {
                            showToast("Failed to restore: " + response.data, "error");
                        }
                    }
                });
            }
        });


        // --- Unified Tab System ---

        $('.tablinks').on('click', function (e) {
            e.preventDefault();
            const tabId = $(this).data('tab');
            if (!tabId) return;

            // Highlight tab button
            $('.tablinks').removeClass('active');
            $(this).addClass('active');

            // Hide all tab content containers
            $('.nhrotm-tab-content').hide();

            // Show target tab content
            const $targetContainer = $('#' + tabId);
            $targetContainer.show();

            // Handle Global UI Elements visibility
            // Feature tabs don't show filters or "Add Option"
            const isFeatureTab = $(this).hasClass('optimization-tab') || $(this).hasClass('settings-tab');

            if (isFeatureTab) {
                $('.nhrotm-filter-container').hide();
                $('.nhrotm-add-option-button').hide();
                $('.logged-user-id').hide();
            } else {
                $('.nhrotm-filter-container').show();
                $('.logged-user-id').show();

                // "Add Option" is specifically for the main Options Table
                if ($(this).hasClass('options-table')) {
                    $('.nhrotm-add-option-button').show();
                } else {
                    $('.nhrotm-add-option-button').hide();
                }

                // Adjust DataTables inside this tab automatically
                $targetContainer.find('table.nhrotm-data-table').each(function () {
                    if ($.fn.DataTable.isDataTable(this)) {
                        $(this).DataTable().columns.adjust().draw();
                    }
                });
            }

            // Trigger specific feature logic
            if ($(this).hasClass('optimization-tab')) {
                loadAutoloadData();
            }
        });



        function loadAutoloadData() {
            // Get Total Size
            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: "GET",
                data: {
                    action: "nhrotm_get_total_autoload_size",
                    nonce: nhrotmOptionsTableManager.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#nhrotm-total-autoload-size').text(response.data.size);
                    }
                }
            });

            // Get Heavy Options
            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: "GET",
                data: {
                    action: "nhrotm_get_heavy_autoload_options",
                    nonce: nhrotmOptionsTableManager.nonce,
                    limit: 20
                },
                success: function (response) {
                    if (response.success) {
                        const rows = response.data;
                        let html = '';
                        if (rows.length === 0) {
                            html = '<tr><td colspan="4">No autoloaded options found.</td></tr>';
                        } else {
                            rows.forEach(row => {
                                const isChecked = row.autoload === 'yes' ? 'checked' : '';
                                html += `<tr>
                                    <td>${row.option_name}</td>
                                    <td>${row.size_formatted}</td>
                                    <td><code>${row.value_snippet}</code></td>
                                    <td>
                                        <label class="switch">
                                            <input type="checkbox" class="nhrotm-toggle-autoload" data-option="${row.option_name}" ${isChecked}>
                                            <span class="slider round"></span>
                                        </label>
                                    </td>
                                </tr>`;
                            });
                        }
                        $('#nhrotm-autoload-list-body').html(html);
                    }
                }
            });
        }

        // Toggle Autoload
        $(document).on('change', '.nhrotm-toggle-autoload', function () {
            const optionName = $(this).data('option');
            const newStatus = $(this).is(':checked') ? 'yes' : 'no';

            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: "POST",
                data: {
                    action: "nhrotm_toggle_autoload",
                    nonce: nhrotmOptionsTableManager.nonce,
                    option_name: optionName,
                    autoload_status: newStatus
                },
                success: function (response) {
                    if (response.success) {
                        showToast("Autoload status updated.", "success");
                        // optimize: reload total size
                        loadAutoloadData();
                    } else {
                        showToast("Failed to update status: " + response.data, "error");
                    }
                }
            });
        });

        // --- Settings / Auto Cleanup Feature ---





        // Initialize Toggle
        if (nhrotmOptionsTableManager.auto_cleanup_enabled === 'true') {
            $('#nhrotm_auto_cleanup_toggle').prop('checked', true);
        } else {
            $('#nhrotm_auto_cleanup_toggle').prop('checked', false);
        }

        // Toggle Change Handler
        $('#nhrotm_auto_cleanup_toggle').on('change', function () {
            const isEnabled = $(this).is(':checked');

            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: "POST",
                data: {
                    action: "nhrotm_update_auto_cleanup_setting",
                    nonce: nhrotmOptionsTableManager.nonce,
                    enabled: isEnabled
                },
                success: function (response) {
                    if (response.success) {
                        showToast("Settings updated.", "success");
                    } else {
                        showToast("Failed to update settings.", "error");
                        // Revert toggle if failed
                        $(this).prop('checked', !isEnabled);
                    }
                },
                error: function () {
                    showToast("System error.", "error");
                    $(this).prop('checked', !isEnabled);
                }
            });
        });

    });
})(jQuery);