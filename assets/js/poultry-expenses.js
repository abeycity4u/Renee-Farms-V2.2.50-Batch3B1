/**
 * Poultry Expenses consolidated workspace.
 *
 * Handles presentation-only behavior:
 * - month navigation;
 * - Production Type -> Production Cycle dependency;
 * - Poultry Shared has no direct cycle.
 *
 * Financial and permission authority remain server-side.
 */

(function () {
    const config =
        document.getElementById(
            'poultryExpensesHubConfig'
        );

    const activeTab =
        config?.dataset.activeTab
        || 'all';

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


    const productionSelect =
        document.getElementById(
            'addPoultryProductionType'
        );

    const cycleSelect =
        document.getElementById(
            'addPoultryExpenseCycle'
        );

    const cycleHelp =
        document.getElementById(
            'addPoultryExpenseCycleHelp'
        );


    function refreshCycleOptions() {
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

        if (matching.length > 0) {
            matching[0].selected =
                true;
        }

        if (cycleHelp) {
            cycleHelp.textContent =
                shared
                    ? 'Poultry-wide shared — no specific production cycle.'
                    : 'Choose a cycle only when the expense belongs directly to it.';
        }
    }


    if (
        productionSelect
        &&
        cycleSelect
    ) {
        productionSelect.addEventListener(
            'change',
            refreshCycleOptions
        );

        refreshCycleOptions();
    }


    const params =
        new URLSearchParams(
            window.location.search
        );

    if (
        params.get(
            'add'
        ) === '1'
    ) {
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
