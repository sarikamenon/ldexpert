import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog } from '../common/sweetalert';

async function initInvoicePaymentsTable() {
    const table = document.getElementById('invoicePaymentsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('invoicePaymentsFiltersForm');

        const dt = await initServerSideDataTable('#invoicePaymentsTable', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
            getExtraData(d) {
                if (!form) return;
                d.filter_from_date = form.querySelector('[name="from_date"]')?.value ?? '';
                d.filter_to_date = form.querySelector('[name="to_date"]')?.value ?? '';
                d.filter_method = form.querySelector('[name="method"]')?.value ?? '';
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
            },
        });

        if (dt && dt.on) {
            dt.on('xhr.dt', function (e, settings, json) {
                const totalEl = document.getElementById('invoicePaymentsTotalAmount');
                const countEl = document.getElementById('invoicePaymentsCount');
                if (totalEl) {
                    totalEl.textContent = '$' + Number(json.totalAmount != null ? json.totalAmount : 0).toFixed(2);
                }
                if (countEl) {
                    countEl.textContent = json.recordsFiltered != null ? json.recordsFiltered : (json.recordsTotal || 0);
                }
            });
        }

        const reloadTable = () => {
            if (typeof window.jQuery === 'undefined') return;
            const dataTable = window.jQuery('#invoicePaymentsTable').DataTable();
            if (dataTable && dataTable.ajax && dataTable.ajax.reload) {
                dataTable.ajax.reload();
            }
        };

        if (form) {
            form.addEventListener('change', reloadTable);
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                reloadTable();
            });
        }
    } catch (error) {
        console.error('Failed to init invoice payments table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('invoicePaymentsTable');
    if (!table) return;

    void initInvoicePaymentsTable();

    table.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-confirm-title], form.js-invoice-payment-delete-form');
        if (!form || !table.contains(form)) return;

        event.preventDefault();

        const title = form.getAttribute('data-confirm-title') || 'Delete payment?';
        const text = form.getAttribute('data-confirm-text') || 'This will delete the payment. This action cannot be undone.';

        const result = await confirmDialog({
            title,
            text,
            icon: 'warning',
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel',
        });

        if (result.isConfirmed) {
            form.submit();
        }
    });
});
