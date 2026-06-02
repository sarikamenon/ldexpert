<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Make-Up Session Scheduled</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <p style="margin:0; color:#475569;">Hello {{ $therapistName }},</p>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                <p style="margin:0 0 12px;">
                    Your student <strong>{{ $studentDisplayName }}</strong> has scheduled a make-up session based on your availability.
                </p>

                <div style="margin:20px 0; padding:16px; border-radius:10px; background:#f0fdf4; border:1px solid #bbf7d0;">
                    <p style="margin:0 0 8px; color:#166534;"><strong>Session Details</strong></p>
                    <p style="margin:0; color:#166534; font-size:14px;">
                        <strong>{{ $scheduledDateTime }}</strong>
                    </p>
                </div>

                <p style="margin:0 0 12px;">
                    The session has been added to your calendar. Please ensure you're available at the scheduled time.
                </p>

                <div style="margin:20px 0; padding:10px 14px; border-radius:6px; background:#fafafa; border:1px solid #e5e7eb; border-left:3px solid #f97316;">
                    <p style="margin:0; font-size:12px; color:#6b7280; line-height:1.6;">
                        If you have any questions or need to reschedule, please contact the parent or administrator directly.
                    </p>
                </div>
            </td>
        </tr>
        @include('emails.partials.footer')
    </table>
</body>

</html>
