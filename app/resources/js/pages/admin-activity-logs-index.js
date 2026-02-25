import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initActivityLogsTable() {
    const table = document.getElementById('activityLogsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('activityLogFiltersForm');

        await initServerSideDataTable('#activityLogsTable', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                d.filter_user_id = form.querySelector('[name="user_id"]')?.value ?? '';
                d.filter_action = form.querySelector('[name="action"]')?.value ?? '';
                d.filter_model_type = form.querySelector('[name="model_type"]')?.value ?? '';
                d.filter_date_from = form.querySelector('[name="date_from"]')?.value ?? '';
                d.filter_date_to = form.querySelector('[name="date_to"]')?.value ?? '';
            },
        });
    } catch (error) {
        console.error('Failed to init activity logs table:', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('activityLogsTable');
    if (table) {
        initActivityLogsTable();
    }

    const form = document.getElementById('activityLogFiltersForm');
    if (form) {
        form.addEventListener('change', () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#activityLogsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#activityLogsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
    }
});
