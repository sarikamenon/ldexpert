import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { setupStatusToggles } from '../common/status-change';

async function initStudentsTable() {
    const table = document.getElementById('studentsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('studentsFiltersForm');

        await initServerSideDataTable('#studentsTable', dataUrl, {
            order: [[1, 'asc']],
            pageLength: 25,
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
                d.filter_school_id = form.querySelector('[name="school_id"]')?.value ?? '';
            },
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

    // Reload table when filters change so server receives filter_search, filter_status, filter_school_id
    const form = document.getElementById('studentsFiltersForm');
    if (form) {
        form.addEventListener('change', () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#studentsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#studentsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
    }
});


