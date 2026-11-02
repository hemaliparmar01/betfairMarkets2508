<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($code) ? ($purpose === 'login' ? __('Your BF Markets login code') : __('Verify your BF Markets account')) : 'Reset your BF Markets password' }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0b2a40;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:12px;">
                    <tr>
                        <td style="padding:24px 28px;background-color:#0b2a40;border-radius:12px 12px 0 0;color:#ffffff;font-size:24px;font-weight:bold;">BF <span style="color:#f3b719;">Markets</span></td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 20px;font-size:24px;line-height:1.3;">{{ isset($code) ? ($purpose === 'login' ? __('Your BF Markets login code') : __('Verify your BF Markets account')) : 'Reset your password' }}</h1>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">Hello {{ $customer->name }},</p>
                            @if (isset($code))
                                <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#475569;">{{ $purpose === 'login' ? __('Use the code below to sign in to your BF Markets account.') : __('Use the code below to verify your BF Markets account.') }}</p>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                                    <tr>
                                        <td align="center" style="padding:20px;background-color:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;font-family:Consolas,monospace;font-size:32px;font-weight:bold;letter-spacing:6px;color:#0b2a40;">{{ $code }}</td>
                                    </tr>
                                </table>
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#475569;">{{ __('This code expires in 10 minutes.') }}</p>
                                <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#475569;">{{ __('If you did not request this, ignore this email. Never share your verification code with anyone.') }}</p>
                            @else
                            <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#475569;">We received a request to reset the password for your BF Markets account. Click the button below to choose a new password.</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                                <tr>
                                    <td align="center" bgcolor="#0b2a40" style="border-radius:6px;">
                                        <a href="{{ $url }}" style="display:inline-block;padding:14px 28px;border:1px solid #0b2a40;border-radius:6px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:bold;">Reset Password</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#475569;">This link expires in <strong>60 minutes</strong>.</p>
                            <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#475569;">If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged.</p>
                            @endif
                            <p style="margin:0;font-size:14px;line-height:1.6;">Regards,<br><strong>The BF Markets Team</strong></p>
                        </td>
                    </tr>
                    @if (isset($url))
                    <tr>
                        <td style="padding:20px 28px;border-top:1px solid #e2e8f0;">
                            <p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#64748b;">If the button does not work, copy and paste this link into your browser:</p>
                            <p style="margin:0;font-size:12px;line-height:1.6;word-break:break-all;overflow-wrap:anywhere;"><a href="{{ $url }}" style="color:#1d6b94;text-decoration:underline;">{{ $url }}</a></p>
                        </td>
                    </tr>
                    @endif
                </table>
                <p style="margin:20px 0 0;font-size:12px;color:#64748b;">BF Markets &middot; Account Security</p>
            </td>
        </tr>
    </table>
</body>
</html>
