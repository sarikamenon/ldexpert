import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { setupStatusToggles } from '../common/status-change';

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
    setupStatusToggles('school', '.toggle-status-button', { idAttribute: 'school' });
    setupExportButton();
});

