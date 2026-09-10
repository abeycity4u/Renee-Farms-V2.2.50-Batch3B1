/**
 * Stock History browser behavior.
 * Dynamic item/API state is supplied through stockHistoryConfig.
 * Externalized for CSP compatibility.
 */
(function () {
    const configElement =
        document.getElementById('stockHistoryConfig');

    if (!configElement) return;

    const stockHistoryConfig = {
        itemId: configElement.dataset.itemId || '',
        historyUrl: configElement.dataset.historyUrl || ''
    };

const itemId = stockHistoryConfig.itemId;
let chartInstance = null;

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' });
}

function renderChart(chartData) {
    const ctx = document.getElementById('stockChart').getContext('2d');
    if (chartInstance) {
        chartInstance.destroy();
    }
    chartInstance = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function attributionText(tx) {
    const farmType = String(tx.farm_type || '').toLowerCase();
    const productionType = String(tx.production_type || '').toLowerCase();

    if (farmType === 'poultry') {
        if (productionType === 'layer') return 'Poultry · Layer';
        if (productionType === 'broiler') return 'Poultry · Broiler';
        return 'Poultry · Shared Poultry';
    }
    if (farmType === 'ruminant') {
        const labels = { cattle: 'Cattle', goat: 'Goat', sheep: 'Sheep', other: 'Other' };
        if (labels[productionType]) return 'Ruminant · ' + labels[productionType];
        return 'Ruminant · Shared Ruminant';
    }
    if (farmType === 'general') return 'Farm-wide / General';

    return farmType || productionType ? [farmType, productionType].filter(Boolean).join(' · ') : 'Unallocated';
}

function renderTable(transactions) {
    const tbody = document.querySelector('#historyTable tbody');
    tbody.innerHTML = '';

    if (!transactions || transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted">No history available for the selected period.</td></tr>';
        return;
    }

    transactions.forEach(tx => {
        const row = document.createElement('tr');
        const typeBadge = tx.transaction_type === 'received' ?
            '<span class="badge bg-success">Received</span>' :
            '<span class="badge bg-danger">Used</span>';

        row.innerHTML = `
            <td>${formatDate(tx.transaction_date)}</td>
            <td>${typeBadge}</td>
            <td class="text-end">${Number(tx.quantity).toLocaleString()}</td>
            <td class="text-end">${Number(tx.previous_stock).toLocaleString()}</td>
            <td class="text-end">${Number(tx.new_stock).toLocaleString()}</td>
            <td>${escapeHtml(attributionText(tx))}</td>
            <td>${tx.cycle_code ? '<strong>' + escapeHtml(tx.cycle_code) + '</strong>' : '<span class="text-muted">Shared / No specific cycle</span>'}</td>
            <td>${tx.remarks ? escapeHtml(tx.remarks) : ''}</td>
            <td>${tx.recorded_by_label ? escapeHtml(tx.recorded_by_label) : 'N/A'}</td>
            <td>${Number(tx.is_reversed) === 1 ? '<span class="badge bg-secondary">Reversed</span>' : (tx.reversal_of_id ? '<span class="badge bg-info text-dark">Restoration</span>' : '<span class="badge bg-success">Active</span>')}</td>
            <td>${tx.created_at ? new Date(tx.created_at.replace(' ', 'T') + 'Z').toLocaleString() : 'N/A'}</td>
        `;
        tbody.appendChild(row);
    });
}

function updateSummary(summary, currentStock) {
    document.getElementById('currentStock').textContent = Number(currentStock ?? 0).toLocaleString();
    document.getElementById('totalReceived').textContent = Number(summary.total_received ?? 0).toLocaleString();
    document.getElementById('totalUsed').textContent = Number(summary.total_used ?? 0).toLocaleString();
    document.getElementById('transactionCount').textContent = summary.transaction_count ?? 0;
    let integrity = document.getElementById('integrityStatus');
    if (!integrity) {
        const card = document.getElementById('currentStock').closest('.card');
        integrity = document.createElement('div');
        integrity.id = 'integrityStatus';
        integrity.className = 'small mt-1';
        card.appendChild(integrity);
    }
    integrity.innerHTML = summary.integrity_status === 'reconciled'
        ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Ledger reconciled</span>'
        : '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Ledger mismatch: ' + Number(summary.stock_difference || 0).toLocaleString() + '</span>';
}

async function loadHistory(days = 30) {
    const response = await fetch(`${stockHistoryConfig.historyUrl}?item_id=${encodeURIComponent(itemId)}&days=${encodeURIComponent(days)}`);
    const data = await response.json();

    if (data.error) {
        document.querySelector('#historyTable tbody').innerHTML = `<tr><td colspan="11" class="text-danger text-center">${escapeHtml(data.error)}</td></tr>`;
        return;
    }

    renderChart(data.chart_data);
    renderTable(data.transactions);
    updateSummary(data.summary, data.current_stock);
}

document.getElementById('daysFilter').addEventListener('change', (event) => {
    loadHistory(event.target.value);
});

loadHistory();
})();
