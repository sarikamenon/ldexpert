import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { setupStatusChanges } from '../common/status-change';
import { setupAssignModal, setupUnassignModal } from '../common/ssa-modals';

async function initSSATable() {
    const table = document.getElementById('ssasTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    const form = document.getElementById('ssaFiltersForm');

    try {
        await loadDataTablesLibrary();
        await initServerSideDataTable('#ssasTable', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: -1 }],
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
                d.filter_student_id = form.querySelector('[name="student_id"]')?.value ?? '';
                d.filter_therapist_id = form.querySelector('[name="therapist_id"]')?.value ?? '';
                d.filter_service_id = form.querySelector('[name="service_id"]')?.value ?? '';
                d.filter_school_id = form.querySelector('[name="school_id"]')?.value ?? '';
            },
        });
    } catch (error) {
        console.error('Failed to init SSAs table', error);
    }
}

function reloadDataTable() {
    if (typeof window.jQuery !== 'undefined') {
        const dt = window.jQuery('#ssasTable').DataTable();
        if (dt?.ajax?.reload) {
            dt.ajax.reload(null, false);
            return;
        }
    }
    window.location.reload();
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('ssasTable')) {
        initSSATable();
    }

    const form = document.getElementById('ssaFiltersForm');
    if (form) {
        form.addEventListener('change', reloadDataTable);
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            reloadDataTable();
        });
    }

    setupStatusChanges('ssa', '.change-status-btn', { idAttribute: 'ssa-id' });
    setupAssignModal({ onSuccess: reloadDataTable });
    setupUnassignModal({ onSuccess: reloadDataTable });
});
