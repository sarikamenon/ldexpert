import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert } from '../common/sweetalert';

async function initTherapistContractsTable() {
    const table = document.getElementById('therapistContractsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    const hideTherapistColumn = table.hasAttribute('data-hide-therapist-column');

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('therapistContractsListFiltersForm');

        const columnDefs = [
            { orderable: false, targets: -1 },
        ];

        if (hideTherapistColumn) {
            columnDefs.push({ visible: false, targets: 1 });
        }

        await initServerSideDataTable('#therapistContractsTable', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs,
            getExtraData(d) {
                if (!form) return;
                const statusVal = form.querySelector('[name="status"]')?.value ?? 'active';
                d.filter_status = statusVal === 'all' ? '' : statusVal;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                d.filter_therapist_id = form.querySelector('[name="therapist_id"]')?.value ?? '';
                const formData = new FormData(form);
                d.filter_therapist_ids = Array.from(formData.getAll('therapist_ids[]') || []);
            },
        });
    } catch (error) {
        console.error('Failed to init therapist contracts table', error);
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    initTherapistContractsTable();

    // Reload table when filters change
    const form = document.getElementById('therapistContractsListFiltersForm');
    if (form) {
        form.addEventListener('change', () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#therapistContractsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#therapistContractsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
    }

    // Event delegation for status toggle (rows loaded via AJAX)
    document.body.addEventListener('click', async (event) => {
        const button = event.target.closest('.therapist-contract-status-toggle');
        if (!button) return;

        event.preventDefault();
        const endpoint = button.dataset.endpoint;
        const nextStatus = button.dataset.nextStatus;
        const isActivating = nextStatus === 'active';

        const result = await confirmDialog({
            title: isActivating ? 'Activate contract?' : 'Deactivate contract?',
            text: isActivating
                ? 'Therapist payouts will use this contract rate.'
                : 'Therapist payouts will no longer use this contract.',
            icon: 'warning',
            confirmButtonText: isActivating ? 'Activate' : 'Deactivate',
        });

        if (!result.isConfirmed) {
            return;
        }

        const formData = new FormData();
        formData.append('_method', 'PATCH');
        formData.append('status', nextStatus);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Unable to update contract status.');
            }

            await successToast(payload.message);
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#therapistContractsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        } catch (error) {
            errorAlert(error.message || 'Unable to update contract status.');
        }
    });
});
