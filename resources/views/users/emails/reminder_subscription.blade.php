<p>Hello {{ $allSubscription['customers']['name'].' '.$allSubscription['customers']['surname'] }},</p>

<p>This is a friendly reminder that your subscription payment is due in {{ $allSubscription['subscription_plan']['renewal_reminder_days_before'] }} days.</p>

<p>To ensure uninterrupted access to {{ $siteName }}, please make sure that your payment method is valid and that sufficient funds are available.</p>

<p>If your subscription is set to renew automatically, no further action is required.</p>

<p>Thank you for choosing {{ $siteName }}.</p>

<p>Best regards,<br>
{{ $siteName }} Team</p>
