/**
 * Shared CSP-friendly application behaviors.
 *
 * Elements opt in through data attributes so pages do not need
 * inline JavaScript event handlers.
 */
(function () {
    'use strict';

    document.addEventListener('change', function (event) {
        const autoSubmitTarget = event.target.closest('[data-auto-submit]');
        if (autoSubmitTarget) {
            const form = autoSubmitTarget.form || autoSubmitTarget.closest('form');
            if (form) {
                // Preserve the behavior of the former:
                // onchange="this.form.submit()"
                form.submit();
            }
            return;
        }

        const recordCheckTarget = event.target.closest('[data-check-existing-record]');
        if (!recordCheckTarget) {
            return;
        }

        if (typeof window.checkExistingRecord !== 'function') {
            return;
        }

        window.checkExistingRecord();
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
