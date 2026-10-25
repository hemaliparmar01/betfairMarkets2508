<p><strong>New feature request received.</strong></p>

<p><strong>User Name:</strong> {{ $featureRequest->email }}</p>

<p><strong>Message:</strong></p>
<p>{!! nl2br(e($featureRequest->message)) !!}</p>
