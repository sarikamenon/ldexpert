/**
 * Invoice Show Page JavaScript
 * Handles invoice send and resend email confirmations
 */

import { actionAlert, confirmDialog } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', function () {
    // Send posts the invoice directly: it emails the school/family when an invoice
    // email is on file, otherwise it just marks the invoice as sent. A $0.00 invoice
    // can never be sent, so intercept the submit and steer the admin to attach
    // sessions; otherwise confirm with wording that reflects whether an email goes out.
    const sendButton = document.getElementById('send-invoice-button');
    const sendForm = document.getElementById('send-email-form');
    if (sendForm && sendButton) {
        sendForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const invoiceTotal = Number.parseFloat(sendButton.dataset.invoiceTotal ?? '0');
            if (invoiceTotal <= 0) {
                const attachSessionsUrl = sendButton.dataset.attachSessionsUrl ?? '';

                const zeroResult = await actionAlert({
                    title: 'Cannot send this invoice',
                    text: 'This invoice total is $0.00, so it cannot be sent. Add billable sessions or keep it as draft.',
                    icon: 'warning',
                    confirmButtonText: 'Add or remove sessions',
                    cancelButtonText: 'Close',
                    reverseButtons: true,
                });

                if (zeroResult.isConfirmed && attachSessionsUrl) {
                    window.location.href = attachSessionsUrl;
                }
                return;
            }

            const hasInvoiceEmail = sendButton.dataset.hasInvoiceEmail === '1';
            const isFamily = sendButton.dataset.isPrivateFamily === '1';
            const recipientEntity = isFamily ? 'family' : 'school';

            const result = await confirmDialog({
                title: 'Send Invoice?',
                text: hasInvoiceEmail
                    ? `This will send the invoice to the ${recipientEntity} via email. Are you sure you want to continue?`
                    : `No invoice email is on file for this ${recipientEntity}, so no email will be sent — the invoice will just be marked as sent. Continue?`,
                icon: 'question',
                confirmButtonText: hasInvoiceEmail ? 'Yes, send invoice' : 'Yes, mark as sent',
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
