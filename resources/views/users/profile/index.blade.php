@extends('users.layouts.app', ['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('home.en'), 'moneywayUrl' => route('football.moneyway.page')])

@section('title', 'My Profile | BF Markets')
@section('meta_description',
    'Manage your BF Markets profile, favorite matches, subscription plan, billing history, and
    auto-renewal settings.')

@section('head')
    <link rel="canonical" href="{{ url()->current() }}">
@endsection

@section('content')
    @php
        $remainingDays = 0;

        if ($currentSubscription?->ends_at) {
            $remainingDays = max(now()->diffInDays($currentSubscription->ends_at, false), 0);
        }

        $totalDays =
            $currentSubscription?->starts_at && $currentSubscription?->ends_at
                ? max($currentSubscription->starts_at->diffInDays($currentSubscription->ends_at), 1)
                : 7;

        $progress = $currentSubscription ? max(0, min(100, ($remainingDays / $totalDays) * 100)) : 0;

        $currentPlanName = $currentSubscription?->subscriptionPlan?->label ?? __('Plan');
        $currentGateway = strtolower((string) ($currentSubscription?->gateway ?? ''));
        $currentGatewayLabel = match ($currentGateway) {
            'paypal' => 'PayPal',
            'bog' => 'Bank of Georgia',
            'tbc' => 'TBC Bank',
            'admin' => __('Admin Manual'),
            default => __('Payment Gateway'),
        };
        $renewableGateway = strtolower((string) ($renewableSubscription?->gateway ?? $currentGateway));
        $renewableGatewayLabel = match ($renewableGateway) {
            'paypal' => 'PayPal',
            'bog' => 'Bank of Georgia',
            'tbc' => 'TBC Bank',
            'admin' => __('Admin Manual'),
            default => $currentGatewayLabel,
        };
        $manageableSubscription = $renewableSubscription ?? $currentSubscription;
        $manageableGateway = strtolower((string) ($manageableSubscription?->gateway ?? $renewableGateway));
        $manageableGatewayLabel = match ($manageableGateway) {
            'paypal' => 'PayPal',
            'bog' => 'Bank of Georgia',
            'tbc' => 'TBC Bank',
            'admin' => __('Admin Manual'),
            default => $renewableGatewayLabel,
        };
        $manageableReference = trim((string) ($manageableSubscription?->gateway_subscription_id ?? ''));
        $hasSavedCard = $manageableReference !== '';
        $maskedManageableReference =
            $manageableReference !== ''
                ? (strlen($manageableReference) > 18
                    ? substr($manageableReference, 0, 10) . '...' . substr($manageableReference, -6)
                    : $manageableReference)
                : __('No saved card added yet');
        $manageablePlan = $manageableSubscription?->subscriptionPlan;
        $manageableBillingLine =
            $manageableSubscription && $manageablePlan
                ? __(':amount :currency every :days days', [
                    'amount' => number_format((float) ($manageableSubscription->amount ?? 0), 2),
                    'currency' => $manageableSubscription->currency ?? '',
                    'days' => $manageablePlan->duration_days,
                ])
                : __('Billing frequency will appear after you save a card');
        $savedCardStatusLabel =
            $renewableSubscription && $hasSavedCard ? __('Saved') : ($hasSavedCard ? __('Removed') : __('Not Added'));
        $savedCardStatusClasses =
            $renewableSubscription && $hasSavedCard
                ? 'theme-badge-status-success'
                : ($hasSavedCard
                    ? 'theme-badge-status-warning'
                    : 'theme-badge-status-neutral');
        $savedCardHelperText =
            $renewableSubscription && $hasSavedCard
                ? __(
                    'This saved card is active and may be used for the next scheduled charge unless you remove it below.',
                )
                : ($hasSavedCard
                    ? __(
                        'This saved card has already been removed. Start a new secure checkout if you want to save a card again.',
                    )
                    : __(
                        'No saved card is linked yet. Complete a recurring checkout to save a card securely with the payment gateway.',
                    ));
        $savedCardActionPlanId = $currentSubscription?->subscription_id ?? $manageablePlan?->id;
        $savedCardActionUrl = $savedCardActionPlanId
            ? route('checkout.show', ['plan' => $savedCardActionPlanId])
            : route('checkout.show');
        $currentAccessEndLabel = $currentSubscription?->ends_at?->format('d M Y') ?? __('your subscription end date');
        $hasActiveAccess = $currentSubscription?->ends_at && now()->lt($currentSubscription->ends_at);
        $savedCardsCount = $savedCards->count() + $savedBogCards->count() + $savedPaypalMethods->count();
        $statusLabel = $currentSubscription
            ? ($currentSubscription->status === 'cancelled' && $hasActiveAccess
                ? __('Access Active')
                : __(ucfirst(str_replace('_', ' ', (string) $currentSubscription->status))))
            : __('No Active Plan');

        $pageHeading = match ($activeTab) {
            'favorites' => __('Favorite Matches'),
            'subscriptions' => __('Subscriptions'),
            'savedcards' => __('Saved Cards'),
            default => __('Profile Settings'),
        };

        $pageDescription = match ($activeTab) {
            'favorites' => __('View all match favorites saved in the database for this user account.'),
            'subscriptions' => __(
                'Manage your current plan, billing gateway, recurring card details, and subscription history.',
            ),
            'savedcards' => __('View and remove the saved payment methods linked to your recurring subscriptions.'),
            default => __('View and update your profile details, password, and profile picture from one place.'),
        };

        $profileTabs = [
            [
                'key' => 'settings',
                'label' => __('Profile Settings'),
                'url' => route('customer.profile'),
                'count' => null,
            ],
            [
                'key' => 'favorites',
                'label' => __('Favorite Matches'),
                'url' => route('customer.profile.favorites'),
                'count' => $favoriteMatches->total(),
            ],
            [
                'key' => 'savedcards',
                'label' => __('Saved Cards'),
                'url' => route('customer.profile.saved-cards'),
                'count' => $savedCardsCount,
            ],
            [
                'key' => 'subscriptions',
                'label' => __('Subscriptions'),
                'url' => route('customer.profile.subscriptions'),
                'count' => $subscriptionHistory->total(),
            ],
        ];
    @endphp

    <div class="betguru-auth-bg min-h-screen" data-profile-payments
        data-fitt-add-url="{{ route('fitt.cards.add') }}"
        data-bog-add-url="{{ route('bog.cards.add') }}"
        data-paypal-client-token-url="{{ route('paypal.payment-methods.client-token') }}"
        data-paypal-setup-token-url="{{ route('paypal.payment-methods.setup-token') }}"
        data-paypal-store-url="{{ route('paypal.payment-methods.store') }}">
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                @if (session('success'))
                    <div class="mb-5 rounded-2xl px-4 py-3 text-sm font-semibold theme-alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-5 rounded-2xl px-4 py-3 text-sm font-semibold theme-chip-danger">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">
                    <aside class="self-start rounded-[2rem] p-6 betguru-card-elevated lg:sticky lg:top-28">
                        <div class="flex items-center gap-4">
                            @if ($customer->avatar_path)
                                <img src="{{ asset('storage/' . $customer->avatar_path) }}" alt="{{ $customer->username }}"
                                    class="h-20 w-20 rounded-full object-cover">
                            @else
                                <div
                                    class="flex h-20 w-20 items-center justify-center rounded-full text-2xl font-bold text-[rgb(var(--surface))] betguru-gradient-fill">
                                    {{ $customer->initials }}
                                </div>
                            @endif

                            <div class="min-w-0">
                                <p class="truncate text-xl font-bold theme-text-main">
                                    {{ $customer->name }} {{ $customer->surname }}
                                </p>
                                <p class="mt-1 truncate text-sm theme-text-muted">
                                    {{ $customer->username }}
                                </p>
                                <p class="mt-1 truncate text-sm theme-text-muted">
                                    {{ $customer->email }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 rounded-[1.75rem] p-4 theme-card-frost">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                {{ __('Quick Info') }}
                            </p>

                            <div class="mt-4 space-y-3">
                                <div class="rounded-2xl p-4 betguru-card-soft">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                        {{ __('Mobile') }}
                                    </p>
                                    <p class="mt-2 font-semibold theme-text-main">
                                        {{ $customer->mobile ?: __('Not provided') }}
                                    </p>
                                </div>

                                <div class="rounded-2xl p-4 betguru-card-soft">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                        {{ __('Country') }}
                                    </p>
                                    <p class="mt-2 font-semibold theme-text-main">
                                        {{ $customer->country ?: __('Not provided') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <nav class="mt-6 space-y-3">
                            @foreach ($profileTabs as $profileTab)
                                @php
                                    $isActiveTab = $activeTab === $profileTab['key'];
                                @endphp

                                <a href="{{ $profileTab['url'] }}"
                                    class="flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-semibold transition {{ $isActiveTab ? 'text-[rgb(var(--surface))] shadow-sm betguru-gradient-cta-flat' : 'theme-panel-subtle theme-text-main' }}">
                                    <span>{{ $profileTab['label'] }}</span>

                                    @if ($profileTab['count'] !== null)
                                        <span
                                            class="rounded-full px-2.5 py-1 text-xs font-bold {{ $isActiveTab ? 'theme-badge-inverse-soft' : 'theme-badge-surface' }}">
                                            {{ $profileTab['count'] }}
                                        </span>
                                    @endif
                                </a>
                            @endforeach

                            <form method="POST" action="{{ route('customer.logout') }}">
                                @csrf

                                <button type="submit"
                                    class="theme-button-danger-soft flex w-full items-center justify-between rounded-2xl px-4 py-3 text-sm font-semibold transition">
                                    <span>{{ __('Logout') }}</span>
                                    <span class="text-xs font-bold">↗</span>
                                </button>
                            </form>
                        </nav>
                    </aside>

                    <div class="space-y-6">
                        <section class="rounded-[2rem] p-6 betguru-card-elevated">
                            <h1 class="text-3xl font-bold theme-text-main">
                                {{ $pageHeading }}
                            </h1>
                            <p class="mt-2 text-sm theme-text-muted">
                                {{ $pageDescription }}
                            </p>
                        </section>

                        @if ($activeTab === 'settings')
                            <section data-customer-form
                                data-selected-country="{{ old('country', $customer->country) }}"
                                data-selected-dial-code="{{ old('mobile_code', $customer->mobile_code ?: '+995') }}"
                                data-selected-country-code="{{ old('country_code', $customer->country_code ?: 'GE') }}"
                                data-default-timezone="{{ old('timezone', $customer->timezone) }}"
                                class="rounded-[2rem] p-6 betguru-card-elevated">
                                <template data-country-config>{!! json_encode($countryOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</template>
                                {{-- <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-2xl font-bold theme-text-main">
                                            {{ __('Profile Details') }}
                                        </p>
                                        <p class="mt-2 text-sm leading-6 theme-text-muted">
                                            {{ __('Update your name, username, email address, password, and profile picture here. Leave the password blank if you do not want to change it.') }}
                                        </p>
                                    </div>

                                    <a
                                        href="{{ route('customer.profile.subscriptions') }}"
                                        class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-[rgb(var(--surface))] betguru-gradient-cta-flat"
                                    >
                                        {{ __('Open Subscriptions') }}
                                    </a>
                                </div> --}}

                                <form method="POST" action="{{ route('customer.profile.update') }}"
                                    enctype="multipart/form-data" data-customer-submit class="mt-6 space-y-6">
                                    @csrf

                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                                        <div class="rounded-[1.75rem] p-5 theme-card-frost">
                                            <p class="text-sm font-semibold theme-text-main">
                                                {{ __('Profile Picture') }}
                                            </p>
                                            <p class="mt-2 text-sm leading-6 theme-text-muted">
                                                {{ __('Upload a clear profile photo. JPG, PNG, and WEBP files are supported.') }}
                                            </p>

                                            <div
                                                class="mt-5 flex flex-col items-center rounded-[1.5rem] p-5 betguru-card-soft">
                                                @if ($customer->avatar_path)
                                                    <img src="{{ asset('storage/' . $customer->avatar_path) }}"
                                                        alt="{{ $customer->username }}"
                                                        class="h-28 w-28 rounded-full object-cover shadow-sm">
                                                @else
                                                    <div
                                                        class="flex h-28 w-28 items-center justify-center rounded-full text-4xl font-bold text-[rgb(var(--surface))] betguru-gradient-fill">
                                                        {{ $customer->initials }}
                                                    </div>
                                                @endif

                                                <p class="mt-4 text-base font-semibold theme-text-main">
                                                    {{ trim($customer->name . ' ' . $customer->surname) ?: __('Customer') }}
                                                </p>
                                                <p class="mt-1 text-sm theme-text-muted">
                                                    {{ '@' . $customer->username }}
                                                </p>
                                            </div>

                                            <div class="mt-5">
                                                <label for="profile_picture"
                                                    class="betguru-label">{{ __('Choose New Picture') }}</label>
                                                <input id="profile_picture" type="file" name="profile_picture"
                                                    accept=".jpg,.jpeg,.png,.webp"
                                                    class="betguru-input file:mr-4 file:rounded-xl file:border-0 file:bg-[rgb(var(--primary))] file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-[rgb(var(--surface))]">
                                                @error('profile_picture')
                                                    <p class="mt-2 text-sm theme-text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="rounded-[1.75rem] p-5 theme-card-frost">
                                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                                <div>
                                                    <label for="profile_name"
                                                        class="betguru-label">{{ __('Name') }}</label>
                                                    <input id="profile_name" type="text" name="name"
                                                        value="{{ old('name', $customer->name) }}"
                                                        placeholder="{{ __('Enter first name') }}" class="betguru-input">
                                                    @error('name')
                                                        <p class="mt-2 text-sm theme-text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div>
                                                    <label for="profile_surname"
                                                        class="betguru-label">{{ __('Surname') }}</label>
                                                    <input id="profile_surname" type="text" name="surname"
                                                        value="{{ old('surname', $customer->surname) }}"
                                                        placeholder="{{ __('Enter surname') }}" class="betguru-input">
                                                    @error('surname')
                                                        <p class="mt-2 text-sm theme-text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                                <div>
                                                    <label for="profile_username"
                                                        class="betguru-label">{{ __('Username') }}</label>
                                                    <input id="profile_username" type="text" name="username"
                                                        value="{{ old('username', $customer->username) }}"
                                                        placeholder="{{ __('Enter username') }}" class="betguru-input">
                                                    @error('username')
                                                        <p class="mt-2 text-sm theme-text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div>
                                                    <label for="profile_email"
                                                        class="betguru-label">{{ __('Email Address') }}</label>
                                                    <input id="profile_email" type="email" name="email"
                                                        value="{{ old('email', $customer->email) }}"
                                                        placeholder="{{ __('Enter email address') }}"
                                                        class="betguru-input">
                                                    @error('email')
                                                        <p class="mt-2 text-sm theme-text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>

                                            {{-- <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2"> --}}
                                            <div class="mt-5">
                                                <label class="betguru-label">{{ __('Mobile Number') }}</label>

                                                <div
                                                    class="flex overflow-visible rounded-2xl border transition theme-panel-soft">
                                                    <div class="relative w-[250px] betguru-divider-input theme-bg-surface">
                                                        <input type="hidden" name="mobile_code">
                                                        <input type="hidden" name="country_code">

                                                        <button type="button" data-country-toggle="code" aria-expanded="false"
                                                            class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left">
                                                            <div class="flex min-w-0 items-center gap-3" data-country-selected="code">
                                                                <img data-country-selected-flag
                                                                    class="h-5 w-7 rounded-sm object-cover shadow-sm">

                                                                <div class="min-w-0">
                                                                    <p class="text-xs theme-text-muted" data-country-selected-label></p>
                                                                </div>
                                                            </div>

                                                            <svg class="h-4 w-4 shrink-0 theme-text-muted"
                                                                viewBox="0 0 20 20" fill="currentColor"
                                                                aria-hidden="true">
                                                                <path fill-rule="evenodd"
                                                                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z"
                                                                    clip-rule="evenodd" />
                                                            </svg>
                                                        </button>

                                                        <div data-country-dropdown="code" hidden
                                                            class="absolute left-0 top-[calc(100%+8px)] z-30 w-[360px] rounded-2xl border p-2 shadow-2xl theme-panel-surface">
                                                            <div class="mb-2">
                                                                <input type="text" data-country-search="code"
                                                                    placeholder="{{ __('Search country or code...') }}"
                                                                    class="betguru-input">
                                                            </div>

                                                            <div class="max-h-72 overflow-y-auto">
                                                                <div data-country-options="code"></div>
                                                                <p data-country-empty="code" hidden
                                                                    class="px-3 py-3 text-sm theme-text-muted">
                                                                    {{ __('No countries found.') }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <input type="text" name="mobile"
                                                        value="{{ old('mobile', $customer->mobile) }}"
                                                        placeholder="{{ __('Enter mobile number') }}" inputmode="numeric"
                                                        autocomplete="tel-national"
                                                        class="w-full bg-transparent px-4 py-3.5 text-sm outline-none theme-text-main">
                                                </div>

                                                @error('country_code')
                                                    <p class="mt-2 text-sm theme-text-danger">{{ $message }}</p>
                                                @enderror

                                                @error('mobile_code')
                                                    <p class="mt-2 text-sm theme-text-danger">{{ $message }}</p>
                                                @enderror

                                                @error('mobile')
                                                    <p class="mt-2 text-sm theme-text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="mt-5">
                                                <label class="betguru-label">{{ __('Country') }}</label>

                                                <div class="relative">
                                                    <input type="hidden" name="country">

                                                    <button type="button" data-country-toggle="country" aria-expanded="false"
                                                        class="flex w-full items-center justify-between rounded-2xl border px-4 py-3.5 text-left transition theme-panel-soft">
                                                        <div class="flex min-w-0 items-center gap-3" data-country-selected="country">
                                                            <img data-country-selected-flag
                                                                class="h-5 w-7 rounded-sm object-cover shadow-sm">

                                                            <span class="truncate text-sm font-semibold theme-text-main" data-country-selected-label></span>
                                                        </div>

                                                        <span data-country-placeholder="country" class="text-sm theme-text-muted" hidden>
                                                            {{ __('Select country') }}
                                                        </span>

                                                        <svg class="h-4 w-4 shrink-0 theme-text-muted" viewBox="0 0 20 20"
                                                            fill="currentColor" aria-hidden="true">
                                                            <path fill-rule="evenodd"
                                                                d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    </button>

                                                    <div data-country-dropdown="country" hidden
                                                        class="absolute left-0 top-[calc(100%+8px)] z-30 w-full rounded-2xl border p-2 shadow-2xl theme-panel-surface">
                                                        <div class="mb-2">
                                                            <input type="text" data-country-search="country"
                                                                placeholder="{{ __('Search country...') }}"
                                                                class="betguru-input">
                                                        </div>

                                                        <div class="max-h-72 overflow-y-auto">
                                                            <div data-country-options="country"></div>
                                                            <p data-country-empty="country" hidden
                                                                class="px-3 py-3 text-sm theme-text-muted">
                                                                {{ __('No countries found.') }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                                @error('country')
                                                    <p class="mt-2 text-sm theme-text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            {{-- </div> --}}

                                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                                <div>
                                                    <label for="profile_password"
                                                        class="betguru-label">{{ __('New Password') }}</label>
                                                    <input id="profile_password" type="password" name="password"
                                                        placeholder="{{ __('Enter new password') }}"
                                                        autocomplete="new-password" class="betguru-input">
                                                    @error('password')
                                                        <p class="mt-2 text-sm theme-text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div>
                                                    <label for="profile_password_confirmation"
                                                        class="betguru-label">{{ __('Confirm Password') }}</label>
                                                    <input id="profile_password_confirmation" type="password"
                                                        name="password_confirmation"
                                                        placeholder="{{ __('Confirm new password') }}"
                                                        autocomplete="new-password" class="betguru-input">
                                                </div>
                                            </div>

                                            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                                                <div>
                                                    <label for="profile_timezone"
                                                        class="betguru-label">{{ __('Timezone') }}</label>
                                                    <input id="profile_timezone" type="text" name="timezone"
                                                        placeholder="{{ __('Enter timezone') }}"
                                                        class="betguru-input">
                                                    @error('timezone')
                                                        <p class="mt-2 text-sm theme-text-danger">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                {{-- <div class="rounded-2xl p-4 betguru-card-soft">
                                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                        {{ __('Mobile') }}
                                                    </p>
                                                    <p class="mt-2 font-semibold theme-text-main">
                                                        {{ $customer->mobile ?: __('Not provided') }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl p-4 betguru-card-soft">
                                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                        {{ __('Country') }}
                                                    </p>
                                                    <p class="mt-2 font-semibold theme-text-main">
                                                        {{ $customer->country ?: __('Not provided') }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl p-4 betguru-card-soft">
                                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                        {{ __('Current Timezone') }}
                                                    </p>
                                                    <p class="mt-2 font-semibold theme-text-main">
                                                        {{ $customer->timezone ?: __('Not provided') }}
                                                    </p>
                                                </div> --}}
                                            </div>

                                            <div
                                                class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                <p class="text-sm leading-6 theme-text-muted">
                                                    {{ __('Your subscription and billing information remains available in the Subscriptions section from the sidebar.') }}
                                                </p>

                                                <button type="submit"
                                                    class="inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-semibold text-[rgb(var(--surface))] betguru-gradient-cta-flat">
                                                    {{ __('Update Profile') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </section>

                            <section class="rounded-[2rem] p-6 betguru-card-elevated">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="rounded-[1.75rem] p-5 theme-card-frost">
                                        <p class="text-sm font-semibold theme-text-main">
                                            {{ __('Current Details') }}
                                        </p>
                                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div class="rounded-2xl p-4 betguru-card-soft">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Full Name') }}
                                                </p>
                                                <p class="mt-2 font-semibold theme-text-main">
                                                    {{ trim($customer->name . ' ' . $customer->surname) ?: __('Not provided') }}
                                                </p>
                                            </div>

                                            <div class="rounded-2xl p-4 betguru-card-soft">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Username') }}
                                                </p>
                                                <p class="mt-2 font-semibold theme-text-main">
                                                    {{ $customer->username ?: __('Not provided') }}
                                                </p>
                                            </div>

                                            <div class="rounded-2xl p-4 betguru-card-soft sm:col-span-2">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Email Address') }}
                                                </p>
                                                <p class="mt-2 break-all font-semibold theme-text-main">
                                                    {{ $customer->email ?: __('Not provided') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-[1.75rem] p-5 theme-card-frost">
                                        <p class="text-sm font-semibold theme-text-main">
                                            {{ __('Quick Access') }}
                                        </p>
                                        <div class="mt-4 space-y-3">
                                            <a href="{{ route('customer.profile.favorites') }}"
                                                class="flex items-center justify-between rounded-2xl p-4 betguru-card-soft">
                                                <span
                                                    class="text-sm font-semibold theme-text-main">{{ __('Favorite Matches') }}</span>
                                                <span
                                                    class="rounded-full bg-[rgb(var(--surface-soft))] px-2.5 py-1 text-xs font-bold theme-text-main">
                                                    {{ $favoriteMatches->total() }}
                                                </span>
                                            </a>

                                            <a href="{{ route('customer.profile.subscriptions') }}"
                                                class="flex items-center justify-between rounded-2xl p-4 betguru-card-soft">
                                                <span
                                                    class="text-sm font-semibold theme-text-main">{{ __('Subscriptions') }}</span>
                                                <span
                                                    class="rounded-full bg-[rgb(var(--surface-soft))] px-2.5 py-1 text-xs font-bold theme-text-main">
                                                    {{ $subscriptionHistory->total() }}
                                                </span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        @endif

                        @if ($activeTab === 'favorites')
                            <section class="overflow-hidden rounded-[2rem] betguru-card-elevated">
                                {{-- <div class="border-b px-6 py-5 theme-border">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <h2 class="text-xl font-bold theme-text-main">
                                                {{ __('Favorite Matches') }}
                                            </h2>
                                            <p class="mt-1 text-sm theme-text-muted">
                                                {{ __('These are the match favorites saved in the database for this user profile.') }}
                                            </p>
                                        </div>

                                        <span class="rounded-full bg-[rgb(var(--surface-soft))] px-3 py-1 text-xs font-semibold theme-text-main">
                                            {{ $favoriteMatches->total() }}
                                        </span>
                                    </div>
                                </div> --}}

                                <div class="p-6">
                                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                        @forelse($favoriteMatches as $favoriteMatch)
                                            @php
                                                $favoriteType = strtolower((string) $favoriteMatch->favorite_type);
                                                $favoriteIdLabel = match ($favoriteType) {
                                                    'league' => __('League ID'),
                                                    'market' => __('Market ID'),
                                                    'team' => __('Team'),
                                                    default => __('Match ID'),
                                                };
                                                $favoriteIdValue = match ($favoriteType) {
                                                    'league' => $favoriteMatch->league_id,
                                                    'market' => $favoriteMatch->market_id,
                                                    'team' => $favoriteMatch->label ?: $favoriteMatch->entity_id,
                                                    default => $favoriteMatch->match_id,
                                                };
                                                $favoriteRouteName =
                                                    strtolower((string) $favoriteMatch->sport) . '.match.data';
                                                $favoriteMatchUrl =
                                                    $favoriteMatch->match_id &&
                                                    \Illuminate\Support\Facades\Route::has($favoriteRouteName)
                                                        ? route($favoriteRouteName, [
                                                            'match_id' => $favoriteMatch->match_id,
                                                        ])
                                                        : null;
                                            @endphp

                                            <article class="rounded-[1.75rem] p-5 theme-card-frost">
                                                <div class="flex items-start justify-between gap-3">
                                                    <span
                                                        class="rounded-full bg-[rgb(var(--surface-soft))] px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] theme-text-main">
                                                        {{ ucfirst((string) $favoriteMatch->sport) }}
                                                    </span>

                                                    <span
                                                        class="rounded-full px-3 py-1 text-xs font-semibold theme-chip-primary">
                                                        {{ ucfirst($favoriteType) }}
                                                    </span>
                                                </div>

                                                <h3 class="mt-4 text-lg font-bold theme-text-main">
                                                    {{ $favoriteMatch->label ?: __('Match #:id', ['id' => $favoriteMatch->match_id]) }}
                                                </h3>

                                                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                    <div class="rounded-2xl p-4 betguru-card-soft">
                                                        <p
                                                            class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                            {{ $favoriteIdLabel }}
                                                        </p>
                                                        <p class="mt-2 font-semibold theme-text-main">
                                                            {{ $favoriteIdValue ?: '-' }}
                                                        </p>
                                                    </div>

                                                    <div class="rounded-2xl p-4 betguru-card-soft">
                                                        <p
                                                            class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                            {{ __('Saved On') }}
                                                        </p>
                                                        <p class="mt-2 font-semibold theme-text-main">
                                                            {{ optional($favoriteMatch->created_at)->format('d M Y, h:i A') ?: '-' }}
                                                        </p>
                                                    </div>
                                                </div>

                                                {{-- <div
                                                    class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <p class="text-sm leading-6 theme-text-muted">
                                                        {{ __('Reference: :key', ['key' => $favoriteMatch->reference_key]) }}
                                                    </p>

                                                    @if ($favoriteMatchUrl)
                                                        <a href="{{ $favoriteMatchUrl }}"
                                                            class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-[rgb(var(--surface))] betguru-gradient-cta-flat">
                                                            {{ __('Open Match') }}
                                                        </a>
                                                    @else
                                                        <span class="text-sm theme-text-muted">
                                                            {{ __('Match page not available') }}
                                                        </span>
                                                    @endif
                                                </div> --}}
                                            </article>
                                        @empty
                                            <div
                                                class="rounded-[1.75rem] p-8 text-center xl:col-span-2 theme-card-frost-dashed">
                                                <p class="text-base font-semibold theme-text-main">
                                                    {{ __('No favorite matches found.') }}
                                                </p>
                                                <p class="mt-2 text-sm theme-text-muted">
                                                    {{ __('Once this user saves a match from the fixture pages, it will appear here automatically.') }}
                                                </p>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                                @if ($favoriteMatches->hasPages())
                                    <div class="border-t px-5 py-4 theme-border">
                                        {{ $favoriteMatches->links() }}
                                    </div>
                                @endif
                            </section>
                        @endif

                        @if ($activeTab === 'subscriptions')
                            <section class="rounded-[2rem] p-6 betguru-card-elevated">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <span class="betguru-pill">{{ __('Subscription') }}</span>

                                        <h2 class="mt-4 text-2xl font-bold theme-text-main">
                                            {{ __('Plan & Billing') }}
                                        </h2>

                                        <p class="mt-2 text-sm theme-text-muted">
                                            {{ __('Manage your current access, recurring billing gateway, and auto-renewal from one place.') }}
                                        </p>
                                    </div>

                                    @if ($currentSubscription)
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-semibold {{ $currentSubscription->status === 'cancelled' && $hasActiveAccess ? 'theme-chip-warning' : 'theme-chip-success' }}">
                                            {{ $statusLabel }}
                                        </span>
                                    @else
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold theme-chip-warning">
                                            {{ __('No Active Plan') }}
                                        </span>
                                    @endif
                                </div>

                                @if ($currentSubscription)
                                    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                                        <div class="rounded-2xl p-4 betguru-card-soft">
                                            <p class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                {{ __('Plan') }}
                                            </p>
                                            <p class="mt-2 text-lg font-bold theme-text-main">
                                                {{ $currentPlanName }}
                                            </p>
                                        </div>

                                        <div class="rounded-2xl p-4 betguru-card-soft">
                                            <p class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                {{ __('Gateway') }}
                                            </p>
                                            <p class="mt-2 text-lg font-bold theme-text-main">
                                                {{ $currentGatewayLabel }}
                                            </p>
                                        </div>

                                        <div class="rounded-2xl p-4 betguru-card-soft">
                                            <p class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                {{ __('Auto-renew') }}
                                            </p>
                                            <p
                                                class="mt-2 text-lg font-bold {{ $renewableSubscription ? 'theme-text-success' : 'theme-text-danger' }}">
                                                {{ $renewableSubscription ? __('Enabled') : __('Cancelled') }}
                                            </p>
                                        </div>

                                        <div class="rounded-2xl p-4 betguru-card-soft">
                                            <p class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                {{ __('Ends On') }}
                                            </p>
                                            <p class="mt-2 text-lg font-bold theme-text-main">
                                                {{ optional($currentSubscription->ends_at)->format('d M Y') }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-6 rounded-3xl p-5 theme-primary-panel">
                                        <div class="flex items-center justify-between gap-4">
                                            <div>
                                                <p class="text-sm font-semibold theme-text-main">
                                                    {{ __(':days days remaining', ['days' => (int) round($remainingDays)]) }}
                                                </p>
                                                <p class="mt-1 text-xs theme-text-muted">
                                                    {{ $renewableSubscription ? __('Your plan is still set to renew automatically until you cancel it.') : __('Auto-renew is off. Your access stays active until the end date above.') }}
                                                </p>
                                            </div>

                                            <p class="text-sm font-bold theme-text-primary">
                                                {{ round($progress) }}%
                                            </p>
                                        </div>

                                        <progress class="mt-4 betguru-progress-bar" value="{{ round($progress) }}"
                                            max="100">
                                            {{ round($progress) }}%
                                        </progress>
                                    </div>
                                @else
                                    <div class="mt-6 rounded-3xl p-5 betguru-card-soft">
                                        <p class="text-sm theme-text-muted">
                                            {{ __('You do not have an active subscription yet.') }}
                                        </p>
                                        <a href="{{ route('checkout.show') }}"
                                            class="mt-4 inline-flex rounded-2xl px-5 py-3 text-sm font-semibold text-[rgb(var(--surface))] betguru-gradient-cta-flat">
                                            {{ __('View Plans') }}
                                        </a>
                                    </div>
                                @endif

                                <div class="mt-6">
                                    <div class="rounded-[1.75rem] p-5 theme-card-frost">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="text-sm font-semibold theme-text-main">
                                                    {{ __('Saved card for recurring payments') }}
                                                </p>
                                                <p class="mt-2 text-sm leading-6 theme-text-muted">
                                                    {{ __('View the saved payment method linked to your recurring subscription and remove it any time from this profile page. Full card details stay securely stored with the payment gateway and are never shown here.') }}
                                                </p>
                                            </div>

                                            <span
                                                class="rounded-full px-3 py-1 text-xs font-semibold {{ $savedCardStatusClasses }}">
                                                {{ $savedCardStatusLabel }}
                                            </span>
                                        </div>

                                        <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                                            <div class="rounded-2xl p-4 betguru-card-soft">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    Gateway
                                                </p>
                                                <p class="mt-2 font-semibold theme-text-main">
                                                    {{ $manageableGatewayLabel }}
                                                </p>
                                            </div>

                                            <div class="rounded-2xl p-4 betguru-card-soft">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Billing') }}
                                                </p>
                                                <p class="mt-2 font-semibold theme-text-main">
                                                    {{ $manageableBillingLine }}
                                                </p>
                                            </div>

                                            <div class="rounded-2xl p-4 betguru-card-soft">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Card Status') }}
                                                </p>
                                                <p class="mt-2 font-semibold theme-text-main">
                                                    {{ $savedCardStatusLabel }}
                                                </p>
                                            </div>

                                            <div class="rounded-2xl p-4 betguru-card-soft">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Recurring Reference') }}
                                                </p>
                                                <p class="mt-2 break-all font-semibold theme-text-main">
                                                    {{ $maskedManageableReference }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="mt-4 rounded-2xl border border-[rgb(var(--border))] bg-[rgb(var(--surface-soft))] px-4 py-3 text-sm leading-6 theme-text-muted">
                                            {{ $savedCardHelperText }}
                                        </div>

                                        <div
                                            class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            @if ($renewableSubscription && $hasSavedCard)
                                                <form method="POST"
                                                    action="{{ route('customer.subscription.cancel') }}">
                                                    @csrf
                                                    <input type="hidden" name="customer_subscription_id"
                                                        value="{{ $renewableSubscription->id }}">

                                                    <button type="submit"
                                                        data-confirm-message="{{ __('Are you sure you want to remove this saved card and cancel auto-renewal?') }}"
                                                        class="theme-button-danger-soft inline-flex w-full items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold transition sm:w-auto">
                                                        {{ __('Cancel Auto-Renewal') }}</button>
                                                </form>
                                            @else
                                                <a href="{{ $savedCardActionUrl }}"
                                                    class="inline-flex w-full items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-[rgb(var(--surface))] betguru-gradient-cta-flat sm:w-auto">{{ __('Add Saved Card') }}</a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="overflow-hidden rounded-[2rem] betguru-card-elevated">
                                <div class="border-b px-6 py-5 theme-border">
                                    <h2 class="text-xl font-bold theme-text-main">
                                        {{ __('Subscription History') }}
                                    </h2>
                                    <p class="mt-1 text-sm theme-text-muted">
                                        {{ __('View previous plans, gateways, payment status, and subscription periods.') }}
                                    </p>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-[rgb(var(--border))]">
                                        <thead class="theme-bg-surface-soft">
                                            <tr>
                                                <th
                                                    class="px-5 py-4 text-left text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">
                                                    {{ __('Plan') }}</th>
                                                <th
                                                    class="px-5 py-4 text-left text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">
                                                    {{ __('Gateway') }}</th>
                                                <th
                                                    class="px-5 py-4 text-left text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">
                                                    {{ __('Amount') }}</th>
                                                <th
                                                    class="px-5 py-4 text-left text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">
                                                    {{ __('Period') }}</th>
                                                <th
                                                    class="px-5 py-4 text-left text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">
                                                    {{ __('Auto-renew') }}</th>
                                                <th
                                                    class="px-5 py-4 text-left text-xs font-bold uppercase tracking-[0.16em] theme-text-muted">
                                                    {{ __('Status') }}</th>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-[rgb(var(--border))]">
                                            @forelse($subscriptionHistory as $subscription)
                                                <tr>
                                                    <td class="px-5 py-5 font-semibold theme-text-main">
                                                        {{ $subscription->subscriptionPlan?->label ?? __('Plan') }}
                                                    </td>

                                                    <td class="px-5 py-5">
                                                        <span
                                                            class="rounded-full bg-[rgb(var(--surface-soft))] px-3 py-1 text-xs font-semibold theme-text-main">
                                                            {{ match (strtolower((string) $subscription->gateway)) {
                                                                'bog' => 'Bank of Georgia',
                                                                'tbc_flitt' => 'TBC Bank',
                                                                'admin' => __('Admin Manual'),
                                                                default => 'PayPal',
                                                            } }}
                                                        </span>
                                                    </td>

                                                    <td class="px-5 py-5 theme-text-soft">
                                                        {{ number_format($subscription->amount ?? 0, 2) }}
                                                        {{ $subscription->currency ?? '' }}
                                                    </td>

                                                    <td class="px-5 py-5 text-sm theme-text-muted">
                                                        {{ optional($subscription->starts_at)->format('d M Y') }}
                                                        -
                                                        {{ optional($subscription->ends_at)->format('d M Y') }}
                                                    </td>

                                                    <td class="px-5 py-5">
                                                        <span
                                                            class="rounded-full px-3 py-1 text-xs font-semibold {{ $subscription->auto_renew ? 'theme-chip-success' : 'theme-chip-danger-strong' }}">
                                                            {{ $subscription->auto_renew ? __('Enabled') : __('Cancelled') }}
                                                        </span>
                                                    </td>

                                                    <td class="px-5 py-5">
                                                        <span
                                                            class="rounded-full px-3 py-1 text-xs font-semibold theme-chip-primary">
                                                            {{ ucfirst(str_replace('_', ' ', (string) $subscription->status)) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6"
                                                        class="px-5 py-10 text-center text-sm theme-text-muted">
                                                        {{ __('No subscription history found.') }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="border-t px-5 py-4 theme-border">
                                    {{ $subscriptionHistory->links() }}
                                </div>
                            </section>
                        @endif

                        @if ($activeTab === 'savedcards')
                            <section class="overflow-hidden rounded-[2rem] betguru-card-elevated">
                                <div class="border-b px-6 py-5 theme-border">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <button type="button" id="fitt-add-card-button"
                                            class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-[rgb(var(--surface))] betguru-gradient-cta-flat">
                                            {{ __('Add TBC Card') }}
                                        </button>

                                        <button type="button" id="bog-add-card-button"
                                            data-subscription-id="{{ $savedCardActionPlanId }}"
                                            class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-[rgb(var(--surface))] betguru-gradient-cta-flat">
                                            {{ __('Add BOG Card') }}
                                        </button>

                                        @if (IsPayPalConfigured())
                                            <div id="paypal-add-method-container" class="min-w-[210px]"></div>
                                        @else
                                            <button type="button" disabled
                                                class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold opacity-50 theme-panel-subtle">
                                                {{ __('PayPal unavailable') }}
                                            </button>
                                        @endif
                                    </div>

                                    <div id="fitt-card-feedback"
                                        class="mt-4 hidden rounded-2xl px-4 py-3 text-sm font-semibold"></div>

                                    <div id="bog-add-card-feedback"
                                        class="mt-4 hidden rounded-2xl px-4 py-3 text-sm font-semibold"></div>

                                    <div id="paypal-card-feedback"
                                        class="mt-4 hidden rounded-2xl px-4 py-3 text-sm font-semibold"></div>
                                </div>

                                <div class="p-6">
                                    @forelse($savedCards as $savedCard)
                                        <div class="mb-5 rounded-[1.75rem] p-5 last:mb-0 theme-card-frost">
                                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="text-lg font-bold theme-text-main">
                                                            {{ $savedCard->card_type ?: __('Card') }}

                                                            {{ $savedCard->masked_card ?: __('Masked number unavailable') }}
                                                        </p>

                                                        @if ($savedCard->is_default)
                                                            <span
                                                                class="rounded-full px-3 py-1 text-xs font-semibold theme-chip-success">
                                                                {{ __('Primary') }}
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <p class="mt-2 text-sm theme-text-muted">
                                                        {{ __('Issued in: :country', [
                                                            'country' => $savedCard->issuer_country ?: __('Unknown'),
                                                        ]) }}
                                                    </p>
                                                </div>

                                                <span
                                                    class="rounded-full px-3 py-1 text-xs font-semibold theme-chip-success">
                                                    {{ __('Active') }}
                                                </span>
                                            </div>

                                            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                                                <div class="rounded-2xl p-4 betguru-card-soft">
                                                    <p
                                                        class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                        {{ __('Card') }}
                                                    </p>

                                                    <p class="mt-2 font-semibold theme-text-main">
                                                        {{ $savedCard->masked_card ?: '-' }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl p-4 betguru-card-soft">
                                                    <p
                                                        class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                        {{ __('Card Type') }}
                                                    </p>

                                                    <p class="mt-2 font-semibold theme-text-main">
                                                        {{ $savedCard->card_type ?: '-' }}
                                                    </p>
                                                </div>

                                                <div class="rounded-2xl p-4 betguru-card-soft">
                                                    <p
                                                        class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                        {{ __('Token Expiry') }}
                                                    </p>

                                                    <p class="mt-2 font-semibold theme-text-main">
                                                        {{ $savedCard->rectoken_expires_at ? $savedCard->rectoken_expires_at->format('d M Y') : __('No expiry provided') }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                                                @if (!$savedCard->is_default)
                                                    {{-- <form method="POST" action="{{ route('fitt.cards.primary', $savedCard) }}"> --}}
                                                    <form method="POST"
                                                        action="{{ route('fitt.cards.primary', ['paymentMethod' => $savedCard->id]) }}">

                                                        @csrf
                                                        <button type="submit"
                                                            class="inline-flex w-full items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-[rgb(var(--surface))] betguru-gradient-cta-flat sm:w-auto">
                                                            {{ __('Make Primary') }}
                                                        </button>
                                                    </form>
                                                @endif

                                                {{-- <form method="POST" action="{{ route('fitt.cards.delete', $savedCard) }}"> --}}
                                                <form method="POST"
                                                    action="{{ route('fitt.cards.delete', ['paymentMethod' => $savedCard->id]) }}">
                                                    @csrf
                                                    @method('DELETE')

                                                    <button type="submit"
                                                        data-confirm-message="{{ __('Are you sure you want to delete this saved card? Auto-renew will be disabled for subscriptions using this card.') }}"
                                                        class="theme-button-danger-soft inline-flex w-full items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold transition sm:w-auto">
                                                        {{ __('Delete Card') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="rounded-[1.75rem] p-8 text-center theme-card-frost-dashed">
                                            <p class="text-base font-semibold theme-text-main">
                                                {{ __('No saved cards found.') }}
                                            </p>

                                            <p class="mt-2 text-sm theme-text-muted">
                                                {{ __('Click Add Saved Card to securely save a new TBC card.') }}
                                            </p>
                                        </div>
                                    @endforelse
                                </div>
                            </section>
                            <div class="space-y-5">
                                @forelse($savedBogCards as $savedBogCard)
                                    <div class="rounded-[1.75rem] p-5 theme-card-frost">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-lg font-bold theme-text-main">
                                                        {{ strtoupper($savedBogCard->card_type ?: 'CARD') }}

                                                        {{ $savedBogCard->masked_card ?: __('Saved BOG Card') }}
                                                    </p>

                                                    @if ($savedBogCard->is_default)
                                                        <span
                                                            class="rounded-full px-3 py-1 text-xs font-semibold theme-chip-success">
                                                            {{ __('Primary') }}
                                                        </span>
                                                    @endif
                                                </div>

                                                <p class="mt-2 text-sm theme-text-muted">
                                                    {{ $savedBogCard->subscriptionPlan?->label ?? __('Subscription plan') }}
                                                </p>

                                                <p class="mt-1 text-sm theme-text-muted">
                                                    {{ number_format((float) $savedBogCard->amount, 2) }}

                                                    {{ $savedBogCard->currency }}
                                                </p>
                                            </div>

                                            <span class="rounded-full px-3 py-1 text-xs font-semibold theme-chip-success">
                                                {{ __('Active') }}
                                            </span>
                                        </div>

                                        <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                                            <div class="rounded-2xl p-4 betguru-card-soft">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Gateway') }}
                                                </p>

                                                <p class="mt-2 font-semibold theme-text-main">
                                                    {{ __('Bank of Georgia') }}
                                                </p>
                                            </div>

                                            <div class="rounded-2xl p-4 betguru-card-soft">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Expiry') }}
                                                </p>

                                                <p class="mt-2 font-semibold theme-text-main">
                                                    {{ $savedBogCard->card_expiry_date ?: '-' }}
                                                </p>
                                            </div>

                                            <div class="rounded-2xl p-4 betguru-card-soft">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Auto Renew') }}
                                                </p>

                                                <p class="mt-2 font-semibold theme-text-success">
                                                    {{ __('Enabled') }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                                            @if (!$savedBogCard->is_default)
                                                <form method="POST"
                                                    action="{{ route('bog.cards.primary', [
                                                        'paymentMethod' => $savedBogCard->id,
                                                    ]) }}">
                                                    @csrf

                                                    <button type="submit"
                                                        class="inline-flex w-full items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-[rgb(var(--surface))] betguru-gradient-cta-flat sm:w-auto">
                                                        {{ __('Make Primary') }}
                                                    </button>
                                                </form>
                                            @endif

                                            <form method="POST"
                                                action="{{ route('bog.cards.delete', [
                                                    'paymentMethod' => $savedBogCard->id,
                                                ]) }}">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                    data-confirm-message="{{ __('Are you sure you want to delete this saved BOG card?') }}"
                                                    class="theme-button-danger-soft inline-flex w-full items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold transition sm:w-auto">
                                                    {{ __('Delete Card') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-[1.75rem] p-8 text-center theme-card-frost-dashed">
                                        <p class="text-base font-semibold theme-text-main">
                                            {{ __('No saved BOG cards found.') }}
                                        </p>

                                        <p class="mt-2 text-sm theme-text-muted">
                                            {{ __('Your BOG card will be saved after the first successful subscription payment.') }}
                                        </p>

                                        <a href="{{ route('checkout.show') }}"
                                            class="mt-5 inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-[rgb(var(--surface))] betguru-gradient-cta-flat">
                                            {{ __('Choose Plan and Add Card') }}
                                        </a>
                                    </div>
                                @endforelse
                            </div>
                            <div class="mt-6 space-y-5">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <h3 class="text-xl font-bold theme-text-main">
                                            {{ __('Saved PayPal Accounts') }}
                                        </h3>

                                        <p class="mt-1 text-sm theme-text-muted">
                                            {{ __('Manage PayPal accounts saved for future recurring payments.') }}
                                        </p>
                                    </div>

                                    <span class="rounded-full px-3 py-1 text-xs font-semibold theme-chip-primary">
                                        {{ $savedPaypalMethods->count() }}
                                    </span>
                                </div>

                                @forelse($savedPaypalMethods as $savedPaypalMethod)
                                    <div class="rounded-[1.75rem] p-5 theme-card-frost">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-lg font-bold theme-text-main">
                                                        PayPal
                                                    </p>

                                                    @if ($savedPaypalMethod->is_default)
                                                        <span
                                                            class="rounded-full px-3 py-1 text-xs font-semibold theme-chip-success">
                                                            {{ __('Primary') }}
                                                        </span>
                                                    @endif
                                                </div>

                                                <p class="mt-2 font-semibold theme-text-main">
                                                    {{ $savedPaypalMethod->display_name ?: __('Saved PayPal Account') }}
                                                </p>

                                                @if ($savedPaypalMethod->email)
                                                    <p class="mt-1 text-sm theme-text-muted">
                                                        {{ $savedPaypalMethod->email }}
                                                    </p>
                                                @endif
                                            </div>

                                            <span class="rounded-full px-3 py-1 text-xs font-semibold theme-chip-success">
                                                {{ __('Active') }}
                                            </span>
                                        </div>

                                        <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                                            <div class="rounded-2xl p-4 betguru-card-soft">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Gateway') }}
                                                </p>

                                                <p class="mt-2 font-semibold theme-text-main">
                                                    PayPal
                                                </p>
                                            </div>

                                            <div class="rounded-2xl p-4 betguru-card-soft">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Account') }}
                                                </p>

                                                <p class="mt-2 break-all font-semibold theme-text-main">
                                                    {{ $savedPaypalMethod->email ?: __('PayPal Wallet') }}
                                                </p>
                                            </div>

                                            <div class="rounded-2xl p-4 betguru-card-soft">
                                                <p
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Saved On') }}
                                                </p>

                                                <p class="mt-2 font-semibold theme-text-main">
                                                    {{ optional($savedPaypalMethod->created_at)->format('d M Y') ?: '-' }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                                            @if (!$savedPaypalMethod->is_default)
                                                <form method="POST"
                                                    action="{{ route('paypal.payment-methods.primary', [
                                                        'paymentMethod' => $savedPaypalMethod->id,
                                                    ]) }}">
                                                    @csrf

                                                    <button type="submit"
                                                        class="inline-flex w-full items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold text-[rgb(var(--surface))] betguru-gradient-cta-flat sm:w-auto">
                                                        {{ __('Make Primary') }}
                                                    </button>
                                                </form>
                                            @endif

                                            <form method="POST"
                                                action="{{ route('paypal.payment-methods.delete', [
                                                    'paymentMethod' => $savedPaypalMethod->id,
                                                ]) }}">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                    data-confirm-message="{{ __('Are you sure you want to delete this saved PayPal payment method? Auto-renewal will be disabled for subscriptions using it.') }}"
                                                    class="theme-button-danger-soft inline-flex w-full items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold transition sm:w-auto">
                                                    {{ __('Delete PayPal') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-[1.75rem] p-8 text-center theme-card-frost-dashed">
                                        <p class="text-base font-semibold theme-text-main">
                                            {{ __('No saved PayPal accounts found.') }}
                                        </p>

                                        <p class="mt-2 text-sm theme-text-muted">
                                            {{ __('Use the Add PayPal button above to securely save a PayPal account.') }}
                                        </p>
                                    </div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
