import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { setupStatusToggles } from '../common/status-change';

async function initTherapistsTable() {
    const table = document.getElementById('therapistsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('therapistsFiltersForm');

        await initServerSideDataTable('#therapistsTable', dataUrl, {
            order: [[1, 'asc']],
            pageLength: 25,
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                const statusVal = form.querySelector('[name="status"]')?.value ?? 'active';
                d.filter_status = statusVal === 'all' ? '' : statusVal;
                d.filter_position_id = form.querySelector('[name="position_id"]')?.value ?? '';
                d.filter_school_id = form.querySelector('[name="school_id"]')?.value ?? '';
                d.filter_student_id = form.querySelector('[name="student_id"]')?.value ?? '';
            },
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
    if (!document.getElementById('therapistsTable')) {
        return;
    }

    initTherapistsTable();
    setupStatusToggles('therapist', '.toggle-status-button', { idAttribute: 'therapist' });
    setupExportButton();

    const form = document.getElementById('therapistsFiltersForm');
    if (form) {
        form.addEventListener('change', () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#therapistsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#therapistsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
    }
});
