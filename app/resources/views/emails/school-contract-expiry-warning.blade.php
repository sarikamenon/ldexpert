<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contract Expiring Soon</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <h1 style="margin:0; font-size:22px; color:#0f172a;">Contract Expiring Soon</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                <p style="margin:0 0 16px;">
                    The active contract for <strong>{{ $school->display_name }}</strong> is expiring in 7 days.
                    Please review and take action before it expires.
                </p>

                <div style="margin:20px 0; padding:16px; border-radius:10px; background:#fef9c3; border:1px solid #fde047;">
                    <h2 style="margin:0 0 12px; font-size:16px; color:#713f12;">Contract Details</h2>
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="padding:4px 0; color:#713f12;">School / Family:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600; color:#713f12;">
                                {{ $school->display_name }}</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0; color:#713f12;">Contract Expires:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600; color:#b45309;">
                                {{ $contract->end_date->format('F j, Y') }}</td>
                        </tr>
                    </table>
                </div>

                <a href="{{ $contractsUrl }}"
                    style="display:inline-block; background:linear-gradient(135deg, #5563b8 0%, #a855f7 100%); color:#ffffff; text-decoration:none; padding:12px 20px; border-radius:999px; font-weight:600; margin:16px 0;">
                    View Contracts
                </a>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px; color:#94a3b8; font-size:12px; text-align:center;">
                &copy; {{ date('Y') }} NOVA - Neuroaffirming Operations & Virtual Administration.
            </td>
        </tr>
    </table>
</body>

</html>
