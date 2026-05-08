<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upcoming Session Reminder</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <p style="margin:0 0 8px; font-size:14px; color:#475569;">Hi {{ $recipientName }},</p>
                <h1 style="margin:0; font-size:22px; color:#0f172a;">Upcoming Session Reminder</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                <p style="margin:0 0 12px;">
                    This is a friendly reminder about an upcoming
                    <strong>{{ $schedule->service->name ?? 'Therapy' }}</strong> session.
                    We know how important consistency is for progress, and we look forward to seeing you.
                </p>

                <div style="margin:20px 0; padding:16px; border-radius:10px; background:#f0f9ff; border:1px solid #bae6fd;">
                    <p style="margin:0 0 6px; color:#0369a1;"><strong>Session Details</strong></p>
                    <p style="margin:0 0 4px; color:#0369a1;">Therapist: <strong>{{ $schedule->therapist->name }}</strong></p>
                    <p style="margin:0 0 4px; color:#0369a1;">Student: <strong>{{ $schedule->student->name }}</strong></p>
                    <p style="margin:0 0 4px; color:#0369a1;">Date: <strong>{{ $scheduleDate }}</strong></p>
                    <p style="margin:0 0 4px; color:#0369a1;">Time: <strong>{{ $startTime }} – {{ $endTime }} ({{ $timezone }})</strong></p>
                    @if(!$schedule->meetingLink() && $schedule->location_details)
                    <p style="margin:0; color:#0369a1;">Location: <strong>{{ $schedule->location_details }}</strong></p>
                    @elseif(!$schedule->meetingLink())
                    <p style="margin:0; color:#0369a1;">Location: <strong>No specific location details</strong></p>
                    @endif
                </div>

                @if ($schedule->notes)
                    <p style="margin:0 0 12px;">
                        <strong>Notes:</strong> {{ $schedule->notes }}
                    </p>
                @endif

                @if($schedule->meetingLink())
                <div style="margin:20px 0;">
                    <a href="{{ $schedule->meetingLink() }}" style="display:inline-block; background:#5563b8; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:6px; font-size:14px; font-weight:600;">
                        Join Session
                    </a>
                </div>
                @endif

                <div style="margin:20px 0; padding:10px 14px; border-radius:6px; background:#fafafa; border:1px solid #e5e7eb; border-left:3px solid #f97316;">
                    <p style="margin:0; font-size:12px; color:#6b7280; line-height:1.6;">
                        This is an automated scheduling reminder. <span style="font-weight:600; color:#ea580c;">Please do not reply to this email.</span>
                        If you need to reschedule or have any questions, please contact your therapist/tutor directly at
                        <a href="mailto:{{ $schedule->therapist->email }}" style="color:#ea580c; text-decoration:none;">{{ $schedule->therapist->email }}</a>
                        @if($schedule->therapist->therapistProfile->phone ?? null)
                            or {{ $schedule->therapist->therapistProfile->phone }},
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
