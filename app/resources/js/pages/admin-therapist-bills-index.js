import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initTherapistBillsTable() {
    const table = document.getElementById('therapistBillsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('therapistBillsFiltersForm');

        await initServerSideDataTable('#therapistBillsTable', dataUrl, {
            order: [[0, 'asc']],
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

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('therapistBillsTable')) {
        return;
    }

    initTherapistBillsTable();
});
