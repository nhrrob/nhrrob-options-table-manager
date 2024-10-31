(function($) {
    $(document).ready(function() {
        "use strict";

        let protectedOptions = nhrotmOptionsTableManager.protected_options;
        let protectedUsermetas = nhrotmOptionsTableManager.protected_usermetas;

        function isProtected(optionName) {
            return protectedOptions.includes(optionName);
        }
        
        function isProtectedMeta(metaKey) {
            return protectedUsermetas.includes(metaKey);
        }

        // Datatable display
        $('#nhrotm-data-table').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "type": "GET",
                "url": nhrotmOptionsTableManager.ajaxUrl + "?action=nhrotm_option_table_data&nonce="+nhrotmOptionsTableManager.nonce,
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
            // "order": [[0, 'asc']], // Default order on the first column in ascending
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
                        alert("Option added successfully!");
                        $('.nhrotm-add-option-modal').hide();
                        $('#nhrotm-data-table').DataTable().ajax.reload(); // Reload table data
                    } else {
                        alert('Failed to add option: ' + response.data);
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
            
            $('.nhrotm-edit-option-name').val(row.option_name);
            $('.nhrotm-edit-option-value').val(row.option_value.replace(/<div class="scrollable-cell">|<\/div>/g, ''));
            $('.nhrotm-edit-option-autoload').val(row.autoload);
                
            if (isProtected(row.option_name)) {
                alert('This option is protected and cannot be edited.');
                return;
            }

            $('.nhrotm-edit-option-modal').show();

            $('.nhrotm-update-option').off('click').on('click', function() {
                const optionName = $('.nhrotm-edit-option-name').val();
                const optionValue = $('.nhrotm-edit-option-value').val();
                const autoload = $('.nhrotm-edit-option-autoload').val();
        
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
                            alert("Option updated successfully!");
                            $('.nhrotm-edit-option-modal').hide();
                            $('#nhrotm-data-table').DataTable().ajax.reload(); // Reload table data
                        } else {
                            alert('Error: ' + response.data);
                            // alert("Failed to update option.");
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
                alert('This option is protected and cannot be deleted.');
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
                            alert("Option deleted successfully!");
                            jQuery('#nhrotm-data-table').DataTable().ajax.reload(); // Reload table data
                        } else {
                            alert("Failed to delete option.");
                            // alert('Error: ' + response.data);
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
                        alert("Error: " + response.data);
                    }
                }
            });
        }

        // Toggle
        $(document).on('click', '.nhrotm-data-table-wrap .tab .tablinks', function() {
            $('.nhrotm-data-table-wrap .tab .tablinks').removeClass('active');
            $(this).addClass('active');
            
            if ( $(this).hasClass('options-table') ) {
                $( '#nhrotm-data-table-usermeta_wrapper' ).fadeOut();
                $('.nhrotm-data-table-wrap .logged-user-id').fadeOut();

                $( '#nhrotm-data-table_wrapper' ).fadeIn();
            } else if ( $(this).hasClass('usermeta-table') ) {
                $( '#nhrotm-data-table_wrapper' ).fadeOut();

                $( '#nhrotm-data-table-usermeta_wrapper' ).fadeIn();
                $('.nhrotm-data-table-wrap .logged-user-id').fadeIn();
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
                alert('This meta is protected and cannot be edited.');
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
                            alert("Meta updated successfully!");
                            $('.nhrotm-edit-usermeta-modal').hide();
                            $('#nhrotm-data-table-usermeta').DataTable().ajax.reload(); // Reload table data
                        } else {
                            alert('Error: ' + response.data);
                            // alert("Failed to update meta.");
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
                alert('This meta is protected and cannot be deleted.');
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
                            alert("Meta deleted successfully!");
                            jQuery('#nhrotm-data-table-usermeta').DataTable().ajax.reload(); // Reload table data
                        } else {
                            // alert("Failed to delete meta.");
                            alert('Error: ' + response.data);
                        }
                    }
                });
            }
        }

        
    });
})(jQuery);