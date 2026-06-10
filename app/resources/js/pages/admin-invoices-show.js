/**
 * Invoice Show Page JavaScript
 * Handles invoice send and resend email confirmations
 */

import { actionAlert, confirmDialog } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', function () {
    // The Send button opens the send modal (which carries the recipient email
    // field). A $0.00 invoice can never be sent, so intercept the click and steer
    // the admin to attach sessions instead of opening the modal.
    const sendButton = document.getElementById('open-send-email-button');
    if (sendButton) {
        sendButton.addEventListener(
            'click',
            async function (e) {
                const invoiceTotal = Number.parseFloat(sendButton.dataset.invoiceTotal ?? '0');
                if (invoiceTotal > 0) {
                    return;
                }

                // Stop Alpine's $dispatch from opening the modal for a $0 invoice.
                e.preventDefault();
                e.stopImmediatePropagation();

                const attachSessionsUrl = sendButton.dataset.attachSessionsUrl ?? '';

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
            },
            true
        );
    }

    const sendForm = document.getElementById('send-email-form');
    if (sendForm) {
        sendForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const isFamily = sendButton?.getAttribute('data-is-private-family') === '1';
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
