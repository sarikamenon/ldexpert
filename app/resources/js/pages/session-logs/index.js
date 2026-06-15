import { initDataTable, initServerSideDataTable, loadDataTablesLibrary } from '../../common/datatables';
import { initSessionLogNotes } from '../../common/session-log-notes';
import { confirmDialog, successToast, errorAlert } from '../../common/sweetalert';

document.addEventListener('DOMContentLoaded', async () => {
    await loadDataTablesLibrary();

    const table = document.querySelector('.session-log-table[data-datatable-url]') || document.getElementById('adminSessionLogsTable') || document.getElementById('therapistSessionLogsTable');
    const dataUrl = table?.getAttribute('data-datatable-url');
    const formId = table?.getAttribute('data-filter-form') || 'adminSessionLogsFiltersForm';
    const form = formId ? document.getElementById(formId) : null;

    if (table && dataUrl) {
        const isTherapistForm = formId === 'sessionLogsFiltersForm';

        // The Notes column (index 4) renders free text and is not orderable.
        const columnDefs = [
            { orderable: false, targets: -1 },
            { orderable: false, targets: 4 },
        ];

        await initServerSideDataTable(table.id ? `#${table.id}` : '.session-log-table', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs,
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
                    d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
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

    initSessionLogNotes(table);

    const reloadTable = () => {
        if (table?.id && typeof window.jQuery !== 'undefined') {
            const dt = window.jQuery(`#${table.id}`).DataTable();
            if (dt?.ajax?.reload) dt.ajax.reload(null, false);
        }
    };

    // Delegated handler for AJAX-rendered rows (DataTable action buttons).
    // Confirmation metadata lives on the <form data-confirm-*> wrapping the
    // submit button, so we intercept the form's submit event. Forms marked
    // data-ajax="true" (approve/delete) are submitted via fetch and reload the
    // table in place, preserving the current page and active filters.
    document.body.addEventListener(
        'submit',
        async (event) => {
            const form = event.target.closest('form[data-confirm-title]');
            if (!form || form.dataset.confirmed === 'true') return;

            event.preventDefault();

            const result = await confirmDialog({
                title: form.dataset.confirmTitle || 'Are you sure?',
                text: form.dataset.confirmText || '',
                icon: form.dataset.confirmIcon || 'warning',
                confirmButtonText: 'Yes',
            });

            if (!result.isConfirmed) return;

            if (form.dataset.ajax !== 'true') {
                form.dataset.confirmed = 'true';
                form.submit();
                return;
            }

            try {
                const response = await fetch(form.action, {
                    method: form.method,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(form),
                });
                const json = await response.json();
                if (json.success) {
                    await successToast(json.message || 'Done.');
                    reloadTable();
                } else {
                    await errorAlert(json.message || 'Something went wrong.');
                }
            } catch {
                await errorAlert('Request failed. Please try again.');
            }
        },
        true,
    );
});
