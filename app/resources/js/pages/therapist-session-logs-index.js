import { initDataTable, initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog } from '../common/sweetalert';

const bindConfirmations = () => {
    document.querySelectorAll('form[data-confirm-title]').forEach((form) => {
        if (form.dataset.confirmBound === 'true') {
            return;
        }

        form.dataset.confirmBound = 'true';

        form.addEventListener('submit', async (event) => {
            if (form.dataset.confirmed === 'true') return;

            event.preventDefault();

            const result = await confirmDialog({
                title: form.dataset.confirmTitle || 'Are you sure?',
                text: form.dataset.confirmText || '',
                icon: form.dataset.confirmIcon || 'warning',
                confirmButtonText: 'Yes',
            });

            if (result.isConfirmed) {
                form.dataset.confirmed = 'true';
                form.submit();
            }
        });
    });
};

async function initServerSideSessionLogsTable() {
    const table = document.getElementById('sessionLogsTable');
    if (!table) return false;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return false;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('sessionLogsFiltersForm');

        await initServerSideDataTable('#sessionLogsTable', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: -1 }],
            getExtraData(d) {
                if (!form) return;
                d.filter_date_from = form.querySelector('[name="date_from"]')?.value ?? '';
                d.filter_date_to = form.querySelector('[name="date_to"]')?.value ?? '';
                d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
                d.filter_student_id = form.querySelector('[name="student_id"]')?.value ?? '';
                d.filter_ssa_id = form.querySelector('[name="ssa_id"]')?.value ?? '';
            },
        });

        // Reload table when filters change
        if (form) {
            form.addEventListener('change', () => {
                if (typeof window.jQuery !== 'undefined') {
                    const dt = window.jQuery('#sessionLogsTable').DataTable();
                    if (dt && dt.ajax && dt.ajax.reload) {
                        dt.ajax.reload();
                    }
                }
            });
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (typeof window.jQuery !== 'undefined') {
                    const dt = window.jQuery('#sessionLogsTable').DataTable();
                    if (dt && dt.ajax && dt.ajax.reload) {
                        dt.ajax.reload();
                    }
                }
            });
        }

        return true;
    } catch (error) {
        console.error('Failed to init session logs server-side table', error);
        return false;
    }
}

// Event delegation for confirm dialogs on AJAX-loaded rows. Confirmation
// metadata lives on the <form data-confirm-*> wrapping the submit button,
// so we intercept the form's submit event (capture phase).
function bindDelegatedConfirmations() {
    document.body.addEventListener(
        'submit',
        async (event) => {
            const form = event.target.closest('#sessionLogsTable form[data-confirm-title]');
            if (!form || form.dataset.confirmed === 'true') return;

            event.preventDefault();

            const result = await confirmDialog({
                title: form.dataset.confirmTitle || 'Are you sure?',
                text: form.dataset.confirmText || '',
                icon: form.dataset.confirmIcon || 'warning',
                confirmButtonText: 'Yes',
            });

            if (result.isConfirmed) {
                form.dataset.confirmed = 'true';
                form.submit();
            }
        },
        true,
    );
}

document.addEventListener('DOMContentLoaded', async () => {
    // Try server-side first (when data-datatable-url is present)
    const usedServerSide = await initServerSideSessionLogsTable();

    if (usedServerSide) {
        bindDelegatedConfirmations();
    } else if (document.querySelector('.session-log-table')) {
        // Fall back to client-side DataTable for legacy Blade-rendered tables
        await loadDataTablesLibrary();
        initDataTable('.session-log-table', {
            order: [[0, 'desc']],
            pageLength: 25,
        });
        bindConfirmations();
    }
});
