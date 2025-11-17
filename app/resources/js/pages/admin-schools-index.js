import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog } from '../common/sweetalert';

async function initSchoolsTable() {
    try {
        await loadDataTablesLibrary();

        await initDataTable('#schoolsTable', {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 }
            ]
        });
    } catch (error) {
        console.error('Failed to init schools table', error);
    }
}

function setupStatusToggles() {
    const buttons = document.querySelectorAll('.toggle-status-button');
    const statusForm = document.getElementById('schoolStatusForm');
    const statusInput = document.getElementById('statusInput');
    const statusReasonInput = document.getElementById('statusReasonInput');

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            const schoolId = button.dataset.school;
            const currentStatus = button.dataset.status;
            const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = nextStatus === 'active' ? 'activate' : 'deactivate';

            const result = await confirmDialog({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} School?`,
                text: `You are about to ${action} this school.`,
                icon: 'warning',
                confirmButtonText: `Yes, ${action}`,
                showInput: true,
                inputPlaceholder: `Provide a reason to ${action}...`,
            });

            if (!result.isConfirmed || !result.value) {
                return;
            }

            statusInput.value = nextStatus;
            statusReasonInput.value = result.value;

            statusForm.action = `/admin/schools/${schoolId}/status`;
            statusForm.submit();
        });
    });
}

function setupExportButton() {
    const button = document.getElementById('exportSchoolsButton');
    const form = document.getElementById('schoolFiltersForm');
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
    if (!document.getElementById('schoolsTable')) {
        return;
    }

    initSchoolsTable();
    setupStatusToggles();
    setupExportButton();
});

