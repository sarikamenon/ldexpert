import { initDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initSSATable() {
    try {
        await loadDataTablesLibrary();
        await initDataTable('#ssasTable', {
            order: [[0, 'desc']],
            pageLength: 25,
        });
    } catch (error) {
        console.error('Failed to init SSAs table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('ssasTable')) {
        initSSATable();
    }
});

