import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert } from '../common/sweetalert';

async function initTherapistsTable() {
    try {
        await loadDataTablesLibrary();

        await initDataTable('#therapistsTable', {
            order: [[1, 'asc']],
            pageLength: 25,
        });
    } catch (error) {
        console.error('Failed to init therapists table', error);
    }
}

function setupStatusToggles() {
    const buttons = document.querySelectorAll('.toggle-status-button');

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            const therapistId = button.dataset.therapist;
            const currentStatus = button.dataset.status;
            const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = nextStatus === 'active' ? 'activate' : 'deactivate';
            
            const result = await confirmDialog({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Therapist?`,
                text: `You are about to ${action} this therapist.`,
                icon: 'warning',
                confirmButtonText: `Yes, ${action}`,
                showInput: true,
                inputPlaceholder: `Provide a reason to ${action}...`,
            });

            if (!result.isConfirmed || !result.value) {
                return;
            }

            try {
                const response = await fetch(`/admin/therapists/${therapistId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        status: nextStatus,
                        reason: result.value,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    await successToast(data.message, 'Success!');
                    window.location.reload();
                } else {
                    errorAlert('Failed to update therapist status');
                }
            } catch (error) {
                console.error('Failed to update status', error);
                errorAlert('An error occurred while updating therapist status');
            }
        });
    });
}

function setupExportButton() {
    const button = document.getElementById('exportTherapistsButton');
    const form = document.getElementById('therapistsFiltersForm');
    if (!button || !form) {
        return;
    }

    button.addEventListener('click', (event) => {
        event.preventDefault();
        const url = new URL(button.href, window.location.origin);
        new FormData(form).forEach((value, key) => {
            url.searchParams.set(key, value.toString());
        });
        window.location.href = url.toString();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('therapistsTable')) {
        return;
    }

    initTherapistsTable();
    setupStatusToggles();
    setupExportButton();
});

