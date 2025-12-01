import { initDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initStudentsTable() {
    try {
        await loadDataTablesLibrary();
        await initDataTable('#studentsTable', {
            order: [[0, 'desc']],
            pageLength: 15,
        });
    } catch (error) {
        console.error('Failed to init students table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('studentsTable')) {
        initStudentsTable();
    }
});

