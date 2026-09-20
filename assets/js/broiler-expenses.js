/**
 * Broiler Expenses filtered-page behavior.
 *
 * Row action visibility is server-rendered from canonical operational
 * permissions. These handlers do not make permission decisions.
 */
window.BroilerExpensesConfig =
    window.BroilerExpensesConfig
    || {
        csrfToken:
            ''
    };


(function () {
    const config =
        document.getElementById(
            'broilerExpensesConfig'
        );

    if (!config) {
        return;
    }

    window.BroilerExpensesConfig = {
        csrfToken:
            config.dataset.csrfToken
            || ''
    };


    const monthSelector =
        document.getElementById(
            'monthSelector'
        );

    if (monthSelector) {
        monthSelector.addEventListener(
            'change',
            function () {
                window.location.href =
                    'broiler_expenses.php?month='
                    + this.value.substring(
                        0,
                        7
                    );
            }
        );
    }


    async function parseJsonResponse(
        response
    ) {
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


    const editExpenseForm =
        document.getElementById(
            'editExpenseForm'
        );

    const editExpenseModal =
        document.getElementById(
            'editExpenseModal'
        );


    if (
        editExpenseForm
        &&
        editExpenseModal
    ) {
        attachEditModal({
            buttonSelector:
                '.edit-expense-btn',

            modalSelector:
                '#editExpenseModal',

            fieldMap: {
                id:
                    '#editExpenseId',

                date:
                    '#editExpenseDate',

                category:
                    '#editCategory',

                amount:
                    '#editAmount',

                unit:
                    '#editUnit',

                description:
                    '#editDescription',

                cycle:
                    '#editExpenseCycle',

                poultry:
                    '#editPoultryCategory'
            }
        });


        editExpenseForm.addEventListener(
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
                        editExpenseForm
                    );

                formData.set(
                    'csrf_token',
                    window.BroilerExpensesConfig.csrfToken
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


    async function deleteExpense(
        expenseId
    ) {
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
                    String(
                        expenseId
                    ),

                csrf_token:
                    window.BroilerExpensesConfig.csrfToken,

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


    window.deleteExpense =
        deleteExpense;

})();
