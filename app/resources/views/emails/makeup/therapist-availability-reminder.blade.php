<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enter Available Make-Up Session Times</title>
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
                    There is an upcoming holiday/school closure that requires make-up sessions for students. Please enter your available make-up times into NOVA in order for families to automatically schedule with you. You can offer make-up sessions on the day of the school closure if you choose.
                </p>

                <div style="margin:20px 0; padding:16px; border-radius:10px; background:#f0f9ff; border:1px solid #bae6fd;">
                    <p style="margin:0 0 6px; color:#0369a1;"><strong>Closure</strong></p>
                    <p style="margin:0; color:#0369a1;">{{ $eventTitle }} — {{ $eventDate }}</p>
                </div>

                <p style="margin:0 0 12px;">
                    If you do not enter in make-up session times, you will be responsible for scheduling make-up sessions directly.
                </p>
            </td>
        </tr>
        @include('emails.partials.footer')
    </table>
</body>

</html>
