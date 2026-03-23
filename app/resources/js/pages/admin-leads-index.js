import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initLeadsTable() {
    const table = document.getElementById('leadsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('leadsFiltersForm');

        await initServerSideDataTable('#leadsTable', dataUrl, {
            order: [[7, 'desc']],
            pageLength: 25,
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
                d.filter_source = form.querySelector('[name="source"]')?.value ?? '';
                d.filter_school_id = form.querySelector('[name="school_id"]')?.value ?? '';
            },
        });
    } catch (error) {
        console.error('Failed to init leads table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('leadsTable')) {
        return;
    }

    initLeadsTable();

    const form = document.getElementById('leadsFiltersForm');
    if (form) {
        form.addEventListener('change', () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#leadsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#leadsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
    }
});
