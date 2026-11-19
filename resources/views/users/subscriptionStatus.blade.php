<dl class="mt-6 grid gap-4 sm:grid-cols-2">
    @foreach ([
        'Plan' => $customer->subscription_plan ?: '7-Day Free Trial',
        'Status' => $customer->hasLifetimeAccess() ? 'Lifetime Access' : ($customer->hasActiveSubscription() ? 'Active' : 'Expired'),
        'Trial Ends At' => $customer->trial_ends_at ? \Illuminate\Support\Carbon::parse($customer->trial_ends_at)->format('d M Y, H:i') : '—',
        'Subscription Ends At' => $customer->hasLifetimeAccess() ? 'Lifetime Access' : ($customer->subscription_ends_at?->copy()->timezone(config('services.subscriptions.expiry_timezone'))->format('d M Y') ?? '—'),
        'Subscription Starts At' => $customer->subscription_starts_at?->format('d M Y, H:i') ?? '—',
        'Subscription Source' => $customer->hasLifetimeAccess() || $customer->admin_free_days > 0 || $customerSubscriptions->first()?->gateway === 'admin' ? 'Admin Granted' : ($customerSubscriptions->isNotEmpty() ? 'Paid' : 'None'),
        'Additional Free Days' => $customer->hasLifetimeAccess() ? 'Lifetime Access' : (int) $customer->admin_free_days,
    ] as $label => $value)
        <div class="rounded-xl border border-[rgb(var(--border))] p-4 dark:border-[rgb(var(--border))]"><dt class="text-xs text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">{{ __($label) }}</dt><dd class="mt-2 text-sm font-semibold text-[rgb(var(--text-main))] dark:text-white">{{ $value }}</dd></div>
    @endforeach
</dl>
@foreach ($customerSubscriptions as $subscription)
    <div class="mt-5 rounded-xl border border-[rgb(var(--border))] p-5 dark:border-[rgb(var(--border))]">
        <h2 class="font-bold">{{ $subscription->subscriptionPlan?->label ?? 'Subscription' }}</h2>
        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
            <div><dt>{{ __('Status') }}</dt><dd>{{ ucfirst(str_replace('_', ' ', $subscription->status)) }}</dd></div>
            <div><dt>{{ __('Subscription source') }}</dt><dd>{{ $subscription->gateway === 'admin' ? 'Admin Granted' : 'Paid · '.$subscription->gateway }}</dd></div>
            <div><dt>{{ __('Billing period starts') }}</dt><dd>{{ $customer->subscription_starts_at?->format('d M Y H:i') ?? '—' }}</dd></div>
            <div><dt>{{ __('Access ends') }}</dt><dd>{{ $customer->hasLifetimeAccess() ? 'Lifetime Access' : ($customer->subscription_ends_at?->copy()->timezone(config('services.subscriptions.expiry_timezone'))->format('d M Y') ?? '—') }}</dd></div>
            <div><dt>{{ __('Next renewal') }}</dt><dd>{{ $subscription->next_billing_at?->format('d M Y H:i') ?? '—' }}</dd></div>
            <div><dt>{{ __('AutoPay') }}</dt><dd>{{ __($subscription->auto_renew ? 'Enabled' : 'Disabled') }}</dd></div>
        </dl>
        @if ($subscription->auto_renew || $subscription->status === 'cancellation_pending')
            <form method="POST" action="{{ route('subscriptions.cancel', $subscription) }}" class="mt-4">
                @csrf
                <button type="submit" class="rounded-lg bg-[var(--surface-red-700)] px-4 py-2 font-semibold text-white">{{ __($subscription->status === 'cancellation_pending' ? 'Check cancellation' : 'Cancel AutoPay') }}</button>
            </form>
        @endif
    </div>
@endforeach
<a href="{{ route('subscriptions') }}" class="mt-6 inline-flex rounded-xl bg-[var(--surface-heading)] px-5 py-3 text-sm font-bold text-white">{{ __('View Subscription Plans') }}</a>
