import { initDataTable, initServerSideDataTable, loadDataTablesLibrary } from '../../common/datatables';
import { confirmDialog } from '../../common/sweetalert';

const bindConfirmations = () => {
    document.querySelectorAll('button[data-confirm-title]').forEach((button) => {
        const form = button.closest('form');
        if (!form || button.dataset.confirmBound === 'true') {
            return;
        }

        button.dataset.confirmBound = 'true';

        button.addEventListener('click', async (event) => {
            event.preventDefault();

            const result = await confirmDialog({
                title: button.dataset.confirmTitle || 'Are you sure?',
                text: button.dataset.confirmText || '',
                icon: button.dataset.confirmIcon || 'question',
                confirmButtonText: 'Yes',
            });

            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
};

document.addEventListener('DOMContentLoaded', async () => {
    await loadDataTablesLibrary();

    const table = document.querySelector('.session-log-table[data-datatable-url]') || document.getElementById('adminSessionLogsTable') || document.getElementById('therapistSessionLogsTable');
    const dataUrl = table?.getAttribute('data-datatable-url');
    const formId = table?.getAttribute('data-filter-form') || 'adminSessionLogsFiltersForm';
    const form = formId ? document.getElementById(formId) : null;

    if (table && dataUrl) {
        const isTherapistForm = formId === 'sessionLogsFiltersForm';

        await initServerSideDataTable(table.id ? `#${table.id}` : '.session-log-table', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 15,
            columnDefs: [{ orderable: false, targets: -1 }],
            getExtraData(d) {
                if (!form) return;
                if (isTherapistForm) {
                    d.filter_student_id = form.querySelector('[name="student_id"]')?.value ?? '';
                    d.filter_ssa_id = form.querySelector('[name="ssa_id"]')?.value ?? '';
                    d.filter_service_id = form.querySelector('[name="service_id"]')?.value ?? '';
                    d.filter_date_from = form.querySelector('[name="date_from"]')?.value ?? '';
                    d.filter_date_to = form.querySelector('[name="date_to"]')?.value ?? '';
                } else {
                    d.filter_school_id = form.querySelector('[name="school_id"]')?.value ?? '';
                    d.filter_therapist_id = form.querySelector('[name="therapist_id"]')?.value ?? '';
                    d.filter_ssa_id = form.querySelector('[name="ssa_id"]')?.value ?? '';
                    d.filter_service_id = form.querySelector('[name="service_id"]')?.value ?? '';
                    d.filter_date_from = form.querySelector('[name="date_from"]')?.value ?? '';
                    d.filter_date_to = form.querySelector('[name="date_to"]')?.value ?? '';
                }
            },
        });

        if (form && table.id) {
            form.addEventListener('change', () => {
                if (typeof window.jQuery !== 'undefined') {
                    const dt = window.jQuery(`#${table.id}`).DataTable();
                    if (dt?.ajax?.reload) dt.ajax.reload();
                }
            });
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (typeof window.jQuery !== 'undefined') {
                    const dt = window.jQuery(`#${table.id}`).DataTable();
                    if (dt?.ajax?.reload) dt.ajax.reload();
                }
            });
        }
    } else {
        initDataTable('.session-log-table', {
            order: [[0, 'desc']],
            paging: false,
            info: false,
            dom: 'lfrt',
        });
    }

    bindConfirmations();

    document.body.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-confirm-title]');
        if (!button) return;
        const form = button.closest('form');
        if (!form) return;

        event.preventDefault();

        const result = await confirmDialog({
            title: button.dataset.confirmTitle || 'Are you sure?',
            text: button.dataset.confirmText || '',
            icon: button.dataset.confirmIcon || 'question',
            confirmButtonText: 'Yes',
        });

        if (result.isConfirmed) {
            form.submit();
        }
    });
});
