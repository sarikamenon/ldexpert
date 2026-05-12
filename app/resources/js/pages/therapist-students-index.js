import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

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
                const statusVal = form.querySelector('[name="status"]')?.value ?? 'active';
                d.filter_status = statusVal === 'all' ? '' : statusVal;
            },
        });
    } catch (error) {
        console.error('Failed to init students table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('studentsTable')) {
        return;
    }

    initStudentsTable();

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

