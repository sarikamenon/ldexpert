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
                    make-up session with <strong>{{ $therapistName }}</strong>.
                </p>

                <div style="margin:20px 0; padding:16px; border-radius:10px; background:#f0f9ff; border:1px solid #bae6fd;">
                    <p style="margin:0 0 6px; color:#0369a1;"><strong>Affected session date(s)</strong></p>
                    @foreach ($dates as $date)
                        <p style="margin:0 0 4px; color:#0369a1;">{{ $date }}</p>
                    @endforeach
                </div>

                <p style="margin:0 0 12px;">
                    Please let me know by <strong>{{ $responseByDate }}</strong> if you'd like me to send over available
                    make-up times. If you would like a make-up, please select <strong>Request Make-Up</strong> and I
                    will send over my available make-up times.
                </p>

                <div style="margin:20px 0; text-align:center;">
                    <a href="{{ $requestUrl }}"
                        style="display:inline-block; background:#16a34a; color:#ffffff; text-decoration:none; padding:12px 22px; border-radius:6px; font-size:14px; font-weight:600; margin:0 6px;">
                        Request Make-Up
                    </a>
                    <a href="{{ $declineUrl }}"
                        style="display:inline-block; background:#e2e8f0; color:#0f172a; text-decoration:none; padding:12px 22px; border-radius:6px; font-size:14px; font-weight:600; margin:0 6px;">
                        Decline Make-Up
                    </a>
                </div>

                <p style="margin:0 0 12px;">
                    If I don't hear back by <strong>{{ $responseByDate }}</strong>, I'll mark the session as declined.
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
