import { setupStatusChanges } from '../common/status-change';

function initTherapistProgressChart() {
    const canvas = document.getElementById('therapistProgressChart');
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
                backgroundColor: ['#0ea5e9', '#e5e7eb'],
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
                        label(context) {
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
    initTherapistProgressChart();
    setupStatusChanges('therapist', '.change-status-btn', { idAttribute: 'therapist-id' });
});

