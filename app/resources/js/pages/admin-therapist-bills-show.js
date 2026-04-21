import { confirmDialog } from '../common/sweetalert';

function initSendForm() {
    const form = document.getElementById('sendBillForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
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

