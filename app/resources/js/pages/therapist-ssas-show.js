// Chart.js is loaded from CDN in the blade template
const CHART_COLORS = {
    approved: '#22c55e',
    loggedNotApproved: '#f59e0b',
    scheduledNotLogged: '#99f6e4',
    remaining: '#e5e7eb',
    served: '#14b8a6',
};

document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js is not loaded. Make sure the CDN script is included in the page.');
        return;
    }

    const chartCanvas = document.getElementById('deliveryProgressChart');
    if (!chartCanvas) return;

    const served = parseFloat(chartCanvas.dataset.served) || 0;
    const tho = parseFloat(chartCanvas.dataset.tho) || 0;
    const scheduled = parseFloat(chartCanvas.dataset.scheduled) || 0;
    const logged = parseFloat(chartCanvas.dataset.logged) || 0;
    const approved = parseFloat(chartCanvas.dataset.approved) || 0;
    const hasMinutesSummary = chartCanvas.dataset.scheduled !== undefined && tho > 0;

    let labels, data, backgroundColor;

    if (hasMinutesSummary) {
        const loggedNotApproved = Math.round(Math.max(0, logged - approved) * 100) / 100;
        const scheduledNotLogged = Math.round(Math.max(0, scheduled - logged) * 100) / 100;
        const remaining = Math.round(Math.max(0, tho - scheduled) * 100) / 100;
        labels = ['Approved Hours', 'Logged (not approved)', 'Scheduled (not logged)', 'Remaining'];
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

    new Chart(chartCanvas, {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor, borderWidth: 2, borderColor: '#ffffff' }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 10, font: { size: 12 } } },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${context.label}: ${value.toFixed(2)} hrs (${pct}%)`;
                        },
                    },
                },
            },
        },
    });
});

