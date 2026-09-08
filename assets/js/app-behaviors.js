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

        const quickStockTarget = event.target.closest('[data-quick-stock-id]');
        if (quickStockTarget) {
            if (typeof window.quickStockUpdate !== 'function') {
                return;
            }

            const itemId = Number.parseInt(quickStockTarget.dataset.quickStockId, 10);
            if (!Number.isInteger(itemId) || itemId <= 0) {
                return;
            }

            window.quickStockUpdate(itemId);
            return;
        }

        const poultryDailyDeleteTarget = event.target.closest('[data-poultry-daily-delete-id]');
        if (poultryDailyDeleteTarget) {
            const recordId = Number.parseInt(
                poultryDailyDeleteTarget.dataset.poultryDailyDeleteId,
                10
            );

            if (!Number.isInteger(recordId) || recordId <= 0) {
                return;
            }

            const recordType = poultryDailyDeleteTarget.dataset.poultryDailyDeleteType;

            if (recordType === 'layer') {
                if (typeof window.deleteLayerDailyRecord !== 'function') {
                    return;
                }

                window.deleteLayerDailyRecord(recordId);
                return;
            }

            if (recordType === 'broiler') {
                if (typeof window.deleteBroilerDailyRecord !== 'function') {
                    return;
                }

                window.deleteBroilerDailyRecord(recordId);
                return;
            }

            return;
        }

        const inventoryActionTarget = event.target.closest('[data-inventory-action][data-inventory-item-id]');
        if (inventoryActionTarget) {
            const itemId = Number.parseInt(inventoryActionTarget.dataset.inventoryItemId, 10);
            if (!Number.isInteger(itemId) || itemId <= 0) {
                return;
            }

            const action = inventoryActionTarget.dataset.inventoryAction;

            if (action === 'history') {
                if (typeof window.viewHistory !== 'function') {
                    return;
                }

                window.viewHistory(itemId);
                return;
            }

            if (action === 'delete') {
                if (typeof window.deleteItem !== 'function') {
                    return;
                }

                window.deleteItem(itemId);
                return;
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

    document.addEventListener('change', function (event) {
        const itemInfoTarget = event.target.closest('[data-inventory-update-item]');
        if (itemInfoTarget) {
            if (typeof window.updateItemInfo !== 'function') {
                return;
            }

            window.updateItemInfo(itemInfoTarget.value);
            return;
        }

        const quantityLabelTarget = event.target.closest('[data-inventory-quantity-label]');
        if (quantityLabelTarget) {
            if (typeof window.updateQuantityLabel !== 'function') {
                return;
            }

            window.updateQuantityLabel();
        }
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
