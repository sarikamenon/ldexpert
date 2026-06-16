import { setupStatusChanges } from '../common/status-change';
import { resolveChartColor } from '../common/chart-colors';
import { sendLoginDetails } from '../common/welcome-email';

function setupSendLoginDetails() {
    const button = document.getElementById('sendLoginDetailsButton');
    if (!button) {
        return;
    }

    button.addEventListener('click', () => {
        sendLoginDetails(button.dataset.url, [button.dataset.studentId]);
    });
}

function initStudentProgressChart() {
    const canvas = document.getElementById('studentProgressChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    let outcomes = [];
    try {
        outcomes = JSON.parse(canvas.dataset.outcomes || '[]');
    } catch {
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
                backgroundColor: outcomes.map((o) => resolveChartColor(o.color_key)),
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

function paintOutcomeSwatches() {
    document.querySelectorAll('.js-outcome-swatch').forEach((el) => {
        const key = el.dataset.colorKey;
        if (!key) {
            return;
        }
        el.style.backgroundColor = resolveChartColor(key);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    paintOutcomeSwatches();
    initStudentProgressChart();
    setupStatusChanges('student', '.change-status-btn', { idAttribute: 'student-id' });
    setupSendLoginDetails();
});

