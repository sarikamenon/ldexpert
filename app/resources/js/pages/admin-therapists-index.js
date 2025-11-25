import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { setupStatusToggles } from '../common/status-change';

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
    setupStatusToggles('therapist', '.toggle-status-button', { idAttribute: 'therapist' });
    setupExportButton();
});

