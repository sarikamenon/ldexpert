import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initLedgerAccountsTable() {
    const table = document.getElementById('ledgerAccountsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        await initServerSideDataTable('#ledgerAccountsTable', dataUrl, {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
            getExtraData(d) {
                d.filter_type = table.getAttribute('data-filter-type') || 'schools';
                d.filter_search = '';
            },
        });
    } catch (error) {
        console.error('Failed to init ledger accounts table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('ledgerAccountsTable')) {
        return;
    }

    initLedgerAccountsTable();
});

