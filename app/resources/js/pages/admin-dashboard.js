function initAccountBalanceChart() {
    const canvas = document.getElementById('accountBalanceChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    const payload = canvas.dataset.chart
        ? JSON.parse(canvas.dataset.chart)
        : { labels: [], data: [], colors: [], formatted: [] };

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: payload.labels || [],
            datasets: [{
                data: payload.data || [],
                backgroundColor: payload.colors || [],
                borderWidth: 2,
                borderColor: '#ffffff',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 10,
                        font: {
                            size: 11,
                        },
                    },
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const formatted = (payload.formatted || [])[context.dataIndex];
                            return `${context.label}: ${formatted}`;
                        },
                    },
                },
            },
        },
    });
}

function initOpenSubRequestsChart() {
    const canvas = document.getElementById('openSubRequestsChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    const payload = canvas.dataset.chart
        ? JSON.parse(canvas.dataset.chart)
        : { labels: [], data: [], colors: [] };

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: payload.labels || [],
            datasets: [{
                label: 'Open Sub Requests',
                data: payload.data || [],
                backgroundColor: payload.colors || [],
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                    },
                },
            },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initAccountBalanceChart();
    initOpenSubRequestsChart();
});
