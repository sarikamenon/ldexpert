import { initDataTable, initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initIrsReportTable() {
    const table = document.getElementById('irsReportTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (dataUrl) {
        try {
            await loadDataTablesLibrary();
            const form = document.getElementById('irsReportFiltersForm');
            await initServerSideDataTable('#irsReportTable', dataUrl, {
                order: [[1, 'desc']],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                getExtraData(d) {
                    if (!form) return;
                    d.filter_date_from = form.querySelector('[name="date_from"]')?.value ?? '';
                    d.filter_date_to = form.querySelector('[name="date_to"]')?.value ?? '';
                    const therapistSelect = form.elements['therapist_ids[]'];
                    const opts = therapistSelect?.selectedOptions;
                    d.filter_therapist_ids = opts ? Array.from(opts).map((o) => o.value) : [];
                },
            });
            if (form) {
                form.addEventListener('change', () => {
                    if (typeof window.jQuery !== 'undefined') {
                        const dt = window.jQuery('#irsReportTable').DataTable();
                        if (dt && dt.ajax && dt.ajax.reload) dt.ajax.reload();
                    }
                });
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    if (typeof window.jQuery !== 'undefined') {
                        const dt = window.jQuery('#irsReportTable').DataTable();
                        if (dt && dt.ajax && dt.ajax.reload) dt.ajax.reload();
                    }
                });
            }
        } catch (error) {
            console.error('Failed to init IRS report table', error);
        }
    } else {
        try {
            await loadDataTablesLibrary();
            await initDataTable('#irsReportTable', {
                order: [[1, 'desc']],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
            });
        } catch (error) {
            console.error('Failed to init IRS report table', error);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('irsReportTable')) {
        return;
    }

    initIrsReportTable();
});
