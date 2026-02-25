import { initDataTable, initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initScheduleTable() {
    const table = document.getElementById('schedulesTable');
    if (!table) return;

    try {
        await loadDataTablesLibrary();
        const dataUrl = table.getAttribute('data-datatable-url');
        if (dataUrl) {
            const form = document.getElementById(table.getAttribute('data-filter-form') || 'scheduleFiltersForm');
            await initServerSideDataTable('#schedulesTable', dataUrl, {
                order: [[0, 'desc']],
                pageLength: 15,
                getExtraData(d) {
                    d.filter_student_id = table.getAttribute('data-student-id') || '';
                    if (!form) return;
                    d.filter_date = form.querySelector('[name="date"]')?.value ?? '';
                    d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
                    d.filter_billing_status = form.querySelector('[name="billing_status"]')?.value ?? '';
                    d.filter_ssa_id = form.querySelector('[name="ssa_id"]')?.value ?? '';
                    d.filter_therapist_id = form.querySelector('[name="therapist_id"]')?.value ?? '';
                },
            });
            if (form) {
                form.addEventListener('change', () => {
                    if (typeof window.jQuery !== 'undefined') {
                        const dt = window.jQuery('#schedulesTable').DataTable();
                        if (dt && dt.ajax && dt.ajax.reload) dt.ajax.reload();
                    }
                });
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    if (typeof window.jQuery !== 'undefined') {
                        const dt = window.jQuery('#schedulesTable').DataTable();
                        if (dt && dt.ajax && dt.ajax.reload) dt.ajax.reload();
                    }
                });
            }
            return;
        }
        const $tableRow = $('#schedulesTable tbody tr');
        if (!$tableRow.length || $tableRow.find('td[colspan]').length) {
            return;
        }
        await initDataTable('#schedulesTable', {
            order: [[0, 'desc']],
            paging: false,
            info: false,
            searching: false,
        });
    } catch (error) {
        console.error('Failed to init schedules table', error);
    }
}

$(document).ready(function () {
    initScheduleTable();
});

