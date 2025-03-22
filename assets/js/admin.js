(function($) {
    $(document).ready(function() {
        "use strict";

        let protectedOptions = nhrotmOptionsTableManager.protected_options;
        let protectedUsermetas = nhrotmOptionsTableManager.protected_usermetas;
        let isBetterPaymentInstalled = nhrotmOptionsTableManager.is_better_payment_installed;

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
            toast.fadeIn(300).delay(3000).fadeOut(300, function() {
                $(this).remove();
            });
        }

        // Datatable display
        var table = $('#nhrotm-data-table').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "type": "GET",
                "url": nhrotmOptionsTableManager.ajaxUrl + "?action=nhrotm_option_table_data&nonce="+nhrotmOptionsTableManager.nonce,
                "data": function(d) {
                    // Add column search values to the request
                    for (let i = 0; i < d.columns.length; i++) {                        
                        d.columns[i].search.value = $('#nhrotm-data-table tfoot input').eq(i).val();
                    }

                    // Option type filter
                    d.optionTypeFilter = $('#option-type-filter').val();

                    if ( d.optionTypeFilter === 'all-transients' ) {
                        let currentSearch = d.columns[1].search.value || '';
                        if (!currentSearch.includes('transient_')) {
                            d.columns[1].search.value = 'transient_' + currentSearch;
                        }
                    }
                }
            },
            "columns": [
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
            // "order": [[0, 'asc']], // Default order on the first column in ascending,
            "initComplete": function () {
                this.api()
                    .columns()
                    .every(function () {
                        let column = this;
                        let title = column.footer().textContent;
         
                        // Create input element
                        let input = document.createElement('input');
                        input.placeholder = title;
                        column.footer().replaceChildren(input);
         
                        input.addEventListener('keyup', function() {
                            if (column.search() !== this.value) {
                              column.search(this.value).draw();
                            }
                        });
                    });
            }
        });

        // Add option
        $('.nhrotm-add-option-button').on('click', function() {
            $('.nhrotm-new-option-name').val('');
            $('.nhrotm-new-option-value').val('');
            $('.nhrotm-new-option-autoload').val('yes');
            $('.nhrotm-add-option-modal').show();
        });
    
        $('.nhrotm-add-option-modal').on('click', function(event) {
            if ($(event.target).is('.nhrotm-add-option-modal')) { // Check if the clicked target is the modal overlay
                $('.nhrotm-add-option-modal').addClass('is-hidden').fadeOut();
            }
        });
    
        $('.nhrotm-save-option').on('click', function() {
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
                success: function(response) {
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
        $('#nhrotm-data-table').on('click', '.nhrotm-edit-button', function() {
            const id = $(this).data('id');
            editOption(id, this);
        });

        // Close modal when clicking outside of it
        $('.nhrotm-edit-option-modal').on('click', function(event) {
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
                success: function(response) {
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
            
            $('.nhrotm-update-option').off('click').on('click', function() {
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
                    success: function(response) {
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
        $('#nhrotm-data-table').on('click', '.nhrotm-delete-button', function() {
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
                    success: function(response) {
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
                success: function(response) {
                    if (response.success) {
                        let tableContent = "<table><tr><th>Option Prefix</th><th>Option Count</th></tr>";
                        response.data.forEach(row => {
                            if ( row.prefix && row.count > 5 ) {
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

        // Toggle
        $(document).on('click', '.nhrotm-data-table-wrap .tab .tablinks', function() {
            $('.nhrotm-data-table-wrap .tab .tablinks').removeClass('active');
            $(this).addClass('active');
            
            if ( $(this).hasClass('options-table') ) {
                $( '#nhrotm-data-table-usermeta_wrapper' ).fadeOut();
                $('.nhrotm-data-table-wrap .logged-user-id').fadeOut();
                $('#nhrotm-data-table-better_payment_wrapper').fadeOut();

                $( '#nhrotm-data-table_wrapper' ).fadeIn();
            } else if ( $(this).hasClass('usermeta-table') ) {
                $( '#nhrotm-data-table_wrapper' ).fadeOut();
                $('#nhrotm-data-table-better_payment_wrapper').fadeOut();

                $( '#nhrotm-data-table-usermeta_wrapper' ).fadeIn();
                $('.nhrotm-data-table-wrap .logged-user-id').fadeIn();

                if ( ! usermetaTableAdjusted ) {
                    $('#nhrotm-data-table-usermeta').DataTable().columns.adjust().draw();
                    usermetaTableAdjusted = true;
                }
            } else if ( $(this).hasClass('better_payment-table') ) {
                $( '#nhrotm-data-table-usermeta_wrapper' ).fadeOut();
                $('.nhrotm-data-table-wrap .logged-user-id').fadeOut();
                $( '#nhrotm-data-table_wrapper' ).fadeOut();

                $('#nhrotm-data-table-better_payment_wrapper').fadeIn();

                if ( ! betterPaymentTableAdjusted ) {
                    $('#nhrotm-data-table-better_payment').DataTable().columns.adjust().draw();
                    betterPaymentTableAdjusted = true;
                }
            }
        });

        // User Meta Table
        $('#nhrotm-data-table-usermeta').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "type": "GET",
                "url": nhrotmOptionsTableManager.ajaxUrl + "?action=nhrotm_usermeta_table_data&nonce="+nhrotmOptionsTableManager.nonce,
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
        $('#nhrotm-data-table-usermeta').on('click', '.nhrotm-edit-button-usermeta', function() {
            editUsermeta(this);
        });

        // Close modal when clicking outside of it
        $('.nhrotm-edit-usermeta-modal').on('click', function(event) {
            if ($(event.target).is('.nhrotm-edit-usermeta-modal')) { // Check if the clicked target is the modal overlay
                $('.nhrotm-edit-usermeta-modal').addClass('is-hidden').fadeOut();
            }
        });
    
        function editUsermeta($this) {
            const row = $('#nhrotm-data-table-usermeta').DataTable().row($($this).parents('tr')).data();
            
            let userId = parseInt( row.user_id );
            $('.nhrotm-edit-usermeta-key').val(row.meta_key);
            $('.nhrotm-edit-usermeta-value').val(row.meta_value.replace(/<div class="scrollable-cell">|<\/div>/g, ''));
                
            if (isProtectedMeta(row.meta_key)) {
                showToast('This meta is protected and cannot be edited.', "warning");
                return;
            }

            $('.nhrotm-edit-usermeta-modal').show();

            $('.nhrotm-update-usermeta').off('click').on('click', function() {
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
                    success: function(response) {
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
        $('#nhrotm-data-table-usermeta').on('click', '.nhrotm-delete-button-usermeta', function() {
            console.log('clicked!');
            deleteUsermeta(this);
        });
    
        function deleteUsermeta($this) {
            const row = $('#nhrotm-data-table-usermeta').DataTable().row($($this).parents('tr')).data();            
            let userId = parseInt( row.user_id );
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
                    success: function(response) {
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
        if ( isBetterPaymentInstalled ) {
            $('#nhrotm-data-table-better_payment').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "type": "GET",
                    "url": nhrotmOptionsTableManager.ajaxUrl + "?action=nhrotm_better_payment_table_data&nonce="+nhrotmOptionsTableManager.nonce,
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

        // Filtering
        // Add filter dropdown handler
        $('#option-type-filter').on('change', function() {
            table.ajax.reload();
        });

        // Add "Delete All Transients" button handler
        $('#delete-all-transients').on('click', function() {
            if (confirm('Are you sure you want to delete all transients? This action cannot be undone.')) {
                $.ajax({
                    url: nhrotmOptionsTableManager.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'nhrotm_delete_all_transients',
                        nonce: nhrotmOptionsTableManager.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('All transients deleted successfully!');
                            table.ajax.reload();
                        } else {
                            alert('Failed to delete transients: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('An error occurred while deleting transients.');
                    }
                });
            }
        });
        
    });
})(jQuery);