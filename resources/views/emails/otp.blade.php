<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Urban verification code</title>
</head>
<body style="margin:0;padding:0;background:#F1F5F9;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1F5F9;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;background:#FFFFFF;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="background:#4F46E5;padding:28px 32px;">
                            <span style="color:#FFFFFF;font-size:20px;font-weight:700;letter-spacing:-0.3px;">Urban</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 8px;color:#0F172A;font-size:18px;font-weight:700;">Verify your email</p>
                            <p style="margin:0 0 24px;color:#475569;font-size:14px;line-height:22px;">
                                Enter this code to finish setting up your Urban account.
                            </p>
                            <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;padding:20px;text-align:center;">
                                <span style="display:inline-block;color:#4F46E5;font-size:34px;font-weight:700;letter-spacing:8px;">{{ $code }}</span>
                            </div>
                            <p style="margin:20px 0 0;color:#64748B;font-size:13px;line-height:20px;">
                                This code expires in {{ $expiresInMinutes }} minutes. If you didn't request it, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background:#F8FAFC;border-top:1px solid #E2E8F0;">
                            <p style="margin:0;color:#94A3B8;font-size:12px;">Urban Services &middot; Never share this code with anyone.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
