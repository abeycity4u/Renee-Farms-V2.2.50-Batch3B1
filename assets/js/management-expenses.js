/**
 * Management Expenses report browser behavior.
 * Dynamic permission and CSRF values are supplied through page config.
 * Externalized for CSP compatibility.
 */
const expensesConfigElement =
    document.getElementById('managementExpensesConfig');

const canManageExpenses =
    expensesConfigElement?.dataset.canManage === '1';

const csrfToken =
    expensesConfigElement?.dataset.csrfToken || '';

// Filter change
    function applyFilters() {
        const farmType = $('#farmTypeFilter').val();
        const productionType = $('#productionTypeFilter').val();
            const category = $('#categoryFilter').val();
        const monthValue = $('#monthFilter').val();
        const month = monthValue ? monthValue.substring(0, 7) : '';
        const reportMode = $('#reportMode').val();
        const year = $('#yearFilter').val();
        window.location.href = `expenses.php?report_mode=${reportMode}&month=${month}&year=${year}&farm_type=${farmType}&production_type=${productionType}&category=${category}`;
    }

    const expenseAttributionTypes = {
        poultry: {layer:'Layer', broiler:'Broiler', shared:'Shared / Unallocated Poultry'},
        ruminant: {cattle:'Cattle', goat:'Goat', sheep:'Sheep', other:'Other', shared:'Shared / Unallocated Ruminant'},
        general: {general:'General / Other Farm Expense'}
    };
    function refreshExpenseProductionTypes(selected = 'all') {
        const farm = $('#farmTypeFilter').val();
        const select = $('#productionTypeFilter');
        select.empty().append(new Option('All Production Types', 'all'));
        Object.entries(expenseAttributionTypes[farm] || {}).forEach(([value,label]) => select.append(new Option(label,value,false,value===selected)));
        if (!select.val()) select.val('all');
    }

    $('#farmTypeFilter').change(function() {
        refreshExpenseProductionTypes('all');
        applyFilters();
    });
    $('#productionTypeFilter, #categoryFilter, #monthFilter, #reportMode, #yearFilter').change(function() {
        const mode = $('#reportMode').val();
        $('#monthFilter').toggle(mode === 'monthly');
        $('#yearFilter').toggle(mode === 'yearly');
        $('#printMonthlyBtn').toggle(mode === 'monthly');
        $('#printYearlyBtn').toggle(mode === 'yearly');
        applyFilters();
    });

    if (canManageExpenses) {
    attachEditModal({
        buttonSelector: '.edit-expense-btn',
        modalSelector: '#editExpenseModal',
            fieldMap: {
                id: '#editExpenseId',
                date: '#editExpenseDate',
                farmType: '#editFarmType',
                category: '#editCategory',
                amount: '#editAmount',
                description: '#editDescription',
                unit: '#editUnit'
            }
        });

    document.getElementById('editExpenseForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch('../api/update_expense.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                location.reload();
            } else {
                AppNotify.error((result.error || result.message || 'Unable to update expense'));
            }
        } catch (error) {
            AppNotify.error('Network error: ' + error.message);
        }
    });
    }
    
    function deleteExpense(expenseId) {
        AppConfirm.ask('Are you sure you want to delete this expense record?', {title:'Delete expense record?', confirmText:'Delete'}).then(function(confirmed){ if (confirmed) {
            const params = new URLSearchParams({ id: expenseId, csrf_token: csrfToken });
            fetch('../api/delete_expense.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        AppNotify.error(data.error || data.message || 'Unable to delete expense');
                    }
                });
        }
        });
    }
