<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Coverage Request Withdrawn</title>
</head>
<body style="font-family: ui-sans-serif, system-ui, -apple-system; background:#f6f7f8; padding:24px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:8px; border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:24px 24px 8px 24px;">
                <h1 style="margin:0; font-size:20px; color:#0b1220;">
                    A coverage request you accepted was withdrawn
                </h1>
                <p style="margin:8px 0 0; color:#475569;">
                    <strong>{{ $requesterName }}</strong> has withdrawn the request you accepted for the session below on <strong>{{ $scheduleDateLong }}</strong>. You are no longer covering this session — the original therapist will run it as planned, so no action is needed from you.
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px; color:#0b1220;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding-bottom:8px; color:#64748b; font-size:14px;">Time ({{ $scheduleTimezone }})</td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:16px; font-size:16px;">
                            {{ $scheduleStartTime }} - {{ $scheduleEndTime }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:8px; color:#64748b; font-size:14px;">Service</td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:16px; font-size:16px;">
                            {{ $schedule?->service?->name ?? 'N/A' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:8px; color:#64748b; font-size:14px;">Student</td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:16px; font-size:16px;">
                            {{ $schedule?->student?->name ?? 'N/A' }}
                        </td>
                    </tr>
                </table>

                <div style="margin:16px 0 4px;">
                    <a href="{{ $reviewUrl }}" style="display:inline-block; background:#5563b8; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:6px; font-size:14px; font-weight:600;">
                        View Sub Requests
                    </a>
                </div>
            </td>
        </tr>
        @include('emails.partials.footer')
    </table>
</body>
</html>
