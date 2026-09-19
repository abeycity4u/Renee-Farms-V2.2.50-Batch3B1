/**
 * Inventory page browser behavior.
 *
 * Dynamic server values are supplied through #inventoryPageConfig.
 * viewHistory and deleteItem remain explicit window contracts because
 * shared app-behaviors.js delegates Inventory row actions to them.
 * Externalized for CSP compatibility.
 */
$(document).ready(function() {
        // Initialize DataTable
        $('#inventoryTable').DataTable({
            pageLength: 25,
            order: [[2, 'asc']],
            responsive: {
                details: {
                    type: 'column',
                    target: 0
                }
            },
            columnDefs: [
                { className: 'dtr-control', targets: 0 },
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: -1 },
                { responsivePriority: 3, targets: 1 },
                { orderable: false, targets: -1 }
            ]
        });
        
        // Show messages

        // Prevent bootstrap modal errors if the target modal is missing
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-bs-toggle="modal"]');
            if (!trigger) return;

            const targetSelector = trigger.getAttribute('data-bs-target') || trigger.getAttribute('href');
            if (!targetSelector) return;

            const modalEl = document.querySelector(targetSelector);
            if (!modalEl) {
                console.warn('Modal target not found for button:', targetSelector);
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
    });
    
    // Update item info when select changes
    const inventoryConfigElement =
        document.getElementById('inventoryPageConfig');

    let inventoryActiveCycles = [];

    if (inventoryConfigElement) {
        try {
            inventoryActiveCycles = JSON.parse(
                inventoryConfigElement.dataset.activeCycles || '[]'
            );
        } catch (error) {
            inventoryActiveCycles = [];
        }
    }

    const inventoryBaseUrl = inventoryConfigElement
        ? (inventoryConfigElement.dataset.baseUrl || '')
        : '';

    function updateTransactionCycleOptions() {
        const cycleWrap = document.getElementById('transactionCycleWrap');
        const cycleSelect = document.getElementById('transactionCycleId');
        const productionSelect = document.getElementById('transactionProductionType');
        const typeSelect = document.querySelector('select[name="transaction_type"]');
        const selectedOption = document.querySelector('#updateItemSelect option:checked');
        if (!cycleWrap || !cycleSelect || !productionSelect || !typeSelect || !selectedOption) return;

        const usage = selectedOption.dataset.feedCategory || 'general';
        const farmType = selectedOption.dataset.farmType || 'both';
        const productionType = productionSelect.value || 'shared';
        const shouldShow = typeSelect.value === 'used' && usage === 'general' && productionType !== 'shared' && farmType !== 'both';
        if (!shouldShow) {
            cycleWrap.classList.add('d-none');
            cycleSelect.innerHTML = '<option value="">No specific cycle / pooled usage</option>';
            return;
        }

        const matches = inventoryActiveCycles.filter(cycle =>
            String(cycle.farm_type).toLowerCase() === farmType &&
            String(cycle.production_type).toLowerCase() === productionType
        );
        cycleSelect.replaceChildren(new Option('No specific cycle / pooled usage', ''));
        matches.forEach(cycle => {
            cycleSelect.add(new Option(String(cycle.cycle_code ?? ''), String(cycle.id ?? '')));
        });
        cycleWrap.classList.remove('d-none');
    }

    function updateTransactionProductionAttribution(selectedOption) {
        const wrap = document.getElementById('transactionProductionWrap');
        const select = document.getElementById('transactionProductionType');
        if (!wrap || !select || !selectedOption) return;

        const usage = selectedOption.dataset.feedCategory || 'general';
        const farmType = selectedOption.dataset.farmType || 'both';
        const defaultProduction = selectedOption.dataset.defaultProductionType || 'shared';
        if (usage !== 'general') {
            wrap.classList.add('d-none');
            select.innerHTML = '';
            updateTransactionCycleOptions();
            return;
        }

        const options = farmType === 'poultry'
            ? [['shared','Shared Poultry'],['layer','Layer'],['broiler','Broiler']]
            : farmType === 'ruminant'
                ? [['shared','Shared Ruminant'],['cattle','Cattle'],['goat','Goat'],['sheep','Sheep'],['other','Other']]
                : [['shared','Shared / Farm-wide']];
        select.replaceChildren();
        options.forEach(([value,label]) => select.add(new Option(label, value)));
        select.value = options.some(([value]) => value === defaultProduction) ? defaultProduction : 'shared';
        select.onchange = updateTransactionCycleOptions;
        wrap.classList.remove('d-none');
        updateTransactionCycleOptions();
    }

    function updateItemInfo(itemId) {
        const selectedOption = document.querySelector(`#updateItemSelect option[value="${itemId}"]`);
        if (selectedOption) {
            const currentStock = selectedOption.dataset.stock;
            const unit = selectedOption.dataset.unit;
            document.getElementById('updateItemId').value = itemId;

            const itemInfo=document.getElementById('itemInfo');
            itemInfo.replaceChildren();

            itemInfo.appendChild(document.createTextNode('Current stock: '));

            const stockStrong=document.createElement('strong');
            stockStrong.textContent=String(currentStock ?? '')+' '+String(unit ?? '');
            itemInfo.appendChild(stockStrong);

            itemInfo.appendChild(document.createElement('br'));
            itemInfo.appendChild(document.createTextNode('Selected item: '));

            const itemStrong=document.createElement('strong');
            itemStrong.textContent=selectedOption.textContent.split(' (Current:')[0].trim();
            itemInfo.appendChild(itemStrong);

            updateTransactionProductionAttribution(selectedOption);
        }
    }
    
    // Update quantity label based on transaction type
    function updateQuantityLabel() {
        const typeSelect = document.querySelector('select[name="transaction_type"]');
        const label = document.getElementById('quantityLabel');
        const input = document.querySelector('input[name="quantity"]');
        const costWrapper = document.getElementById('unitCostWrapper');
        const costInput = costWrapper ? costWrapper.querySelector('input[name="unit_cost"]') : null;

        if (!typeSelect || !label || !input) return;

        if (typeSelect.value === 'used') {
            label.innerHTML = 'Quantity <small class="text-danger">(will be subtracted)</small>';
            input.min = 0.01;
            if (costWrapper) {
                costWrapper.classList.add('d-none');
            }
            if (costInput) {
                costInput.required = false;
                costInput.value = '';
            }
            updateTransactionCycleOptions();
        } else {
            label.innerHTML = 'Quantity <small class="text-success">(will be added)</small>';
            input.min = 0.01;
            if (costWrapper) {
                costWrapper.classList.remove('d-none');
            }
            if (costInput) {
                costInput.required = true;
            }
            updateTransactionCycleOptions();
        }
    }
    
    // Quick update stock from row actions (uses event delegation for DataTables support)
    document.addEventListener('click', function(event) {
        const button = event.target.closest('.js-quick-update');
        if (!button) return;

        const itemId = button.dataset.itemId;

        const itemIdInput = document.getElementById('updateItemId');
        const itemSelect = document.getElementById('updateItemSelect');
        if (!itemIdInput || !itemSelect) return;

        itemIdInput.value = itemId;
        itemSelect.value = itemId;
        updateItemInfo(itemId);

        const modalEl = document.getElementById('updateStockModal');
        if (!modalEl) return;

        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    });
    
    // View stock history
    function viewHistory(itemId) {
        window.location.href = `${inventoryBaseUrl}/api/stock_history.php?item_id=${itemId}`;
    }
    
    // Inventory deletion uses the same modern confirmation experience as the rest of the platform.
    async function deleteItem(itemId) {
        const row = document.querySelector(`[data-inventory-action="delete"][data-inventory-item-id="${itemId}"]`)?.closest('tr');
        const itemName = row?.querySelector('td strong')?.textContent?.trim() || 'this inventory item';
        const confirmed = await AppConfirm.ask(
            `Delete ${itemName}? Items with only setup/opening history are removed permanently. Items with real stock activity are archived so their audit history remains available.`,
            { title: 'Delete inventory item?', confirmText: 'Continue' }
        );
        if (!confirmed) return;
        const form = document.getElementById('deleteForm');
        document.getElementById('deleteItemId').value = itemId;
        const marker = document.createElement('input');
        marker.type = 'hidden';
        marker.name = 'delete_item';
        marker.value = '1';
        form.appendChild(marker);
        form.submit();
    }
    
    function openRequestedCategoryWorkspace() {
        const params =
            new URLSearchParams(
                window.location.search
            );

        if (
            params.get('manage_categories')
            !== '1'
        ) {
            return;
        }

        const modalElement =
            document.getElementById(
                'addCategoryModal'
            );

        if (
            !modalElement
            ||
            typeof bootstrap === 'undefined'
            ||
            !bootstrap.Modal
        ) {
            return;
        }

        bootstrap.Modal
            .getOrCreateInstance(
                modalElement
            )
            .show();
    }

    function refreshCategoryFinancialTypeGuidance() {
        const select = document.getElementById('categoryFinancialTypeSelect');
        const help = document.getElementById('categoryFinancialTypeHelp');

        if (!select || !help) return;

        const option = select.selectedOptions?.[0];
        help.textContent = option?.dataset?.help || '';
    }

    function selectedAddItemCategory() {
        const categorySelect =
            document.getElementById(
                'addItemCategory'
            );

        if (!categorySelect) {
            return null;
        }

        const option =
            categorySelect
                .selectedOptions?.[0];

        return (
            option
            && option.value
        )
            ? option
            : null;
    }

    function refreshAddItemCategoryGuidance() {
        const help =
            document.getElementById(
                'addItemCategoryHelp'
            );

        if (!help) {
            return;
        }

        const category =
            selectedAddItemCategory();

        if (!category) {
            help.textContent =
                'Select a category first. Its Financial Type determines which Usage Classification choices are valid.';
            return;
        }

        const label =
            category.dataset.financialLabel
            || 'Unknown';

        const financialHelp =
            category.dataset.financialHelp
            || '';

        help.textContent =
            `Financial Type: ${label}. ${financialHelp}`;
    }

    function refreshAddItemFarmTypeOptions() {
        const category =
            selectedAddItemCategory();

        const farmSelect =
            document.getElementById(
                'addItemFarmType'
            );

        if (!farmSelect) {
            return;
        }

        if (!category) {
            farmSelect.value = '';
            farmSelect.disabled = true;

            Array.from(
                farmSelect.options
            ).forEach(
                option => {
                    option.hidden = false;
                    option.disabled = false;
                }
            );

            return;
        }

        const categoryFarmType =
            category.dataset.farmType
            || '';

        const financialType =
            category.dataset.financialType
            || '';

        farmSelect.disabled = false;

        Array.from(
            farmSelect.options
        ).forEach(
            option => {
                if (!option.value) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                let compatible =
                    categoryFarmType === 'both'
                        ? true
                        : option.value === categoryFarmType;

                /*
                 * A Feed item must resolve to an operational module before its
                 * usage can be Layer/Broiler/Ruminant. Therefore a category
                 * scoped Both may feed Poultry or Ruminant, but an individual
                 * Feed item cannot remain Farm Type Both.
                 */
                if (
                    financialType === 'feed'
                    && option.value === 'both'
                ) {
                    compatible = false;
                }

                option.hidden =
                    !compatible;

                option.disabled =
                    !compatible;
            }
        );

        const selected =
            farmSelect
                .selectedOptions?.[0];

        if (
            selected
            && selected.value
            && selected.disabled
        ) {
            farmSelect.value = '';
        }
    }

    function refreshAddItemUsageOptions() {
        const category =
            selectedAddItemCategory();

        const farmSelect =
            document.getElementById(
                'addItemFarmType'
            );

        const usageSelect =
            document.getElementById(
                'addItemUsageClassification'
            );

        const help =
            document.getElementById(
                'addItemUsageHelp'
            );

        if (
            !farmSelect
            || !usageSelect
        ) {
            return;
        }

        const farmType =
            farmSelect.value;

        if (
            !category
            || !farmType
        ) {
            usageSelect.value = '';
            usageSelect.disabled = true;

            Array.from(
                usageSelect.options
            ).forEach(
                option => {
                    option.hidden = false;
                    option.disabled = false;
                }
            );

            if (help) {
                help.textContent =
                    'Select Category and Farm Type first. Only financially and operationally valid Usage choices will be shown.';
            }

            return;
        }

        const financialType =
            category.dataset.financialType
            || '';

        let allowedUsage = [];

        if (financialType === 'feed') {
            if (farmType === 'poultry') {
                allowedUsage = [
                    'layer',
                    'broiler',
                ];
            } else if (
                farmType === 'ruminant'
            ) {
                allowedUsage = [
                    'ruminant',
                ];
            }
        } else {
            allowedUsage = [
                'general',
            ];
        }

        usageSelect.disabled = false;

        Array.from(
            usageSelect.options
        ).forEach(
            option => {
                if (!option.value) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                const compatible =
                    allowedUsage.includes(
                        option.value
                    );

                option.hidden =
                    !compatible;

                option.disabled =
                    !compatible;
            }
        );

        const selected =
            usageSelect
                .selectedOptions?.[0];

        if (
            selected
            && selected.value
            && selected.disabled
        ) {
            usageSelect.value = '';
        }

        if (help) {
            if (
                financialType === 'feed'
                && farmType === 'poultry'
            ) {
                help.textContent =
                    'Feed + Poultry: choose Layer Feed or Broiler Feed.';
            } else if (
                financialType === 'feed'
                && farmType === 'ruminant'
            ) {
                help.textContent =
                    'Feed + Ruminant: Ruminant Feed is the valid Usage Classification.';
            } else {
                help.textContent =
                    'This category is non-feed financially, so Usage Classification is General / Non-feed item.';
            }
        }
    }

    function refreshDefaultProductionAttribution() {
        const farmSelect =
            document.getElementById(
                'addItemFarmType'
            );

        const usageSelect =
            document.getElementById(
                'addItemUsageClassification'
            );

        const wrap =
            document.getElementById(
                'defaultProductionTypeWrap'
            );

        const select =
            document.getElementById(
                'defaultProductionType'
            );

        if (
            !farmSelect
            || !usageSelect
            || !wrap
            || !select
        ) {
            return;
        }

        const farmType =
            farmSelect.value;

        const usage =
            usageSelect.value;

        if (
            !farmType
            || !usage
        ) {
            wrap.classList.add(
                'd-none'
            );

            select.replaceChildren();

            return;
        }

        if (usage !== 'general') {
            wrap.classList.add(
                'd-none'
            );

            select.replaceChildren(
                new Option(
                    'Automatic',
                    usage === 'layer'
                        ? 'layer'
                        : (
                            usage === 'broiler'
                                ? 'broiler'
                                : 'shared'
                        )
                )
            );

            return;
        }

        wrap.classList.remove(
            'd-none'
        );

        const options =
            farmType === 'poultry'
                ? [
                    ['shared', 'Shared Poultry'],
                    ['layer', 'Layer'],
                    ['broiler', 'Broiler'],
                ]
                : farmType === 'ruminant'
                    ? [
                        ['shared', 'Shared Ruminant'],
                        ['cattle', 'Cattle'],
                        ['goat', 'Goat'],
                        ['sheep', 'Sheep'],
                        ['other', 'Other'],
                    ]
                    : [
                        ['shared', 'Shared / Farm-wide'],
                    ];

        const previous =
            select.value;

        select.replaceChildren();

        options.forEach(
            ([value, label]) =>
                select.add(
                    new Option(
                        label,
                        value
                    )
                )
        );

        select.value =
            options.some(
                ([value]) =>
                    value === previous
            )
                ? previous
                : options[0][0];
    }

    function refreshAddItemFormContract() {
        refreshAddItemCategoryGuidance();
        refreshAddItemFarmTypeOptions();
        refreshAddItemUsageOptions();
        refreshDefaultProductionAttribution();
    }

    // Shared app-behaviors.js invokes these Inventory actions through window.
    window.viewHistory = viewHistory;
    window.deleteItem = deleteItem;


    document.addEventListener('DOMContentLoaded', function () {
        updateQuantityLabel();

        openRequestedCategoryWorkspace();

        refreshCategoryFinancialTypeGuidance();
        document.getElementById('categoryFinancialTypeSelect')
            ?.addEventListener(
                'change',
                refreshCategoryFinancialTypeGuidance
            );

        refreshAddItemFormContract();

        document.getElementById('addItemCategory')
            ?.addEventListener(
                'change',
                refreshAddItemFormContract
            );

        document.getElementById('addItemFarmType')
            ?.addEventListener(
                'change',
                refreshAddItemFormContract
            );

        document.getElementById('addItemUsageClassification')
            ?.addEventListener(
                'change',
                refreshAddItemFormContract
            );
    });
