/**
 * Layer feeds page behavior.
 * Externalized for CSP compatibility.
 */
$(document).ready(function() {
        const feedTable = $('#feedsTable').DataTable({
            autoWidth: false,
            pageLength: 25,
            order: [[0, 'desc']],
            responsive: true,
            columnDefs: [
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: -1 }
            ]
        });
        
        // Month selector
        $('#monthSelector').change(function() {
            window.location.href = 'layer_feeds.php?month=' + this.value.substring(0, 7);
        });

        $('.edit-transaction').on('click', function() {
            const button = $(this);
            $('#editTransactionId').val(button.data('id'));
            $('#editTransactionDate').val(button.data('date'));
            $('#editFeedItem').val(button.data('item'));
            $('#editTransactionType').val(button.data('type'));
            $('#editCycleId').val(button.data('cycle') || 0);
            $('#editQuantity').val(button.data('quantity'));
            $('#editRemarks').val(button.data('remarks'));
            bootstrap.Modal.getOrCreateInstance(document.getElementById('editTransactionModal')).show();
        });
    });
