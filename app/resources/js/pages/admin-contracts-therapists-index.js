import { loadDataTablesLibrary, initDataTable } from '../common/datatables';
import { confirmDialog, successToast, errorAlert } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', async () => {
    await loadDataTablesLibrary();
    await initDataTable('#therapistContractsTable', {
        order: [[0, 'desc']],
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: -1 }
        ],
    });

    document.querySelectorAll('.therapist-contract-status-toggle').forEach((button) => {
        button.addEventListener('click', async () => {
            const endpoint = button.dataset.endpoint;
            const nextStatus = button.dataset.nextStatus;
            const isActivating = nextStatus === 'active';

            const result = await confirmDialog({
                title: isActivating ? 'Activate contract?' : 'Deactivate contract?',
                text: isActivating
                    ? 'Therapist payouts will use this contract rate.'
                    : 'Inactive contracts cannot be used for billing.',
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
                window.location.reload();
            } catch (error) {
                errorAlert(error.message || 'Unable to update contract status.');
            }
        });
    });
});

