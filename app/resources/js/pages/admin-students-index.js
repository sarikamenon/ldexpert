import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert } from '../common/sweetalert';

async function initStudentsTable() {
    try {
        await loadDataTablesLibrary();

        await initDataTable('#studentsTable', {
            order: [[1, 'asc']],
            pageLength: 25,
        });
    } catch (error) {
        console.error('Failed to init students table', error);
    }
}

function setupStatusToggles() {
    document.querySelectorAll('.toggle-student-status').forEach((button) => {
        button.addEventListener('click', async () => {
            const studentId = button.dataset.student;
            const currentStatus = button.dataset.status;
            const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = nextStatus === 'active' ? 'activate' : 'deactivate';

            const result = await confirmDialog({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Student?`,
                text: `You are about to ${action} this student.`,
                icon: 'warning',
                confirmButtonText: `Yes, ${action}`,
                showInput: true,
                inputPlaceholder: `Provide a reason to ${action}...`,
            });

            if (!result.isConfirmed || !result.value) {
                return;
            }

            try {
                const response = await fetch(`/admin/students/${studentId}/status`, {
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
                    errorAlert('Failed to update student status');
                }
            } catch (error) {
                console.error('Failed to update student status', error);
                errorAlert('An error occurred while updating student status');
            }
        });
    });
}

function setupExportButton() {
    const button = document.getElementById('exportStudentsButton');
    const form = document.getElementById('studentsFiltersForm');
    if (!button || !form) {
        return;
    }

    button.addEventListener('click', (event) => {
        event.preventDefault();
        const url = new URL(button.href, window.location.origin);
        new FormData(form).forEach((value, key) => {
            if (value) {
                url.searchParams.set(key, value.toString());
            } else {
                url.searchParams.delete(key);
            }
        });
        window.location.href = url.toString();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('studentsTable')) {
        return;
    }

    initStudentsTable();
    setupStatusToggles();
    setupExportButton();
});


