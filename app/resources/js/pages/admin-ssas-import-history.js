import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initImportsTable() {
    const table = document.getElementById('importsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        await initServerSideDataTable('#importsTable', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
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
