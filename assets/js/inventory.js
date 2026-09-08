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
            cycleWrap.style.display = 'none';
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
        cycleWrap.style.display = '';
    }

    function updateTransactionProductionAttribution(selectedOption) {
        const wrap = document.getElementById('transactionProductionWrap');
        const select = document.getElementById('transactionProductionType');
        if (!wrap || !select || !selectedOption) return;

        const usage = selectedOption.dataset.feedCategory || 'general';
        const farmType = selectedOption.dataset.farmType || 'both';
        const defaultProduction = selectedOption.dataset.defaultProductionType || 'shared';
        if (usage !== 'general') {
            wrap.style.display = 'none';
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
        wrap.style.display = '';
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
                costWrapper.style.display = 'none';
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
                costWrapper.style.display = 'block';
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
    
    function refreshDefaultProductionAttribution() {
        const farmSelect = document.getElementById('addItemFarmType');
        const usageSelect = document.getElementById('addItemUsageClassification');
        const wrap = document.getElementById('defaultProductionTypeWrap');
        const select = document.getElementById('defaultProductionType');
        if (!farmSelect || !usageSelect || !wrap || !select) return;

        const usage = usageSelect.value;
        let farmType = farmSelect.value;
        if (usage === 'layer' || usage === 'broiler') farmType = 'poultry';
        if (usage === 'ruminant') farmType = 'ruminant';

        // Feed ownership is already explicit in Usage Classification; only
        // General / Non-feed stock needs an owner chosen by the farmer.
        if (usage !== 'general') {
            wrap.style.display = 'none';
            select.replaceChildren(
                new Option(
                    'Automatic',
                    usage === 'layer' ? 'layer' : (usage === 'broiler' ? 'broiler' : 'shared')
                )
            );
            return;
        }

        wrap.style.display = '';
        const options = farmType === 'poultry'
            ? [['shared','Shared Poultry'],['layer','Layer'],['broiler','Broiler']]
            : farmType === 'ruminant'
                ? [['shared','Shared Ruminant'],['cattle','Cattle'],['goat','Goat'],['sheep','Sheep'],['other','Other']]
                : [['shared','Shared / Farm-wide']];
        const previous = select.value;
        select.replaceChildren();
        options.forEach(([value,label]) => select.add(new Option(label, value)));
        select.value = options.some(([value]) => value === previous) ? previous : options[0][0];
    }

    // Shared app-behaviors.js invokes these Inventory actions through window.
    window.viewHistory = viewHistory;
    window.deleteItem = deleteItem;


    document.addEventListener('DOMContentLoaded', function () {
        updateQuantityLabel();
        refreshDefaultProductionAttribution();
        document.getElementById('addItemFarmType')?.addEventListener('change', refreshDefaultProductionAttribution);
        document.getElementById('addItemUsageClassification')?.addEventListener('change', refreshDefaultProductionAttribution);
    });
