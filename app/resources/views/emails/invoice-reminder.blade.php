<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Reminder — Invoice {{ $invoice->invoice_number }}</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <h1 style="margin:0; font-size:22px; color:#0f172a;">Payment Reminder</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                @php
                    $recipientName = $invoice->parent_name
                        ?? $invoice->school_contact_first_name
                        ?? 'Valued Client';
                @endphp

                <p style="margin:0 0 12px;">
                    Dear {{ $recipientName }},
                </p>

                <p style="margin:0 0 16px;">
                    This is a friendly reminder that invoice <strong>{{ $invoice->invoice_number }}</strong> for
                    <strong>${{ number_format((float) $invoice->total, 2) }}</strong> is due on
                    <strong>{{ $invoice->due_date?->format('M d, Y') ?? '—' }}</strong>.
                </p>

                <div style="margin:20px 0; padding:16px; border-radius:10px; background:#f0f9ff; border:1px solid #bae6fd;">
                    <p style="margin:0 0 8px; color:#0369a1; font-weight:600;">Invoice Summary:</p>
                    <p style="margin:0 0 4px; color:#0369a1;">
                        <strong>Invoice Number:</strong> {{ $invoice->invoice_number }}
                    </p>
                    <p style="margin:0 0 4px; color:#0369a1;">
                        <strong>Billing Period:</strong>
                        {{ $invoice->billing_period_start?->format('M d') }} -
                        {{ $invoice->billing_period_end?->format('M d, Y') }}
                    </p>
                    <p style="margin:0 0 4px; color:#0369a1;">
                        <strong>Total Amount:</strong> ${{ number_format((float) $invoice->total, 2) }}
                    </p>
                    <p style="margin:0; color:#0369a1;">
                        <strong>Due Date:</strong> {{ $invoice->due_date?->format('M d, Y') ?? '—' }}
                    </p>
                </div>

                @if ($paymentUrl)
                    <div style="margin:20px 0; text-align:center;">
                        <a href="{{ $paymentUrl }}"
                            style="display:inline-block; padding:14px 36px; background:#5563b8; color:#ffffff; text-decoration:none; border-radius:8px; font-size:16px; font-weight:600;">
                            Pay Now
                        </a>
                        <p style="margin:10px 0 0; font-size:12px; color:#94a3b8;">
                            Click the button above to pay securely online.
                        </p>
                    </div>
                @endif

                <p style="margin:0 0 16px;">
                    If you have already submitted payment, please disregard this reminder. For questions about this
                    invoice, please contact us at
                    <a href="mailto:{{ $invoice->company_email ?? 'support@nova.com' }}"
                        style="color:#5563b8;">{{ $invoice->company_email ?? 'our office' }}</a>.
                </p>

                <p style="margin:24px 0 0;">
                    Thank you,<br>
                    {{ $invoice->company_name }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px; color:#94a3b8; font-size:12px; text-align:center;">
                &copy; {{ date('Y') }} {{ $invoice->company_name ?? config('app.name') }}. This is an automated payment
                reminder.
            </td>
        </tr>
    </table>
</body>

</html>
