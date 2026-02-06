/**
 * Therapist Bill Show Page JavaScript
 * Handles bill send confirmation
 */

import { confirmDialog } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', function () {
    const sendForm = document.getElementById('sendBillForm');
    if (!sendForm) {
        return;
    }

    sendForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const result = await confirmDialog({
            title: 'Send Bill?',
            text: 'This will send the bill to the therapist via email. Are you sure you want to continue?',
            icon: 'question',
            confirmButtonText: 'Yes, send bill',
            cancelButtonText: 'Cancel',
        });

        if (result.isConfirmed) {
            sendForm.submit();
        }
    });
});

