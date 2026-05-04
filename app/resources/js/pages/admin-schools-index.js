import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { setupStatusToggles } from '../common/status-change';

async function initSchoolsTable() {
    const table = document.getElementById('schoolsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    const form = document.getElementById('schoolFiltersForm');

    try {
        await loadDataTablesLibrary();

        await initServerSideDataTable('#schoolsTable', dataUrl, {
            order: [[1, 'asc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                const statusVal = form.querySelector('[name="status"]')?.value ?? 'active';
                d.filter_status = statusVal === 'all' ? '' : statusVal;
            },
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
    if (!document.getElementById('schoolsTable')) {
        return;
    }

    initSchoolsTable();
    setupStatusToggles('school', '.toggle-status-button', { idAttribute: 'school' });
    setupExportButton();

    const form = document.getElementById('schoolFiltersForm');
    if (form) {
        form.addEventListener('change', () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#schoolsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#schoolsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
    }
});

