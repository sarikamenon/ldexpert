<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Schedule Notification</title>
</head>
<body style="font-family: ui-sans-serif, system-ui, -apple-system; background:#f6f7f8; padding:24px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:8px; border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:24px 24px 8px 24px;">
                <h1 style="margin:0; font-size:20px; color:#0b1220;">
                    {{ $type === 'created' ? 'New Schedule Added' : 'Schedule Updated' }}
                </h1>
                <p style="margin:8px 0 0; color:#475569;">
                    A schedule has been {{ $type }} for <strong>{{ $scheduleDateLong }}</strong>.
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
                            {{ $schedule->service->name ?? 'N/A' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:8px; color:#64748b; font-size:14px;">Therapist</td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:16px; font-size:16px;">
                            {{ $schedule->therapist->name ?? 'N/A' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:8px; color:#64748b; font-size:14px;">Student</td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:16px; font-size:16px;">
                            {{ $schedule->student->name ?? 'N/A' }}
                        </td>
                    </tr>
                    @if($schedule->location_details)
                    <tr>
                        <td style="padding-bottom:8px; color:#64748b; font-size:14px;">Location / Link</td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:16px; font-size:16px;">
                            {{ $schedule->location_details }}
                        </td>
                    </tr>
                    @endif
                </table>
                
                <div style="margin-top:24px;">
                    <a href="{{ config('app.url') }}" style="display:inline-block; background:linear-gradient(135deg, #5563b8 0%, #a855f7 100%); color:#ffffff; text-decoration:none; padding:10px 14px; border-radius:8px;">
                        View Schedule
                    </a>
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:24px; color:#94a3b8; font-size:12px;">
                &copy; {{ date('Y') }} NOVA - Neuroaffirming Operations & Virtual Administration
            </td>
        </tr>
    </table>
</body>
</html>

