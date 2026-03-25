import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initTable() {
    const table = document.getElementById('therapistQglobRequestsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    await loadDataTablesLibrary();
    const form = document.getElementById('qglobRequestsFiltersForm');

    await initServerSideDataTable('#therapistQglobRequestsTable', dataUrl, {
        order: [[0, 'desc']],
        pageLength: 25,
        columnDefs: [{ orderable: false, targets: -1 }],
        getExtraData(d) {
            if (!form) return;
            d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
            d.filter_date_from = form.querySelector('[name="date_from"]')?.value ?? '';
            d.filter_date_to = form.querySelector('[name="date_to"]')?.value ?? '';
        },
    });

    if (form) {
        const reload = () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#therapistQglobRequestsTable').DataTable();
                if (dt?.ajax?.reload) {
                    dt.ajax.reload();
                }
            }
        };
        form.addEventListener('change', reload);
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            reload();
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    void initTable();
});
