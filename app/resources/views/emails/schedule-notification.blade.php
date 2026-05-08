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
                    @if($schedule->location_details && !$schedule->meetingLink())
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

                <div style="margin:16px 0 4px; display:flex; gap:8px;">
                    @if($schedule->meetingLink())
                    <a href="{{ $schedule->meetingLink() }}" style="display:inline-block; background:#5563b8; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:6px; font-size:14px; font-weight:600;">
                        Join Session
                    </a>
                    @endif
                    <a href="{{ config('app.url') }}" style="display:inline-block; background:#f1f5f9; color:#0b1220; text-decoration:none; padding:10px 20px; border-radius:6px; font-size:14px; font-weight:600;">
                        View Schedule
                    </a>
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:0 24px 20px 24px;">
                <div style="padding:10px 14px; border-radius:6px; background:#fafafa; border:1px solid #e5e7eb; border-left:3px solid #f97316;">
                    <p style="margin:0; font-size:12px; color:#6b7280; line-height:1.6;">
                        This is an automated scheduling reminder. <span style="font-weight:600; color:#ea580c;">Please do not reply to this email.</span>
                        If you need to reschedule or have any questions, please contact your therapist/tutor directly
                        @if(!empty($therapistEmail))
                            at <a href="mailto:{{ $therapistEmail }}" style="color:#ea580c; text-decoration:none;">{{ $therapistEmail }}</a>
                        @endif
                        @if(!empty($therapistPhone))
                            or {{ $therapistPhone }},
                        @endif
                        or email our leadership team at
                        <a href="mailto:{{ config('brand.support_email') }}" style="color:#ea580c; text-decoration:none;">{{ config('brand.support_email') }}</a>.
                    </p>
                </div>
            </td>
        </tr>
        @include('emails.partials.footer')
    </table>
</body>
</html>
