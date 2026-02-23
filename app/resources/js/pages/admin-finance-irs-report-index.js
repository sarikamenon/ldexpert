import { initDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initIrsReportTable() {
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

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('irsReportTable')) {
        return;
    }

    initIrsReportTable();
});
