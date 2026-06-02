/**
 * Invoice Show Page JavaScript
 * Handles invoice send and resend email confirmations
 */

import { actionAlert, confirmDialog } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', function () {
    const sendForm = document.querySelector('form[action*="/send"]');
    if (sendForm && !sendForm.action.includes('resend')) {
        sendForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            // Reset the Alpine loading state set by x-on:submit so the button
            // does not stay stuck while a dialog is open or after it is dismissed.
            const sendFormData = window.Alpine?.$data(sendForm);
            if (sendFormData) {
                sendFormData.loading = false;
            }

            const invoiceTotal = Number.parseFloat(sendForm.dataset.invoiceTotal ?? '0');
            if (invoiceTotal <= 0) {
                const attachSessionsUrl = sendForm.dataset.attachSessionsUrl ?? '';

                const result = await actionAlert({
                    title: 'Cannot send this invoice',
                    text: 'This invoice total is $0.00, so it cannot be sent. Add billable sessions or keep it as draft.',
                    icon: 'warning',
                    confirmButtonText: 'Add or remove sessions',
                    cancelButtonText: 'Close',
                    reverseButtons: true,
                });

                if (result.isConfirmed && attachSessionsUrl) {
                    window.location.href = attachSessionsUrl;
                }
                return;
            }

            const isFamily = sendForm.getAttribute('data-is-private-family') === '1';
            const recipientEntity = isFamily ? 'family' : 'school';

            const result = await confirmDialog({
                title: 'Send Invoice?',
                text: `This will send the invoice to the ${recipientEntity} via email. Are you sure you want to continue?`,
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
