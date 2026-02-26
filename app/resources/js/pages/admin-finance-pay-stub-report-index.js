import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initPayStubTable() {
    const table = document.getElementById('payStubTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const yearSelect = document.getElementById('year');

        await initServerSideDataTable('#payStubTable', dataUrl, {
            order: [[0, 'asc']],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            columnDefs: [
                { targets: 3, orderable: false, searchable: false },
            ],
            getExtraData(d) {
                d.filter_year = yearSelect ? yearSelect.value : new Date().getFullYear();
            },
        });

        // Use jQuery for change event since Select2 triggers jQuery events
        if (yearSelect && typeof window.jQuery !== 'undefined') {
            window.jQuery(yearSelect).on('change', () => {
                const dt = window.jQuery('#payStubTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            });
        }
    } catch (error) {
        console.error('Failed to init Pay Stub report table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('payStubTable')) {
        return;
    }

    initPayStubTable();
});
