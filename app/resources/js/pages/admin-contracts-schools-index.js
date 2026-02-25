import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert } from '../common/sweetalert';

async function initSchoolContractsTable() {
    const table = document.getElementById('schoolContractsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('schoolContractsFiltersForm');

        await initServerSideDataTable('#schoolContractsTable', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
            getExtraData(d) {
                if (!form) return;
                d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
                const formData = new FormData(form);
                d.filter_school_ids = Array.from(formData.getAll('school_ids[]') || []);
            },
        });
    } catch (error) {
        console.error('Failed to init school contracts table', error);
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    initSchoolContractsTable();

    // Reload table when filters change
    const form = document.getElementById('schoolContractsFiltersForm');
    if (form) {
        form.addEventListener('change', () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#schoolContractsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#schoolContractsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
    }

    // Event delegation for status toggle (rows loaded via AJAX)
    document.body.addEventListener('click', async (event) => {
        const button = event.target.closest('.contract-status-toggle');
        if (!button) return;

        event.preventDefault();
        const endpoint = button.dataset.endpoint;
        const nextStatus = button.dataset.nextStatus;
        const isActivating = nextStatus === 'active';

        const result = await confirmDialog({
            title: isActivating ? 'Activate contract?' : 'Deactivate contract?',
            text: isActivating
                ? 'This contract will replace any existing active contract for overlapping dates.'
                : 'Therapists cannot invoice against inactive contracts.',
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
                const dt = window.jQuery('#schoolContractsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        } catch (error) {
            errorAlert(error.message || 'Unable to update contract status.');
        }
    });
});
