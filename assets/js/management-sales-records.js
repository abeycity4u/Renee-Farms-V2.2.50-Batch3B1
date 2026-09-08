/**
 * Sales Records browser behavior.
 * Dynamic server state is supplied through the managementSalesRecordsConfig
 * element so the page remains compatible with strict CSP.
 */
(function () {
    const configElement =
        document.getElementById('managementSalesRecordsConfig');

    if (!configElement) return;

    const parseJson = (value, fallback) => {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    };

    const salesRecordsConfig = {
        saleUnitPresets: parseJson(
            configElement.dataset.saleUnitPresets,
            []
        ),
        salesCycles: parseJson(
            configElement.dataset.salesCycles,
            []
        ),
        ruminantSaleAnimals: parseJson(
            configElement.dataset.ruminantSaleAnimals,
            []
        ),
        ruminantSaleAllocationMap: parseJson(
            configElement.dataset.ruminantSaleAllocationMap,
            {}
        ),
        ruminantSaleExitMap: parseJson(
            configElement.dataset.ruminantSaleExitMap,
            {}
        ),
        csrfToken: configElement.dataset.csrfToken || '',
        deleteSaleUrl: configElement.dataset.deleteSaleUrl || ''
    };

const saleUnitPresets = salesRecordsConfig.saleUnitPresets;
    function toggleCustomSaleUnit(selectSelector, customSelector) {
        const isCustom = $(selectSelector).val() === '__custom__';
        $(customSelector).toggle(isCustom).prop('required', isCustom);
        if (!isCustom) $(customSelector).val('');
    }
    function setEditSaleUnit(unit) {
        unit = String(unit || '').trim();
        $('#editSaleUnitLegacyHint').text('');
        if (!unit) {
            $('#editSaleUnitPreset').val('');
            $('#editSaleUnitCustom').val('').hide().prop('required', false);
            $('#editSaleUnitLegacyHint').text('Legacy sale: choose the correct unit before saving changes.');
            return;
        }
        if (saleUnitPresets.includes(unit)) {
            $('#editSaleUnitPreset').val(unit);
            $('#editSaleUnitCustom').val('').hide().prop('required', false);
        } else {
            $('#editSaleUnitPreset').val('__custom__');
            $('#editSaleUnitCustom').val(unit).show().prop('required', true);
        }
    }

    function updateTotalField(quantitySelector, priceSelector, outputSelector) {
        const quantity = parseFloat($(quantitySelector).val()) || 0;
        const unitPrice = parseFloat($(priceSelector).val()) || 0;
        const total = quantity * unitPrice;
        $(outputSelector).val('₦' + total.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));
    }

    function updateOutstandingField() {
        const quantity = parseFloat($('#addQuantity').val()) || 0;
        const unitPrice = parseFloat($('#addUnitPrice').val()) || 0;
        const total = quantity * unitPrice;
        const paid = parseFloat($('#addPaymentReceived').val()) || 0;
        const outstanding = Math.max(0, total - paid);
        $('#addOutstandingAmount').val('₦' + outstanding.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));
    }

    function setupTotalCalculator(quantitySelector, priceSelector, outputSelector) {
        $(quantitySelector + ', ' + priceSelector).on('input', function() {
            updateTotalField(quantitySelector, priceSelector, outputSelector);
        });
    }

    const attributionTypes = {
        poultry: {layer:'Layer', broiler:'Broiler', shared:'Shared Poultry / Other Poultry'},
        ruminant: {cattle:'Cattle', goat:'Goat', sheep:'Sheep', other:'Other', shared:'Shared Ruminant / Other Ruminant'},
        general: {general:'General / Other Farm Income'}
    };
    const salesCycles = salesRecordsConfig.salesCycles;
    const ruminantSaleAnimals = salesRecordsConfig.ruminantSaleAnimals;
    const ruminantSaleAllocationMap = salesRecordsConfig.ruminantSaleAllocationMap;
    const ruminantSaleExitMap = salesRecordsConfig.ruminantSaleExitMap;

    function refreshSaleAttribution(prefix, selectedProduction = '', selectedCycle = 0) {
        // Add modal uses addFarmType; Edit modal uses editSaleFarmType.
        // Resolve the actual controls explicitly so edit never targets missing IDs.
        const ids = prefix === 'edit'
            ? {farm:'#editSaleFarmType', production:'#editSaleProductionType', cycle:'#editSaleCycleId'}
            : {farm:'#addFarmType', production:'#addProductionType', cycle:'#addCycleId'};
        const farm = $(ids.farm).val();
        const prod = $(ids.production);
        const cycle = $(ids.cycle);
        const options = attributionTypes[farm] || {general:'General'};
        prod.empty();
        Object.entries(options).forEach(([value,label]) => prod.append(new Option(label,value,false,value===selectedProduction)));
        if (!prod.val()) prod.prop('selectedIndex',0);
        const production = prod.val();
        const sharedCycleLabel = farm === 'general' ? 'Not applicable' : (production === 'shared' ? 'No specific cycle' : 'Shared between cycles');
        cycle.empty().append(new Option(sharedCycleLabel, '0'));
        salesCycles.filter(c => c.farm_type === farm && c.production_type === production).forEach(c => {
            cycle.append(new Option(`${c.cycle_code} — ${c.status}`, String(c.id), false, Number(c.id)===Number(selectedCycle)));
        });
        const wantedCycle = String(selectedCycle || 0);
        if (cycle.find(`option[value="${wantedCycle}"]`).length) cycle.val(wantedCycle); else cycle.val('0');
    }

    function refreshRuminantSaleAnimalChoices(prefix, selectedRows = null) {
        const isEdit = prefix === 'edit';
        const farm = $(isEdit ? '#editSaleFarmType' : '#addFarmType').val();
        const production = $(isEdit ? '#editSaleProductionType' : '#addProductionType').val();
        const panel = $(isEdit ? '#editRuminantSaleAnimalPanel' : '#addRuminantSaleAnimalPanel');
        const modeEl = $(isEdit ? '#editSaleAnimalAllocationMode' : '#addSaleAnimalAllocationMode');
        const choices = $(isEdit ? '#editSaleAnimalChoices' : '#addSaleAnimalChoices');
        const specific = farm === 'ruminant' && production && production !== 'shared';
        panel.toggle(farm === 'ruminant');
        if (farm !== 'ruminant') { modeEl.val('shared'); choices.hide().empty(); return; }
        if (!specific && modeEl.val() !== 'shared') modeEl.val('shared');
        modeEl.find('option[value="equal"],option[value="custom"]').prop('disabled', !specific);
        const mode = modeEl.val() || 'shared';
        if (mode === 'shared' || !specific) { choices.hide().empty(); return; }
        let rows = Array.isArray(selectedRows) ? selectedRows : null;
        if (rows === null) {
            rows = [];
            choices.find('.sale-animal-check:checked').each(function() {
                const animalId = Number($(this).val());
                const amountInput = choices.find('input[name="sale_animal_amounts['+animalId+']"]');
                const exitInput = choices.find('select[name="sale_animal_exit_outcomes['+animalId+']"]');
                rows.push({animal_id: animalId, allocated_amount: amountInput.length ? Number(amountInput.val() || 0) : 0, exit_outcome: exitInput.length ? String(exitInput.val() || 'remain_active') : 'remain_active'});
            });
        }
        const selected = new Map(rows.map(r => [Number(r.animal_id), r]));
        const animals = ruminantSaleAnimals.filter(a => a.species === production);
        let html = '<div class="fw-semibold mb-2">Select '+(production.charAt(0).toUpperCase()+production.slice(1))+' animals</div>';
        if (!animals.length) html += '<div class="text-muted small">No registered animals found for this production type.</div>';
        animals.forEach(a => {
            const checked = selected.has(Number(a.id)) ? ' checked' : '';
            const row = selected.get(Number(a.id));
            const amount = row ? Number(row.allocated_amount || 0).toFixed(2) : '';
            const safeTag = $('<div>').text(String(a.tag_no)).html();
            html += '<div class="d-flex flex-wrap align-items-center gap-2 mb-2">'
                + '<input class="form-check-input sale-animal-check" type="checkbox" name="sale_animal_ids[]" value="'+a.id+'" id="'+prefix+'SaleAnimal'+a.id+'"'+checked+'>'
                + '<label class="form-check-label flex-grow-1" for="'+prefix+'SaleAnimal'+a.id+'"><strong>'+safeTag+'</strong> <span class="text-muted small">'+a.status+'</span></label>';
            if (mode === 'custom') html += '<input type="number" step="0.01" min="0" class="form-control form-control-sm sale-animal-amount" style="max-width:150px" name="sale_animal_amounts['+a.id+']" value="'+amount+'" placeholder="Amount (₦)">';
            const saleIdForOutcome = isEdit ? Number($('#editSaleId').val() || 0) : 0;
            const eventRow = saleIdForOutcome && ruminantSaleExitMap[String(saleIdForOutcome)] ? ruminantSaleExitMap[String(saleIdForOutcome)][String(a.id)] : null;
            const selectedOutcome = row && row.exit_outcome ? String(row.exit_outcome) : (eventRow ? String(eventRow.exit_outcome || 'remain_active') : 'remain_active');
            const canExit = String(a.status || '') === 'active' || !!eventRow;
            const exitDisplay = checked ? '' : 'display:none;';
            html += '<select class="form-select form-select-sm sale-animal-exit" style="flex-basis:100%;max-width:calc(100% - 1.75rem);margin-left:1.75rem;'+exitDisplay+'" name="sale_animal_exit_outcomes['+a.id+']">'
                + '<option value="remain_active"'+(selectedOutcome==='remain_active'?' selected':'')+'>Revenue only — no exit</option>'
                + '<option value="sold_live"'+(selectedOutcome==='sold_live'?' selected':'')+(canExit?'':' disabled')+'>Sold live — mark Sold</option>'
                + '<option value="culled_slaughtered"'+(selectedOutcome==='culled_slaughtered'?' selected':'')+(canExit?'':' disabled')+'>Culled/slaughtered — mark Culled</option>'
                + '</select>';
            html += '</div>';
        });
        choices.html(html).show();
    }

    function loadEditSaleAnimalAllocation(saleId) {
        const rows = ruminantSaleAllocationMap[String(saleId)] || [];
        const mode = rows.length ? String(rows[0].allocation_method || 'equal') : 'shared';
        $('#editSaleAnimalAllocationMode').val(mode);
        refreshRuminantSaleAnimalChoices('edit', rows);
    }

    $(document).ready(function() {
        refreshSaleAttribution('add');
        $('#addFarmType').on('change', () => { refreshSaleAttribution('add'); refreshRuminantSaleAnimalChoices('add'); });
        $('#addProductionType').on('change', () => { refreshSaleAttribution('add', $('#addProductionType').val()); refreshRuminantSaleAnimalChoices('add'); });
        $('#editSaleFarmType').on('change', () => { refreshSaleAttribution('edit'); refreshRuminantSaleAnimalChoices('edit'); });
        $('#editSaleProductionType').on('change', () => { refreshSaleAttribution('edit', $('#editSaleProductionType').val()); refreshRuminantSaleAnimalChoices('edit'); });
        refreshRuminantSaleAnimalChoices('add');
        $('#addSaleAnimalAllocationMode').on('change', () => refreshRuminantSaleAnimalChoices('add'));
        $('#editSaleAnimalAllocationMode').on('change', () => refreshRuminantSaleAnimalChoices('edit'));
        $(document).on('change', '.sale-animal-check', function() {
            $(this).closest('.d-flex').find('.sale-animal-exit').toggle(this.checked);
        });

        function refreshEditReceivablePosition() {
            const total=(parseFloat($('#editSaleQuantity').val())||0)*(parseFloat($('#editSalePrice').val())||0);
            const upfront=parseFloat($('#editSaleUpfront').val())||0;
            const settlements=parseFloat($('#editSaleSettlements').data('amount'))||0;
            const remaining=total-upfront-settlements;
            const el=$('#editSalePosition');
            if(remaining < -0.005) el.removeClass('text-success text-muted').addClass('text-danger').text('Overpayment detected: ₦'+Math.abs(remaining).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})+'. Resolve/reverse excess payment before saving.');
            else el.removeClass('text-danger text-muted').addClass('text-success').text('Revised outstanding after recorded payments: ₦'+Math.max(0,remaining).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}));
        }
        $('.edit-sale-btn').on('click', function() {
            const button=$(this);
            const upfront=Number(button.data('upfront')||0), settlements=Number(button.data('settlements')||0);
            $('#editSaleUpfront').val(upfront.toFixed(2));
            $('#editSaleSettlements').val('₦'+settlements.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})).data('amount',settlements);
            $('#editSaleLegacyHint').text(String(button.data('cash-snapshot'))==='1' ? 'Preserved sale-time cash snapshot.' : 'Legacy sale: verify this upfront cash once; saving will preserve it as the canonical cash snapshot.');
            setTimeout(() => { refreshSaleAttribution('edit', String(button.data('production') || ''), Number(button.data('cycle') || 0)); loadEditSaleAnimalAllocation(Number(button.data('id') || 0)); refreshEditReceivablePosition(); }, 0);
        });
        $('#editSaleQuantity,#editSalePrice,#editSaleUpfront').on('input',refreshEditReceivablePosition);

        // Filter change
        function applyFilters() {
            const farmType = $('#farmTypeFilter').val();
            const productionType = $('#productionTypeFilter').val();
            const reportMode = $('#reportMode').val();
            const monthValue = $('#monthFilter').val();
            const month = monthValue ? monthValue.substring(0, 7) : '';
            const year = $('#yearFilter').val();
            window.location.href = `sales_records.php?report_mode=${reportMode}&month=${month}&year=${year}&farm_type=${farmType}&production_type=${productionType}`;
        }

        function refreshReportProductionTypes(selected = 'all') {
            const farm = $('#farmTypeFilter').val();
            const select = $('#productionTypeFilter');
            select.empty().append(new Option('All Production Types', 'all'));
            Object.entries(attributionTypes[farm] || {}).forEach(([value,label]) => {
                select.append(new Option(label, value, false, value === selected));
            });
            if (!select.val()) select.val('all');
        }

        $('#farmTypeFilter').change(function() {
            refreshReportProductionTypes('all');
            applyFilters();
        });
        $('#productionTypeFilter, #monthFilter, #yearFilter, #reportMode').change(function() {
            const mode = $('#reportMode').val();
            $('#monthFilter').toggle(mode === 'monthly');
            $('#yearFilter').toggle(mode === 'yearly');
            $('#printMonthlyBtn').toggle(mode === 'monthly');
            $('#printYearlyBtn').toggle(mode === 'yearly');
            applyFilters();
        });

        $('#addUnitPreset').on('change', function(){ toggleCustomSaleUnit('#addUnitPreset','#addUnitCustom'); });
        $('#editSaleUnitPreset').on('change', function(){ toggleCustomSaleUnit('#editSaleUnitPreset','#editSaleUnitCustom'); $('#editSaleUnitLegacyHint').text(''); });

        // Auto-calculate total amounts
        setupTotalCalculator('#addQuantity', '#addUnitPrice', '#totalAmount');
        setupTotalCalculator('#editSaleQuantity', '#editSalePrice', '#editTotalAmount');
        $('#addQuantity, #addUnitPrice, #addPaymentReceived').on('input', updateOutstandingField);
        updateOutstandingField();

        $('.edit-ledger-btn').on('click', function() {
            $('#editLedgerId').val($(this).data('id'));
            $('#editLedgerCustomer').val($(this).data('customer'));
            $('#editLedgerDate').val($(this).data('date'));
            $('#editLedgerAmount').val($(this).data('amount'));
            $('#editLedgerNotes').val($(this).data('notes'));
            const modal = new bootstrap.Modal(document.getElementById('editLedgerModal'));
            modal.show();
        });
    });

    attachEditModal({
        buttonSelector: '.edit-sale-btn',
        modalSelector: '#editSaleModal',
        fieldMap: {
            id: '#editSaleId',
            date: '#editSaleDate',
            farm: '#editSaleFarmType',
            product: '#editSaleProduct',
            quantity: '#editSaleQuantity',
            price: '#editSalePrice',
            customer: '#editSaleCustomer',
            remarks: '#editSaleRemarks'
        },
        onShow: ({data}) => {
            refreshSaleAttribution('edit', String(data.production || ''), Number(data.cycle || 0));
            setEditSaleUnit(String(data.unit || ''));
            loadEditSaleAnimalAllocation(Number(data.id || 0));
            updateTotalField('#editSaleQuantity', '#editSalePrice', '#editTotalAmount');
        }
    });

    function deleteSale(saleId) {
        AppConfirm.ask('Are you sure you want to delete this sale record?', {title:'Delete sale record?', confirmText:'Delete'}).then(function(confirmed){ if (confirmed) {
            const params = new URLSearchParams({ id: saleId, csrf_token: salesRecordsConfig.csrfToken });
            fetch(salesRecordsConfig.deleteSaleUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        AppNotify.error(data.error || data.message || 'Unable to delete sale');
                    }
                });
        }
        });
    }
})();
