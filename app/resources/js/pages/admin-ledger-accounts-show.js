import { initDataTable, loadDataTablesLibrary } from '../common/datatables';

window.jQuery(function ($) {
    const $table = $('.ledger-transactions-table');
    if (!$table.length) {
        return;
    }

    void (async function init() {
        await loadDataTablesLibrary();
        await initDataTable('.ledger-transactions-table', {
            order: [[0, 'desc']],
            pageLength: 10,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
        });
    })();
});
