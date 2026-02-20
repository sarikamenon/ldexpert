import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';

async function initPositionsTable() {
    try {
        await loadDataTablesLibrary();
        await initDataTable('#positionsTable', {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: -1 }],
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
                    window.location.reload();
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
});
