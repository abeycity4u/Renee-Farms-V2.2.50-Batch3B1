(function () {
    'use strict';

    const form =
        document.getElementById(
            'stockConsumptionAllocationWorkspaceForm'
        );

    if (!form) {
        return;
    }

    function moneyStringToCents(value) {
        const normalized =
            String(
                value ?? ''
            ).trim();

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

    function centsToMoneyString(cents) {
        return (
            Math.max(
                0,
                cents
            )
            / 100
        ).toFixed(2);
    }

    function displayMoney(cents) {
        return (
            '₦'
            +
            (
                cents
                / 100
            ).toLocaleString(
                'en-NG',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            )
        );
    }

    const parentCents =
        moneyStringToCents(
            form.dataset.parentAmount
            || '0'
        );

    const hasRevision =
        form.dataset.hasRevision
            === '1';

    const amountInputs =
        Array.from(
            form.querySelectorAll(
                '.stock-consumption-allocation-amount'
            )
        );

    const hasAllocationTargets =
        amountInputs.length > 0;

    const noteInputs =
        Array.from(
            form.querySelectorAll(
                '.stock-consumption-allocation-note'
            )
        );

    const percentDisplays =
        Array.from(
            form.querySelectorAll(
                '.stock-consumption-allocation-percent'
            )
        );

    const noteMap =
        new Map();

    const percentMap =
        new Map();

    noteInputs.forEach(
        (input) => {
            noteMap.set(
                String(
                    input.dataset.cycleId
                    || ''
                ),
                input
            );
        }
    );

    percentDisplays.forEach(
        (display) => {
            percentMap.set(
                String(
                    display.dataset.cycleId
                    || ''
                ),
                display
            );
        }
    );

    const allocatedDisplay =
        document.getElementById(
            'stockAllocationAllocatedDisplay'
        );

    const remainderDisplay =
        document.getElementById(
            'stockAllocationRemainderDisplay'
        );

    const messageBox =
        document.getElementById(
            'stockConsumptionAllocationWorkspaceMessage'
        );

    const saveButton =
        document.getElementById(
            'stockConsumptionAllocationSaveButton'
        );

    const retainSharedButton =
        document.getElementById(
            'stockConsumptionAllocationRetainSharedButton'
        );

    const reasonInput =
        document.getElementById(
            'stockConsumptionAllocationRevisionReason'
        );

    const equalSplitCheckbox =
        document.getElementById(
            'stockConsumptionAllocationEqualSplit'
        );

    const clearAmountsButton =
        document.getElementById(
            'stockConsumptionAllocationClearAmounts'
        );

    let manualAmountSnapshot =
        null;

    function notify(type, message) {
        if (
            typeof window.showAlert
                === 'function'
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
            typeof window.AppNotify.show
                === 'function'
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
            +
            (
                type === 'success'
                    ? 'success'
                    : 'danger'
            );

        messageBox.textContent =
            message;
    }

    function currentTotalCents() {
        return amountInputs.reduce(
            (total, input) => {
                return (
                    total
                    +
                    moneyStringToCents(
                        input.value
                        || '0'
                    )
                );
            },
            0
        );
    }

    function refreshPercentages() {
        amountInputs.forEach(
            (input) => {
                const cycleId =
                    String(
                        input.dataset.cycleId
                        || ''
                    );

                const display =
                    percentMap.get(
                        cycleId
                    );

                if (!display) {
                    return;
                }

                const amountCents =
                    moneyStringToCents(
                        input.value
                        || '0'
                    );

                const percent =
                    parentCents > 0
                        ? (
                            amountCents
                            / parentCents
                        ) * 100
                        : 0;

                display.textContent =
                    percent.toFixed(4)
                    + '%';
            }
        );
    }

    function refreshSummary() {
        const allocatedCents =
            currentTotalCents();

        const remainderCents =
            parentCents
            - allocatedCents;

        if (allocatedDisplay) {
            allocatedDisplay.textContent =
                displayMoney(
                    allocatedCents
                );
        }

        if (remainderDisplay) {
            remainderDisplay.textContent =
                displayMoney(
                    Math.max(
                        0,
                        remainderCents
                    )
                );

            remainderDisplay.classList.toggle(
                'text-danger',
                remainderCents < 0
            );
        }

        if (saveButton) {
            saveButton.disabled =
                !hasAllocationTargets
                ||
                remainderCents < 0;
        }

        if (
            retainSharedButton
            &&
            form.dataset.retainedShared !== '1'
        ) {
            retainSharedButton.disabled =
                allocatedCents > 0;
        }

        refreshPercentages();
    }

    function splitEqually() {
        if (
            !equalSplitCheckbox
            ||
            amountInputs.length < 1
        ) {
            return;
        }

        manualAmountSnapshot =
            amountInputs.map(
                (input) =>
                    input.value
            );

        const baseCents =
            Math.floor(
                parentCents
                / amountInputs.length
            );

        const remainderCents =
            parentCents
            % amountInputs.length;

        amountInputs.forEach(
            (input, index) => {
                const cycleCents =
                    baseCents
                    +
                    (
                        index
                            < remainderCents
                            ? 1
                            : 0
                    );

                input.value =
                    centsToMoneyString(
                        cycleCents
                    );

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
                        < manualAmountSnapshot.length
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

    amountInputs.forEach(
        (input) => {
            input.addEventListener(
                'input',
                refreshSummary
            );
        }
    );

    if (equalSplitCheckbox) {
        equalSplitCheckbox.addEventListener(
            'change',
            function () {
                if (
                    equalSplitCheckbox.checked
                ) {
                    splitEqually();
                } else {
                    restoreManualAmounts();
                }
            }
        );
    }

    if (clearAmountsButton) {
        clearAmountsButton.addEventListener(
            'click',
            function () {
                if (equalSplitCheckbox) {
                    equalSplitCheckbox.checked =
                        false;
                }

                manualAmountSnapshot =
                    null;

                amountInputs.forEach(
                    (input) => {
                        input.readOnly =
                            false;

                        input.value =
                            '0.00';
                    }
                );

                refreshSummary();
            }
        );
    }

    refreshSummary();

    form.addEventListener(
        'submit',
        async function (event) {
            event.preventDefault();

            const submitter =
                event.submitter || null;

            const decisionAction =
                String(
                    submitter
                    &&
                    submitter.dataset
                        ? (
                            submitter.dataset.allocationDecision
                            || 'allocate'
                        )
                        : 'allocate'
                ).trim()
                || 'allocate';

            const allocatedCents =
                currentTotalCents();

            if (
                decisionAction === 'retain_shared'
                &&
                allocatedCents > 0
            ) {
                notify(
                    'error',
                    'Clear cycle amounts before retaining this consumed stock cost as shared.'
                );

                return;
            }

            if (
                allocatedCents
                    > parentCents
            ) {
                notify(
                    'error',
                    'Total allocations cannot exceed the consumed stock cost.'
                );

                return;
            }

            const reason =
                String(
                    reasonInput
                        ? reasonInput.value
                        : ''
                ).trim();

            if (
                (
                    hasRevision
                    ||
                    decisionAction === 'retain_shared'
                )
                &&
                reason === ''
            ) {
                notify(
                    'error',
                    decisionAction === 'retain_shared'
                        ? 'Enter a reason for keeping this consumed stock cost at shared-operation level.'
                        : 'Enter a reason for changing this stock allocation.'
                );

                if (reasonInput) {
                    reasonInput.focus();
                }

                return;
            }

            const rows = [];

            amountInputs.forEach(
                (input) => {
                    const amountCents =
                        moneyStringToCents(
                            input.value
                            || '0'
                        );

                    if (
                        amountCents < 1
                    ) {
                        return;
                    }

                    const cycleId =
                        Number.parseInt(
                            input.dataset.cycleId
                            || '0',
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
                            centsToMoneyString(
                                amountCents
                            ),

                        notes:
                            noteInput
                                ? String(
                                    noteInput.value
                                    || ''
                                ).trim()
                                : ''
                    });
                }
            );

            const body =
                new FormData();

            body.set(
                'csrf_token',
                form.dataset.csrfToken
                || ''
            );

            body.set(
                'stock_transaction_id',
                form.dataset.stockTransactionId
                || ''
            );

            body.set(
                'decision_action',
                decisionAction
            );

            body.set(
                'revision_reason',
                reason
            );

            body.set(
                'rows_json',
                JSON.stringify(
                    rows
                )
            );

            const activeButton =
                submitter || saveButton;

            const originalText =
                activeButton
                    ? activeButton.innerHTML
                    : '';

            if (activeButton) {
                activeButton.disabled =
                    true;

                activeButton.innerHTML =
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

                let result =
                    null;

                try {
                    result =
                        await response.json();

                } catch (ignored) {
                    result =
                        null;
                }

                if (
                    !response.ok
                    ||
                    !result
                    ||
                    result.success
                        !== true
                ) {
                    throw new Error(
                        result
                        &&
                        result.error
                            ? result.error
                            : 'The consumed stock cost allocation could not be saved.'
                    );
                }

                notify(
                    'success',
                    result.message
                    || 'Consumed stock cost allocation saved successfully.'
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
                    &&
                    error.message
                        ? error.message
                        : 'The consumed stock cost allocation could not be saved.'
                );

                if (activeButton) {
                    activeButton.disabled =
                        false;

                    activeButton.innerHTML =
                        originalText;
                }

                refreshSummary();
            }
        }
    );
})();
