import { initDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initTherapistBillsTable() {
    try {
        await loadDataTablesLibrary();

        await initDataTable('#therapistBillsTable', {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
        });
    } catch (error) {
        console.error('Failed to init therapist bills table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('therapistBillsTable')) {
        return;
    }

    initTherapistBillsTable();
});

