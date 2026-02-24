import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';

async function initPositionsTable() {
    const table = document.getElementById('positionsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    const form = document.getElementById('positionFiltersForm');

    try {
        await loadDataTablesLibrary();
        await initServerSideDataTable('#positionsTable', dataUrl, {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: -1 }],
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
            },
        });
    } catch (error) {
        console.error('Failed to init positions table', error);
    }
}

function setupStatusToggles() {
    const buttons = document.querySelectorAll('.toggle-position-status');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            const positionId = button.dataset.position;
            const currentStatus = button.dataset.status;
            const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = nextStatus === 'active' ? 'activate' : 'deactivate';

            const result = await confirmDialog({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Position?`,
                text: `You are about to ${action} this position.`,
                icon: 'warning',
                confirmButtonText: `Yes, ${action}`,
            });

            if (!result.isConfirmed) {
                return;
            }

            try {
                showLoading('Updating position status...');
                const response = await fetch(`/admin/positions/${positionId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ status: nextStatus }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    await successToast(data.message);
                    if (typeof window.jQuery !== 'undefined') {
                        const dt = window.jQuery('#positionsTable').DataTable();
                        if (dt && dt.ajax && dt.ajax.reload) {
                            dt.ajax.reload();
                        } else {
                            window.location.reload();
                        }
                    } else {
                        window.location.reload();
                    }
                } else {
                    errorAlert(data.message || 'Failed to update position status.');
                }
            } catch (error) {
                console.error('Failed to update position status', error);
                errorAlert('An unexpected error occurred.');
            } finally {
                closeAlert();
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('positionsTable')) {
        return;
    }

    initPositionsTable();
    setupStatusToggles();

    const form = document.getElementById('positionFiltersForm');
    if (form) {
        form.addEventListener('change', () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#positionsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#positionsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
    }
});
