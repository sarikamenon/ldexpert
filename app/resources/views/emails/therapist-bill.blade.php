<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bill {{ $bill->bill_number }}</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <h1 style="margin:0; font-size:22px; color:#0f172a;">Bill {{ $bill->bill_number }}</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                <p style="margin:0 0 12px;">
                    Dear {{ $bill->therapist_name }},
                </p>

                <p style="margin:0 0 16px;">
                    Please find attached bill <strong>{{ $bill->bill_number }}</strong> for services provided
                    during the billing period of
                    <strong>{{ $bill->billing_period_start->format('M d') }} -
                        {{ $bill->billing_period_end->format('M d, Y') }}</strong>.
                </p>

                <div
                    style="margin:20px 0; padding:16px; border-radius:10px; background:#f0f9ff; border:1px solid #bae6fd;">
                    <p style="margin:0 0 8px; color:#0369a1; font-weight:600;">Bill Summary:</p>
                    <p style="margin:0 0 4px; color:#0369a1;">
                        <strong>Bill Number:</strong> {{ $bill->bill_number }}
                    </p>
                    <p style="margin:0 0 4px; color:#0369a1;">
                        <strong>Billing Period:</strong>
                        {{ $bill->billing_period_start->format('M d') }} -
                        {{ $bill->billing_period_end->format('M d, Y') }}
                    </p>
                    <p style="margin:0; color:#0369a1;">
                        <strong>Total Amount:</strong> ${{ number_format($bill->total_due, 2) }}
                    </p>
                </div>

                @if ($customMessage)
                    <p style="margin:16px 0;">
                        {{ $customMessage }}
                    </p>
                @endif

                <p style="margin:0 0 16px;">
                    The bill includes {{ $bill->sessionLogs->count() }} session(s) totaling
                    ${{ number_format($bill->subtotal, 2) }}.
                </p>

                <p style="margin:0 0 16px;">
                    If you have any questions about this bill, please contact
                    us at
                    <a href="mailto:{{ $bill->company_email ?? 'support@nova.com' }}"
                        style="color:#5563b8;">{{ $bill->company_email ?? 'our office' }}</a>.
                </p>

                <p style="margin:24px 0 0;">
                    Thank you for your service!
                </p>

                <p style="margin:24px 0 0;">
                    Best regards,<br>
                    {{ $bill->company_name }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px; color:#94a3b8; font-size:12px; text-align:center;">
                &copy; {{ date('Y') }} {{ $bill->company_name ?? config('app.name') }}. You're receiving this
                email
                because a bill was sent to you.
            </td>
        </tr>
    </table>
</body>

</html>
