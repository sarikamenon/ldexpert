import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { setupStatusToggles } from '../common/status-change';

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
    setupStatusToggles('student', '.toggle-student-status', { idAttribute: 'student' });
    setupExportButton();
});


