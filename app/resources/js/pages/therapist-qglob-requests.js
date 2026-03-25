import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog } from '../common/sweetalert';

function bindDelegatedConfirmations() {
    document.body.addEventListener('click', async (event) => {
        const button = event.target.closest('#therapistQglobRequestsTable button[data-confirm-title]');
        if (!button) return;

        const form = button.closest('form');
        if (!form) return;

        event.preventDefault();

        const result = await confirmDialog({
            title: button.dataset.confirmTitle || 'Are you sure?',
            text: button.dataset.confirmText || '',
            icon: button.dataset.confirmIcon || 'warning',
            confirmButtonText: 'Yes, delete',
        });

        if (result.isConfirmed) {
            form.submit();
        }
    });
}

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
    bindDelegatedConfirmations();
    void initTable();
});
