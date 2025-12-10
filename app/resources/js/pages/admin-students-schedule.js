import { initDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initScheduleTable() {
    try {
        await loadDataTablesLibrary();
        // Only initialize if table has data rows (not just empty state)
        const $tableRow = $('#schedulesTable tbody tr');
        if (!$tableRow.length || $tableRow.find('td[colspan]').length) {
            // Table is empty, don't initialize DataTables
            return;
        }
        
        await initDataTable('#schedulesTable', {
            order: [[0, 'desc']],
            paging: false, // Use Laravel pagination instead
            info: false, // Use Laravel pagination info instead
            searching: false, // Use form filters instead
        });
    } catch (error) {
        console.error('Failed to init schedules table', error);
    }
}

$(document).ready(function() {
    initScheduleTable();
});

