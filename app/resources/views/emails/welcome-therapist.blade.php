<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome to NOVA</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden;">
        <tr>
            <td style="padding:32px 32px 24px 32px; background:#ffffff;">
                <h1 style="margin:0 0 16px; font-size:24px; font-weight:700; color:#0f172a; letter-spacing:-0.025em;">Welcome to NOVA</h1>
                <p style="margin:0 0 24px; font-size:16px; line-height:1.6; color:#475569;">
                    Hi {{ $name }},
                    <br><br>
                    We are delighted to welcome you to the NOVA team. Your account has been successfully created and is ready for use.
                </p>
                
                <p style="margin:0 0 16px; font-size:16px; line-height:1.6; color:#475569;">
                    To get started, please log in using the temporary credentials below. For your security, we recommend updating your password immediately after your first login.
                </p>

                <div style="margin:24px 0; padding:20px; border-radius:8px; background:#f8fafc; border:1px solid #e2e8f0;">
                    <p style="margin:0 0 8px; font-size:14px; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; font-weight:600;">Your Credentials</p>
                    <p style="margin:0 0 8px; font-family:monospace; font-size:16px; color:#0f172a;">
                        <span style="color:#475569;">Username:</span> <strong>{{ $email }}</strong>
                    </p>
                    <p style="margin:0; font-family:monospace; font-size:16px; color:#0f172a;">
                        <span style="color:#475569;">Password:</span> <strong>{{ $plainPassword }}</strong>
                    </p>
                </div>

                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding-bottom:24px;">
                            <a href="{{ config('app.url') }}/login"
                                style="display:inline-block; background:linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color:#ffffff; font-weight:600; text-decoration:none; padding:12px 24px; border-radius:8px; font-size:16px; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.1), 0 2px 4px -1px rgba(79, 70, 229, 0.06);">
                                Log In to Your Account
                            </a>
                        </td>
                    </tr>
                </table>

                <p style="margin:24px 0 0; font-size:15px; line-height:1.6; color:#475569;">
                    If you have any questions or need assistance getting set up, please don't hesitate to reply to this email or contact our support team.
                </p>

                <p style="margin:24px 0 0; font-size:15px; color:#475569;">
                    Best regards,<br>
                    <strong style="color:#0f172a;">The NOVA Team</strong>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 32px; background:#f1f5f9; border-top:1px solid #e2e8f0; color:#64748b; font-size:12px; text-align:center; line-height:1.5;">
                &copy; {{ date('Y') }} NOVA - Neuroaffirming Operations & Virtual Administration.<br>
                All rights reserved.
            </td>
        </tr>
    </table>
</body>

</html>
