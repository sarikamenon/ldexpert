import { initDataTable, initServerSideDataTable, loadDataTablesLibrary } from '../../common/datatables';
import { confirmDialog } from '../../common/sweetalert';

document.addEventListener('DOMContentLoaded', async () => {
    await loadDataTablesLibrary();

    const table = document.querySelector('.session-log-table[data-datatable-url]') || document.getElementById('adminSessionLogsTable') || document.getElementById('therapistSessionLogsTable');
    const dataUrl = table?.getAttribute('data-datatable-url');
    const formId = table?.getAttribute('data-filter-form') || 'adminSessionLogsFiltersForm';
    const form = formId ? document.getElementById(formId) : null;

    if (table && dataUrl) {
        const isTherapistForm = formId === 'sessionLogsFiltersForm';

        // The admin table has a non-orderable Notes column (index 4); the
        // therapist table has no Notes column, so only disable ordering there.
        const columnDefs = [{ orderable: false, targets: -1 }];
        if (!isTherapistForm) {
            columnDefs.push({ orderable: false, targets: 4 });
        }

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

        // Reveal the "Read more" toggle only on notes that actually overflow
        // the 2-line clamp. A MutationObserver on the tbody re-runs this on
        // every redraw (paging/search/sort re-render the rows) without
        // depending on jQuery's draw.dt event.
        const revealOverflowingNotes = () => {
            document.querySelectorAll('[data-notes-cell]').forEach((cell) => {
                const text = cell.querySelector('[data-notes-text]');
                const toggle = cell.querySelector('[data-notes-toggle]');
                if (!text || !toggle) return;
                if (text.classList.contains('notes-expanded')) return;
                toggle.classList.toggle('hidden', text.scrollHeight <= text.clientHeight + 1);
            });
        };

        const tbody = table.querySelector('tbody');
        if (tbody) {
            new MutationObserver(revealOverflowingNotes).observe(tbody, { childList: true });
        }
        revealOverflowingNotes();

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

    // Delegated handler for the notes "Read more / Read less" toggle. Rows are
    // re-rendered on every redraw, so we bind once on the body.
    document.body.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-notes-toggle]');
        if (!toggle) return;

        const text = toggle.closest('[data-notes-cell]')?.querySelector('[data-notes-text]');
        if (!text) return;

        const expanded = text.classList.toggle('notes-expanded');
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.textContent = expanded ? 'Read less' : 'Read more';
    });

    // Delegated handler for AJAX-rendered rows (DataTable action buttons).
    // Confirmation metadata lives on the <form data-confirm-*> wrapping the
    // submit button, so we intercept the form's submit event.
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

            if (result.isConfirmed) {
                form.dataset.confirmed = 'true';
                form.submit();
            }
        },
        true,
    );
});
