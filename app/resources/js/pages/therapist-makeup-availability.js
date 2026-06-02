import { initDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, errorAlert, successToast } from '../common/sweetalert';

async function initAvailabilityTable() {
    const table = document.getElementById('makeupAvailabilityTable');
    if (!table) return;

    try {
        await loadDataTablesLibrary();
        await initDataTable('#makeupAvailabilityTable', {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
        });
    } catch (error) {
        console.error('Failed to initialize availability table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    void initAvailabilityTable();

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-delete-availability');
        if (!btn) return;

        const result = await confirmDialog({
            title: 'Delete availability window?',
            text: 'This window will be removed. Any existing make-up bookings within it are not affected.',
            icon: 'warning',
            confirmButtonText: 'Yes, delete',
        });

        if (!result.isConfirmed) return;

        const url = btn.dataset.deleteUrl;

        try {
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                const row = btn.closest('tr[data-row-id]');
                if (
                    row &&
                    typeof window.jQuery !== 'undefined' &&
                    typeof window.jQuery.fn.dataTable !== 'undefined' &&
                    window.jQuery.fn.dataTable.isDataTable('#makeupAvailabilityTable')
                ) {
                    const dataTable = window.jQuery('#makeupAvailabilityTable').DataTable();
                    dataTable.row(row).remove().draw(false);
                    if (dataTable.rows().count() === 0) {
                        location.reload();
                    }
                } else {
                    if (row) {
                        row.remove();
                    }
                    const tbody = document.querySelector('#availability-list');
                    if (tbody && tbody.querySelectorAll('tr').length === 0) {
                        location.reload();
                    }
                }

                successToast('Availability window removed.');
            } else {
                errorAlert('Unable to delete this window. Please try again.');
            }
        } catch {
            errorAlert('An unexpected error occurred.');
        }
    });
});
