import { initDataTable, initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

window.jQuery(function ($) {
    const table = document.getElementById('ledgerTransactionsTable') || document.querySelector('.ledger-transactions-table');
    if (!table) {
        return;
    }

    void (async function init() {
        await loadDataTablesLibrary();
        const dataUrl = table.getAttribute('data-datatable-url');
        if (dataUrl) {
            const selector = table.id ? `#${table.id}` : '.ledger-transactions-table';
            await initServerSideDataTable(selector, dataUrl, {
                order: [[0, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: [2, 3, 4, 5, 7] },
                ],
                getExtraData(d) {
                    d.filter_type = table.getAttribute('data-filter-type') || '';
                    d.filter_id = table.getAttribute('data-filter-id') || '';
                },
            });
        } else {
            await initDataTable('.ledger-transactions-table', {
                order: [[0, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: -1 },
                ],
            });
        }
    })();
});
