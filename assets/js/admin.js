(function ($) {
    // new datatable
    jQuery(document).ready(function($) {
        // Open the modal on "Add" button click
        $('#add-button').on('click', function() {
            $('#add-modal').show(); // Show modal
        });
    
        // Close modal when clicking outside of it
        $(document).mouseup(function(e) {
            var modal = $("#add-modal");
            if (!modal.is(e.target) && modal.has(e.target).length === 0) {
                modal.hide();
            }
        });
    
        // Save new option via AJAX
        $('#save-option').on('click', function() {
            const optionName = $('#new-option-name').val();
            const optionValue = $('#new-option-value').val();
            const autoload = $('#new-option-autoload').val();
    
            $.ajax({
                url: ajaxurl,
                method: "POST",
                data: {
                    action: "db_table_add_option",
                    option_name: optionName,
                    option_value: optionValue,
                    autoload: autoload
                },
                success: function(response) {
                    if (response.success) {
                        alert("Option added successfully!");
                        $('#add-modal').hide();
                        $('#data-table').DataTable().ajax.reload(); // Reload table data
                    } else {
                        alert("Failed to add option.");
                    }
                }
            });
        });
    
        $('#data-table').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": ajaxurl + "?action=db_table_display_data",
                "type": "GET"
            },
            "columns": [
                { "data": "option_id" },
                { "data": "option_name" },
                { "data": "option_value" },
                { "data": "autoload" },
                { "data": "actions", "orderable": false } // Add actions column
                // Add additional columns as needed
            ],
            "searchDelay": 500, // Delay in milliseconds (0.5 seconds)
            // "scrollY": "400px",     // Fixed height
            // "scrollCollapse": true,
            // "paging": true,
            // "order": [[0, 'asc']], // Default order on the first column in ascending
        });
    
        // Edit button click handler
        $('#data-table').on('click', '.edit-button', function() {
            const id = $(this).data('id');
            // Call edit function with data
            editOption(id, this);
        });
    
        // Delete button click handler
        $('#data-table').on('click', '.delete-button', function() {
            const id = $(this).data('id');
            // Call delete function with data
            deleteOption(id);
        });
    
        function editOption(id, $this) {
            // Retrieve the current row data
            const row = $('#data-table').DataTable().row($($this).parents('tr')).data();
            
            // Set the current data in the edit modal fields
            $('#edit-option-name').val(row.option_name);
            $('#edit-option-value').val(row.option_value.replace(/<div class="scrollable-cell">|<\/div>/g, ''));
            $('#edit-option-autoload').val(row.autoload);
        
            // Show the modal
            $('#edit-modal').show();
        
            // Update option when the "Update" button is clicked
            $('#update-option').off('click').on('click', function() {
                const optionValue = $('#edit-option-value').val();
                const autoload = $('#edit-option-autoload').val();
        
                $.ajax({
                    url: ajaxurl,
                    method: "POST",
                    data: {
                        action: "db_table_edit_option",
                        option_id: id,
                        option_value: optionValue,
                        autoload: autoload
                    },
                    success: function(response) {
                        if (response.success) {
                            alert("Option updated successfully!");
                            $('#edit-modal').hide();
                            $('#data-table').DataTable().ajax.reload(); // Reload table data
                        } else {
                            alert("Failed to update option.");
                        }
                    }
                });
            });
        }
        
        // Close modal when clicking outside of it
        $(document).mouseup(function(e) {
            var modal = $("#edit-modal");
            if (!modal.is(e.target) && modal.has(e.target).length === 0) {
                modal.hide();
            }
        });
        
    
        function deleteOption(id) {
            if (confirm("Are you sure you want to delete this option?")) {
                jQuery.ajax({
                    url: ajaxurl,
                    method: "POST",
                    data: {
                        action: "db_table_delete_option",
                        option_id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            alert("Option deleted successfully!");
                            jQuery('#data-table').DataTable().ajax.reload(); // Reload table data
                        } else {
                            alert("Failed to delete option.");
                        }
                    }
                });
            }
        }
        
        
    });
    
})(jQuery);
