function initStudentProgressChart() {
    const canvas = document.getElementById('studentProgressChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    const served = parseInt(canvas.dataset.served || '0', 10);
    const tho = parseInt(canvas.dataset.tho || '0', 10);
    const remaining = Math.max(0, tho - served);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Served', 'Remaining'],
            datasets: [{
                data: [served, remaining],
                backgroundColor: ['#14b8a6', '#e5e7eb'],
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
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((sum, item) => sum + item, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value.toLocaleString()} (${percentage}%)`;
                        },
                    },
                },
            },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initStudentProgressChart();
});

