<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome to {{ $platformName }}</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <h1 style="margin:0 0 8px; font-size:22px; color:#0f172a;">Welcome, {{ $name }}</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                <p style="margin:0 0 12px;">
                    We're excited to work with you! {{ $platformName }}, your secure student portal, will help
                    you keep track of sessions and information from your student's team.
                </p>

                <p style="margin:0 0 16px;">
                    Click the button below to choose a password you'll remember, then sign in.
                </p>

                <div
                    style="margin:20px 0; padding:16px; border-radius:10px; background:#f0f9ff; border:1px solid #bae6fd;">
                    <p style="margin:0; color:#0369a1;">Username: <strong>{{ $username }}</strong></p>
                </div>

                <a href="{{ $resetUrl }}"
                    style="display:inline-block; background:linear-gradient(135deg, #5563b8 0%, #a855f7 100%); color:#ffffff; text-decoration:none; padding:12px 20px; border-radius:999px; font-weight:600;">
                    Set Your Password
                </a>

                <p style="margin:24px 0 0;">
                    If you ever feel unsure or need help, reply to this email or reach us at
                    <a href="mailto:{{ $supportEmail }}"
                        style="color:#5563b8;">{{ $supportEmail }}</a>.
                </p>

                <p style="margin:24px 0 0;">
                    We're here to celebrate progress and support you through the calm days and the tough ones. Know that
                    your {{ $brandName }} team is just a message away!
                </p>

                <p style="margin:24px 0 0;">
                    Warmly,<br>
                    {{ $brandName }} Team
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px; color:#94a3b8; font-size:12px; text-align:center;">
                &copy; {{ $currentYear }} {{ $copyrightName }}. You're receiving
                this email because a secure student
                account was created for you.
            </td>
        </tr>
    </table>
</body>

</html>
