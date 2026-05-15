import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from '../common/sweetalert';

async function initSubRequestsTable() {
    const table = document.getElementById('subRequestsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    await loadDataTablesLibrary();

    await initServerSideDataTable('#subRequestsTable', dataUrl, {
        order: [[0, 'asc']],
        pageLength: 25,
        columnDefs: [{ orderable: false, targets: [1, 2, 3, 4, 5] }],
    });
}

function bindAcceptHandler() {
    document.body.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-accept-sub-request]');
        if (!form) return;

        event.preventDefault();

        const result = await confirmDialog({
            title: 'Accept Sub Request?',
            text: 'You will be assigned as the substitute therapist for this session.',
            icon: 'question',
            confirmButtonText: 'Yes, accept',
        });

        if (!result.isConfirmed) return;

        showLoading('Accepting sub request...');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            closeAlert();

            if (!response.ok) {
                const data = await response.json().catch(() => ({ message: 'Failed to accept sub request.' }));
                throw new Error(data.message ?? 'Failed to accept sub request.');
            }

            await successToast('Sub request accepted. The session is now in your Past Sessions Queue.');
            window.location.reload();
        } catch (error) {
            errorAlert(error instanceof Error ? error.message : 'An error occurred.');
        }
    });
}

function bindDeclineHandler() {
    document.body.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-decline-url]');
        if (!button) return;

        event.preventDefault();

        const result = await confirmDialog({
            title: 'Decline Sub Request?',
            text: 'You will be marked as declined. The requester can still invite others.',
            icon: 'question',
            confirmButtonText: 'Yes, decline',
        });

        if (!result.isConfirmed) return;

        showLoading('Declining sub request...');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const url = button.getAttribute('data-decline-url') ?? '';

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            closeAlert();

            if (!response.ok) {
                const data = await response.json().catch(() => ({ message: 'Failed to decline sub request.' }));
                throw new Error(data.message ?? 'Failed to decline sub request.');
            }

            await successToast('Sub request declined.');
            window.location.reload();
        } catch (error) {
            errorAlert(error instanceof Error ? error.message : 'An error occurred.');
        }
    });
}

function bindCancelHandler() {
    document.body.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-cancel-url]');
        if (!button) return;

        event.preventDefault();

        const result = await confirmDialog({
            title: 'Cancel Sub Request?',
            text: 'This will withdraw the coverage request. It can be re-submitted from the schedule.',
            icon: 'warning',
            confirmButtonText: 'Yes, cancel it',
        });

        if (!result.isConfirmed) return;

        showLoading('Cancelling sub request...');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const url = button.getAttribute('data-cancel-url') ?? '';

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            closeAlert();

            if (!response.ok) {
                const data = await response.json().catch(() => ({ message: 'Failed to cancel sub request.' }));
                throw new Error(data.message ?? 'Failed to cancel sub request.');
            }

            await successToast('Sub request cancelled.');
            window.location.reload();
        } catch (error) {
            errorAlert(error instanceof Error ? error.message : 'An error occurred.');
        }
    });
}

document.addEventListener('DOMContentLoaded', async () => {
    await initSubRequestsTable();
    bindAcceptHandler();
    bindDeclineHandler();
    bindCancelHandler();
});
