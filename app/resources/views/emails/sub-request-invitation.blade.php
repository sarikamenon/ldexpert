<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Coverage Request</title>
</head>
<body style="font-family: ui-sans-serif, system-ui, -apple-system; background:#f6f7f8; padding:24px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:8px; border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:24px 24px 8px 24px;">
                <h1 style="margin:0; font-size:20px; color:#0b1220;">
                    You've been invited to cover a session
                </h1>
                <p style="margin:8px 0 0; color:#475569;">
                    <strong>{{ $requesterName }}</strong> has asked if you can cover the session below on <strong>{{ $scheduleDateLong }}</strong>.
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
                    @if($schedule?->location_details && !$schedule?->meetingLink())
                    <tr>
                        <td style="padding-bottom:8px; color:#64748b; font-size:14px;">Location</td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:16px; font-size:16px;">
                            {{ $schedule->location_details }}
                        </td>
                    </tr>
                    @endif
                </table>

                @if(!empty($reason))
                <div style="margin:8px 0 16px; padding:12px 14px; background:#f8fafc; border-left:3px solid #5563b8; border-radius:4px;">
                    <p style="margin:0 0 4px; font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:0.04em;">Reason from {{ $requesterName }}</p>
                    <p style="margin:0; font-size:14px; color:#0b1220; line-height:1.5;">{{ $reason }}</p>
                </div>
                @endif

                <div style="margin:16px 0 4px;">
                    <a href="{{ $reviewUrl }}" style="display:inline-block; background:#5563b8; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:6px; font-size:14px; font-weight:600;">
                        Review Request
                    </a>
                </div>
            </td>
        </tr>
        @include('emails.partials.footer')
    </table>
</body>
</html>
