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
            
            // "Add New Option" button - Visible ONLY for "Options Table"
            if ($(this).hasClass('options-table')) {
                $('.nhrotm-add-option-button').show();
            } else {
                $('.nhrotm-add-option-button').hide();
            }

            // Filters and User ID - Hidden for Feature tabs
            const isFeatureTab = $(this).hasClass('optimization-tab') || $(this).hasClass('settings-tab') || $(this).hasClass('scanner-tab') || $(this).hasClass('search-replace-tab') || $(this).hasClass('import-export-tab');
            
            if (isFeatureTab) {
                $('.nhrotm-filter-container').hide();
                $('.logged-user-id').hide();
            } else {
                $('.nhrotm-filter-container').show();
                $('.logged-user-id').show();

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
            } else if ($(this).hasClass('scanner-tab')) {
                // Potential initial load or reset view
            } else if ($(this).hasClass('search-replace-tab')) {
                // Potential reset view
                $('#nhrotm-search-replace-results').hide();
                $('.nhrotm-search-replace-form').show();
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
                    } else {
                        $('#nhrotm-total-autoload-size').text('Error');
                    }
                },
                error: function () {
                    $('#nhrotm-total-autoload-size').text('Error');
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
                                // Broad check for autoload truthiness
                                const isChecked = ['yes', '1', 'true', 'on'].includes(String(row.autoload).toLowerCase()) ? 'checked' : '';
                                html += `<tr>
                                    <td>${row.option_name}</td>
                                    <td>${row.size_formatted}</td>
                                    <td><code>${row.value_snippet}</code></td>
                                    <td>
                                        <label class="nhrotm-switch">
                                            <input type="checkbox" class="nhrotm-toggle-autoload" data-option="${row.option_name}" ${isChecked}>
                                            <span class="nhrotm-slider nhrotm-round"></span>
                                        </label>
                                    </td>
                                </tr>`;
                            });
                        }
                        $('#nhrotm-autoload-list-body').html(html);
                    } else {
                        $('#nhrotm-autoload-list-body').html('<tr><td colspan="4">Error loading data: ' + (response.data || 'Unknown error') + '</td></tr>');
                    }
                },
                error: function () {
                    $('#nhrotm-autoload-list-body').html('<tr><td colspan="4">Connection error while loading data.</td></tr>');
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

        // --- Orphan Scanner Feature ---

        $('#nhrotm-start-scan').on('click', function () {
            $('.nhrotm-scanner-actions').addClass('d-none');
            $('.nhrotm-scanner-loading').removeClass('d-none');
            $('#nhrotm-scanner-results').addClass('d-none');
            $('#nhrotm-scanner-empty').addClass('d-none');

            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: "GET",
                data: {
                    action: "nhrotm_scan_orphans",
                    nonce: nhrotmOptionsTableManager.nonce
                },
                success: function (response) {
                    $('.nhrotm-scanner-loading').addClass('d-none');
                    $('.nhrotm-scanner-actions').removeClass('d-none');

                    if (response.success) {
                        const orphans = response.data;
                        if (orphans.length === 0) {
                            $('#nhrotm-scanner-empty').removeClass('d-none');
                        } else {
                            let html = '';
                            orphans.forEach(item => {
                                html += `<tr>
                                    <td><strong>${item.prefix}</strong></td>
                                    <td>${item.count}</td>
                                    <td>${item.possible_source}</td>
                                    <td><span class="nhrotm-risk-${item.risk.toLowerCase()}">${item.risk}</span></td>
                                    <td>
                                        <button class="button button-danger nhrotm-delete-orphans" data-prefix="${item.prefix}">Delete All</button>
                                    </td>
                                </tr>`;
                            });
                            $('#nhrotm-scanner-list-body').html(html);
                            $('#nhrotm-scanner-results').removeClass('d-none');
                        }
                    } else {
                        showToast("Scan failed: " + response.data, "error");
                    }
                }
            });
        });

        $(document).on('click', '.nhrotm-delete-orphans', function () {
            const prefix = $(this).data('prefix');

            if (confirm(`Are you sure you want to delete all options starting with "${prefix}"? This action cannot be undone.`)) {
                const $btn = $(this);
                $btn.prop('disabled', true).text('Deleting...');

                $.ajax({
                    url: nhrotmOptionsTableManager.ajaxUrl,
                    method: "POST",
                    data: {
                        action: "nhrotm_delete_orphaned_prefix",
                        nonce: nhrotmOptionsTableManager.nonce,
                        prefix: prefix
                    },
                    success: function (response) {
                        if (response.success) {
                            showToast(response.data.message, "success");
                            // Refresh scan
                            $('#nhrotm-start-scan').trigger('click');
                            // Also reload main table if it's there
                            if ($.fn.DataTable.isDataTable('#nhrotm-data-table')) {
                                $('#nhrotm-data-table').DataTable().ajax.reload(null, false);
                            }
                        } else {
                            showToast("Delete failed: " + response.data, "error");
                            $btn.prop('disabled', false).text('Delete All');
                        }
                    }
                });
            }
        });

        // --- Search & Replace Feature ---

        $('#nhrotm-search-replace-btn').on('click', function (e) {
            e.preventDefault();
            const search = $('#nhrotm-search-string').val();
            const replace = $('#nhrotm-replace-string').val();
            const dryRun = $('#nhrotm-dry-run-toggle').is(':checked');

            if (!search) {
                showToast("Search string is required", "error");
                return;
            }

            if (!dryRun && !confirm("WARNING: This will permanently modify your database records. Are you sure you want to proceed?")) {
                return;
            }

            $('.nhrotm-search-replace-form').addClass('d-none');
            $('.nhrotm-search-replace-loading').removeClass('d-none');
            $('#nhrotm-search-replace-results').addClass('d-none');

            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: "POST",
                data: {
                    action: "nhrotm_search_replace_execute",
                    nonce: nhrotmOptionsTableManager.nonce,
                    search: search,
                    replace: replace,
                    dry_run: dryRun
                },
                success: function (response) {
                    $('.nhrotm-search-replace-loading').addClass('d-none');
                    $('.nhrotm-search-replace-form').removeClass('d-none');

                    if (response.success) {
                        const data = response.data;
                        const summary = `Found ${data.total_occurrences} occurrences in ${data.total_updated} options.` + 
                                       (data.dry_run ? " (Preview Mode - No changes saved)" : " (Changes Saved)");
                        
                        $('#nhrotm-sr-summary-text').text(summary);
                        
                        let html = '';
                        if (data.details.length === 0) {
                            html = '<tr><td colspan="2">No matches found.</td></tr>';
                        } else {
                            data.details.forEach(item => {
                                const safeName = $('<div>').text(item.option_name).html();
                                html += `<tr>
                                    <td>${safeName}</td>
                                    <td>${item.occurrences}</td>
                                </tr>`;
                            });
                        }
                        $('#nhrotm-sr-list-body').html(html);
                        $('#nhrotm-search-replace-results').show();
                        $('#nhrotm-search-replace-results').removeClass('d-none'); // Ensure class doesn't interfere

                        showToast(data.dry_run ? "Search complete (Preview)" : "Search and replace complete!", "success");
                        
                        // Reload main table if changes were made
                        if (!data.dry_run && $.fn.DataTable.isDataTable('#nhrotm-data-table')) {
                            $('#nhrotm-data-table').DataTable().ajax.reload(null, false);
                        }
                    } else {
                        showToast("Operation failed: " + response.data, "error");
                    }
                },
                error: function () {
                    $('.nhrotm-search-replace-loading').addClass('d-none');
                    $('.nhrotm-search-replace-form').removeClass('d-none');
                    showToast("Connection error", "error");
                }
            });
        });

        // --- Import / Export Feature ---

        // Export Basket
        let exportBasket = new Set();
        
        // Search for options to export
        let searchTimeout;
        $('#nhrotm-export-search').on('input', function() {
            const term = $(this).val();
            const $suggestions = $('#nhrotm-export-suggestions');
            
            clearTimeout(searchTimeout);
            
            if (term.length < 2) {
                $suggestions.addClass('d-none').html('');
                return;
            }

            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: nhrotmOptionsTableManager.ajaxUrl,
                    method: 'GET',
                    data: {
                        action: 'nhrotm_search_options_for_export',
                        nonce: nhrotmOptionsTableManager.nonce,
                        term: term
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            let html = '';
                            response.data.forEach(opt => {
                                html += `<div class="nhrotm-suggestion-item" data-option="${opt}">${opt}</div>`;
                            });
                            $suggestions.html(html).removeClass('d-none');
                        } else {
                            $suggestions.addClass('d-none');
                        }
                    }
                });
            }, 300);
        });

        // Add option to basket
        $(document).on('click', '.nhrotm-suggestion-item', function() {
            const option = $(this).data('option');
            if (exportBasket.has(option)) return;

            exportBasket.add(option);
            updateExportBasketUI();
            
            $('#nhrotm-export-search').val('');
            $('#nhrotm-export-suggestions').addClass('d-none');
        });

        // Remove from basket
        $(document).on('click', '.nhrotm-basket-remove', function() {
            const option = $(this).data('option');
            exportBasket.delete(option);
            updateExportBasketUI();
        });

        function updateExportBasketUI() {
            const $list = $('#nhrotm-basket-list');
            const $count = $('#nhrotm-basket-count');
            const $btn = $('#nhrotm-do-export');
            
            $count.text(exportBasket.size);
            
            if (exportBasket.size === 0) {
                $list.html('<li class="empty-basket">No options selected.</li>');
                $btn.prop('disabled', true);
            } else {
                let html = '';
                exportBasket.forEach(opt => {
                    html += `<li>
                        ${opt} 
                        <span class="nhrotm-basket-remove dashicons dashicons-trash" data-option="${opt}" title="Remove"></span>
                    </li>`;
                });
                $list.html(html);
                $btn.prop('disabled', false);
            }
        }

        // Execute Export
        $('#nhrotm-do-export').on('click', function() {
            const options = Array.from(exportBasket);
            const $btn = $(this);
            $btn.prop('disabled', true).text('Generating...');

            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'nhrotm_export_options',
                    nonce: nhrotmOptionsTableManager.nonce,
                    options: options
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Export to JSON');
                    if (response.success) {
                        // Download File
                        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(response.data, null, 2));
                        const downloadAnchorNode = document.createElement('a');
                        const date = new Date().toISOString().slice(0, 10);
                        downloadAnchorNode.setAttribute("href", dataStr);
                        downloadAnchorNode.setAttribute("download", `wp-options-export-${date}.json`);
                        document.body.appendChild(downloadAnchorNode); // required for firefox
                        downloadAnchorNode.click();
                        downloadAnchorNode.remove();
                        showToast("Export generated successfully!", "success");
                    } else {
                        showToast("Export failed: " + response.data, "error");
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Export to JSON');
                    showToast("Export request failed.", "error");
                }
            });
        });

        // Import Preview
        $('#nhrotm-import-file').on('change', function() {
            const file = this.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('action', 'nhrotm_preview_import');
            formData.append('nonce', nhrotmOptionsTableManager.nonce);
            formData.append('import_file', file);

            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response.success) {
                        const preview = response.data.preview;
                        const rawData = response.data.raw_data; // Store this for final import
                        
                        $('#nhrotm-import-total').text(preview.length);
                        
                        let html = '';
                        preview.forEach(item => {
                            const statusClass = item.status === 'modified' ? 'update' : (item.status === 'new' ? 'install-now' : 'none');
                            html += `<tr>
                                <td class="check-column"><input type="checkbox" class="nhrotm-import-item-checkbox" value="${item.name}" checked></td>
                                <td><strong>${item.name}</strong></td>
                                <td><span class="nhrotm-status-badge ${item.status}">${item.status}</span></td>
                                <td><code>${item.current_snippet || '-'}</code></td>
                            </tr>`;
                        });

                        $('#nhrotm-import-preview-body').html(html);
                        $('#nhrotm-import-preview-area').removeClass('d-none');
                        
                        // Store raw data in a hidden way for next step (or re-send file, but storing JSON is easier for now)
                        $('#nhrotm-execute-import').data('rawData', JSON.stringify(rawData));
                    } else {
                        showToast("Preview failed: " + response.data, "error");
                        $('#nhrotm-import-file').val('');
                    }
                }
            });
        });

        // Execute Import
        $('#nhrotm-execute-import').on('click', function() {
            const rawData = $(this).data('rawData');
            if (!rawData) return;

            const selected = [];
            $('.nhrotm-import-item-checkbox:checked').each(function() {
                selected.push($(this).val());
            });

            if (selected.length === 0) {
                showToast("No options selected for import.", "error");
                return;
            }

            if (!confirm(`Are you sure you want to import ${selected.length} options? This will overwrite existing values.`)) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).text('Importing...');

            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'nhrotm_execute_import',
                    nonce: nhrotmOptionsTableManager.nonce,
                    raw_data: rawData,
                    selected_options: selected
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Execute Import');
                    if (response.success) {
                        showToast(`Successfully imported ${response.data.count} options!`, "success");
                        $('#nhrotm-import-preview-area').addClass('d-none');
                        $('#nhrotm-import-file').val('');
                    } else {
                        showToast("Import failed: " + response.data, "error");
                    }
                }
            });
        });

        // Save History Settings
        $('#nhrotm-history-settings-form').on('submit', function(e) {
            e.preventDefault();
            const days = $('#nhrotm_history_retention_days').val();
            const $btn = $('#nhrotm-save-history-settings');
            
            $btn.prop('disabled', true).val('Saving...');

            $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'nhrotm_save_history_settings',
                    nonce: nhrotmOptionsTableManager.nonce,
                    days: days
                },
                success: function(response) {
                    $btn.prop('disabled', false).val('Save Changes');
                    if (response.success) {
                        showToast(response.data, "success");
                    } else {
                        showToast("Failed to save: " + response.data, "error");
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).val('Save Changes');
                    showToast("Request failed.", "error");
                }
            });
        });

        // Prune History Now
        $('#nhrotm-prune-history-now').on('click', function() {
           if(!confirm('Are you sure you want to delete old history logs immediately?')) return;
           
           const $btn = $(this);
           $btn.prop('disabled', true).text('Pruning...');
           
           $.ajax({
                url: nhrotmOptionsTableManager.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'nhrotm_prune_history',
                    nonce: nhrotmOptionsTableManager.nonce
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Prune Now');
                    if (response.success) {
                        const count = response.data.deleted !== false ? response.data.deleted : 0;
                        showToast(`Pruned ${count} old entries.`, "success");
                    } else {
                        showToast("Prune failed: " + response.data, "error");
                    }
                },
                error: function() {
                     $btn.prop('disabled', false).text('Prune Now');
                     showToast("Request failed.", "error");
                }
           });
        });


    });
})(jQuery);