/**
 * Broiler Daily Record browser behavior.
 * Dynamic server state is supplied through broilerDailyConfig.
 * Externalized for CSP compatibility.
 */
const broilerDailyConfigElement =
    document.getElementById('broilerDailyConfig');

const broilerDailyConfig = {
    selectedCycleId: Number(
        broilerDailyConfigElement?.dataset.selectedCycleId || 0
    ),
    yearMonth:
        broilerDailyConfigElement?.dataset.yearMonth || '',
    canEditRetrievedOpeningStock:
        broilerDailyConfigElement?.dataset.canEditOpening === '1',
    canDelete:
        broilerDailyConfigElement?.dataset.canDelete === '1',
    csrfToken:
        broilerDailyConfigElement?.dataset.csrfToken || ''
};

// Month selector
document.getElementById('monthSelector').addEventListener('blur', function() {
    window.location.href = 'broiler_daily_record.php?month='
        + encodeURIComponent(this.value.substring(0, 7))
        + '&cycle_id='
        + encodeURIComponent(broilerDailyConfig.selectedCycleId);
});
const cycleSelector = document.getElementById('activeCycleSelector');
if (cycleSelector) {
    cycleSelector.addEventListener('change', function () {
        window.location.href = 'broiler_daily_record.php?month='
            + encodeURIComponent(broilerDailyConfig.yearMonth)
            + '&cycle_id='
            + encodeURIComponent(this.value);
    });
}


    const selectedCycleId = broilerDailyConfig.selectedCycleId;
    const canEditRetrievedOpeningStock = broilerDailyConfig.canEditRetrievedOpeningStock;
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
            document.getElementById('modalTitle').textContent = 'Add Daily Record';
            loadExistingRecordOrPreviousStock(selectedDate, 'Add Daily Record');
        }

        modal.show();
    }

    function loadExistingRecordOrPreviousStock(date, addTitle) {
        if (selectedCycleId <= 0) {
            document.getElementById('modalTitle').textContent = addTitle;
            return;
        }

        fetch(`../api/check_record.php?type=broiler&date=${date}&cycle_id=${selectedCycleId}`)
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
        fetch(`../api/get_record.php?type=broiler&date=${date}&cycle_id=${selectedCycleId}`)
            .then(response => response.json())
            .then(payload => {
                if (payload && payload.success === false) {
                    throw new Error(payload.error || 'Failed to fetch record');
                }
                const data = payload ? payload.data : null;
                if (data) {
                    document.getElementById('birdsAge').value = data.birds_age || '';
                    document.getElementById('openingStock').value = data.opening_stock || '';
                    document.getElementById('mortality').value = data.mortality || 0;
                    document.getElementById('feedConsumption').value = data.feed_consumption_bags || '';
                    if (document.getElementById('feedItemId')) document.getElementById('feedItemId').value = data.feed_item_id || '';
                    document.getElementById('waterConsumption').value = data.water_consumption_liters || '';
                    document.getElementById('medications').value = data.medications || '';
                    document.getElementById('remarks').value = data.remarks || '';
                }
            })
            .catch(error => {
                console.error(error);
            });
    }

    // Fetch latest earlier closing stock
    function fetchPreviousStock(selectedDate) {
        fetch(`../api/get_previous_stock.php?type=broiler&date=${selectedDate}&cycle_id=${selectedCycleId}`)
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

        fetch(`../api/check_record.php?type=broiler&date=${date}&cycle_id=${selectedCycleId}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    document.getElementById('modalTitle').textContent = 'Edit Record';
                    fetchRecordData(date);
                } else {
                    document.getElementById('modalTitle').textContent = 'Add Daily Record';
                    resetForm();

                    document.getElementById('selectedDate').value = date;
                    document.getElementById('recordDate').value = date;

                    fetchPreviousStock(date);
                }
            });
    }

    // Reset form
    function resetForm() {
        unlockOpeningStock();
        document.getElementById('recordForm').reset();
        document.getElementById('mortality').value = 0;
    }

    // Attach edit modals
    attachEditModal({
        buttonSelector: '.edit-record-btn',
        modalSelector: '#recordModal',
        fieldMap: {
            recordDate: '#recordDate',
            selectedDate: '#selectedDate',
            birdsAge: '#birdsAge',
            openingStock: '#openingStock',
            mortality: '#mortality',
            feedConsumption: '#feedConsumption',
            feedItemId: '#feedItemId',
            waterConsumption: '#waterConsumption',
            medications: '#medications',
            remarks: '#remarks'
        },
        onShow: ({ modalElement }) => {
            modalElement.querySelector('#modalTitle').textContent = 'Edit Record';
        }
    });

    // Add record from calendar
    document.querySelectorAll('.add-record-btn').forEach(button => {
        button.addEventListener('click', () => {
            openRecordModal(button.dataset.recordDate, false);
        });
    });

    // Show messages
    
    if (broilerDailyConfig.canDelete) {
    window.deleteBroilerDailyRecord = async function deleteBroilerDailyRecord(recordId) {
        const confirmed = await AppConfirm.ask('Are you sure you want to delete this record? This action cannot be undone.', {title:'Delete daily record?', confirmText:'Delete'});
        if (!confirmed) return;
        try {
            const params = new URLSearchParams({ type: 'broiler', id: recordId, csrf_token: broilerDailyConfig.csrfToken });
            const response = await fetch('../api/delete_record.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: params.toString() });
            const data = await response.json().catch(() => ({}));
            if (response.ok && data.success) {
                AppNotify.success('Broiler daily record deleted successfully.');
                setTimeout(() => location.reload(), 700);
            } else {
                AppNotify.error(data.error || data.message || 'Unable to delete broiler daily record.');
            }
        } catch (error) {
            AppNotify.error('Unable to delete broiler daily record. Please try again.');
        }
    };
}
