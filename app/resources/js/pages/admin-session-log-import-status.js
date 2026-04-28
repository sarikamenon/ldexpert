document.addEventListener('DOMContentLoaded', () => {
    const statusBadge = document.querySelector('[class*="rounded-full"]');
    const statusText = statusBadge ? statusBadge.textContent.trim().toLowerCase() : '';
    const isProcessing = statusText === 'processing' || statusText === 'pending';

    if (!isProcessing) {
        return;
    }

    // Extract import ID from URL path: /admin/session-logs/imports/{id}
    const pathParts = window.location.pathname.split('/');
    const importId = pathParts[pathParts.length - 1];

    if (!importId) {
        return;
    }

    // Poll for status updates every 3 seconds while processing
    const pollInterval = setInterval(async () => {
        try {
            const response = await fetch(`/admin/session-logs/imports/${importId}/status`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                const data = await response.json();

                // Update progress bar
                if (data.stats) {
                    updateProgress(data.stats);
                }

                // Stop polling if completed or failed
                if (data.status === 'completed' || data.status === 'failed') {
                    clearInterval(pollInterval);
                    // Reload page to show final state
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }
            }
        } catch (error) {
            console.error('Error polling import status:', error);
        }
    }, 3000);

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        clearInterval(pollInterval);
    });
});

function updateProgress(stats) {
    const progressBar = document.querySelector('.bg-primary.h-2\\.5');
    if (progressBar && stats.total > 0) {
        const percentage = (stats.processed / stats.total) * 100;
        progressBar.style.width = `${percentage}%`;

        // Update percentage text
        const percentageText = progressBar.closest('div')?.parentElement?.querySelector('.text-foreground\\/60');
        if (percentageText) {
            percentageText.textContent = `${percentage.toFixed(1)}%`;
        }
    }
}

// Row CSV data modal
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('rowDataModal');
    const closeBtn = document.getElementById('closeRowModal');
    const rowDataBody = document.getElementById('rowDataBody');

    if (!modal || !closeBtn || !rowDataBody) {
        return;
    }

    function openModal(rawData) {
        rowDataBody.innerHTML = '';
        Object.entries(rawData).forEach(([key, value]) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td class="px-4 py-2 font-medium text-foreground/70 whitespace-nowrap">${key}</td>`
                + `<td class="px-4 py-2 text-foreground break-all">${value !== null && value !== undefined ? String(value) : '—'}</td>`;
            rowDataBody.appendChild(tr);
        });
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.view-row-data');
        if (btn) {
            try {
                const raw = JSON.parse(btn.dataset.raw);
                openModal(raw);
            } catch {
                // malformed data — silently ignore
            }
        }
    });

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
});

// Filter functionality
document.addEventListener('DOMContentLoaded', () => {
    const filterButtons = {
        filterAll: document.getElementById('filterAll'),
        filterSuccess: document.getElementById('filterSuccess'),
        filterDuplicates: document.getElementById('filterDuplicates'),
        filterSkipped: document.getElementById('filterSkipped'),
        filterErrors: document.getElementById('filterErrors'),
    };

    if (!filterButtons.filterAll) {
        return;
    }

    const statusMap = {
        filterAll: 'all',
        filterSuccess: 'done',
        filterDuplicates: 'duplicate',
        filterSkipped: 'skipped',
        filterErrors: 'validation_error',
    };

    Object.entries(filterButtons).forEach(([id, button]) => {
        if (!button) return;

        button.addEventListener('click', () => {
            const status = statusMap[id];
            filterRows(status);

            // Update active button
            Object.values(filterButtons).forEach(btn => {
                if (btn) {
                    btn.classList.remove('bg-primary', 'text-white');
                    btn.classList.add('border', 'hover:bg-foreground/5');
                }
            });
            button.classList.add('bg-primary', 'text-white');
            button.classList.remove('border', 'hover:bg-foreground/5');
        });
    });
});

function filterRows(status) {
    const rows = document.querySelectorAll('.row-item');
    rows.forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
