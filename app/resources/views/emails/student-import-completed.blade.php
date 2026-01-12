<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Import Completed</title>
</head>

<body style="font-family: 'Inter', ui-sans-serif, system-ui; background:#f5f7fb; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0"
        style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0;">
        <tr>
            <td style="padding:28px 28px 12px 28px;">
                <h1 style="margin:0; font-size:22px; color:#0f172a;">Student Import Completed</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 28px 0 28px; line-height:1.5; color:#475569;">
                <p style="margin:0 0 16px;">
                    Your student import (Import #{{ $import->id }}) has been completed.
                </p>

                <div
                    style="margin:20px 0; padding:16px; border-radius:10px; background:#f0f9ff; border:1px solid #bae6fd;">
                    <h2 style="margin:0 0 12px; font-size:16px; color:#0369a1;">Import Summary</h2>
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="padding:4px 0; color:#0369a1;">Total Rows:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600; color:#0369a1;">
                                {{ $stats['total'] }}</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0; color:#0369a1;">Successfully Imported:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600; color:#10b981;">
                                {{ $stats['success'] }}</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0; color:#0369a1;">Duplicates Skipped:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600; color:#f59e0b;">
                                {{ $stats['duplicates'] }}</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 0; color:#0369a1;">Errors:</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600; color:#ef4444;">
                                {{ $stats['errors'] }}</td>
                        </tr>
                    </table>
                </div>

                <p style="margin:16px 0;">
                    <strong>Import Type:</strong> {{ $import->type->value }}<br>
                    <strong>File:</strong> {{ $import->file_name }}<br>
                    <strong>Status:</strong> {{ strtoupper($import->status->value) }}
                </p>

                <a href="{{ config('app.url') }}/admin/students/imports/{{ $import->id }}"
                    style="display:inline-block; background:linear-gradient(135deg, #5563b8 0%, #a855f7 100%); color:#ffffff; text-decoration:none; padding:12px 20px; border-radius:999px; font-weight:600; margin:16px 0;">
                    View Import Details
                </a>

                @if ($stats['errors'] > 0)
                    <p style="margin:16px 0 0; color:#ef4444;">
                        <strong>Note:</strong> Some rows had validation errors. Please review the import details to see
                        specific error messages.
                    </p>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px; color:#94a3b8; font-size:12px; text-align:center;">
                &copy; {{ date('Y') }} NOVA - Neuroaffirming Operations & Virtual Administration.
            </td>
        </tr>
    </table>
</body>

</html>
