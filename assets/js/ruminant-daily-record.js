/**
 * Ruminant Daily Record browser behavior.
 * Dynamic server state is supplied through ruminantDailyConfig.
 *
 * This remains a classic top-level script so openRecordModal() and
 * checkExistingRecord() preserve the existing shared runtime contract.
 * Externalized for CSP compatibility.
 */
const ruminantDailyConfigElement =
    document.getElementById('ruminantDailyConfig');

const ruminantDailyConfig = {
    selectedCycleId: Number(
        ruminantDailyConfigElement?.dataset.selectedCycleId || 0
    ),
    yearMonth:
        ruminantDailyConfigElement?.dataset.yearMonth || '',
    selectedCycleAnimalType:
        ruminantDailyConfigElement?.dataset.selectedCycleAnimalType || '',
    canEditRetrievedOpeningStock:
        ruminantDailyConfigElement?.dataset.canEditOpening === '1'
};

// Feed item unit synchronization
const feedItemSelector = document.getElementById('feedItemId');
    if (feedItemSelector) {
        feedItemSelector.addEventListener('change', function () {
            const option = this.options[this.selectedIndex];
            const unit = option ? (option.dataset.unit || 'kg') : 'kg';
            const unitInput = document.getElementById('feedConsumptionUnit');
            if (unitInput) unitInput.value = unit;
            const label = document.getElementById('feedConsumptionLabel');
            if (label) label.textContent = 'Feed Consumption (' + unit + ')';
        });
    }

