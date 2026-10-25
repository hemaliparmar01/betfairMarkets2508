<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Login Verification Code') }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;font-family:Arial,sans-serif;color:#111827;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f6f7fb;padding:32px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:28px 32px;text-align:center;background:#ffb300;color:#111827;">
                            <h1 style="margin:0;font-size:24px;font-weight:800;">
                                {{ __('Login Verification') }}
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
                                {{ __('Hello,') }}
                            </p>

                            <p style="margin:0 0 22px;font-size:16px;line-height:1.6;">
                                {{ __('Use the verification code below to complete your login.') }}
                            </p>

                            <div style="margin:28px 0;text-align:center;">
                                <div style="display:inline-block;padding:18px 30px;border-radius:14px;background:#fff8dc;border:1px solid #f2d675;font-size:34px;font-weight:800;letter-spacing:8px;color:#111827;">
                                    {{ $otp }}
                                </div>
                            </div>

                            <p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#6b7280;">
                                {{ __('This code is valid for a limited time. Please do not share it with anyone.') }}
                            </p>

                            <p style="margin:0;font-size:14px;line-height:1.6;color:#6b7280;">
                                {{ __('If you did not request this login code, you can safely ignore this email.') }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 32px;text-align:center;background:#f9fafb;color:#6b7280;font-size:13px;">
                            {{ config('app.name') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
