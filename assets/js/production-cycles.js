/**
 * Production Cycles page behavior.
 * Externalized for CSP compatibility.
 */
document.addEventListener('DOMContentLoaded', function () {
    const farmType = document.querySelector('select[name="farm_type"]');
    const productionType = document.getElementById('productionType');
    if (!farmType || !productionType) return;

    const options = {
        poultry: ['layer', 'broiler'],
        ruminant: ['cattle', 'goat', 'sheep', 'other']
    };

    const render = () => {
        const selectedFarm = farmType.value || 'poultry';
        const requestedProduction = productionType.dataset.selected || productionType.value;
        productionType.innerHTML = '';
        (options[selectedFarm] || []).forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value.charAt(0).toUpperCase() + value.slice(1);
            if (value === requestedProduction) option.selected = true;
            productionType.appendChild(option);
        });
    };

    farmType.addEventListener('change', function () {
        productionType.dataset.selected = '';
        render();
    });
    render();
});

/**
 * New-cycle poultry onboarding.
 *
 * This controls presentation only. Server-side acquisition/lifecycle services
 * remain authoritative.
 */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const farmType = document.querySelector('select[name="farm_type"]');
        const productionType = document.getElementById('productionType');
        const wrap = document.getElementById('poultryCycleOnboardingWrap');
        const acquisitionType = document.getElementById('createPoultryAcquisitionType');
        const purchasedOption = document.getElementById('createPurchasedBirdsOption');
        const pointOfLayOption = document.getElementById('createPointOfLayOption');
        const initialPhase = document.getElementById('createPoultryInitialPhase');
        const totalCost = document.getElementById('createPoultryTotalCost');
        const costHelp = document.getElementById('createPoultryCostHelp');

        if (
            !farmType
            || !productionType
            || !wrap
            || !acquisitionType
            || !initialPhase
        ) return;

        const sync = function () {
            const isPoultry = farmType.value === 'poultry';
            wrap.style.display = isPoultry ? '' : 'none';

            wrap.querySelectorAll('input, select, textarea').forEach(function (control) {
                control.disabled = !isPoultry;
            });

            if (!isPoultry) return;

            const type = productionType.value;

            if (purchasedOption) {
                purchasedOption.textContent = type === 'broiler'
                    ? 'Purchased birds (DOC or older birds)'
                    : 'Purchased pullets / young birds';
            }

            if (pointOfLayOption) {
                const layerOnly = type !== 'layer';
                pointOfLayOption.hidden = layerOnly;
                pointOfLayOption.disabled = layerOnly;

                if (layerOnly && acquisitionType.value === 'purchased_point_of_lay') {
                    acquisitionType.value = 'purchased';
                }
            }

            let firstValidPhase = '';

            Array.from(initialPhase.options).forEach(function (option) {
                const valid = option.getAttribute('data-production-type') === type;
                option.hidden = !valid;
                option.disabled = !valid;
                if (valid && firstValidPhase === '') firstValidPhase = option.value;
            });

            const selectedPhase = initialPhase.options[initialPhase.selectedIndex];
            if (
                !selectedPhase
                || selectedPhase.disabled
                || selectedPhase.getAttribute('data-production-type') !== type
            ) {
                initialPhase.value = firstValidPhase;
            }

            if (
                acquisitionType.value === 'purchased_point_of_lay'
                && type === 'layer'
            ) {
                initialPhase.value = 'production';
            }

            acquisitionType.required = true;
            initialPhase.required = true;

            if (totalCost) {
                totalCost.required = acquisitionType.value !== 'internal_transfer';
            }

            if (costHelp) {
                costHelp.textContent = acquisitionType.value === 'internal_transfer'
                    ? 'Internal carry-in may remain uncosted until a defensible total acquisition value exists; Acquisition Cost / Bird remains unavailable until then.'
                    : 'Enter the actual total acquisition cost. Acquisition Cost / Bird is calculated automatically from total cost and Opening Headcount.';
            }
        };

        farmType.addEventListener('change', sync);
        productionType.addEventListener('change', sync);
        acquisitionType.addEventListener('change', sync);

        sync();
    });
})();


/**
 * Open Advanced Maintenance only when a deep link targets something inside it.
 * Create Cycle is intentionally visible directly on the page.
 */
(function () {
    const openTargetedMaintenance = function () {
        const maintenanceTools =
            document.getElementById(
                'cycle-maintenance-tools'
            );

        if (
            !maintenanceTools
            || !window.location.hash
        ) {
            return;
        }

        let target = null;

        try {
            target =
                document.querySelector(
                    window.location.hash
                );
        } catch (error) {
            return;
        }

        if (
            target
            && maintenanceTools.contains(target)
        ) {
            maintenanceTools.open = true;
        }
    };

    document.addEventListener(
        'DOMContentLoaded',
        openTargetedMaintenance
    );

    window.addEventListener(
        'hashchange',
        openTargetedMaintenance
    );
})();
