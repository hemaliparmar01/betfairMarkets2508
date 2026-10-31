@extends('users.layout.main')

@section('title', __('My Profile · BF Markets'))

@section('content')
    <section class="my-6 grid items-start gap-5 lg:grid-cols-[230px_minmax(0,1fr)]">
        <aside class="rounded-2xl border border-[rgb(var(--border))] bg-[var(--surface-muted)] p-4 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))]">
            <p class="mb-4 px-3 text-xs font-bold uppercase tracking-wider text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">{{ __('My Account') }}</p>
            <nav aria-label="{{ __('Account menu') }}" class="flex flex-wrap gap-2 lg:flex-col">
                @foreach (['profile' => ['My Profile', 'fa-user'], 'favorites' => ['Favorites', 'fa-star'], 'subscription' => ['Subscription Details', 'fa-credit-card']] as $key => [$menu, $icon])
                    <a href="{{ route('customers.profile', $key === 'profile' ? [] : ['section' => $key]) }}" @if ($section === $key) aria-current="page" @endif @class(['flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold', 'bg-[var(--surface-heading)] text-white' => $section === $key, 'text-[var(--text-brand-50)] hover:bg-[var(--surface-neutral-0-6)] dark:text-[rgb(var(--text-soft))] dark:hover:bg-[rgb(var(--surface))]' => $section !== $key])><i class="fas {{ $icon }} w-4" aria-hidden="true"></i>{{ $menu }}</a>
                @endforeach
                <a href="{{ route('notifications') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold text-[var(--text-brand-50)] hover:bg-[var(--surface-neutral-0-6)] dark:text-[rgb(var(--text-soft))] dark:hover:bg-[rgb(var(--surface))]"><i class="fas fa-bell w-4" aria-hidden="true"></i>{{ __('Notifications') }}</a>
                <form action="{{ route('logout') }}" method="post">@csrf<button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold text-[var(--text-red-600)] hover:bg-[var(--surface-red-50)] dark:text-[var(--text-red-300)] dark:hover:bg-red-950"><i class="fas fa-right-from-bracket w-4" aria-hidden="true"></i>{{ __('Logout') }}</button></form>
            </nav>
        </aside>
        <div class="min-w-0 rounded-3xl border border-[rgb(var(--border))] bg-[rgb(var(--surface))] p-5 sm:p-8 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))]">
            <h1 class="text-2xl font-extrabold text-[rgb(var(--text-main))] dark:text-white">{{ ['profile' => 'My Profile', 'favorites' => 'Favorites', 'subscription' => 'Subscription Details'][$section] }}</h1>
            @if (session('status'))<p role="status" class="mt-4 rounded-xl bg-[var(--surface-emerald-50)] p-3 text-sm text-[var(--text-emerald-700)] dark:bg-emerald-950 dark:text-[var(--text-emerald-200)]">{{ session('status') }}</p>@endif
            @if ($section === 'favorites')
                <div class="mt-6 space-y-3">
                    @forelse ($favorites as $favorite)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[rgb(var(--border))] p-4 dark:border-[rgb(var(--border))]">
                            <div class="min-w-0"><p class="break-words font-semibold text-[rgb(var(--text-main))] dark:text-white">{{ $favorite->team1 ?: 'Team A' }} {{ __('vs') }} {{ $favorite->team2 ?: 'Team B' }}</p><p class="mt-1 text-xs capitalize text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">{{ $favorite->sport }}</p></div>
                            @if (in_array(strtolower($favorite->sport), ['football', 'icehockey', 'americanfootball', 'baseball', 'basketball', 'tennis', 'greyhounds', 'horseracing'], true) && $favorite->event_id)
                                <a href="{{ route($favorite->sport.'.matches.show', $favorite->event_id) }}" class="rounded-lg bg-[var(--surface-heading)] px-4 py-2 text-sm font-semibold text-white">{{ __('Open Match →') }}</a>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">{{ __('You haven\'t added any favorites yet. Use the star next to a match to save it.') }}</p>
                    @endforelse
                    {{ $favorites->links() }}
                </div>
            @elseif ($section === 'subscription')
                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'Plan' => $customer->subscription_plan ?: '7-Day Free Trial',
                        'Status' => $customer->hasLifetimeAccess() ? 'Lifetime Access' : ($customer->hasActiveSubscription() ? 'Active' : 'Expired'),
                        'Trial Ends At' => $customer->trial_ends_at ? \Illuminate\Support\Carbon::parse($customer->trial_ends_at)->format('d M Y, H:i') : '—',
                        'Subscription Ends At' => $customer->hasLifetimeAccess() ? 'Lifetime Access' : ($customer->subscription_ends_at?->format('d M Y, H:i') ?? '—'),
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
                            <div><dt>{{ __('Access ends') }}</dt><dd>{{ $customer->hasLifetimeAccess() ? 'Lifetime Access' : ($customer->subscription_ends_at?->format('d M Y H:i') ?? '—') }}</dd></div>
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
            @else
                <p class="mt-2 text-sm text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">{{ __('Update your personal details and profile photo. Username, email and mobile number cannot be changed.') }}</p>
                <form action="{{ route('customers.profile.update') }}" method="post" enctype="multipart/form-data" class="mt-6">
                    @csrf
                    @method('PUT')
                    <div class="mb-6 flex flex-wrap items-center gap-4">
                        <div class="relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[var(--surface-neutral-0-7)] text-2xl font-bold text-[rgb(var(--primary))] dark:bg-[rgb(var(--bg))]">
                            <span aria-hidden="true">{{ mb_strtoupper(mb_substr($customer->name, 0, 1)) }}</span>
                            <img id="profile-avatar-preview" @if ($customer->avatar_path) src="{{ route('customers.avatar') }}" @endif alt="{{ __('Your profile photo') }}" @class(['absolute inset-0 size-full h-full! object-cover', 'hidden' => ! $customer->avatar_path]) />
                        </div>
                        <div class="min-w-0 flex-1">
                            <label for="profile-avatar" class="mb-2 block text-sm font-semibold text-[rgb(var(--text-main))] dark:text-white">{{ __('Profile image') }}</label>
                            <input id="profile-avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="max-w-full text-sm text-[rgb(var(--text-muted))] file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--surface-neutral-0-7)] file:px-3 file:py-2 file:font-semibold file:text-[rgb(var(--primary))] dark:text-[rgb(var(--text-soft))]" />
                            <p class="mt-2 text-xs text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">{{ __('JPG, PNG or WebP. Maximum 2 MB and 4096 × 4096 pixels.') }}</p>
                            @error('avatar') <p role="alert" class="mt-2 text-xs text-[var(--text-red-600)] dark:text-[var(--text-red-300)]">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach (['name' => 'Name', 'surname' => 'Surname'] as $field => $label)
                            <div><label for="profile-{{ $field }}" class="mb-2 block text-sm font-semibold text-[rgb(var(--text-main))] dark:text-white">{{ __($label) }}</label><input id="profile-{{ $field }}" name="{{ $field }}" value="{{ old($field, $customer->{$field}) }}" required maxlength="100" type="text" class="h-12 w-full rounded-xl border border-[var(--border-brand-5-3)] bg-[var(--surface-muted)] px-4 text-sm text-[rgb(var(--text-main))] outline-none focus:border-[var(--border-primary)] focus:ring-4 focus:ring-[var(--border-primary)]/10 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-white" />@error($field)<p role="alert" class="mt-2 text-xs text-[var(--text-red-600)] dark:text-[var(--text-red-300)]">{{ $message }}</p>@enderror</div>
                        @endforeach
                        <div><label for="profile-username" class="mb-2 block text-sm font-semibold text-[rgb(var(--text-main))] dark:text-white">{{ __('Username') }}</label><input id="profile-username" type="text" value="{{ $customer->username }}" disabled class="h-12 w-full cursor-not-allowed rounded-xl border border-[var(--border-brand-5-3)] bg-[var(--surface-muted)] px-4 text-sm text-[rgb(var(--text-main))] opacity-75 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-white" />@error('username')<p role="alert" class="mt-2 text-xs text-[var(--text-red-600)] dark:text-[var(--text-red-300)]">{{ $message }}</p>@enderror</div>
                        <div><label for="profile-email" class="mb-2 block text-sm font-semibold text-[rgb(var(--text-main))] dark:text-white">{{ __('Email Address') }}</label><input id="profile-email" type="email" value="{{ $customer->email }}" readonly class="h-12 w-full rounded-xl border border-[var(--border-brand-5-3)] bg-[var(--surface-muted)] px-4 text-sm text-[rgb(var(--text-main))] outline-none focus:border-[var(--border-primary)] focus:ring-4 focus:ring-[var(--border-primary)]/10 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-white cursor-not-allowed opacity-75" />@error('email')<p role="alert" class="mt-2 text-xs text-[var(--text-red-600)] dark:text-[var(--text-red-300)]">{{ $message }}</p>@enderror</div>
                        <div><label for="profile-mobile" class="mb-2 block text-sm font-semibold text-[rgb(var(--text-main))] dark:text-white">{{ __('Mobile Number') }}</label><input id="profile-mobile" type="text" value="+{{ $customer->mobile_code }} {{ $customer->mobile }}" readonly class="h-12 w-full rounded-xl border border-[var(--border-brand-5-3)] bg-[var(--surface-muted)] px-4 text-sm text-[rgb(var(--text-main))] outline-none focus:border-[var(--border-primary)] focus:ring-4 focus:ring-[var(--border-primary)]/10 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-white cursor-not-allowed opacity-75" />@error('mobile')<p role="alert" class="mt-2 text-xs text-[var(--text-red-600)] dark:text-[var(--text-red-300)]">{{ $message }}</p>@enderror @error('mobile_code')<p role="alert" class="mt-2 text-xs text-[var(--text-red-600)] dark:text-[var(--text-red-300)]">{{ $message }}</p>@enderror</div>
                        <div><label for="profile-country" class="mb-2 block text-sm font-semibold text-[rgb(var(--text-main))] dark:text-white">{{ __('Country') }}</label><select id="profile-country" name="country_code" class="h-12 w-full rounded-xl border border-[var(--border-brand-5-3)] bg-[var(--surface-muted)] px-4 text-sm text-[rgb(var(--text-main))] outline-none focus:border-[var(--border-primary)] focus:ring-4 focus:ring-[var(--border-primary)]/10 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-white">@foreach ($countries as $country)<option value="{{ $country['code'] }}" @selected($country['code'] === old('country_code', $customer->country_code))>{{ $country['flag'] }} {{ $country['name'] }}</option>@endforeach</select>@error('country_code')<p role="alert" class="mt-2 text-xs text-[var(--text-red-600)] dark:text-[var(--text-red-300)]">{{ $message }}</p>@enderror</div>
                        <div class="sm:col-span-2"><label for="profile-timezone" class="mb-2 block text-sm font-semibold text-[rgb(var(--text-main))] dark:text-white">{{ __('Timezone') }}</label><select id="profile-timezone" name="timezone" class="h-12 w-full rounded-xl border border-[var(--border-brand-5-3)] bg-[var(--surface-muted)] px-4 text-sm text-[rgb(var(--text-main))] outline-none focus:border-[var(--border-primary)] focus:ring-4 focus:ring-[var(--border-primary)]/10 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-white">@foreach ($timezones as $zone)<option value="{{ $zone }}" @selected($zone === old('timezone', $timezone))>{{ $zone }}</option>@endforeach</select>@error('timezone')<p role="alert" class="mt-2 text-xs text-[var(--text-red-600)] dark:text-[var(--text-red-300)]">{{ $message }}</p>@enderror</div>
                    </div>
                    <fieldset class="mt-7 border-t border-[rgb(var(--border))] pt-5 dark:border-[rgb(var(--border))]">
                        <legend class="px-2 text-sm font-bold text-[rgb(var(--text-main))] dark:text-white">{{ __('Change password (optional)') }}</legend>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2"><label for="profile-current-password" class="mb-2 block text-sm font-semibold text-[rgb(var(--text-main))] dark:text-white">{{ __('Current Password') }}</label><input id="profile-current-password" name="current_password" type="password" autocomplete="current-password" class="h-12 w-full rounded-xl border border-[var(--border-brand-5-3)] bg-[var(--surface-muted)] px-4 text-sm text-[rgb(var(--text-main))] outline-none focus:border-[var(--border-primary)] focus:ring-4 focus:ring-[var(--border-primary)]/10 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-white" />@error('current_password')<p role="alert" class="mt-2 text-xs text-[var(--text-red-600)] dark:text-[var(--text-red-300)]">{{ $message }}</p>@enderror</div>
                            <div><label for="profile-password" class="mb-2 block text-sm font-semibold text-[rgb(var(--text-main))] dark:text-white">{{ __('New Password') }}</label><input id="profile-password" name="password" type="password" autocomplete="new-password" class="h-12 w-full rounded-xl border border-[var(--border-brand-5-3)] bg-[var(--surface-muted)] px-4 text-sm text-[rgb(var(--text-main))] outline-none focus:border-[var(--border-primary)] focus:ring-4 focus:ring-[var(--border-primary)]/10 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-white" />@error('password')<p role="alert" class="mt-2 text-xs text-[var(--text-red-600)] dark:text-[var(--text-red-300)]">{{ $message }}</p>@enderror</div>
                            <div><label for="profile-password-confirmation" class="mb-2 block text-sm font-semibold text-[rgb(var(--text-main))] dark:text-white">{{ __('Confirm New Password') }}</label><input id="profile-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="h-12 w-full rounded-xl border border-[var(--border-brand-5-3)] bg-[var(--surface-muted)] px-4 text-sm text-[rgb(var(--text-main))] outline-none focus:border-[var(--border-primary)] focus:ring-4 focus:ring-[var(--border-primary)]/10 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-white" /></div>
                        </div>
                        <p class="mt-2 text-xs text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">{{ __('Use at least 8 characters with letters, numbers, and symbols. Changing your password requires logging in again.') }}</p>
                    </fieldset>
                    <button type="submit" class="mt-6 h-12 w-full rounded-xl bg-gradient-to-r from-[var(--surface-heading)] to-[var(--surface-primary)] px-6 text-sm font-bold text-white sm:w-auto">{{ __('Save Changes') }}</button>
                </form>
            @endif
        </div>
    </section>
@endsection

@if ($section === 'profile')
@push('js')
    <script>
        document.getElementById('profile-avatar').addEventListener('change', (event) => {
            const file = event.target.files[0];
            const preview = document.getElementById('profile-avatar-preview');
            if (preview.dataset.previewUrl) {
                URL.revokeObjectURL(preview.dataset.previewUrl);
                delete preview.dataset.previewUrl;
            }
            if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) return;
            const url = URL.createObjectURL(file);
            preview.dataset.previewUrl = url;
            preview.src = url;
            preview.classList.remove('hidden');
        });
    </script>
@endpush
@endif
