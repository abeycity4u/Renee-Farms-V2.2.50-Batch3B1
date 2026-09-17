(function () {
    'use strict';

    const form =
        document.getElementById(
            'financialAllocationWorkspaceForm'
        );

    if (!form) {
        return;
    }

    const gross =
        Number.parseFloat(
            form.dataset.gross || '0'
        ) || 0;

    function moneyStringToCents(value) {
        const normalized =
            String(value || '').trim();

        if (
            !/^\d+(?:\.\d{1,2})?$/.test(
                normalized
            )
        ) {
            return 0;
        }

        const parts =
            normalized.split('.');

        const whole =
            Number.parseInt(
                parts[0] || '0',
                10
            );

        const fraction =
            Number.parseInt(
                String(
                    parts[1] || ''
                ).padEnd(2, '0'),
                10
            );

        return (
            whole * 100
            +
            (
                Number.isFinite(fraction)
                    ? fraction
                    : 0
            )
        );
    }

    const grossCents =
        moneyStringToCents(
            form.dataset.gross || '0'
        );

    const mutationBlocked =
        form.dataset.mutationBlocked === '1';

    const amountInputs =
        Array.from(
            form.querySelectorAll(
                '.financial-allocation-amount'
            )
        );

    const noteInputs =
        Array.from(
            form.querySelectorAll(
                '.financial-allocation-note'
            )
        );

    const allocatedDisplay =
        document.getElementById(
            'allocationAllocatedDisplay'
        );

    const remainderDisplay =
        document.getElementById(
            'allocationRemainderDisplay'
        );

    const messageBox =
        document.getElementById(
            'financialAllocationWorkspaceMessage'
        );

    const saveButton =
        document.getElementById(
            'financialAllocationSaveButton'
        );

    const reasonInput =
        document.getElementById(
            'financialAllocationRevisionReason'
        );

    const equalSplitCheckbox =
        document.getElementById(
            'financialAllocationEqualSplit'
        );

    let manualAmountSnapshot =
        null;

    const noteMap =
        new Map();

    noteInputs.forEach((input) => {
        noteMap.set(
            String(input.dataset.cycleId || ''),
            input
        );
    });

    function money(value) {
        return '₦'
            + Number(value).toLocaleString(
                'en-NG',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );
    }

    function notify(type, message) {
        if (
            typeof window.showAlert === 'function'
        ) {
            window.showAlert(
                type === 'error'
                    ? 'danger'
                    : type,
                message
            );

            return;
        }

        if (
            window.AppNotify
            &&
            typeof window.AppNotify.show === 'function'
        ) {
            window.AppNotify.show(
                type,
                message
            );

            return;
        }

        if (!messageBox) {
            return;
        }

        messageBox.className =
            'alert alert-'
            + (
                type === 'success'
                    ? 'success'
                    : 'danger'
            );

        messageBox.textContent =
            message;
    }

    function splitGrossEqually() {
        if (
            mutationBlocked
            ||
            !equalSplitCheckbox
            ||
            amountInputs.length < 1
        ) {
            return;
        }

        manualAmountSnapshot =
            amountInputs.map(
                (input) => input.value
            );

        const baseCents =
            Math.floor(
                grossCents
                /
                amountInputs.length
            );

        const remainderCents =
            grossCents
            %
            amountInputs.length;

        amountInputs.forEach(
            (input, index) => {
                const cycleCents =
                    baseCents
                    +
                    (
                        index < remainderCents
                            ? 1
                            : 0
                    );

                input.value =
                    (
                        cycleCents
                        /
                        100
                    ).toFixed(2);

                input.readOnly =
                    true;
            }
        );

        refreshSummary();
    }

    function restoreManualAmounts() {
        amountInputs.forEach(
            (input, index) => {
                input.readOnly =
                    false;

                if (
                    Array.isArray(
                        manualAmountSnapshot
                    )
                    &&
                    index
                        <
                        manualAmountSnapshot.length
                ) {
                    input.value =
                        manualAmountSnapshot[
                            index
                        ];
                }
            }
        );

        manualAmountSnapshot =
            null;

        refreshSummary();
    }

    function currentTotal() {
        return amountInputs.reduce(
            (total, input) => {
                const value =
                    Number.parseFloat(
                        input.value || '0'
                    );

                if (
                    !Number.isFinite(value)
                    ||
                    value <= 0
                ) {
                    return total;
                }

                return total + value;
            },
            0
        );
    }

    function refreshSummary() {
        const allocated =
            currentTotal();

        const remainder =
            gross - allocated;

        if (allocatedDisplay) {
            allocatedDisplay.textContent =
                money(allocated);
        }

        if (remainderDisplay) {
            remainderDisplay.textContent =
                money(
                    Math.max(
                        0,
                        remainder
                    )
                );

            remainderDisplay.classList.toggle(
                'text-danger',
                remainder < -0.005
            );
        }

        if (saveButton) {
            saveButton.disabled =
                mutationBlocked
                ||
                remainder < -0.005;
        }
    }

    amountInputs.forEach((input) => {
        input.addEventListener(
            'input',
            refreshSummary
        );
    });

    if (equalSplitCheckbox) {
        equalSplitCheckbox.addEventListener(
            'change',
            function () {
                if (
                    equalSplitCheckbox.checked
                ) {
                    splitGrossEqually();
                } else {
                    restoreManualAmounts();
                }
            }
        );
    }

    refreshSummary();

    form.addEventListener(
        'submit',
        async function (event) {
            event.preventDefault();

            if (mutationBlocked) {
                notify(
                    'error',
                    'This expense has individual-animal allocations and cannot also be allocated to production cycles.'
                );

                return;
            }

            const allocated =
                currentTotal();

            if (allocated > gross + 0.005) {
                notify(
                    'error',
                    'Total allocations cannot exceed the parent expense total.'
                );

                return;
            }

            const reason =
                String(
                    reasonInput
                        ? reasonInput.value
                        : ''
                ).trim();

            if (reason === '') {
                notify(
                    'error',
                    'Enter a reason for changing this expense allocation.'
                );

                if (reasonInput) {
                    reasonInput.focus();
                }

                return;
            }

            const rows = [];

            amountInputs.forEach((input) => {
                const amount =
                    Number.parseFloat(
                        input.value || '0'
                    );

                if (
                    !Number.isFinite(amount)
                    ||
                    amount <= 0
                ) {
                    return;
                }

                const cycleId =
                    Number.parseInt(
                        input.dataset.cycleId || '0',
                        10
                    );

                if (cycleId < 1) {
                    return;
                }

                const noteInput =
                    noteMap.get(
                        String(cycleId)
                    );

                rows.push({
                    cycle_id:
                        cycleId,

                    allocated_amount:
                        amount.toFixed(2),

                    notes:
                        noteInput
                            ? String(
                                noteInput.value || ''
                            ).trim()
                            : ''
                });
            });

            const body =
                new FormData();

            body.set(
                'csrf_token',
                form.dataset.csrfToken || ''
            );

            body.set(
                'expense_id',
                form.dataset.expenseId || ''
            );

            body.set(
                'permission_scope',
                form.dataset.permissionScope
                    || 'operational'
            );

            body.set(
                'revision_reason',
                reason
            );

            body.set(
                'rows_json',
                JSON.stringify(rows)
            );

            const originalText =
                saveButton
                    ? saveButton.innerHTML
                    : '';

            if (saveButton) {
                saveButton.disabled =
                    true;

                saveButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Saving...';
            }

            try {
                const response =
                    await fetch(
                        form.dataset.endpoint,
                        {
                            method:
                                'POST',

                            headers: {
                                Accept:
                                    'application/json'
                            },

                            body
                        }
                    );

                let result = null;

                try {
                    result =
                        await response.json();

                } catch (ignored) {
                    result = null;
                }

                if (
                    !response.ok
                    ||
                    !result
                    ||
                    result.success !== true
                ) {
                    throw new Error(
                        result
                        && result.error
                            ? result.error
                            : 'The shared cost allocation could not be saved.'
                    );
                }

                notify(
                    'success',
                    result.message
                        || 'Shared cost allocation saved successfully.'
                );

                setTimeout(
                    function () {
                        window.location.reload();
                    },
                    700
                );

            } catch (error) {
                notify(
                    'error',
                    error
                    && error.message
                        ? error.message
                        : 'The shared cost allocation could not be saved.'
                );

                if (saveButton) {
                    saveButton.disabled =
                        false;

                    saveButton.innerHTML =
                        originalText;
                }
            }
        }
    );
})();
