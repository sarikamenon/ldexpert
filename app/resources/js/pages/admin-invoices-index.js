import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initInvoicesTable() {
    const table = document.getElementById('invoicesTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('invoiceFiltersForm');

        await initServerSideDataTable('#invoicesTable', dataUrl, {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
            getExtraData(d) {
                if (!form) return;
                d.filter_school_id = form.querySelector('[name="school_id"]')?.value ?? '';
                d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
                d.filter_date_from = form.querySelector('[name="date_from"]')?.value ?? '';
                d.filter_date_to = form.querySelector('[name="date_to"]')?.value ?? '';
                d.filter_invoice_number = form.querySelector('[name="invoice_number"]')?.value ?? '';
            },
        });

        if (form && typeof window.jQuery !== 'undefined') {
            form.addEventListener('change', () => {
                const dt = window.jQuery('#invoicesTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            });

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const dt = window.jQuery('#invoicesTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            });
        }
    } catch (error) {
        console.error('Failed to init invoices table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('invoicesTable')) {
        return;
    }

    initInvoicesTable();
});

