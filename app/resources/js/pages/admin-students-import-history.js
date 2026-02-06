import { initDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initImportsTable() {
    try {
        await loadDataTablesLibrary();

        await initDataTable('#importsTable', {
            order: [[0, 'desc']], // Order by ID descending
            pageLength: 25,
        });
    } catch (error) {
        console.error('Failed to init imports table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('importsTable')) {
        initImportsTable();
    }
});
