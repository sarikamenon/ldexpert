import { actionAlert, confirmDialog } from '../common/sweetalert';

function initSendForm() {
    const form = document.getElementById('sendBillForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const totalDue = Number.parseFloat(form.dataset.totalDue ?? '0');
        if (totalDue <= 0) {
            const attachSessionsUrl = form.dataset.attachSessionsUrl ?? '';
            const deleteForm = document.getElementById('deleteBillForm');
            const canDelete = Boolean(deleteForm);

            const result = await actionAlert({
                title: 'Cannot send this bill',
                text: 'This bill total is $0.00, so it cannot be sent. Add billable sessions or keep it as draft/delete it.',
                icon: 'warning',
                showDenyButton: canDelete,
                confirmButtonText: 'Delete bill',
                confirmButtonColor: '#d33',
                denyButtonText: 'Add or remove sessions',
                denyButtonColor: '#3085d6',
                cancelButtonText: 'Cancel',
                cancelButtonColor: '#6e7881',
                reverseButtons: true,
            });

            if (result.isDenied && attachSessionsUrl) {
                window.location.href = attachSessionsUrl;
                return;
            }

            if (result.isConfirmed && deleteForm) {
                deleteForm.requestSubmit();
            }
            return;
        }

        const result = await confirmDialog({
            title: 'Send Bill?',
            text: 'This will send the bill to the therapist via email. Are you sure you want to continue?',
            icon: 'question',
            confirmButtonText: 'Yes, send bill',
            cancelButtonText: 'Cancel',
        });
        if (result.isConfirmed) form.submit();
    });
}

function initDeleteForm() {
    const form = document.getElementById('deleteBillForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const result = await confirmDialog({
            title: 'Delete Bill?',
            text: 'This will unlink all sessions and permanently delete the bill. This cannot be undone.',
            icon: 'warning',
            confirmButtonText: 'Yes, delete bill',
            cancelButtonText: 'Cancel',
        });
        if (result.isConfirmed) form.submit();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initSendForm();
    initDeleteForm();
});

