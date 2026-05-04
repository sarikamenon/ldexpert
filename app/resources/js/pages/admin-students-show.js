import { setupStatusChanges } from '../common/status-change';

function initStudentProgressChart() {
    const canvas = document.getElementById('studentProgressChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    let outcomes = [];
    try {
        outcomes = JSON.parse(canvas.dataset.outcomes || '[]');
    } catch (e) {
        outcomes = [];
    }

    if (!outcomes.length) {
        return;
    }

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: outcomes.map((o) => o.label),
            datasets: [{
                data: outcomes.map((o) => o.hours),
                backgroundColor: outcomes.map((o) => o.color),
                borderWidth: 2,
                borderColor: '#ffffff',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
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

