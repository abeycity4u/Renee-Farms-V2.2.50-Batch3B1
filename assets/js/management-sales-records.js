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
        salePopulationEffectMap: parseJson(
            configElement.dataset.salePopulationEffectMap,
            {}
        ),
        slaughterSaleLots: parseJson(
            configElement.dataset.slaughterSaleLots,
            []
        ),
        slaughterSaleHistoryMap: parseJson(
            configElement.dataset.slaughterSaleHistoryMap,
            {}
        ),
        csrfToken: configElement.dataset.csrfToken || '',
        deleteSaleUrl: configElement.dataset.deleteSaleUrl || ''
    };

const saleUnitPresets = salesRecordsConfig.saleUnitPresets;
    function toggleCustomSaleUnit(selectSelector, customSelector) {
        const isCustom = $(selectSelector).val() === '__custom__';
        $(customSelector).toggleClass('d-none', !isCustom).prop('required', isCustom);
        if (!isCustom) $(customSelector).val('');
    }
    function setEditSaleUnit(unit) {
        unit = String(unit || '').trim();
        $('#editSaleUnitLegacyHint').text('');
        if (!unit) {
            $('#editSaleUnitPreset').val('');
            $('#editSaleUnitCustom').val('').addClass('d-none').prop('required', false);
            $('#editSaleUnitLegacyHint').text('Legacy sale: choose the correct unit before saving changes.');
            return;
        }
        if (saleUnitPresets.includes(unit)) {
            $('#editSaleUnitPreset').val(unit);
            $('#editSaleUnitCustom').val('').addClass('d-none').prop('required', false);
        } else {
            $('#editSaleUnitPreset').val('__custom__');
            $('#editSaleUnitCustom').val(unit).removeClass('d-none').prop('required', true);
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
    const salePopulationEffectMap = salesRecordsConfig.salePopulationEffectMap;
    const slaughterSaleLots = salesRecordsConfig.slaughterSaleLots;
    const slaughterSaleHistoryMap = salesRecordsConfig.slaughterSaleHistoryMap;

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
        panel.toggleClass('d-none', farm !== 'ruminant');
        if (farm !== 'ruminant') { modeEl.val('shared'); choices.addClass('d-none').empty(); return; }
        if (!specific && modeEl.val() !== 'shared') modeEl.val('shared');
        modeEl.find('option[value="equal"],option[value="custom"]').prop('disabled', !specific);
        const mode = modeEl.val() || 'shared';
        if (mode === 'shared' || !specific) { choices.addClass('d-none').empty(); return; }
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
            if (mode === 'custom') html += '<input type="number" step="0.01" min="0" class="form-control form-control-sm sale-animal-amount" name="sale_animal_amounts['+a.id+']" value="'+amount+'" placeholder="Amount (₦)">';
            const saleIdForOutcome = isEdit ? Number($('#editSaleId').val() || 0) : 0;
            const eventRow = saleIdForOutcome && ruminantSaleExitMap[String(saleIdForOutcome)] ? ruminantSaleExitMap[String(saleIdForOutcome)][String(a.id)] : null;
            const selectedOutcome = row && row.exit_outcome ? String(row.exit_outcome) : (eventRow ? String(eventRow.exit_outcome || 'remain_active') : 'remain_active');
            const canExit = String(a.status || '') === 'active' || !!eventRow;
            const exitHiddenClass = checked ? '' : ' d-none';
            html += '<select class="form-select form-select-sm sale-animal-exit'+exitHiddenClass+'" name="sale_animal_exit_outcomes['+a.id+']">'
                + '<option value="remain_active"'+(selectedOutcome==='remain_active'?' selected':'')+'>Revenue only — no exit</option>'
                + '<option value="sold_live"'+(selectedOutcome==='sold_live'?' selected':'')+(canExit?'':' disabled')+'>Sold live — mark Sold</option>'
                + '<option value="culled_slaughtered"'+(selectedOutcome==='culled_slaughtered'?' selected':'')+(canExit?'':' disabled')+'>Culled/slaughtered — mark Culled</option>'
                + '</select>';
            html += '</div>';
        });
        choices.html(html).removeClass('d-none');
    }

    function loadEditSaleAnimalAllocation(saleId) {
        const rows = ruminantSaleAllocationMap[String(saleId)] || [];
        const mode = rows.length ? String(rows[0].allocation_method || 'equal') : 'shared';
        $('#editSaleAnimalAllocationMode').val(mode);
        refreshRuminantSaleAnimalChoices('edit', rows);
    }

    function slaughterSaleSelectors(prefix) {
        const edit = prefix === 'edit';

        return {
            source: edit
                ? '#editSaleStockSource'
                : '#addSaleStockSource',

            panel: edit
                ? '#editSlaughterLotPanel'
                : '#addSlaughterLotPanel',

            rows: edit
                ? '#editSlaughterLotRows'
                : '#addSlaughterLotRows',

            summary: edit
                ? '#editSlaughterLotSummary'
                : '#addSlaughterLotSummary',

            farm: edit
                ? '#editSaleFarmType'
                : '#addFarmType',

            production: edit
                ? '#editSaleProductionType'
                : '#addProductionType',

            cycle: edit
                ? '#editSaleCycleId'
                : '#addCycleId',

            product: edit
                ? '#editSaleProduct'
                : '#addProductType',

            quantity: edit
                ? '#editSaleQuantity'
                : '#addQuantity',

            unitPreset: edit
                ? '#editSaleUnitPreset'
                : '#addUnitPreset',

            unitCustom: edit
                ? '#editSaleUnitCustom'
                : '#addUnitCustom',

            populationCard: edit
                ? '#editPopulationEffectCard'
                : '#addPopulationEffectCard',

            populationMode: edit
                ? '#editPopulationEffectMode'
                : '#addPopulationEffectMode',

            animalPanel: edit
                ? '#editRuminantSaleAnimalPanel'
                : '#addRuminantSaleAnimalPanel',

            animalMode: edit
                ? '#editSaleAnimalAllocationMode'
                : '#addSaleAnimalAllocationMode'
        };
    }

    function activeSlaughterSaleHistory(saleId) {
        return (
            slaughterSaleHistoryMap[String(saleId)]
            || []
        ).filter(
            row =>
                Number(row.is_active || 0) === 1
        );
    }

    function slaughterSaleCatalog(
        prefix
    ) {
        const saleId =
            prefix === 'edit'
                ? Number(
                    $('#editSaleId').val()
                    || 0
                )
                : 0;

        const catalog =
            new Map();

        slaughterSaleLots.forEach(
            lot => {
                const id =
                    Number(
                        lot.output_id
                        || 0
                    );

                if (!id) return;

                catalog.set(
                    id,
                    Object.assign(
                        {},
                        lot,
                        {
                            available_for_sale:
                                Number(
                                    lot.remaining_quantity
                                    || 0
                                )
                        }
                    )
                );
            }
        );

        if (
            prefix === 'edit'
            && saleId > 0
        ) {
            activeSlaughterSaleHistory(
                saleId
            ).forEach(
                row => {
                    const id =
                        Number(
                            row.output_id
                            || 0
                        );

                    if (!id) return;

                    const existing =
                        catalog.get(id)
                        || {};

                    catalog.set(
                        id,
                        Object.assign(
                            {},
                            existing,
                            row,
                            {
                                output_id:
                                    id,

                                unit:
                                    row.inventory_unit
                                    || existing.unit
                                    || '',

                                sales_unit:
                                    existing.sales_unit
                                    || row.inventory_unit
                                    || '',

                                available_for_sale:
                                    Number(
                                        row.remaining_quantity
                                        || 0
                                    )
                                    + Number(
                                        row.quantity
                                        || 0
                                    )
                            }
                        )
                    );
                }
            );
        }

        return Array.from(
            catalog.values()
        );
    }

    function slaughterSaleLot(
        prefix,
        outputId
    ) {
        return (
            slaughterSaleCatalog(prefix)
                .find(
                    lot =>
                        Number(lot.output_id)
                        === Number(outputId)
                )
            || null
        );
    }

    function slaughterSaleLabel(lot) {
        const unit =
            String(
                lot.unit
                || lot.inventory_unit
                || ''
            );

        const available =
            Number(
                lot.available_for_sale
                ?? lot.remaining_quantity
                ?? 0
            );

        return (
            String(
                lot.item_name
                || 'Slaughter output'
            )
            + ' | Batch '
            + String(
                lot.batch_code
                || '—'
            )
            + ' | Animal '
            + String(
                lot.tag_no
                || '—'
            )
            + ' | '
            + String(
                lot.slaughter_date
                || '—'
            )
            + ' | Available '
            + available.toFixed(2)
            + ' '
            + unit
        );
    }

    function setSlaughterUnit(
        prefix,
        unit
    ) {
        const ids =
            slaughterSaleSelectors(
                prefix
            );

        unit =
            String(
                unit
                || ''
            ).trim();

        if (
            saleUnitPresets.includes(
                unit
            )
        ) {
            $(ids.unitPreset)
                .val(unit);

            $(ids.unitCustom)
                .val('')
                .addClass('d-none')
                .prop('required', false);

            return;
        }

        if (unit) {
            $(ids.unitPreset)
                .val('__custom__');

            $(ids.unitCustom)
                .val(unit)
                .removeClass('d-none')
                .prop('required', true);
        }
    }

    function setSlaughterLocks(
        prefix,
        locked
    ) {
        const ids =
            slaughterSaleSelectors(
                prefix
            );

        $(
            ids.farm
            + ','
            + ids.production
            + ','
            + ids.cycle
            + ','
            + ids.unitPreset
        ).prop(
            'disabled',
            locked
        );

        $(ids.product)
            .prop(
                'readonly',
                locked
            );

        $(ids.quantity)
            .prop(
                'readonly',
                locked
            );

        $(ids.unitCustom)
            .prop(
                'disabled',
                locked
            );

        if (!locked) {
            toggleCustomSaleUnit(
                ids.unitPreset,
                ids.unitCustom
            );
        }
    }

    function refreshSlaughterRowLimit(
        prefix,
        row
    ) {
        const lot =
            slaughterSaleLot(
                prefix,
                Number(
                    row.find(
                        '.slaughter-sale-lot-select'
                    ).val()
                    || 0
                )
            );

        const quantity =
            row.find(
                '.slaughter-sale-lot-quantity'
            );

        if (!lot) {
            quantity.removeAttr(
                'max'
            );
            return;
        }

        quantity.attr(
            'max',
            Number(
                lot.available_for_sale
                ?? lot.remaining_quantity
                ?? 0
            ).toFixed(2)
        );
    }

    function appendSlaughterSaleRow(
        prefix,
        selected = {}
    ) {
        const ids =
            slaughterSaleSelectors(
                prefix
            );

        const catalog =
            slaughterSaleCatalog(
                prefix
            );

        if (!catalog.length) {
            $(ids.summary)
                .removeClass('text-success')
                .addClass('text-warning')
                .text(
                    'No eligible slaughter-output lot is currently available.'
                );
            return;
        }

        const wrapper =
            $('<div>', {
                class:
                    'row g-2 align-items-end mb-2 slaughter-sale-lot-row'
            });

        const lotCol =
            $('<div>', {
                class:
                    'col-md-7'
            });

        const qtyCol =
            $('<div>', {
                class:
                    'col-md-3'
            });

        const actionCol =
            $('<div>', {
                class:
                    'col-md-2'
            });

        lotCol.append(
            $('<label>', {
                class:
                    'form-label small mb-1',
                text:
                    'Output lot'
            })
        );

        const select =
            $('<select>', {
                class:
                    'form-select slaughter-sale-lot-select',
                name:
                    'slaughter_output_ids[]',
                required:
                    true
            }).attr(
                'data-slaughter-prefix',
                prefix
            );

        select.append(
            new Option(
                'Select slaughter output lot...',
                ''
            )
        );

        catalog.forEach(
            lot => {
                select.append(
                    new Option(
                        slaughterSaleLabel(
                            lot
                        ),
                        String(
                            lot.output_id
                        ),
                        false,
                        Number(
                            lot.output_id
                        ) === Number(
                            selected.output_id
                            || 0
                        )
                    )
                );
            }
        );

        lotCol.append(
            select
        );

        qtyCol.append(
            $('<label>', {
                class:
                    'form-label small mb-1',
                text:
                    'Quantity sold'
            })
        );

        qtyCol.append(
            $('<input>', {
                type:
                    'number',
                class:
                    'form-control slaughter-sale-lot-quantity',
                name:
                    'slaughter_output_quantities[]',
                min:
                    '0.01',
                step:
                    '0.01',
                required:
                    true,
                value:
                    selected.quantity
                    ?? ''
            }).attr(
                'data-slaughter-prefix',
                prefix
            )
        );

        actionCol.append(
            $('<button>', {
                type:
                    'button',
                class:
                    'btn btn-outline-danger slaughter-sale-lot-remove',
                text:
                    'Remove'
            }).attr(
                'data-slaughter-prefix',
                prefix
            )
        );

        wrapper.append(
            lotCol,
            qtyCol,
            actionCol
        );

        $(ids.rows)
            .append(
                wrapper
            );

        refreshSlaughterRowLimit(
            prefix,
            wrapper
        );
    }

    function refreshSlaughterDerived(
        prefix
    ) {
        const ids =
            slaughterSaleSelectors(
                prefix
            );

        let total =
            0;

        let first =
            null;

        $(ids.rows)
            .find(
                '.slaughter-sale-lot-row'
            )
            .each(
                function () {
                    const row =
                        $(this);

                    const outputId =
                        Number(
                            row.find(
                                '.slaughter-sale-lot-select'
                            ).val()
                            || 0
                        );

                    const quantity =
                        Number(
                            row.find(
                                '.slaughter-sale-lot-quantity'
                            ).val()
                            || 0
                        );

                    if (
                        outputId > 0
                        && quantity > 0
                    ) {
                        total +=
                            quantity;

                        if (!first) {
                            first =
                                slaughterSaleLot(
                                    prefix,
                                    outputId
                                );
                        }
                    }

                    refreshSlaughterRowLimit(
                        prefix,
                        row
                    );
                }
            );

        if (first) {
            $(ids.farm)
                .val(
                    'ruminant'
                );

            const production =
                String(
                    first.production_type
                    || $(ids.production).val()
                    || ''
                );

            const cycleId =
                Number(
                    first.cycle_id
                    || $(ids.cycle).val()
                    || 0
                );

            refreshSaleAttribution(
                prefix,
                production,
                cycleId
            );

            $(ids.product)
                .val(
                    String(
                        first.item_name
                        || ''
                    )
                );

            setSlaughterUnit(
                prefix,
                first.sales_unit
                || first.unit
                || first.inventory_unit
                || ''
            );

            $(ids.summary)
                .removeClass('text-warning')
                .addClass('text-success')
                .text(
                    'Inventory-linked sale. Quantity is derived from the selected lot rows; frozen COGS is independent of selling price.'
                );
        }

        $(ids.quantity)
            .val(
                total > 0
                    ? total.toFixed(2)
                    : ''
            )
            .trigger(
                'input'
            );

        setSlaughterLocks(
            prefix,
            true
        );
    }

    function setSlaughterSaleMode(
        prefix,
        enabled,
        selectedRows = null
    ) {
        const ids =
            slaughterSaleSelectors(
                prefix
            );

        $(ids.panel)
            .toggleClass(
                'd-none',
                !enabled
            );

        if (!enabled) {
            $(ids.rows)
                .empty();

            $(ids.summary)
                .text('')
                .removeClass(
                    'text-success text-warning'
                );

            setSlaughterLocks(
                prefix,
                false
            );

            $(ids.populationCard)
                .removeClass(
                    'd-none'
                );

            refreshSalePopulationEffect(
                prefix
            );

            refreshRuminantSaleAnimalChoices(
                prefix
            );

            return;
        }

        $(ids.rows)
            .empty();

        if (
            Array.isArray(
                selectedRows
            )
            && selectedRows.length
        ) {
            selectedRows.forEach(
                row =>
                    appendSlaughterSaleRow(
                        prefix,
                        row
                    )
            );
        } else {
            appendSlaughterSaleRow(
                prefix
            );
        }

        $(ids.populationMode)
            .val(
                'financial_only'
            );

        refreshSalePopulationEffect(
            prefix,
            []
        );

        $(ids.populationCard)
            .addClass(
                'd-none'
            );

        $(ids.animalMode)
            .val(
                'shared'
            );

        $(ids.animalPanel)
            .addClass(
                'd-none'
            );

        refreshSlaughterDerived(
            prefix
        );
    }

    function loadEditSlaughterSale(
        saleId
    ) {
        const rows =
            activeSlaughterSaleHistory(
                saleId
            ).map(
                row => ({
                    output_id:
                        Number(
                            row.output_id
                            || 0
                        ),

                    quantity:
                        Number(
                            row.quantity
                            || 0
                        )
                })
            );

        if (rows.length) {
            $('#editSaleStockSource')
                .val(
                    'slaughter_output'
                );

            setSlaughterSaleMode(
                'edit',
                true,
                rows
            );

            return;
        }

        $('#editSaleStockSource')
            .val(
                'financial_only'
            );

        setSlaughterSaleMode(
            'edit',
            false
        );
    }


    function salePopulationSelectors(prefix) {
        const isEdit = prefix === 'edit';
        return {
            farm: isEdit ? '#editSaleFarmType' : '#addFarmType',
            production: isEdit ? '#editSaleProductionType' : '#addProductionType',
            cycle: isEdit ? '#editSaleCycleId' : '#addCycleId',
            mode: isEdit ? '#editPopulationEffectMode' : '#addPopulationEffectMode',
            explanation: isEdit
                ? '#editPopulationEffectExplanation'
                : '#addPopulationEffectExplanation',
            rows: isEdit ? '#editPopulationEffectRows' : '#addPopulationEffectRows',
            addRow: isEdit ? '#editPopulationEffectAddRow' : '#addPopulationEffectAddRow',
            ruminantNote: isEdit
                ? '#editPopulationEffectRuminantNote'
                : '#addPopulationEffectRuminantNote'
        };
    }

    const salePopulationEffectExplanations = {
        financial_only: {
            default:
                'This option updates only the financial sale record. It creates no sale-owned live-population deduction. Product type, quantity, and unit of measure are treated as financial/revenue data only.',
            ruminant:
                'This sale creates no additional aggregate/group headcount deduction. Tagged ruminants explicitly marked Sold live or Culled/slaughtered are still removed from live population through the Animal Registry lifecycle. Product type, quantity, and unit of measure remain financial/revenue data only.'
        },
        remove_live_population: {
            default:
                'This option records a physical population removal. Saving this sale will reduce live population only by the whole headcount you explicitly enter for the selected source production cycle(s). Product type, sales quantity, and unit of measure do not determine the population change.',
            ruminant:
                'Use this for additional aggregate/group headcount physically removed by this sale. Enter only the whole headcount removed from the selected source production cycle(s). Do not include tagged animals already marked Sold live or Culled/slaughtered because their Animal Registry lifecycle already updates population.'
        }
    };

    function salePopulationEffectExplanation(mode, farm) {
        const explanations =
            salePopulationEffectExplanations[mode]
            || salePopulationEffectExplanations.financial_only;

        return farm === 'ruminant'
            ? explanations.ruminant
            : explanations.default;
    }

    function eligibleSalePopulationCycles(prefix) {
        const ids = salePopulationSelectors(prefix);
        const farm = String($(ids.farm).val() || '');
        const production = String($(ids.production).val() || '');

        if (!['poultry', 'ruminant'].includes(farm) || !production) {
            return [];
        }

        return salesCycles.filter(cycle => {
            if (String(cycle.farm_type) !== farm) return false;
            return production === 'shared'
                || String(cycle.production_type) === production;
        });
    }

    function currentSalePopulationRows(prefix) {
        const ids = salePopulationSelectors(prefix);
        const rows = [];

        $(ids.rows).find('.sale-population-effect-row').each(function() {
            const row = $(this);
            rows.push({
                cycle_id: Number(
                    row.find('.sale-population-cycle').val() || 0
                ),
                population_quantity: String(
                    row.find('.sale-population-quantity').val() || ''
                )
            });
        });

        return rows;
    }

    function appendSalePopulationEffectRow(prefix, row = {}) {
        const ids = salePopulationSelectors(prefix);
        const directCycle = Number($(ids.cycle).val() || 0);
        let cycles = eligibleSalePopulationCycles(prefix);

        if (directCycle > 0) {
            cycles = cycles.filter(
                cycle => Number(cycle.id) === directCycle
            );
        }

        const wrapper = $('<div>', {
            class: 'row g-2 align-items-end mb-2 sale-population-effect-row'
        });

        const cycleColumn = $('<div>', {class: 'col-md-6'});
        cycleColumn.append(
            $('<label>', {
                class: 'form-label small mb-1',
                text: 'Source production cycle'
            })
        );

        const cycleSelect = $('<select>', {
            class: 'form-select sale-population-cycle',
            name: 'population_cycle_ids[]',
            required: true
        });

        if (directCycle <= 0) {
            cycleSelect.append(new Option('Select source cycle...', ''));
        }

        cycles.forEach(cycle => {
            const cycleProduction = String(cycle.production_type || '');
            const productionLabel = cycleProduction
                ? cycleProduction.charAt(0).toUpperCase()
                    + cycleProduction.slice(1)
                : 'Production';
            const label = String($(ids.production).val() || '') === 'shared'
                ? `${cycle.cycle_code} — ${productionLabel} — ${cycle.status}`
                : `${cycle.cycle_code} — ${cycle.status}`;

            cycleSelect.append(
                new Option(
                    label,
                    String(cycle.id),
                    false,
                    Number(cycle.id) === Number(row.cycle_id || directCycle)
                )
            );
        });

        if (directCycle > 0) {
            cycleSelect.val(String(directCycle));
        } else if (Number(row.cycle_id || 0) > 0) {
            cycleSelect.val(String(row.cycle_id));
        }

        cycleColumn.append(cycleSelect);

        const quantityColumn = $('<div>', {class: 'col-md-3'});
        quantityColumn.append(
            $('<label>', {
                class: 'form-label small mb-1',
                text: 'Whole headcount'
            })
        );

        quantityColumn.append(
            $('<input>', {
                type: 'number',
                class: 'form-control sale-population-quantity',
                name: 'population_quantities[]',
                min: '1',
                step: '1',
                required: true,
                value: row.population_quantity ?? ''
            })
        );

        const actionColumn = $('<div>', {class: 'col-md-3'});

        if (directCycle <= 0) {
            actionColumn.append(
                $('<button>', {
                    type: 'button',
                    class: 'btn btn-outline-danger text-nowrap px-3 sale-population-remove-row',
                    text: 'Remove'
                }).attr('data-population-prefix', prefix)
            );
        } else {
            actionColumn.append(
                $('<div>', {
                    class: 'small text-muted pb-2',
                    text: 'Selected sale cycle'
                })
            );
        }

        wrapper.append(cycleColumn, quantityColumn, actionColumn);
        $(ids.rows).append(wrapper);
    }

    function refreshSalePopulationEffect(prefix, selectedRows = null) {
        const ids = salePopulationSelectors(prefix);
        const farm = String($(ids.farm).val() || '');
        const eligibleFarm = ['poultry', 'ruminant'].includes(farm);
        const modeElement = $(ids.mode);

        modeElement
            .find('option[value="remove_live_population"]')
            .prop('disabled', !eligibleFarm);

        if (!eligibleFarm) {
            modeElement.val('financial_only');
        }

        const mode = String(
            modeElement.val() || 'financial_only'
        );

        modeElement
            .find('option[value="financial_only"]')
            .text(
                farm === 'ruminant'
                    ? 'Financial only — no additional aggregate/group removal'
                    : 'Financial only — no sale-owned population removal'
            );

        modeElement
            .find('option[value="remove_live_population"]')
            .text(
                farm === 'ruminant'
                    ? 'Remove live population — aggregate/group headcount'
                    : 'Remove live population — explicit headcount by source cycle'
            );

        $(ids.explanation).text(
            salePopulationEffectExplanation(mode, farm)
        );

        const removeLive =
            mode === 'remove_live_population'
            && eligibleFarm;

        const rowsElement = $(ids.rows);
        const addRowButton = $(ids.addRow);

        $(ids.ruminantNote).toggleClass(
            'd-none',
            !(removeLive && farm === 'ruminant')
        );

        if (!removeLive) {
            rowsElement.empty().addClass('d-none');
            addRowButton.addClass('d-none');
            return;
        }

        let rows = Array.isArray(selectedRows)
            ? selectedRows.slice()
            : currentSalePopulationRows(prefix);

        const directCycle = Number($(ids.cycle).val() || 0);

        if (directCycle > 0) {
            const matching = rows.find(
                row => Number(row.cycle_id) === directCycle
            );

            rows = [{
                cycle_id: directCycle,
                population_quantity:
                    matching ? matching.population_quantity : ''
            }];
        } else if (!rows.length) {
            rows = [{}];
        }

        rowsElement.empty().removeClass('d-none');

        if (!eligibleSalePopulationCycles(prefix).length) {
            rowsElement.append(
                $('<div>', {
                    class: 'alert alert-warning py-2 mb-0',
                    text: 'No matching production cycle is available for this population effect. To remove live population, first select or create an eligible production cycle. Otherwise, keep this sale as Financial only.'
                })
            );
            addRowButton.addClass('d-none');
            return;
        }

        rows.forEach(row => appendSalePopulationEffectRow(prefix, row));
        addRowButton.toggleClass('d-none', directCycle > 0);
    }

    function loadEditSalePopulationEffect(saleId) {
        const rows = salePopulationEffectMap[String(saleId)] || [];

        $('#editPopulationEffectMode').val(
            rows.length
                ? 'remove_live_population'
                : 'financial_only'
        );

        refreshSalePopulationEffect('edit', rows);
    }


    $(document).ready(function() {
        refreshSaleAttribution('add');
        refreshRuminantSaleAnimalChoices('add');
        refreshSalePopulationEffect('add');


        $('#addSaleStockSource').on(
            'change',
            function () {
                setSlaughterSaleMode(
                    'add',
                    String(
                        $(this).val()
                        || ''
                    ) === 'slaughter_output'
                );
            }
        );

        $('#editSaleStockSource').on(
            'change',
            function () {
                setSlaughterSaleMode(
                    'edit',
                    String(
                        $(this).val()
                        || ''
                    ) === 'slaughter_output'
                );
            }
        );

        $('#addSlaughterLotAddRow').on(
            'click',
            function () {
                appendSlaughterSaleRow(
                    'add'
                );

                refreshSlaughterDerived(
                    'add'
                );
            }
        );

        $('#editSlaughterLotAddRow').on(
            'click',
            function () {
                appendSlaughterSaleRow(
                    'edit'
                );

                refreshSlaughterDerived(
                    'edit'
                );
            }
        );

        $(document).on(
            'change',
            '.slaughter-sale-lot-select',
            function () {
                const prefix =
                    String(
                        $(this).attr(
                            'data-slaughter-prefix'
                        )
                        || 'add'
                    );

                refreshSlaughterDerived(
                    prefix
                );
            }
        );

        $(document).on(
            'input',
            '.slaughter-sale-lot-quantity',
            function () {
                const prefix =
                    String(
                        $(this).attr(
                            'data-slaughter-prefix'
                        )
                        || 'add'
                    );

                refreshSlaughterDerived(
                    prefix
                );
            }
        );

        $(document).on(
            'click',
            '.slaughter-sale-lot-remove',
            function () {
                const prefix =
                    String(
                        $(this).attr(
                            'data-slaughter-prefix'
                        )
                        || 'add'
                    );

                const ids =
                    slaughterSaleSelectors(
                        prefix
                    );

                $(this)
                    .closest(
                        '.slaughter-sale-lot-row'
                    )
                    .remove();

                if (
                    !$(ids.rows)
                        .find(
                            '.slaughter-sale-lot-row'
                        )
                        .length
                ) {
                    appendSlaughterSaleRow(
                        prefix
                    );
                }

                refreshSlaughterDerived(
                    prefix
                );
            }
        );

        $('#addFarmType').on('change', () => {
            refreshSaleAttribution('add');
            refreshRuminantSaleAnimalChoices('add');
            refreshSalePopulationEffect('add');
        });

        $('#addProductionType').on('change', () => {
            refreshSaleAttribution('add', $('#addProductionType').val());
            refreshRuminantSaleAnimalChoices('add');
            refreshSalePopulationEffect('add');
        });

        $('#editSaleFarmType').on('change', () => {
            refreshSaleAttribution('edit');
            refreshRuminantSaleAnimalChoices('edit');
            refreshSalePopulationEffect('edit');
        });

        $('#editSaleProductionType').on('change', () => {
            refreshSaleAttribution(
                'edit',
                $('#editSaleProductionType').val()
            );
            refreshRuminantSaleAnimalChoices('edit');
            refreshSalePopulationEffect('edit');
        });

        $('#addCycleId').on(
            'change',
            () => refreshSalePopulationEffect('add')
        );
        $('#editSaleCycleId').on(
            'change',
            () => refreshSalePopulationEffect('edit')
        );

        $('#addPopulationEffectMode').on(
            'change',
            () => refreshSalePopulationEffect('add')
        );
        $('#editPopulationEffectMode').on(
            'change',
            () => refreshSalePopulationEffect('edit')
        );

        $('#addPopulationEffectAddRow').on(
            'click',
            () => appendSalePopulationEffectRow('add')
        );
        $('#editPopulationEffectAddRow').on(
            'click',
            () => appendSalePopulationEffectRow('edit')
        );

        $(document).on(
            'click',
            '.sale-population-remove-row',
            function() {
                const prefix = String(
                    $(this).attr('data-population-prefix') || 'add'
                );
                const ids = salePopulationSelectors(prefix);

                $(this)
                    .closest('.sale-population-effect-row')
                    .remove();

                if (
                    $(ids.mode).val() === 'remove_live_population'
                    && !$(ids.rows)
                        .find('.sale-population-effect-row')
                        .length
                ) {
                    appendSalePopulationEffectRow(prefix);
                }
            }
        );

        $('#addSaleAnimalAllocationMode').on(
            'change',
            () => refreshRuminantSaleAnimalChoices('add')
        );
        $('#editSaleAnimalAllocationMode').on(
            'change',
            () => refreshRuminantSaleAnimalChoices('edit')
        );

        $(document).on('change', '.sale-animal-check', function() {
            $(this)
                .closest('.d-flex')
                .find('.sale-animal-exit')
                .toggleClass('d-none', !this.checked);
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
            $('#monthFilter').toggleClass('d-none', mode !== 'monthly');
            $('#yearFilter').toggleClass('d-none', mode !== 'yearly');
            $('#printMonthlyBtn').toggleClass('d-none', mode !== 'monthly');
            $('#printYearlyBtn').toggleClass('d-none', mode !== 'yearly');
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
            loadEditSalePopulationEffect(Number(data.id || 0));
            loadEditSlaughterSale(Number(data.id || 0));
            updateTotalField('#editSaleQuantity', '#editSalePrice', '#editTotalAmount');
        }
    });

})();
