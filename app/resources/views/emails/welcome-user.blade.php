<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome</title>
</head>

<body style="font-family: ui-sans-serif, system-ui, -apple-system; background:#f6f7f8; padding:24px;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:8px; border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:24px 24px 8px 24px;">
                <h1 style="margin:0; font-size:20px; color:#0b1220;">Welcome to NOVA</h1>
                <p style="margin:8px 0 0; color:#475569;">Hi {{ $name }}, your account has been created.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 24px 0 24px; color:#0b1220;">
                <p style="margin:0 0 8px;">Login Email: <strong>{{ $email }}</strong></p>
                <p style="margin:0 0 16px;">Temporary Password: <strong>{{ $plainPassword }}</strong></p>
                <p style="margin:0 0 16px; color:#475569;">Please verify your email and change the password after first
                    login.</p>
                <a href="{{ config('app.url') }}/login"
                    style="display:inline-block; background:linear-gradient(135deg, #5563b8 0%, #a855f7 100%); color:#ffffff; text-decoration:none; padding:10px 14px; border-radius:8px;">Go
                    to Login</a>
            </td>
        </tr>
        <tr>
            <td style="padding:24px; color:#94a3b8; font-size:12px;">&copy; {{ date('Y') }} NOVA - Neuroaffirming
                Operations & Virtual Administration</td>
        </tr>
    </table>
</body>

</html>
