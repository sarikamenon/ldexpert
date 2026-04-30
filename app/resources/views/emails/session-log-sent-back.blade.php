<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session log sent back for rectification</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <h1 style="margin:0; font-size:22px; color:#0f172a;">Session log sent back for rectification</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                <p style="margin:0 0 12px;">
                    Dear {{ $sessionLog->therapist?->name ?? 'Therapist' }},
                </p>

                <p style="margin:0 0 16px;">
                    A session log has been sent back to you for rectification. Please review the comment below, make the necessary corrections, and resubmit.
                </p>

                <div style="margin:20px 0; padding:16px; border-radius:10px; background:#fef3c7; border:1px solid #fcd34d;">
                    <p style="margin:0 0 8px; color:#92400e; font-weight:600;">Session details</p>
                    <p style="margin:0 0 4px; color:#92400e;">
                        <strong>Student:</strong> {{ $sessionLog->student?->name ?? 'N/A' }}
                    </p>
                    <p style="margin:0 0 4px; color:#92400e;">
                        <strong>Service:</strong> {{ $sessionLog->service?->name ?? 'N/A' }}
                    </p>
                    <p style="margin:0 0 4px; color:#92400e;">
                        <strong>Session date:</strong> {{ $sessionDateFormatted ?? 'N/A' }}
                    </p>
                </div>

                @php
                    $latestComment = $sessionLog->getLatestSentBackComment();
                @endphp
                @if ($latestComment)
                    <div style="margin:20px 0; padding:16px; border-radius:10px; background:#f0f9ff; border:1px solid #bae6fd;">
                        <p style="margin:0 0 8px; color:#0369a1; font-weight:600;">Admin feedback</p>
                        <p style="margin:0; color:#0369a1; white-space: pre-wrap;">{{ $latestComment->comment }}</p>
                    </div>
                @endif

                <p style="margin:24px 0 16px;">
                    <a href="{{ route('therapist.session-logs.edit', $sessionLog) }}"
                        style="display:inline-block; padding:12px 24px; background:#2563eb; color:#ffffff; text-decoration:none; border-radius:8px; font-weight:600;">
                        Edit and resubmit session log
                    </a>
                </p>

                <p style="margin:24px 0 0;">
                    Best regards,<br>
                    {{ config('app.name') }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px; color:#94a3b8; font-size:12px; text-align:center;">
                You are receiving this email because a session log you submitted was sent back for rectification.
            </td>
        </tr>
    </table>
</body>

</html>
