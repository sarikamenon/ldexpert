import { setupStatusChanges } from '../common/status-change';
import { setupAssignModal, setupUnassignModal } from '../common/ssa-modals';

// Design system colors (from tailwind.config.js)
const CHART_COLORS = {
    approved: '#22c55e',       // success
    loggedNotApproved: '#f59e0b', // warning
    scheduledNotLogged: '#99f6e4', // secondary-200
    remaining: '#e5e7eb',      // gray
    served: '#14b8a6',         // secondary (teal)
};

// Initialize delivery progress chart (2-segment or 4-segment when minutes summary exists)
function initDeliveryProgressChart() {
    const canvas = document.getElementById('deliveryProgressChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const served = parseFloat(canvas.dataset.served || '0');
    const tho = parseFloat(canvas.dataset.tho || '0');
    const scheduled = parseFloat(canvas.dataset.scheduled || '0');
    const logged = parseFloat(canvas.dataset.logged || '0');
    const approved = parseFloat(canvas.dataset.approved || '0');
    const hasMinutesSummary = canvas.dataset.scheduled !== undefined && tho > 0;

    let labels;
    let data;
    let backgroundColor;

    if (hasMinutesSummary) {
        const loggedNotApproved = Math.round(Math.max(0, logged - approved) * 100) / 100;
        const scheduledNotLogged = Math.round(Math.max(0, scheduled - logged) * 100) / 100;
        const remaining = Math.round(Math.max(0, tho - scheduled) * 100) / 100;

        labels = [
            'Approved Hours',
            'Logged (not approved)',
            'Scheduled (not logged)',
            'Remaining',
        ];
        data = [approved, loggedNotApproved, scheduledNotLogged, remaining];
        backgroundColor = [
            CHART_COLORS.approved,
            CHART_COLORS.loggedNotApproved,
            CHART_COLORS.scheduledNotLogged,
            CHART_COLORS.remaining,
        ];
    } else {
        const remaining = Math.round(Math.max(0, tho - served) * 100) / 100;
        labels = ['Served Hours', 'Remaining Hours'];
        data = [served, remaining];
        backgroundColor = [CHART_COLORS.served, CHART_COLORS.remaining];
    }

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor,
                borderWidth: 2,
                borderColor: '#ffffff',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 10,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value.toFixed(2)} hrs (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    initDeliveryProgressChart();
    setupStatusChanges('ssa', '.change-status-btn', { idAttribute: 'ssa-id' });
    setupAssignModal({ onSuccess: () => window.location.reload() });
    setupUnassignModal({ onSuccess: () => window.location.reload() });
});

