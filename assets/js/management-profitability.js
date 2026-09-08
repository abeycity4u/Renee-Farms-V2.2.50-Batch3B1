/**
 * Management Profitability filter behavior.
 * Dynamic cycle/selection state is supplied through page config.
 * Externalized for CSP compatibility.
 */
(function () {
    const productionTypes = {
        all: {},
        poultry: {layer:'Layer', broiler:'Broiler', shared:'Shared / Unallocated Poultry'},
        ruminant: {cattle:'Cattle', goat:'Goat', sheep:'Sheep', other:'Other', shared:'Shared / Unallocated Ruminant'},
        general: {general:'General / Other Farm Income'}
    };
    const configElement =
        document.getElementById('managementProfitabilityConfig');

    if (!configElement) return;

    let cycles = [];

    try {
        cycles = JSON.parse(
            configElement.dataset.cycles || '[]'
        );
    } catch (error) {
        cycles = [];
    }

    const selectedProductionType =
        configElement.dataset.productionType || 'all';

    const selectedCycleId =
        parseInt(configElement.dataset.cycleId || '0', 10) || 0;
    const farmSelect = document.getElementById('profitFarmType');
    const productionSelect = document.getElementById('profitProductionType');
    const cycleSelect = document.getElementById('profitCycleId');
    if (!farmSelect || !productionSelect || !cycleSelect) return;

    function rebuildCycles(selectedCycle) {
        const farm = farmSelect.value;
        const production = productionSelect.value;
        cycleSelect.innerHTML = '';
        const allCycleLabel = production !== 'all' ? `All ${production.replace(/_/g,' ')} cycles` : 'All cycles';
        cycleSelect.add(new Option(allCycleLabel, '0'));
        cycles.filter(c => (farm === 'all' || c.farm_type === farm) &&
                           (production === 'all' || c.production_type === production))
              .forEach(c => cycleSelect.add(new Option(`${c.cycle_code} — ${c.production_type} (${c.status})`, String(c.id))));
        const wanted = String(selectedCycle || '0');
        cycleSelect.value = Array.from(cycleSelect.options).some(o => o.value === wanted) ? wanted : '0';
    }

    function rebuildProduction(selectedProduction, selectedCycle) {
        const farm = farmSelect.value;
        productionSelect.innerHTML = '';
        productionSelect.add(new Option('All production types', 'all'));
        Object.entries(productionTypes[farm] || {}).forEach(([value,label]) => productionSelect.add(new Option(label,value)));
        const wanted = String(selectedProduction || 'all');
        productionSelect.value = Array.from(productionSelect.options).some(o => o.value === wanted) ? wanted : 'all';
        rebuildCycles(selectedCycle);
    }

    farmSelect.addEventListener('change', () => rebuildProduction('all', 0));
    productionSelect.addEventListener('change', () => rebuildCycles(0));
    rebuildProduction(selectedProductionType, selectedCycleId);
})();
