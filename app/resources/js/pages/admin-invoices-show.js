/**
 * Invoice Show Page JavaScript
 * Handles invoice send confirmation
 */

import { confirmDialog } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', function () {
    const sendForm = document.querySelector('form[action*="send"]');
    if (!sendForm) {
        return;
    }

    sendForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const result = await confirmDialog({
            title: 'Send Invoice?',
            text: 'This will send the invoice to the school via email. Are you sure you want to continue?',
            icon: 'question',
            confirmButtonText: 'Yes, send invoice',
            cancelButtonText: 'Cancel',
        });

        if (result.isConfirmed) {
            sendForm.submit();
        }
    });
});

