import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog } from '../common/sweetalert';

async function initTherapistBillsTable() {
    const table = document.getElementById('therapistBillsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('therapistBillsFiltersForm');

        await initServerSideDataTable('#therapistBillsTable', dataUrl, {
            order: [],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
            getExtraData(d) {
                if (!form) return;
                d.filter_therapist_id = form.querySelector('[name="therapist_id"]')?.value ?? '';
                d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
                d.filter_date_from = form.querySelector('[name="date_from"]')?.value ?? '';
                d.filter_date_to = form.querySelector('[name="date_to"]')?.value ?? '';
                d.filter_bill_number = form.querySelector('[name="bill_number"]')?.value ?? '';
            },
        });

        if (form && typeof window.jQuery !== 'undefined') {
            form.addEventListener('change', () => {
                const dt = window.jQuery('#therapistBillsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            });
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const dt = window.jQuery('#therapistBillsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            });
        }
    } catch (error) {
        console.error('Failed to init therapist bills table', error);
    }
}

function initDeleteConfirmation() {
    const table = document.getElementById('therapistBillsTable');
    if (!table) return;

    table.addEventListener('submit', async (e) => {
        const form = e.target.closest('.js-therapist-bill-delete-form');
        if (!form) return;

        e.preventDefault();
        const result = await confirmDialog({
            title: form.dataset.confirmTitle || 'Delete Bill?',
            text: form.dataset.confirmText || 'This will unlink all sessions and remove the bill. This cannot be undone.',
            icon: 'warning',
            confirmButtonText: 'Yes, delete bill',
            cancelButtonText: 'Cancel',
        });
        if (result.isConfirmed) form.submit();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('therapistBillsTable')) {
        return;
    }

    initTherapistBillsTable();
    initDeleteConfirmation();
});
