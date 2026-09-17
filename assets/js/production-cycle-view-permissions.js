/**
 * Delegated Production Cycle read-only view behavior.
 * Externalized for CSP compatibility.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll(
        'form[method="post"],form[method="POST"]'
    ).forEach(function (form) {
        const action =
            form.querySelector('input[name="action"]')?.value || '';

        if (
            action === 'create_cycle'
            || action === 'close_cycle'
        ) {
            const col = form.closest('.col-lg-6');
            if (col) col.remove();
        }
    });

    document.querySelectorAll('a.btn').forEach(function (anchor) {
        const text = anchor.textContent.trim();

        if (text === 'Manage Cycle') {
            anchor.textContent = 'View Cycle';
        } else if (
            text === 'Manage acquisition records on Production Cycles'
        ) {
            anchor.textContent =
                'View acquisition records on Production Cycles';
        } else if (
            text === 'Manage lifecycle on Production Cycles'
        ) {
            anchor.textContent =
                'View lifecycle on Production Cycles';
        }
    });
});
