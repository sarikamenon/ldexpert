import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';

async function initServicesTable() {
    try {
        await loadDataTablesLibrary();
        await initDataTable('#servicesTable', {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: -1 }],
        });
    } catch (error) {
        console.error('Failed to init services table', error);
    }
}

function setupStatusToggles() {
    const buttons = document.querySelectorAll('.toggle-service-status');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            const serviceId = button.dataset.service;
            const currentStatus = button.dataset.status;
            const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = nextStatus === 'active' ? 'activate' : 'deactivate';

            const result = await confirmDialog({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Service?`,
                text: `You are about to ${action} this service.`,
                icon: 'warning',
                confirmButtonText: `Yes, ${action}`,
            });

            if (!result.isConfirmed) {
                return;
            }

            try {
                showLoading('Updating service status...');
                const response = await fetch(`/admin/services/${serviceId}/status`, {
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
                    errorAlert(data.message || 'Failed to update service status.');
                }
            } catch (error) {
                console.error('Failed to update service status', error);
                errorAlert('An unexpected error occurred.');
            } finally {
                closeAlert();
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('servicesTable')) {
        return;
    }

    initServicesTable();
    setupStatusToggles();
});


