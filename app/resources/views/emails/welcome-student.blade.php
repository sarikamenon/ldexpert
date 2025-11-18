<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome to Learning Dynamics</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <p style="margin:0 0 8px; font-size:14px; color:#475569;">Hi {{ $name }},</p>
                <h1 style="margin:0; font-size:22px; color:#0f172a;">Welcome to your Learning Dynamics space</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                <p style="margin:0 0 12px;">
                    We’re excited to partner with you. Your secure student portal will help you keep track of sessions,
                    goals, and messages from your care team. Your login details are below—please keep them private.
                </p>

                <div
                    style="margin:20px 0; padding:16px; border-radius:10px; background:#f0f9ff; border:1px solid #bae6fd;">
                    <p style="margin:0 0 8px; color:#0369a1;">Login Email: <strong>{{ $email }}</strong></p>
                    <p style="margin:0; color:#0369a1;">Temporary Password: <strong>{{ $plainPassword }}</strong></p>
                </div>

                <p style="margin:0 0 16px;">
                    For your safety, please sign in soon and create a new password you’ll remember. If you ever feel
                    unsure
                    or need help, reply to this email or reach us at <a href="mailto:support@learningdynamics.com"
                        style="color:#0ea5e9;">support@learningdynamics.com</a>.
                </p>

                <a href="{{ config('app.url') }}/login"
                    style="display:inline-block; background:#0ea5e9; color:#ffffff; text-decoration:none; padding:12px 20px; border-radius:999px; font-weight:600;">
                    Go to Student Portal
                </a>

                <p style="margin:24px 0 0;">
                    We’re here to celebrate your progress and support you on the calm days and the tough ones. Take your
                    time, breathe, and know that your LD team is just a message away.
                </p>

                <p style="margin:24px 0 0;">
                    With care,<br>
                    The Learning Dynamics Support Team
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px; color:#94a3b8; font-size:12px; text-align:center;">
                &copy; {{ date('Y') }} Learning Dynamics. You’re receiving this email because a secure student
                account was created for you.
            </td>
        </tr>
    </table>
</body>

</html>
