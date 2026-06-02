<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Non-Accepted Make-Up Session</title>
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
                    Your student has not accepted a make-up session for the upcoming holiday/school closure. <strong>Please enter this as an NBC-Client in the RSM.</strong> You can enter this for any date between now and the school closure (including the day of the school closure).
                </p>

                <div style="margin:20px 0; padding:16px; border-radius:10px; background:#fef3c7; border:1px solid #fcd34d;">
                    <p style="margin:0; color:#92400e;">
                        <strong>Example:</strong> Robin, speech - Student was offered a make-up session for school closure, but did not accept.
                    </p>
                </div>
            </td>
        </tr>
        @include('emails.partials.footer')
    </table>
</body>

</html>
