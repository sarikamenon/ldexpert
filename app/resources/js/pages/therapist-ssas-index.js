import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initSSATable() {
    const table = document.getElementById('ssasTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('ssaFiltersForm');

        await initServerSideDataTable('#ssasTable', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: -1 }],
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
                d.filter_student_id = form.querySelector('[name="student_id"]')?.value ?? '';
            },
        });
    } catch (error) {
        console.error('Failed to init SSAs table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('ssasTable')) {
        initSSATable();
    }

    const form = document.getElementById('ssaFiltersForm');
    if (form) {
        form.addEventListener('change', () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#ssasTable').DataTable();
                if (dt?.ajax?.reload) dt.ajax.reload();
            }
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#ssasTable').DataTable();
                if (dt?.ajax?.reload) dt.ajax.reload();
            }
        });
    }
});
