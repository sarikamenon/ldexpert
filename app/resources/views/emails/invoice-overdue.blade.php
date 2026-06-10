<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Overdue Payment Notice</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <h1 style="margin:0; font-size:22px; color:#dc2626;">Overdue Payment Notice</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                <p style="margin:0 0 12px;">
                    Dear {{ $invoice->school_contact_first_name ?? 'Valued Client' }},
                </p>

                <p style="margin:0 0 16px;">
                    Your invoice for
                    <strong>${{ number_format((float) $invoice->total, 2) }}</strong> was due on
                    <strong>{{ $invoice->due_date?->format(config('display.date')) ?? '—' }}</strong> and is now
                    <strong>{{ $daysOverdue }} {{ $daysOverdue === 1 ? 'day' : 'days' }} past due</strong>.
                </p>

                <div style="margin:20px 0; padding:16px; border-radius:10px; background:#fef2f2; border:1px solid #fecaca;">
                    <p style="margin:0 0 8px; color:#dc2626; font-weight:600;">Overdue Invoice:</p>
                    <p style="margin:0 0 4px; color:#991b1b;">
                        <strong>Amount Due:</strong> ${{ number_format((float) $invoice->total, 2) }}
                    </p>
                    <p style="margin:0 0 4px; color:#991b1b;">
                        <strong>Original Due Date:</strong> {{ $invoice->due_date?->format(config('display.date')) ?? '—' }}
                    </p>
                    <p style="margin:0; color:#991b1b;">
                        <strong>Days Overdue:</strong> {{ $daysOverdue }}
                    </p>
                </div>

                @if ($paymentUrl)
                    <div style="margin:20px 0; text-align:center;">
                        <a href="{{ $paymentUrl }}"
                            style="display:inline-block; padding:14px 36px; background:#dc2626; color:#ffffff; text-decoration:none; border-radius:8px; font-size:16px; font-weight:600;">
                            Pay Now
                        </a>
                        <p style="margin:10px 0 0; font-size:12px; color:#94a3b8;">
                            Click the button above to pay securely online.
                        </p>
                    </div>
                @endif

                <p style="margin:0 0 16px;">
                    Please arrange payment at your earliest convenience. If you have already submitted payment,
                    please disregard this notice. If you have any questions, please contact us at
                    <a href="mailto:{{ config('invoice.contact_email') }}"
                        style="color:#5563b8;">{{ config('invoice.contact_email') }}</a>.
                </p>

                <p style="margin:0 0 16px;">
                    To avoid a convenience fee, you can also pay via Venmo or check.<br>
                    Venmo: {{ config('invoice.venmo_handle') }}<br>
                    Mailing address for check payment: {{ config('invoice.check_mailing_address') }}
                </p>

                <p style="margin:24px 0 0;">
                    Warmly,<br>
                    The LD Expert Team
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px; color:#94a3b8; font-size:12px; text-align:center;">
                &copy; {{ date('Y') }} {{ $invoice->company_name ?? config('app.name') }}. This is an automated overdue
                payment notice.
            </td>
        </tr>
    </table>
</body>

</html>
