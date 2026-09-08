/**
 * Platform Farms subscription plan and seat UI.
 * Server-generated plan/seat data is supplied through
 * #subscriptionPlanSeatUiConfig.
 * Externalized for CSP compatibility.
 */
document.addEventListener('DOMContentLoaded', function () {
    const configElement =
        document.getElementById('subscriptionPlanSeatUiConfig');

    const form = document.getElementById('farmAccountForm');

    if (!configElement || !form) {
        return;
    }

    let catalog = {};
    let initialExtras = {};

    try {
        catalog = JSON.parse(
            configElement.dataset.planCatalog || '{}'
        );
    } catch (error) {
        catalog = {};
    }

    try {
        initialExtras = JSON.parse(
            configElement.dataset.seatAddons || '{}'
        );
    } catch (error) {
        initialExtras = {};
    }

    const plan = form.querySelector('select[name=plan]');

    const poultry = form.querySelector(
        'input[name="modules[]"][value=poultry]'
    );

    const ruminant = form.querySelector(
        'input[name="modules[]"][value=ruminant]'
    );

    const roles = {
        poultry_manager: poultry,
        ruminant_manager: ruminant,
        sales_rep: null,
        viewer: null
    };

    const extras = {};
    const includedDisplays = {};
    const totalDisplays = {};

    Object.keys(roles).forEach(function (role) {
        const total = form.querySelector(
            'input[name="role_limits[' + role + ']"]'
        );

        if (!total) {
            return;
        }

        total.readOnly = true;
        total.setAttribute('aria-readonly', 'true');
        total.setAttribute('tabindex', '-1');
        total.style.display = 'none';

        const ownLabel = total.previousElementSibling;

        if (ownLabel && ownLabel.tagName === 'LABEL') {
            ownLabel.textContent =
                (ownLabel.textContent || '').replace(
                    / \(.*\)$/g,
                    ''
                );
        }

        const summary = document.createElement('div');
        summary.className =
            'border rounded p-2 mb-2 bg-body-tertiary';

        const includedLine = document.createElement('div');
        includedLine.className = 'small';
        includedLine.innerHTML =
            'Included seats: <strong>0</strong>';

        const totalLine = document.createElement('div');
        totalLine.className = 'small mt-1';
        totalLine.innerHTML =
            'Total seats: <strong>0</strong>';

        summary.appendChild(includedLine);
        summary.appendChild(totalLine);

        total.insertAdjacentElement('afterend', summary);

        includedDisplays[role] =
            includedLine.querySelector('strong');

        totalDisplays[role] =
            totalLine.querySelector('strong');

        const wrap = document.createElement('div');
        wrap.className = 'mt-2';

        const label = document.createElement('label');
        label.className = 'form-label small mb-1';
        label.textContent = 'Extra seats';

        const addon = document.createElement('input');
        addon.className = 'form-control form-control-sm';
        addon.type = 'number';
        addon.min = '0';
        addon.max = '500';
        addon.name = 'seat_addons[' + role + ']';

        addon.value = String(
            Math.max(
                0,
                parseInt(initialExtras[role] || 0, 10) || 0
            )
        );

        addon.setAttribute('data-seat-addon', role);

        wrap.appendChild(label);
        wrap.appendChild(addon);

        summary.insertAdjacentElement('afterend', wrap);

        extras[role] = addon;
    });

    function refresh() {
        const definition =
            catalog[(plan && plan.value) || 'starter'] || {};

        const limits =
            definition.included_role_limits || {};

        const hasLivestock = !!(
            (poultry && poultry.checked)
            || (ruminant && ruminant.checked)
        );

        Object.keys(roles).forEach(function (role) {
            const total = form.querySelector(
                'input[name="role_limits[' + role + ']"]'
            );

            const addon = extras[role];

            if (!total || !addon) {
                return;
            }

            const moduleBox = roles[role];

            const relevant = moduleBox
                ? moduleBox.checked
                : hasLivestock;

            const included = relevant
                ? (parseInt(limits[role] || 0, 10) || 0)
                : 0;

            const extra = Math.max(
                0,
                parseInt(addon.value || 0, 10) || 0
            );

            const effective = relevant
                ? included + extra
                : 0;

            total.value = effective;

            if (includedDisplays[role]) {
                includedDisplays[role].textContent =
                    String(included);
            }

            if (totalDisplays[role]) {
                totalDisplays[role].textContent =
                    String(effective);
            }

            const col = total.closest('.col-sm-6');

            if (col) {
                col.style.display = relevant ? '' : 'none';
            }
        });
    }

    Object.keys(extras).forEach(function (role) {
        extras[role].addEventListener('input', refresh);
    });

    if (plan) {
        plan.addEventListener('change', refresh);
    }

    [poultry, ruminant].forEach(function (element) {
        if (element) {
            element.addEventListener('change', refresh);
        }
    });

    refresh();
});
