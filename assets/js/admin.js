(function($) {
    $(document).ready(function() {
        "use strict";

        let protectedOptions = nhrotmOptionsTableManager.protected_options;

        function isProtected(optionName) {
            return protectedOptions.includes(optionName);
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
    
        $(document).mouseup(function(e) {
            var modal = $(".nhrotm-add-option-modal");
            if (!modal.is(e.target) && modal.has(e.target).length === 0) {
                modal.hide();
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
        $(document).mouseup(function(e) {
            var modal = $(".nhrotm-edit-option-modal");
            if (!modal.is(e.target) && modal.has(e.target).length === 0) {
                modal.hide();
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

    });
})(jQuery);