// Month selector
    document.getElementById('monthSelector').addEventListener('blur', function() {
        window.location.href = 'ruminant_daily_record.php?month='
        + encodeURIComponent(this.value.substring(0, 7))
        + '&cycle_id='
        + encodeURIComponent(ruminantDailyConfig.selectedCycleId);
    });
    const cycleSelector = document.getElementById('activeCycleSelector');
    if (cycleSelector) {
        cycleSelector.addEventListener('change', function () {
            window.location.href = 'ruminant_daily_record.php?month='
            + encodeURIComponent(ruminantDailyConfig.yearMonth)
            + '&cycle_id='
            + encodeURIComponent(this.value);
        });
    }

    const selectedCycleId = ruminantDailyConfig.selectedCycleId;
    const selectedCycleAnimalType = ruminantDailyConfig.selectedCycleAnimalType;
    const validRuminantTypes = ['cattle', 'goat', 'sheep', 'other'];

    function getSelectedCycleAnimalType() {
        const animalType = String(selectedCycleAnimalType || '').toLowerCase();
        return selectedCycleId > 0 && validRuminantTypes.includes(animalType) ? animalType : null;
    }

    function parseNumericInput(value) {
        if (value === null || value === undefined) return '';
        return String(value).replace(/,/g, '').trim();
    }

    // Open modal for new record
    function openRecordModal(date = null, animalType = null, hasRecord = false) {
        const modalElement = document.getElementById('recordModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const today = (window.ReneeCalendar && window.ReneeCalendar.today) ? window.ReneeCalendar.today : appToday();
        const selectedDate = date || today;
        const defaultAnimalType = animalType || getSelectedCycleAnimalType();

        resetForm();
        document.getElementById('animalType').disabled = false;

        document.getElementById('selectedDate').value = selectedDate;
        document.getElementById('recordDate').value = selectedDate;

        if (defaultAnimalType) {
            document.getElementById('animalType').value = defaultAnimalType;
            document.getElementById('animalTypeHidden').value = defaultAnimalType;
        }

        if (hasRecord) {
            document.getElementById('modalTitle').textContent = 'Edit Record';
        } else {
            document.getElementById('modalTitle').textContent = "Add Today's Record";
            if (defaultAnimalType) {
                loadExistingRecordOrPreviousStock(selectedDate, defaultAnimalType);
            }
        }

        modal.show();
    }

    function loadExistingRecordOrPreviousStock(date, animalType) {
        if (selectedCycleId <= 0 || !date || !animalType) {
            document.getElementById('modalTitle').textContent = "Add Today's Record";
            return;
        }

        const params = new URLSearchParams({
            date: date,
            animal_type: animalType
        });

        fetch(`../api/check_ruminant_record.php?cycle_id=${selectedCycleId}&${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    document.getElementById('modalTitle').textContent = 'Edit Record';
                    document.getElementById('animalType').disabled = true;
                    fetchRecordData(date, animalType);
                    return;
                }

                document.getElementById('modalTitle').textContent = "Add Today's Record";
                document.getElementById('animalType').disabled = false;
                fetchPreviousStock(date, animalType);
            })
            .catch(error => console.error(error));
    }

    const canEditRetrievedOpeningStock = ruminantDailyConfig.canEditRetrievedOpeningStock;
    function lockRetrievedOpeningStock() {
        if (!canEditRetrievedOpeningStock) {
            document.getElementById('openingStock').readOnly = true;
        }
    }
    function unlockOpeningStock() {
        document.getElementById('openingStock').readOnly = false;
    }

    // Fetch record data
    function fetchRecordData(date, animalType) {
        const params = new URLSearchParams({
            date: date,
            animal_type: animalType
        });

        fetch(`../api/get_record.php?type=ruminant&cycle_id=${selectedCycleId}&${params}`)
            .then(response => response.json())
            .then(payload => {
                if (payload && payload.success === false) {
                    throw new Error(payload.error || 'Failed to fetch record');
                }
                const data = payload ? payload.data : null;
                if (data) {
                    document.getElementById('openingStock').value = parseNumericInput(data.opening_stock || '');
                    document.getElementById('mortality').value = parseNumericInput(data.mortality || 0);
                    document.getElementById('feedConsumption').value = parseNumericInput(data.feed_consumption_kg || '');
                    if (document.getElementById('feedItemId')) { document.getElementById('feedItemId').value = data.feed_item_id || ''; document.getElementById('feedConsumptionUnit').value = data.feed_consumption_unit || 'kg'; const label = document.getElementById('feedConsumptionLabel'); if (label) label.textContent = 'Feed Consumption (' + (data.feed_consumption_unit || 'kg') + ')'; }
                    document.getElementById('waterConsumption').value = parseNumericInput(data.water_consumption_liters || '');
                    document.getElementById('tagNo').value = data.tag_no || '';
                    document.getElementById('medications').value = data.medications || '';
                    document.getElementById('reproductionDetails').value = data.reproduction_details || '';
                    document.getElementById('otherDetails').value = data.other_details || '';
                    document.getElementById('remarks').value = data.remarks || '';
                }
            })
            .catch(error => {
                console.error(error);
            });
    }


    function fetchPreviousStock(date, animalType) {
        const params = new URLSearchParams({
            type: 'ruminant',
            date: date,
            animal_type: animalType,
            cycle_id: selectedCycleId
        });

        fetch(`../api/get_previous_stock.php?${params}`)
            .then(response => response.json())
            .then(payload => {
                if (payload && payload.closing_stock !== null && payload.closing_stock !== undefined) {
                    document.getElementById('openingStock').value = payload.closing_stock > 0 ? payload.closing_stock : '';
                    lockRetrievedOpeningStock();
                }
            })
            .catch(error => console.error(error));
    }

    // Check existing record
    function checkExistingRecord() {
        const date = document.getElementById('selectedDate').value;
        const animalType = document.getElementById('animalType').value;

        document.getElementById('recordDate').value = date;
        document.getElementById('animalTypeHidden').value = animalType;

        if (!date || !animalType) return;
        if (selectedCycleId <= 0) {
            resetForm();
            document.getElementById('selectedDate').value = date;
            document.getElementById('recordDate').value = date;
            document.getElementById('animalType').value = animalType;
            document.getElementById('animalTypeHidden').value = animalType;
            return;
        }

        const params = new URLSearchParams({
            date: date,
            animal_type: animalType
        });

        fetch(`../api/check_ruminant_record.php?cycle_id=${selectedCycleId}&${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    document.getElementById('modalTitle').textContent = 'Edit Record';
                    document.getElementById('animalType').disabled = true;
                    fetchRecordData(date, animalType);
                } else {
                    document.getElementById('modalTitle').textContent = "Add Today's Record";
                    document.getElementById('animalType').disabled = false;
                    const currentDate = date;
                    const currentAnimalType = animalType;
                    resetForm();
                    document.getElementById('selectedDate').value = currentDate;
                    document.getElementById('recordDate').value = currentDate;
                    document.getElementById('animalType').value = currentAnimalType;
                    document.getElementById('animalTypeHidden').value = currentAnimalType;
                    fetchPreviousStock(currentDate, currentAnimalType);
                }
            });
    }

    document.getElementById('animalType').addEventListener('change', () => {
        document.getElementById('animalTypeHidden').value = document.getElementById('animalType').value;
        checkExistingRecord();
    });

    // Reset form
    function resetForm() {
        unlockOpeningStock();
        document.getElementById('recordForm').reset();
        document.getElementById('mortality').value = 0;
        document.getElementById('recordDate').value = document.getElementById('selectedDate').value;
        document.getElementById('animalTypeHidden').value = document.getElementById('animalType').value;
    }

    document.getElementById('recordForm').addEventListener('submit', () => {
        ['openingStock', 'mortality', 'feedConsumption', 'waterConsumption'].forEach((fieldId) => {
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
            animalType: '#animalType',
            openingStock: '#openingStock',
            mortality: '#mortality',
            feedConsumption: '#feedConsumption',
            feedItemId: '#feedItemId',
            waterConsumption: '#waterConsumption',
            tagNo: '#tagNo',
            medications: '#medications',
            reproductionDetails: '#reproductionDetails',
            otherDetails: '#otherDetails',
            remarks: '#remarks'
        },
        onShow: ({ modalElement, data }) => {
            modalElement.querySelector('#modalTitle').textContent = 'Edit Record';
            modalElement.querySelector('#animalType').disabled = true;
            modalElement.querySelector('#animalTypeHidden').value = data.animalType || '';
        }
    });

    document.querySelectorAll('.add-record-btn').forEach(button => {
        button.addEventListener('click', () => {
            const cycleAnimalType = (button.dataset.cycleAnimalType || '').toLowerCase();
            const animalType = validRuminantTypes.includes(cycleAnimalType) ? cycleAnimalType : null;
            openRecordModal(button.dataset.recordDate, animalType, false);
        });
    });

    // Show messages
