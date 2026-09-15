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

    const birdCostBasisWrap = document.getElementById('birdCostBasisWrap');

    const render = () => {
        const selectedFarm = farmType.value || 'poultry';
        if (birdCostBasisWrap) birdCostBasisWrap.style.display = selectedFarm === 'poultry' ? '' : 'none';
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
        const unitPrice = document.getElementById('createPoultryUnitPrice');
        const totalCost = document.getElementById('createPoultryTotalCost');
        const costHelp = document.getElementById('createPoultryCostHelp');
        const openingHeadcount = document.querySelector('input[name="opening_headcount"]');

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
                    ? 'Internal carry-in may remain uncosted until a defensible cost basis exists.'
                    : 'Purchased entries require the actual total bird acquisition amount.';
            }
        };

        let totalManuallyEdited = false;

        if (totalCost) {
            totalCost.addEventListener('input', function () {
                totalManuallyEdited = totalCost.value !== '';
            });
        }

        const recalc = function () {
            if (
                totalManuallyEdited
                || !unitPrice
                || !totalCost
                || !openingHeadcount
            ) return;

            const quantity = Number(openingHeadcount.value);
            const unit = Number(unitPrice.value);

            if (quantity > 0 && unit >= 0 && unitPrice.value !== '') {
                totalCost.value = (quantity * unit).toFixed(2);
            } else if (!unitPrice.value) {
                totalCost.value = '';
            }
        };

        farmType.addEventListener('change', sync);
        productionType.addEventListener('change', sync);
        acquisitionType.addEventListener('change', sync);

        if (openingHeadcount) {
            openingHeadcount.addEventListener('input', recalc);
        }

        if (unitPrice) {
            unitPrice.addEventListener('input', function () {
                totalManuallyEdited = false;
                recalc();
            });
        }

        sync();
    });
})();


/**
 * Keep the long setup/maintenance area out of the way by default while still
 * opening it when an admin deliberately targets one of its tools.
 */
(function () {
    const openTargetedCycleTools = function () {
        const tools = document.getElementById('cycle-tools');
        if (!tools || !window.location.hash) return;

        let target = null;

        try {
            target = document.querySelector(window.location.hash);
        } catch (error) {
            return;
        }

        if (target && tools.contains(target)) {
            tools.open = true;
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        const tools = document.getElementById('cycle-tools');

        document.querySelectorAll('[data-open-cycle-tools]').forEach(function (link) {
            link.addEventListener('click', function () {
                if (tools) tools.open = true;
            });
        });

        openTargetedCycleTools();
    });

    window.addEventListener('hashchange', openTargetedCycleTools);
})();
