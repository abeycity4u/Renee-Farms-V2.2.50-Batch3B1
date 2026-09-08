/**
 * Shared CSP-friendly application behaviors.
 *
 * Elements opt in through data attributes so pages do not need
 * inline JavaScript event handlers.
 */
(function () {
    'use strict';

    document.addEventListener('change', function (event) {
        const target = event.target.closest('[data-auto-submit]');
        if (!target) {
            return;
        }

        const form = target.form || target.closest('form');
        if (!form) {
            return;
        }

        // Preserve the behavior of the former:
        // onchange="this.form.submit()"
        form.submit();
    });

    document.addEventListener('click', function (event) {
        const target = event.target.closest('[data-open-record-modal]');
        if (!target) {
            return;
        }

        if (typeof window.openRecordModal !== 'function') {
            return;
        }

        window.openRecordModal();
    });
})();
