function initSchoolSsaChart() {
    const canvas = document.getElementById('schoolSsaChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    const payload = canvas.dataset.chart ? JSON.parse(canvas.dataset.chart) : { labels: [], data: [] };

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: payload.labels || [],
            datasets: [{
                data: payload.data || [],
                backgroundColor: ['#22c55e', '#fbbf24', '#6366f1', '#94a3b8'],
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
                            size: 12,
                        },
                    },
                },
            },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initSchoolSsaChart();
});

