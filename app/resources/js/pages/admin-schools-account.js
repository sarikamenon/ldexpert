import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('schoolAccountTable');
    if (!table) {
        return;
    }

    void initAccountTable(table);
});

async function initAccountTable(table) {
    await loadDataTablesLibrary();

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) {
        return;
    }

    await initServerSideDataTable('#schoolAccountTable', dataUrl, {
        order: [],
        ordering: false,
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: '_all' },
        ],
    });
}
