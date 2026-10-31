<p><strong>{{ __('New contact message received.') }}</strong></p>

<p><strong>{{ __('Name:') }}</strong> {{ $contactMessage->name }}</p>
<p><strong>{{ __('Email:') }}</strong> {{ $contactMessage->email }}</p>
<p><strong>{{ __('Subject:') }}</strong> {{ $contactMessage->subject }}</p>

<p><strong>{{ __('Message:') }}</strong></p>
<p>{!! nl2br(e($contactMessage->message)) !!}</p>
