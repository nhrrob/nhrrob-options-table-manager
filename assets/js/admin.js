(function ($) {
    $(document).ready(function() {
        let table = $('.nhrotm-options-table-manager .form-table').DataTable({
            "paging": true,      // Enable pagination
            "searching": true,   // Enable search functionality
            "ordering": true,    // Enable column ordering
            "info": true         // Show table information (e.g., "Showing 1 to 10 of 20 entries")
        });

        let protectedOptions = nhrotmOptionsTableManager.protected_options;

        function isProtected(optionName) {
            return protectedOptions.includes(optionName);
        }

        // Edit Options
        $(document).on('click', '.nhrotm-edit-option-button', function() {
            let row = $(this).closest('tr');
            let optionName = row.find('.nhrotm-option-name').text().trim();

            if (isProtected(optionName)) {
                alert('This option is protected and cannot be edited.');
                return;
            }

            let newValue = prompt('Enter new value for ' + optionName + ':', row.find('.nhrotm-option-value').text().trim());

            if (newValue !== null) {
                $.ajax({
                    type: 'POST',
                    url: nhrotmOptionsTableManager.ajaxUrl,
                    data: {
                        action: 'nhrotm_edit_option',
                        nonce: nhrotmOptionsTableManager.nonce,
                        option_name: optionName,
                        option_value: newValue
                    },
                    success: function(response) {
                        if (response.success) {
                            row.find('.nhrotm-option-value').text(newValue);
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(response) {
                        alert('Error: ' + response.responseText);
                    }
                });
            }
        });

        // Delete Options
        $(document).on('click', '.nhrotm-delete-option-button', function() {
            let row = $(this).closest('tr');
            let optionName = row.find('.nhrotm-option-name').text().trim();

            if (isProtected(optionName)) {
                alert('This option is protected and cannot be deleted.');
                return;
            }
            
            if (confirm('Are you sure you want to delete this option?')) {
                $.ajax({
                    type: 'POST',
                    url: nhrotmOptionsTableManager.ajaxUrl,
                    data: {
                        action: 'nhrotm_delete_option',
                        nonce: nhrotmOptionsTableManager.nonce,
                        option_name: optionName
                    },
                    success: function(response) {
                        if (response.success) {
                            row.remove();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(response) {
                        alert('Error: ' + response.responseText);
                    }
                });
            }
        });

        $('#nhrotm-add-option-form').on('submit', function(e) {
            e.preventDefault();
            
            var data = {
                action: 'nhrotm_add_option',
                nonce: nhrotmOptionsTableManager.nonce,
                new_option_name: $('#new_option_name').val(),
                new_option_value: $('#new_option_value').val(),
                new_option_autoload: $('#new_option_autoload').val()
            };
    
            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    alert('Option added successfully');
                    location.reload(); // Reload the page to see the new option
                } else {
                    alert('Failed to add option: ' + response.data);
                }
            });
        });

    }); 
})(jQuery);
