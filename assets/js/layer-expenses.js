/**
 * Layer Expenses page behavior.
 * Externalized for CSP compatibility.
 */
window.LayerExpensesConfig = window.LayerExpensesConfig || {
    csrfToken: '',
    canManage: false
};

(function () {
    const config = document.getElementById('layerExpensesConfig');

    if (!config) {
        return;
    }

    window.LayerExpensesConfig = {
        csrfToken: config.dataset.csrfToken || '',
        canManage: config.dataset.canManage === '1'
    };
})();

// Month selector
    document.getElementById('monthSelector').addEventListener('change', function() {
        window.location.href = 'layer_expenses.php?month=' + this.value.substring(0, 7);
    });

    
    if (window.LayerExpensesConfig.canManage) {
    attachEditModal({
        buttonSelector: '.edit-expense-btn',
        modalSelector: '#editExpenseModal',
        fieldMap: {
            id: '#editExpenseId',
            date: '#editExpenseDate',
            category: '#editCategory',
            amount: '#editAmount',
            unit: '#editUnit',
            description: '#editDescription',
            cycle: '#editExpenseCycle',
            poultry: '#editPoultryCategory'
        }
    });

    document.getElementById('editExpenseForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('csrf_token', window.LayerExpensesConfig.csrfToken);

        try {
            const response = await fetch('../api/update_expense.php', {
                method: 'POST',
                body: formData
            });
            const result = await parseJsonResponse(response);

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
    

    async function parseJsonResponse(response) {
        const contentType = response.headers.get('content-type') || '';

        if (!contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(text || 'Unexpected non-JSON response');
        }

        return response.json();
    }

    async function deleteExpense(expenseId) {
        if (!window.LayerExpensesConfig.canManage) {
            return;
        }

        const confirmed = await AppConfirm.ask('Are you sure you want to delete this expense record?', {title:'Delete expense record?', confirmText:'Delete'});
        if (!confirmed) return;

        try {
            const params = new URLSearchParams({ id: expenseId, csrf_token: window.LayerExpensesConfig.csrfToken });
            const response = await fetch('../api/delete_expense.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });
            const data = await parseJsonResponse(response);

            if (data.success) {
                location.reload();
            } else {
                AppNotify.error(data.error || data.message || 'Unable to delete expense');
            }
        } catch (error) {
            AppNotify.error('Network error: ' + error.message);
        }
    }

    window.deleteExpense = deleteExpense;

    // Show messages
