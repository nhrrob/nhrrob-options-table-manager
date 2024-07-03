(function ($) {
    $(document).ready(function() {
        let table = $('.nhrotm-options-table-manager .form-table').DataTable({
            "paging": true,      // Enable pagination
            "searching": true,   // Enable search functionality
            "ordering": true,    // Enable column ordering
            "info": true         // Show table information (e.g., "Showing 1 to 10 of 20 entries")
        });
    });
})(jQuery);
