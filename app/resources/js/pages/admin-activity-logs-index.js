import { initDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initActivityLogsTable() {
    try {
        await loadDataTablesLibrary();

        const tableElement = document.getElementById('activityLogsTable');
        const statusRegion = document.getElementById('activityLogsStatus');

        await initDataTable('#activityLogsTable', {
            paging: false,
            searching: false,
            info: false,
            order: [[0, 'desc']],
            columnDefs: [{ orderable: false, targets: [4] }],
            drawCallback: function (settings) {
                if (!statusRegion || !tableElement) return;
                const body = tableElement.querySelector('tbody');
                const rows = body ? body.querySelectorAll('tr') : [];
                statusRegion.textContent = `Showing ${rows.length} activity log${rows.length === 1 ? '' : 's'}.`;
            },
        });
    } catch (error) {
        console.error('Failed to init activity logs table:', error);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('activityLogsTable');

    if (table) {
        initActivityLogsTable();
    }
});

