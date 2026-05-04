document.addEventListener('DOMContentLoaded', () => {
    const importId = window.location.pathname.split('/').pop();
    const isProcessing = document.querySelector('[data-status="processing"], [data-status="pending"]');

    if (!isProcessing || !importId) {
        return;
    }

    // Poll for status updates every 3 seconds while processing
    const pollInterval = setInterval(async () => {
        try {
            const response = await fetch(`/admin/students/imports/${importId}/status`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                const data = await response.json();
                
                // Update progress
                if (data.stats) {
                    updateProgress(data.stats);
                }

                // Update rows if provided
                if (data.rows) {
                    updateRows(data.rows);
                }

                // Stop polling if completed or failed
                if (data.status === 'completed' || data.status === 'failed') {
                    clearInterval(pollInterval);
                    if (data.status === 'completed') {
                        // Reload page to show final state
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }
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
    }

    // Update stat numbers if elements exist
    const elements = {
        processed: document.querySelector('.text-blue-700.text-2xl'),
        success: document.querySelector('.text-green-700.text-2xl'),
        duplicates: document.querySelector('.text-yellow-700.text-2xl'),
        errors: document.querySelector('.text-red-700.text-2xl'),
    };

    // Note: These selectors may need adjustment based on actual DOM structure
}

function updateRows(rows) {
    // Update individual row statuses if needed
    // This would require more complex DOM manipulation
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
        filterErrors: document.getElementById('filterErrors'),
    };

    if (!filterButtons.filterAll) {
        return;
    }

    Object.entries(filterButtons).forEach(([id, button]) => {
        if (!button) return;

        button.addEventListener('click', () => {
            const status = id.replace('filter', '').toLowerCase();
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
