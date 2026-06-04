<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>First Session Scheduled</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <p style="margin:0; color:#475569;">Hello {{ $managerName }},</p>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                <p style="margin:0 0 12px;">
                    The first session has just been scheduled for <strong>{{ $schoolName }}</strong>.
                </p>

                <div style="margin:20px 0; padding:16px; border-radius:10px; background:#eff6ff; border:1px solid #bfdbfe;">
                    <p style="margin:0; color:#1e40af; font-size:14px; line-height:1.6;">
                        Since this is a prepaid (advance) family, please generate and send the first invoice
                        as soon as possible so billing stays on track.
                    </p>
                </div>

                <p style="margin:0 0 12px;">
                    You can create the invoice from the admin billing area for this school or family.
                </p>

                <div style="margin:20px 0; padding:10px 14px; border-radius:6px; background:#fafafa; border:1px solid #e5e7eb; border-left:3px solid #2563eb;">
                    <p style="margin:0; font-size:12px; color:#6b7280; line-height:1.6;">
                        This is a one-time reminder sent when the first schedule is created for a private-student family.
                    </p>
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 28px 28px; color:#94a3b8; font-size:12px;">
                <p style="margin:0;">LD Expert</p>
            </td>
        </tr>
    </table>
</body>

</html>
