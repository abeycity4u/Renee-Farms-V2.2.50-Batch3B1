/**
 * Poultry Expenses consolidated workspace.
 *
 * Presentation / interaction only:
 * - month navigation;
 * - Add/Edit Production Type -> Production Cycle dependency;
 * - row-level Edit/Delete requests;
 * - Poultry Shared has no direct cycle.
 *
 * Financial ownership, authorization, integrity and revision authority remain
 * server-side.
 */

(function () {
    const config =
        document.getElementById(
            'poultryExpensesHubConfig'
        );

    if (!config) {
        return;
    }

    const activeTab =
        config.dataset.activeTab
        || 'all';

    const csrfToken =
        config.dataset.csrfToken
        || '';


    async function parseJsonResponse(response) {
        const contentType =
            response.headers.get(
                'content-type'
            )
            || '';

        if (
            !contentType.includes(
                'application/json'
            )
        ) {
            const text =
                await response.text();

            throw new Error(
                text
                || 'Unexpected non-JSON response'
            );
        }

        return response.json();
    }


    function refreshCycleOptions(
        productionSelect,
        cycleSelect,
        cycleHelp,
        requestedCycleId = null
    ) {
        if (
            !productionSelect
            ||
            !cycleSelect
        ) {
            return;
        }

        const productionType =
            productionSelect.value;

        const matching = [];

        Array.from(
            cycleSelect.options
        ).forEach(
            function (option) {
                const belongs =
                    option.dataset.productionType
                    === productionType;

                option.hidden =
                    !belongs;

                option.disabled =
                    !belongs;

                if (belongs) {
                    matching.push(
                        option
                    );
                }
            }
        );

        const shared =
            productionType === 'shared';

        cycleSelect.disabled =
            shared;

        let selected = null;

        if (
            requestedCycleId !== null
            &&
            requestedCycleId !== undefined
        ) {
            const wanted =
                String(
                    requestedCycleId
                );

            selected =
                matching.find(
                    function (option) {
                        return option.value === wanted;
                    }
                )
                || null;
        }

        if (
            !selected
            &&
            matching.length > 0
        ) {
            selected =
                matching[0];
        }

        if (selected) {
            selected.selected =
                true;
        }

        if (cycleHelp) {
            cycleHelp.textContent =
                shared
                    ? 'Poultry-wide shared — no specific production cycle.'
                    : 'Choose a cycle only when the expense belongs directly to it.';
        }
    }


    /*
     * Month navigation.
     */
    const monthInput =
        document.getElementById(
            'poultryExpenseMonth'
        );

    if (monthInput) {
        monthInput.addEventListener(
            'change',
            function () {
                const month =
                    String(
                        this.value
                        || ''
                    ).substring(
                        0,
                        7
                    );

                const params =
                    new URLSearchParams();

                params.set(
                    'month',
                    month
                );

                params.set(
                    'tab',
                    activeTab
                );

                window.location.href =
                    'expenses.php?'
                    + params.toString();
            }
        );
    }


    /*
     * Add form dependent cycle selector.
     */
    const addProductionSelect =
        document.getElementById(
            'addPoultryProductionType'
        );

    const addCycleSelect =
        document.getElementById(
            'addPoultryExpenseCycle'
        );

    const addCycleHelp =
        document.getElementById(
            'addPoultryExpenseCycleHelp'
        );

    if (
        addProductionSelect
        &&
        addCycleSelect
    ) {
        addProductionSelect.addEventListener(
            'change',
            function () {
                refreshCycleOptions(
                    addProductionSelect,
                    addCycleSelect,
                    addCycleHelp
                );
            }
        );

        refreshCycleOptions(
            addProductionSelect,
            addCycleSelect,
            addCycleHelp
        );
    }


    /*
     * Edit form.
     *
     * The hub intentionally does not submit poultry_category. The canonical
     * update API derives that legacy compatibility field from production_type.
     */
    const editForm =
        document.getElementById(
            'editPoultryExpenseForm'
        );

    const editId =
        document.getElementById(
            'editPoultryExpenseId'
        );

    const editDate =
        document.getElementById(
            'editPoultryExpenseDate'
        );

    const editProduction =
        document.getElementById(
            'editPoultryProductionType'
        );

    const editCycle =
        document.getElementById(
            'editPoultryExpenseCycle'
        );

    const editCycleHelp =
        document.getElementById(
            'editPoultryExpenseCycleHelp'
        );

    const editCategory =
        document.getElementById(
            'editPoultryExpenseCategory'
        );

    const editUnit =
        document.getElementById(
            'editPoultryExpenseUnit'
        );

    const editAmount =
        document.getElementById(
            'editPoultryExpenseAmount'
        );

    const editDescription =
        document.getElementById(
            'editPoultryExpenseDescription'
        );


    document.querySelectorAll(
        '.poultry-expense-edit-btn'
    ).forEach(
        function (button) {
            button.addEventListener(
                'click',
                function () {
                    if (
                        !editForm
                        ||
                        !editProduction
                        ||
                        !editCycle
                    ) {
                        return;
                    }

                    if (editId) {
                        editId.value =
                            button.dataset.expenseId
                            || '';
                    }

                    if (editDate) {
                        editDate.value =
                            button.dataset.expenseDate
                            || '';
                    }

                    editProduction.value =
                        button.dataset.productionType
                        || '';

                    refreshCycleOptions(
                        editProduction,
                        editCycle,
                        editCycleHelp,
                        button.dataset.cycleId
                        || '0'
                    );

                    if (editCategory) {
                        editCategory.value =
                            button.dataset.category
                            || 'misc';
                    }

                    if (editUnit) {
                        editUnit.value =
                            button.dataset.unit
                            || '1';
                    }

                    if (editAmount) {
                        editAmount.value =
                            button.dataset.amount
                            || '';
                    }

                    if (editDescription) {
                        editDescription.value =
                            button.dataset.description
                            || '';
                    }
                }
            );
        }
    );


    if (
        editProduction
        &&
        editCycle
    ) {
        editProduction.addEventListener(
            'change',
            function () {
                refreshCycleOptions(
                    editProduction,
                    editCycle,
                    editCycleHelp
                );
            }
        );
    }


    if (editForm) {
        editForm.addEventListener(
            'submit',
            async function (event) {
                event.preventDefault();

                const revisionReason =
                    await AppConfirm.askReason(
                        'Why are you changing this expense?',
                        {
                            title:
                                'Reason for expense change',

                            confirmText:
                                'Save changes',

                            tone:
                                'primary'
                        }
                    );

                if (revisionReason === null) {
                    return;
                }

                const formData =
                    new FormData(
                        editForm
                    );

                /*
                 * Disabled controls are omitted from FormData. For Shared,
                 * explicitly send cycle 0; the API also enforces this.
                 */
                if (
                    editProduction
                    &&
                    editProduction.value === 'shared'
                ) {
                    formData.set(
                        'cycle_id',
                        '0'
                    );
                }

                formData.set(
                    'csrf_token',
                    csrfToken
                );

                formData.set(
                    'permission_scope',
                    'operational'
                );

                formData.set(
                    'revision_reason',
                    revisionReason
                );

                try {
                    const response =
                        await fetch(
                            '../api/update_expense.php',
                            {
                                method:
                                    'POST',

                                body:
                                    formData
                            }
                        );

                    const result =
                        await parseJsonResponse(
                            response
                        );

                    if (result.success) {
                        window.location.reload();
                        return;
                    }

                    AppNotify.error(
                        result.error
                        ||
                        result.message
                        ||
                        'Unable to update expense'
                    );

                } catch (error) {
                    AppNotify.error(
                        'Network error: '
                        + error.message
                    );
                }
            }
        );
    }


    /*
     * Row delete.
     */
    document.querySelectorAll(
        '.poultry-expense-delete-btn'
    ).forEach(
        function (button) {
            button.addEventListener(
                'click',
                async function () {
                    const expenseId =
                        button.dataset.expenseId
                        || '';

                    if (!expenseId) {
                        return;
                    }

                    const revisionReason =
                        await AppConfirm.askReason(
                            'Why are you deleting this expense record?',
                            {
                                title:
                                    'Delete expense record?',

                                confirmText:
                                    'Delete',

                                tone:
                                    'danger'
                            }
                        );

                    if (revisionReason === null) {
                        return;
                    }

                    const params =
                        new URLSearchParams({
                            id:
                                expenseId,

                            csrf_token:
                                csrfToken,

                            permission_scope:
                                'operational',

                            revision_reason:
                                revisionReason
                        });

                    try {
                        const response =
                            await fetch(
                                '../api/delete_expense.php',
                                {
                                    method:
                                        'POST',

                                    headers: {
                                        'Content-Type':
                                            'application/x-www-form-urlencoded'
                                    },

                                    body:
                                        params.toString()
                                }
                            );

                        const result =
                            await parseJsonResponse(
                                response
                            );

                        if (result.success) {
                            window.location.reload();
                            return;
                        }

                        AppNotify.error(
                            result.error
                            ||
                            result.message
                            ||
                            'Unable to delete expense'
                        );

                    } catch (error) {
                        AppNotify.error(
                            'Network error: '
                            + error.message
                        );
                    }
                }
            );
        }
    );


    /*
     * Deep-link into Add modal. D2B3 will use this for the old Layer/Broiler
     * Add Expense shortcuts.
     */
    const params =
        new URLSearchParams(
            window.location.search
        );

    if (
        params.get(
            'add'
        ) === '1'
    ) {
        const requestedProduction =
            String(
                params.get(
                    'production_type'
                )
                || ''
            ).toLowerCase();

        if (
            addProductionSelect
            &&
            requestedProduction
            &&
            Array.from(
                addProductionSelect.options
            ).some(
                function (option) {
                    return option.value
                        === requestedProduction;
                }
            )
        ) {
            addProductionSelect.value =
                requestedProduction;

            refreshCycleOptions(
                addProductionSelect,
                addCycleSelect,
                addCycleHelp
            );
        }

        const modalElement =
            document.getElementById(
                'addPoultryExpenseModal'
            );

        if (
            modalElement
            &&
            window.bootstrap
            &&
            window.bootstrap.Modal
        ) {
            window.bootstrap.Modal
                .getOrCreateInstance(
                    modalElement
                )
                .show();
        }
    }
})();
