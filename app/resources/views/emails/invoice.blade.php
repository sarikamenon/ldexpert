@component('mail::message')
    # Invoice {{ $invoice->invoice_number }}

    Dear {{ $invoice->school_contact_first_name ?? 'School Administrator' }},

    Please find attached invoice **{{ $invoice->invoice_number }}** for services provided during the billing period of
    **{{ $invoice->billing_period_start->format('M d') }} - {{ $invoice->billing_period_end->format('M d, Y') }}**.

    **Invoice Summary:**
    - **Invoice Number:** {{ $invoice->invoice_number }}
    - **Billing Period:** {{ $invoice->billing_period_start->format('M d') }} -
    {{ $invoice->billing_period_end->format('M d, Y') }}
    - **Total Amount:** ${{ number_format($invoice->total, 2) }}
    - **Due Date:** {{ $invoice->due_date->format('M d, Y') }}

    @if ($customMessage)
        {{ $customMessage }}
    @endif

    The invoice includes {{ $invoice->sessionLogs->count() }} session(s) totaling
    ${{ number_format($invoice->subtotal, 2) }}.

    Please remit payment by the due date. If you have any questions about this invoice, please contact us at
    {{ $invoice->company_email ?? 'our office' }}.

    Thank you for your business!

    Best regards,<br>
    {{ $invoice->company_name }}
@endcomponent
