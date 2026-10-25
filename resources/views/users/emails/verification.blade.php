{{-- resources/views/emails/verification-code.blade.php --}}

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email Verification Code</title>
</head>
<body style="margin:0; padding:0; background:#f5f5f5; font-family:Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5; padding:30px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:10px; overflow:hidden;">
                    <tr>
                        <td style="padding:30px; text-align:center; background:#111827; color:#ffffff;">
                            <h1 style="margin:0; font-size:24px;">Email Verification</h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:35px 30px; color:#333333;">
                            <p style="font-size:16px; margin:0 0 15px;">
                                Hello {{ $user['name'].' '.$user['surname'] ?? 'User' }},
                            </p>

                            <p style="font-size:16px; line-height:1.6; margin:0 0 25px;">
                                Please use the verification code below to verify your email address.
                            </p>

                            <div style="text-align:center; margin:30px 0;">
                                <span style="display:inline-block; padding:15px 30px; background:#f3f4f6; color:#111827; font-size:32px; font-weight:bold; letter-spacing:8px; border-radius:8px;">
                                    {{ $user['code'] }}
                                </span>
                            </div>

                            <p style="font-size:14px; line-height:1.6; color:#666; margin:0;">
                                If you did not request this verification, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 30px; text-align:center; background:#f9fafb; color:#888; font-size:13px;">
                            © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
