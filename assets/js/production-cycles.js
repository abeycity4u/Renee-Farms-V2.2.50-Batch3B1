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

(function () {
    const cycle = document.getElementById('acquisitionCycle');
    const type = document.getElementById('acquisitionType');
    if (!cycle || !type) return;
    const sync = function () {
        const selected = cycle.options[cycle.selectedIndex];
        const productionType = selected ? selected.getAttribute('data-production-type') : '';
        Array.from(type.options).forEach(function (option) {
            if (option.getAttribute('data-layer-only') === '1') {
                option.hidden = productionType === 'broiler';
                option.disabled = productionType === 'broiler';
                if (productionType === 'broiler' && option.selected) type.value = '';
            }
        });
    };
    cycle.addEventListener('change', sync);
    sync();
})();

(function () {
    const qty = document.querySelector('input[name="acquisition_quantity"]');
    const unit = document.getElementById('acquisitionUnitPrice');
    const total = document.getElementById('acquisitionTotalCost');
    if (!qty || !unit || !total) return;
    let totalManuallyEdited = false;
    total.addEventListener('input', function () { totalManuallyEdited = total.value !== ''; });
    const recalc = function () {
        if (totalManuallyEdited) return;
        const q = Number(qty.value);
        const u = Number(unit.value);
        if (q > 0 && u >= 0 && unit.value !== '') total.value = (q * u).toFixed(2);
        else if (!unit.value) total.value = '';
    };
    qty.addEventListener('input', recalc);
    unit.addEventListener('input', function () { totalManuallyEdited = false; recalc(); });
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
