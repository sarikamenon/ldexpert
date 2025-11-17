import { initDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initActivityLogsTable() {
    try {
        await loadDataTablesLibrary();

        await initDataTable('#activityLogsTable', {
            paging: false,
            searching: false,
            info: false,
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: [4] }
            ]
        });
    } catch (error) {
        console.error('Failed to init activity logs table:', error);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('activityLogsTable');
    
    if (table) {
        initActivityLogsTable();
    }
});

