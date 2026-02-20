import { initDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initInvoicesTable() {
    try {
        await loadDataTablesLibrary();

        await initDataTable('#invoicesTable', {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
        });
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

