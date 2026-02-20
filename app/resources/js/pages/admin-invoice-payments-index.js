import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog } from '../common/sweetalert';

async function initInvoicePaymentsTable() {
    try {
        await loadDataTablesLibrary();

        await initDataTable('#invoicePaymentsTable', {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
        });
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
