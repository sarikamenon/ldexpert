<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Make-Up Session Needed</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <p style="margin:0; color:#475569;">Hello!</p>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                <p style="margin:0 0 12px;">
                    This is a friendly reminder that {{ $studentName }}'s school is closed on the date(s) below. Because
                    your student is typically seen for their session on the date(s) listed, they are eligible for a
                    make-up session with <strong>{{ $therapistName }}</strong>. Sessions are less frequent on this
                    plan, so we want to make sure {{ $studentName }} gets the time they're due.
                </p>

                <div style="margin:20px 0; padding:16px; border-radius:10px; background:#f0f9ff; border:1px solid #bae6fd;">
                    <p style="margin:0 0 6px; color:#0369a1;"><strong>Affected session date(s)</strong></p>
                    @foreach ($dates as $date)
                        <p style="margin:0 0 4px; color:#0369a1;">{{ $date }}</p>
                    @endforeach
                </div>

                <p style="margin:0 0 12px;">
                    To schedule your make-up session, please click the button below by <strong>{{ $responseByDate }}</strong>. You'll be able to select from available times that work with your schedule.
                </p>

                <div style="margin:24px 0; text-align:center;">
                    <a href="{{ $requestUrl }}"
                        style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:8px; font-size:14px; font-weight:600; margin-right:12px; white-space:nowrap;">
                        Accept
                    </a>
                    <a href="{{ $declineUrl }}"
                        style="display:inline-block; background:#e5e7eb; color:#374151; text-decoration:none; padding:12px 24px; border-radius:8px; font-size:14px; font-weight:500; white-space:nowrap;">
                        No, Thanks
                    </a>
                </div>

                <p style="margin:0 0 12px;">
                    If you don't respond by <strong>{{ $responseByDate }}</strong>, we'll assume you don't need a make-up session at this time.
                </p>

                <div style="margin:20px 0; padding:10px 14px; border-radius:6px; background:#fafafa; border:1px solid #e5e7eb; border-left:3px solid #f97316;">
                    <p style="margin:0; font-size:12px; color:#6b7280; line-height:1.6;">
                        Questions? Reply to this email or contact {{ $therapistName }} directly at
                        <a href="mailto:{{ $therapistEmail }}"
                            style="color:#ea580c; text-decoration:none;">{{ $therapistEmail }}</a>.
                    </p>
                </div>
            </td>
        </tr>
        @include('emails.partials.footer')
    </table>
</body>

</html>
