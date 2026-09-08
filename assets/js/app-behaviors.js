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
        const recordModalTarget = event.target.closest('[data-open-record-modal]');
        if (recordModalTarget) {
            if (typeof window.openRecordModal === 'function') {
                window.openRecordModal();
            }
            return;
        }

        const investigationTarget = event.target.closest('[data-investigation-followup]');
        if (investigationTarget) {
            const form = investigationTarget.form || investigationTarget.closest('form');
            if (!form) {
                return;
            }

            const mode = investigationTarget.dataset.investigationFollowup;

            form.removeAttribute('data-confirm');
            form.removeAttribute('data-confirm-title');
            form.removeAttribute('data-confirm-button');
            form.removeAttribute('data-confirm-tone');

            if (mode === 'resolve') {
                form.setAttribute(
                    'data-confirm',
                    'Resolve this investigation with the recorded management finding? Source records will not be changed.'
                );
                form.setAttribute('data-confirm-title', 'Resolve investigation?');
                form.setAttribute('data-confirm-button', 'Resolve Investigation');
                form.setAttribute('data-confirm-tone', 'primary');
            }

            return;
        }

        const deleteExpenseTarget = event.target.closest('[data-delete-expense-id]');
        if (!deleteExpenseTarget) {
            return;
        }

        if (typeof window.deleteExpense !== 'function') {
            return;
        }

        const expenseId = Number.parseInt(deleteExpenseTarget.dataset.deleteExpenseId, 10);
        if (!Number.isInteger(expenseId) || expenseId <= 0) {
            return;
        }

        window.deleteExpense(expenseId);
    });

    document.addEventListener('error', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLScriptElement)) {
            return;
        }

        if (!target.matches('[data-chart-fallback]')) {
            return;
        }

        if (typeof window.loadChartFallback !== 'function') {
            return;
        }

        window.loadChartFallback();
    }, true);
})();
