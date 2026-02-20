import { initDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initLedgerAccountsTable() {
    try {
        await loadDataTablesLibrary();

        await initDataTable('#ledgerAccountsTable', {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
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

