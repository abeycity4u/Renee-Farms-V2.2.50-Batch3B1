/**
 * Reports & Analytics browser behavior.
 * Dynamic chart datasets are supplied through page config.
 * Externalized for CSP compatibility.
 */
const reportsConfigElement =
    document.getElementById('managementReportsConfig');

let reportChartData = {
    profitLabels: [],
    profitValues: [],
    productLabels: [],
    productValues: [],
    expenseLabels: [],
    expenseValues: []
};

if (reportsConfigElement) {
    try {
        reportChartData = {
            profitLabels: JSON.parse(
                reportsConfigElement.dataset.profitLabels || '[]'
            ),
            profitValues: JSON.parse(
                reportsConfigElement.dataset.profitValues || '[]'
            ),
            productLabels: JSON.parse(
                reportsConfigElement.dataset.productLabels || '[]'
            ),
            productValues: JSON.parse(
                reportsConfigElement.dataset.productValues || '[]'
            ),
            expenseLabels: JSON.parse(
                reportsConfigElement.dataset.expenseLabels || '[]'
            ),
            expenseValues: JSON.parse(
                reportsConfigElement.dataset.expenseValues || '[]'
            )
        };
    } catch (error) {
        reportChartData = {
            profitLabels: [],
            profitValues: [],
            productLabels: [],
            productValues: [],
            expenseLabels: [],
            expenseValues: []
        };
    }
}

// Filter change
    document.getElementById('yearFilter').addEventListener('change', function() {
        updateReport();
    });

    document.getElementById('farmTypeFilter').addEventListener('change', function() {
        updateReport();
    });

    function updateReport() {
        const year = document.getElementById('yearFilter').value;
        const farmType = document.getElementById('farmTypeFilter').value;
        window.location.href = `reports.php?year=${year}&farm_type=${farmType}`;
    }
window.exportToExcel = function exportToExcel() {
        const year = document.getElementById('yearFilter').value;
        const farmType = document.getElementById('farmTypeFilter').value;
        window.location.href = `reports.php?year=${year}&farm_type=${farmType}&export=excel`;
    };

    // Initialize charts when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Profit/Loss Chart
        const profitCtx = document.getElementById('profitChart').getContext('2d');
        const profitChart = new Chart(profitCtx, {
            type: 'line',
            data: {
                labels: reportChartData.profitLabels,
                datasets: [{
                    label: 'Net Profit (₦)',
                    data: reportChartData.profitValues,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Top Products Chart
        const productsCtx = document.getElementById('productsChart').getContext('2d');
        const productsChart = new Chart(productsCtx, {
            type: 'bar',
            data: {
                labels: reportChartData.productLabels,
                datasets: [{
                    label: 'Revenue (₦)',
                    data: reportChartData.productValues,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Expenses Chart
        const expensesCtx = document.getElementById('expensesChart').getContext('2d');
        const expensesChart = new Chart(expensesCtx, {
            type: 'pie',
            data: {
                labels: reportChartData.expenseLabels,
                datasets: [{
                    data: reportChartData.expenseValues,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += '₦' + context.parsed.toLocaleString();
                                return label;
                            }
                        }
                    }
                }
            }
        });
    });
