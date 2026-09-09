/**
 * Layer Daily Record browser behavior.
 * Dynamic server state is supplied through layerDailyConfig.
 *
 * This remains a classic top-level script so the existing
 * openRecordModal(), checkExistingRecord(), calculateLayingRate(),
 * and related page contracts keep their original runtime behavior.
 * Externalized for CSP compatibility.
 */
const layerDailyConfigElement =
    document.getElementById('layerDailyConfig');

const layerDailyConfig = {
    selectedCycleId: Number(
        layerDailyConfigElement?.dataset.selectedCycleId || 0
    ),
    yearMonth:
        layerDailyConfigElement?.dataset.yearMonth || '',
    canEditRetrievedOpeningStock:
        layerDailyConfigElement?.dataset.canEditOpening === '1',
    canDelete:
        layerDailyConfigElement?.dataset.canDelete === '1',
    csrfToken:
        layerDailyConfigElement?.dataset.csrfToken || ''
};

// Month selector
    document.getElementById('monthSelector').addEventListener('blur', function() {
        window.location.href = 'layers_daily_record.php?month='
        + encodeURIComponent(this.value.substring(0, 7))
        + '&cycle_id='
        + encodeURIComponent(layerDailyConfig.selectedCycleId);
    });
    const cycleSelector = document.getElementById('activeCycleSelector');
    if (cycleSelector) {
        cycleSelector.addEventListener('change', function () {
            window.location.href = 'layers_daily_record.php?month='
            + encodeURIComponent(layerDailyConfig.yearMonth)
            + '&cycle_id='
            + encodeURIComponent(this.value);
        });
    }


    function parseNumericInput(value) {
        if (value === null || value === undefined) return '';
        return String(value).replace(/,/g, '').trim();
    }

    // Auto-calculate laying rate
    function calculateLayingRate() {
        const openingStock = parseFloat(parseNumericInput(document.getElementById('openingStock').value)) || 0;
        const eggProduction = parseFloat(parseNumericInput(document.getElementById('eggProduction').value)) || 0;

        if (openingStock > 0) {
            const layingRate = (eggProduction / openingStock) * 100;
            document.getElementById('layingRate').value = layingRate.toFixed(1);
        }
    }

    // Auto-calculate crates count (30 eggs per crate)
    function calculateCratesCount() {
        const eggProduction = parseFloat(parseNumericInput(document.getElementById('eggProduction').value)) || 0;
        const crates = eggProduction / 30;
        document.getElementById('cratesCount').value = crates.toFixed(2);
    }

    // Set up auto-calculation
    document.getElementById('openingStock').addEventListener('input', calculateLayingRate);
    document.getElementById('eggProduction').addEventListener('input', function() {
        calculateLayingRate();
        calculateCratesCount();
    });


    const selectedCycleId = layerDailyConfig.selectedCycleId;
    const canEditRetrievedOpeningStock = layerDailyConfig.canEditRetrievedOpeningStock;
    function lockRetrievedOpeningStock() {
        if (!canEditRetrievedOpeningStock) {
            document.getElementById('openingStock').readOnly = true;
        }
    }
    function unlockOpeningStock() {
        document.getElementById('openingStock').readOnly = false;
    }

    // Open modal for new record
    function openRecordModal(date = null, hasRecord = false) {
        const modalElement = document.getElementById('recordModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const today = (window.ReneeCalendar && window.ReneeCalendar.today) ? window.ReneeCalendar.today : appToday();
        const selectedDate = date || today;

        resetForm();

        document.getElementById('selectedDate').value = selectedDate;
        document.getElementById('recordDate').value = selectedDate;

        if (hasRecord) {
            document.getElementById('modalTitle').textContent = 'Edit Record';
        } else {
            document.getElementById('modalTitle').textContent = "Add Today's Record";
            loadExistingRecordOrPreviousStock(selectedDate, "Add Today's Record");
        }

        modal.show();
    }

    function loadExistingRecordOrPreviousStock(date, addTitle) {
        if (selectedCycleId <= 0) {
            document.getElementById('modalTitle').textContent = addTitle;
            return;
        }

        fetch(`../api/check_record.php?type=layer&date=${date}&cycle_id=${selectedCycleId}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    document.getElementById('modalTitle').textContent = 'Edit Record';
                    fetchRecordData(date);
                    return;
                }

                document.getElementById('modalTitle').textContent = addTitle;
                fetchPreviousStock(date);
            })
            .catch(error => {
                console.error(error);
            });
    }

    // Fetch record data
    function fetchRecordData(date) {
        fetch(`../api/get_record.php?type=layer&date=${date}&cycle_id=${selectedCycleId}`)
            .then(response => response.json())
            .then(payload => {
                if (payload && payload.success === false) {
                    throw new Error(payload.error || 'Failed to fetch record');
                }
                const data = payload ? payload.data : null;
                if (data) {
                    document.getElementById('birdsAge').value = parseNumericInput(data.birds_age || '');
                    document.getElementById('openingStock').value = parseNumericInput(data.opening_stock || '');
                    document.getElementById('mortality').value = parseNumericInput(data.mortality || 0);
                    document.getElementById('eggProduction').value = parseNumericInput(data.egg_production || '');
                    document.getElementById('feedConsumption').value = parseNumericInput(data.feed_consumption_bags || '');
                    if (document.getElementById('feedItemId')) document.getElementById('feedItemId').value = data.feed_item_id || '';
                    document.getElementById('waterConsumption').value = parseNumericInput(data.water_consumption_liters || '');
                    document.getElementById('cratesCount').value = parseNumericInput(data.crates_count || '');
                    document.getElementById('layingRate').value = parseNumericInput(data.laying_rate || '');
                    document.getElementById('medications').value = data.medications || '';
                    document.getElementById('remarks').value = data.remarks || '';
                    calculateCratesCount();
                }
            })
            .catch(error => {
                console.error(error);
            });
    }

    // Fetch latest earlier closing stock
    function fetchPreviousStock(selectedDate) {
        fetch(`../api/get_previous_stock.php?type=layer&date=${selectedDate}&cycle_id=${selectedCycleId}`)
            .then(response => response.json())
            .then(payload => {
                if (payload && payload.success === false) {
                    throw new Error(payload.error || 'Failed to fetch previous record');
                }
                if (payload && payload.closing_stock !== null && payload.closing_stock !== undefined) {
                    document.getElementById('openingStock').value = payload.closing_stock > 0 ? payload.closing_stock : '';
                    lockRetrievedOpeningStock();
                }
            })
            .catch(error => {
                console.error(error);
            });
    }

    // Check existing record
    function checkExistingRecord() {
        const date = document.getElementById('selectedDate').value;
        document.getElementById('recordDate').value = date;
        if (selectedCycleId <= 0) {
            resetForm();
            document.getElementById('selectedDate').value = date;
            document.getElementById('recordDate').value = date;
            return;
        }

        fetch(`../api/check_record.php?type=layer&date=${date}&cycle_id=${selectedCycleId}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    document.getElementById('modalTitle').textContent = 'Edit Record';
                    fetchRecordData(date);
                } else {
                    document.getElementById('modalTitle').textContent = "Add Today's Record";
                    resetForm();

                    // Restore the selected date after resetting the form
                    document.getElementById('selectedDate').value = date;
                    document.getElementById('recordDate').value = date;

                    // If an active cycle is selected, try to get the previous stock.
                    fetchPreviousStock(date);
                }
            });
    }

    // Reset form
    function resetForm() {
        unlockOpeningStock();
        document.getElementById('recordForm').reset();
        document.getElementById('mortality').value = 0;
        document.getElementById('cratesCount').value = '';
    }


    document.getElementById('recordForm').addEventListener('submit', () => {
        ['openingStock', 'mortality', 'eggProduction', 'feedConsumption', 'waterConsumption', 'layingRate', 'birdsAge']
            .forEach((fieldId) => {
                const input = document.getElementById(fieldId);
                if (input) {
                    input.value = parseNumericInput(input.value);
                }
            });
    });

    attachEditModal({
        buttonSelector: '.edit-record-btn',
        modalSelector: '#recordModal',
        fieldMap: {
            recordDate: '#recordDate',
            selectedDate: '#selectedDate',
            birdsAge: '#birdsAge',
            openingStock: '#openingStock',
            mortality: '#mortality',
            eggProduction: '#eggProduction',
            feedConsumption: '#feedConsumption',
            feedItemId: '#feedItemId',
            waterConsumption: '#waterConsumption',
            cratesCount: '#cratesCount',
            layingRate: '#layingRate',
            medications: '#medications',
            remarks: '#remarks'
        },
        onShow: ({ modalElement }) => {
            modalElement.querySelector('#modalTitle').textContent = 'Edit Record';
        }
    });

    document.querySelectorAll('.add-record-btn').forEach(button => {
        button.addEventListener('click', () => openRecordModal(button.dataset.recordDate, false));
    });

    if (layerDailyConfig.canDelete) {
    // Delete record
    window.deleteLayerDailyRecord = function deleteLayerDailyRecord(recordId) {
        AppConfirm.ask('Are you sure you want to delete this record? This action cannot be undone.', {
            title: 'Delete daily record?',
            confirmText: 'Delete'
        }).then(function(confirmed) {
            if (!confirmed) return;

            const params = new URLSearchParams({
                type: 'layer',
                id: recordId,
                csrf_token: layerDailyConfig.csrfToken
            });

            fetch('../api/delete_record.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    AppNotify.error(data.error || data.message || 'Unable to delete record');
                }
            })
            .catch(error => {
                console.error(error);
                AppNotify.error('Unable to delete record. Please try again.');
            });
        });
    };
}
