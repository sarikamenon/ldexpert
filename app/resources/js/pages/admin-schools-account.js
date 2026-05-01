import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { openScheduleDetailsModal } from '../common/schedule-modal';

document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('schoolAccountTable');
    if (!table) {
        return;
    }

    void initAccountTable(table);
    bindScheduleModal(table);
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
        language: {
            emptyTable: 'No account activity yet.',
            zeroRecords: 'No matching records found.',
        },
    });
}

function bindScheduleModal(table) {
    const detailsUrl = table.getAttribute('data-schedule-details-url');
    if (!detailsUrl) {
        return;
    }

    table.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-schedule-id]');
        if (!button) {
            return;
        }

        const scheduleId = button.getAttribute('data-schedule-id');
        if (!scheduleId) {
            return;
        }

        event.preventDefault();
        openScheduleDetailsModal(scheduleId, detailsUrl, {});
    });
}
