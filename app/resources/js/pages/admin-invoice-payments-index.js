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

        if (form && typeof window.jQuery !== 'undefined') {
            form.addEventListener('change', () => {
                const dataTable = window.jQuery('#invoicePaymentsTable').DataTable();
                if (dataTable && dataTable.ajax && dataTable.ajax.reload) {
                    dataTable.ajax.reload();
                }
            });
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const dataTable = window.jQuery('#invoicePaymentsTable').DataTable();
                if (dataTable && dataTable.ajax && dataTable.ajax.reload) {
                    dataTable.ajax.reload();
                }
            });
        }
    } catch (error) {
        console.error('Failed to init invoice payments table', error);
    }
}

window.jQuery(function ($) {
    const $table = $('#invoicePaymentsTable');

    if (!$table.length) {
        return;
    }

    void initInvoicePaymentsTable();

    $table.on('submit', '.js-invoice-payment-delete-form', async function (event) {
        event.preventDefault();

        const form = this;
        const $form = $(form);

        const title = $form.data('confirm-title') || 'Delete payment?';
        const text = $form.data('confirm-text') || 'This will delete the payment. This action cannot be undone.';

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
