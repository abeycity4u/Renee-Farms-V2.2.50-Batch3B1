(function () {
    'use strict';

    function productInputs(scope) {
        return Array.from(
            scope.querySelectorAll(
                'input[type="checkbox"][name="modules[]"],' +
                'input[type="checkbox"][name="approved_modules[]"]'
            )
        ).filter(function (input) {
            return [
                'sales',
                'poultry',
                'ruminant'
            ].includes(
                String(input.value || '')
                    .toLowerCase()
            );
        });
    }

    function reconcile(scope, changed) {
        var inputs =
            productInputs(scope);

        var sales =
            inputs.find(function (input) {
                return String(input.value || '')
                    .toLowerCase() === 'sales';
            });

        if (!sales) {
            return;
        }

        var livestock =
            inputs.filter(function (input) {
                return [
                    'poultry',
                    'ruminant'
                ].includes(
                    String(input.value || '')
                        .toLowerCase()
                );
            });

        var changedCode =
            changed
                ? String(changed.value || '')
                    .toLowerCase()
                : '';

        if (
            changed
            && changed.checked
            && changedCode === 'sales'
        ) {
            livestock.forEach(function (input) {
                input.checked =
                    false;
            });
        }

        if (
            changed
            && changed.checked
            && [
                'poultry',
                'ruminant'
            ].includes(changedCode)
        ) {
            sales.checked =
                false;
        }

        var livestockSelected =
            livestock.some(function (input) {
                return input.checked;
            });

        if (livestockSelected) {
            sales.checked =
                false;
        }

        sales.disabled =
            livestockSelected;

        sales.setAttribute(
            'aria-disabled',
            livestockSelected
                ? 'true'
                : 'false'
        );

        sales.title =
            livestockSelected
                ? 'Sales is already included with Poultry or Ruminant.'
                : '';
    }

    document.addEventListener(
        'DOMContentLoaded',
        function () {
            document.querySelectorAll('form')
                .forEach(function (form) {
                    if (
                        productInputs(form).length
                    ) {
                        reconcile(
                            form,
                            null
                        );
                    }
                });
        }
    );

    document.addEventListener(
        'change',
        function (event) {
            var changed =
                event.target;

            if (
                !(changed instanceof HTMLInputElement)
                || changed.type !== 'checkbox'
                || ![
                    'modules[]',
                    'approved_modules[]'
                ].includes(changed.name)
            ) {
                return;
            }

            var code =
                String(changed.value || '')
                    .toLowerCase();

            if (
                ![
                    'sales',
                    'poultry',
                    'ruminant'
                ].includes(code)
            ) {
                return;
            }

            reconcile(
                changed.closest('form')
                    || document,
                changed
            );
        }
    );
})();
