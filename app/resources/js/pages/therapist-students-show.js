// Chart.js is loaded from CDN in the blade template
// It will be available as a global Chart object

document.addEventListener('DOMContentLoaded', () => {
    // Wait for Chart.js to be available from CDN
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js is not loaded. Make sure the CDN script is included in the page.');
        return;
    }

    const chartCanvas = document.getElementById('studentProgressChart');
    if (chartCanvas) {
        const served = parseFloat(chartCanvas.dataset.served) || 0;
        const tho = parseFloat(chartCanvas.dataset.tho) || 0;
        const remaining = Math.round(Math.max(0, tho - served) * 100) / 100;

        new Chart(chartCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Served Hours', 'Remaining Hours'],
                datasets: [
                    {
                        data: [served, remaining],
                        backgroundColor: ['#3b82f6', '#e5e7eb'],
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                },
            },
        });
    }
});

