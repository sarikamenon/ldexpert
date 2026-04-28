<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contract Auto-Extended</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <h1 style="margin:0; font-size:22px; color:#0f172a;">Contract Auto-Extended</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                <p style="margin:0 0 16px;">
                    The active contract and service support agreements for <strong>{{ $school->display_name }}</strong>
                    have been automatically extended by 1 year.
                </p>

                <div style="margin:20px 0; padding:16px; border-radius:10px; background:#f0fdf4; border:1px solid #86efac;">
                    <h2 style="margin:0 0 12px; font-size:16px; color:#166534;">Extension Summary</h2>
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="padding:4px 0; color:#166534;">School / Family:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600; color:#166534;">
                                {{ $school->display_name }}</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0; color:#166534;">Previous End Date:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600; color:#6b7280;">
                                {{ $oldEndDate->format('F j, Y') }}</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0; color:#166534;">New End Date:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600; color:#15803d;">
                                {{ $contract->end_date->format('F j, Y') }}</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0; color:#166534;">SSAs Extended:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600; color:#166534;">
                                {{ $ssasExtended }}</td>
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
