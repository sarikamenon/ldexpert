import { setupStatusChanges } from '../common/status-change';

function initStudentProgressChart() {
    const canvas = document.getElementById('studentProgressChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    const served = parseFloat(canvas.dataset.served || '0');
    const tho = parseFloat(canvas.dataset.tho || '0');
    const remaining = Math.round(Math.max(0, tho - served) * 100) / 100;

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Served Hours', 'Remaining Hours'],
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
                            return `${label}: ${value.toFixed(2)} hrs (${percentage}%)`;
                        },
                    },
                },
            },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initStudentProgressChart();
    setupStatusChanges('student', '.change-status-btn', { idAttribute: 'student-id' });
});

