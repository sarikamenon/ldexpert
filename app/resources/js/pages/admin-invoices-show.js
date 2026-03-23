/**
 * Invoice Show Page JavaScript
 * Handles invoice send and resend email confirmations
 */

import { confirmDialog } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', function () {
    const sendForm = document.querySelector('form[action*="/send"]');
    if (sendForm && !sendForm.action.includes('resend')) {
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
    }

    const resendForm = document.getElementById('resend-email-form');
    if (resendForm) {
        resendForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const result = await confirmDialog({
                title: 'Resend Invoice Email?',
                text: 'This will resend the invoice email with the PDF and payment link. Continue?',
                icon: 'question',
                confirmButtonText: 'Yes, resend email',
                cancelButtonText: 'Cancel',
            });

            if (result.isConfirmed) {
                resendForm.submit();
            }
        });
    }
});
