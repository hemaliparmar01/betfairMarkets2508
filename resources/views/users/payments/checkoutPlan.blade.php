@extends('users.layouts.app', [
    'activeHeaderUrl' => 'fixtures',
    'fixtuesUrl' => route('home.en'),
    'moneywayUrl' => route('football.moneyway.page'),
])

@section('title', 'Choose Your Plan | Secure Checkout')
@section('meta_description',
    'Compare BetGuru subscription plans and continue to a clear secure checkout with PayPal or
    Georgian card options.')

@section('head')
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="{{ __('Choose Your Plan | Secure Checkout') }}">
    <meta property="og:description"
        content="{{ __('Compare BetGuru subscription plans and continue to a clear secure checkout with PayPal or   Georgian card options.') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    <style>
        .checkout-shell {
            position: relative;
            overflow: hidden;
        }

        .checkout-shell::before,
        .checkout-shell::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
            filter: blur(14px);
        }

        .checkout-shell::before {
            top: 2rem;
            left: -4rem;
            width: 13rem;
            height: 13rem;
            background: rgb(var(--primary) / 0.12);
        }

        .checkout-shell::after {
            right: -4rem;
            bottom: 2rem;
            width: 12rem;
            height: 12rem;
            background: rgb(var(--secondary) / 0.14);
        }

        .checkout-content {
            position: relative;
            z-index: 1;
        }

        .checkout-steps {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .checkout-step {
            border-radius: 1.5rem;
            border: 1px solid rgb(var(--border));
            background: rgb(var(--surface-soft));
            padding: 1rem 1.1rem;
        }

        .checkout-plan-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }

        .checkout-plan-card {
            display: flex;
            min-height: 100%;
            flex-direction: column;
            gap: 1.5rem;
            border-radius: 2rem;
            border: 1px solid rgb(var(--border));
            background: rgb(var(--surface));
            padding: 1.75rem;
            box-shadow: 0 20px 48px rgb(var(--shadow-soft) / 0.08);
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .checkout-plan-card:hover {
            transform: translateY(-2px);
            border-color: rgb(var(--primary) / 0.35);
            box-shadow: 0 24px 52px rgb(var(--shadow-soft) / 0.12);
        }

        .checkout-plan-card.is-selected {
            border-color: rgb(var(--primary));
            box-shadow: 0 26px 60px rgb(var(--shadow-primary) / 0.16);
        }

        .checkout-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.45rem 0.95rem;
            background: rgb(var(--accent) / 0.12);
            color: rgb(var(--accent));
            font-size: 0.78rem;
            font-weight: 700;
        }

        .checkout-duration {
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgb(var(--primary));
        }

        .checkout-price {
            display: flex;
            align-items: baseline;
            gap: 0.4rem;
            font-size: 3rem;
            line-height: 1;
            letter-spacing: -0.05em;
            color: rgb(var(--text-main));
        }

        .checkout-price small {
            font-size: 1rem;
            letter-spacing: 0;
            color: rgb(var(--text-muted));
        }

        .checkout-plan-list {
            display: grid;
            gap: 0.9rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .checkout-plan-list li {
            display: flex;
            gap: 0.75rem;
            color: rgb(var(--text-soft));
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .checkout-check {
            display: inline-flex;
            width: 1.4rem;
            height: 1.4rem;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgb(var(--primary) / 0.12);
            color: rgb(var(--primary));
            font-size: 0.8rem;
            font-weight: 700;
        }

        .checkout-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
        }

        .checkout-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 999px;
            border: 1px solid rgb(var(--border));
            background: rgb(var(--surface-soft));
            padding: 0.6rem 0.95rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: rgb(var(--text-soft));
        }

        .checkout-chip.dark {
            background: rgb(var(--text-main));
            border-color: rgb(var(--text-main));
            color: rgb(var(--surface));
        }

        .checkout-mastercard {
            position: relative;
            width: 22px;
            height: 14px;
            display: inline-block;
        }

        .checkout-mastercard::before,
        .checkout-mastercard::after {
            content: "";
            position: absolute;
            top: 0;
            width: 14px;
            height: 14px;
            border-radius: 999px;
        }

        .checkout-mastercard::before {
            left: 0;
            background: rgb(var(--danger));
        }

        .checkout-mastercard::after {
            right: 0;
            background: rgb(var(--warning));
            opacity: 0.95;
        }

        .checkout-panel {
            border-radius: 2rem;
            border: 1px solid rgb(var(--border));
            background: rgb(var(--surface) / 0.94);
            box-shadow: 0 20px 54px rgb(var(--shadow-soft) / 0.08);
            backdrop-filter: blur(10px);
        }

        .checkout-summary-line {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.95rem 0;
            border-bottom: 1px solid rgb(var(--border));
            font-size: 0.95rem;
            color: rgb(var(--text-soft));
        }

        .checkout-summary-line:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .checkout-summary-line strong {
            color: rgb(var(--text-main));
            text-align: right;
        }

        .checkout-method-card {
            border-radius: 1.5rem;
            border: 1px solid rgb(var(--border));
            background: rgb(var(--surface-soft));
            padding: 1.25rem;
        }

        .checkout-method-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .checkout-consent-panel {
            border-radius: 1.35rem;
            border: 1px solid rgb(var(--border));
            background: linear-gradient(180deg, rgb(var(--surface)) 0%, rgb(var(--surface-soft)) 100%);
            padding: 1rem 1.1rem;
        }

        .checkout-consent-label {
            display: flex;
            align-items: flex-start;
            gap: 0.9rem;
            font-size: 0.95rem;
            line-height: 1.9;
            color: rgb(var(--text-soft));
        }

        .checkout-consent-checkbox {
            width: 1.1rem;
            height: 1.1rem;
            margin-top: 0.25rem;
            flex-shrink: 0;
            accent-color: rgb(var(--primary));
        }

        .checkout-consent-note {
            margin-top: 0.9rem;
            padding-left: 2rem;
            font-size: 0.82rem;
            line-height: 1.7;
            color: rgb(var(--text-muted));
        }

        .checkout-method-button {
            display: inline-flex;
            min-width: 130px;
            align-items: center;
            justify-content: center;
            border-radius: 1rem;
            border: 1px solid rgb(var(--border));
            background: rgb(var(--surface));
            padding: 0.85rem 1rem;
            font-size: 0.9rem;
            font-weight: 700;
            color: rgb(var(--text-main));
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, opacity 0.2s ease;
        }

        .checkout-method-button.is-primary {
            min-width: 240px;
            background: rgb(var(--text-main));
            border-color: rgb(var(--text-main));
            color: rgb(var(--surface));
        }

        .checkout-method-button:disabled {
            cursor: not-allowed;
            opacity: 0.48;
            background: rgb(var(--surface));
            border-color: rgb(var(--border));
            color: rgb(var(--text-muted));
            box-shadow: none;
        }

        .checkout-method-note {
            margin-top: 0.9rem;
            font-size: 0.85rem;
            line-height: 1.7;
            color: rgb(var(--text-muted));
        }

        .checkout-empty {
            border-radius: 2rem;
            border: 1px dashed rgb(var(--border));
            background: rgb(var(--surface-soft));
            padding: 2rem;
            text-align: center;
        }

        .checkout-feedback {
            display: none;
            margin-top: 1rem;
            border-radius: 1rem;
            padding: 0.95rem 1rem;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .checkout-feedback.is-visible {
            display: block;
        }

        .checkout-feedback.success {
            background: rgb(var(--success) / 0.10);
            color: rgb(var(--success));
        }

        .checkout-feedback.error {
            background: rgb(var(--danger) / 0.10);
            color: rgb(var(--danger));
        }

        .checkout-feedback.info {
            background: rgb(var(--primary) / 0.10);
            color: rgb(var(--primary));
        }

        #paypal-button-container {
            min-height: 50px;
        }

        @media (max-width: 1024px) {
            .checkout-payment-grid {
                grid-template-columns: 1fr;
            }
        }

        .checkout-tbc-renew-card {
            border-radius: 2rem;
            border: 1px solid rgb(var(--border));
            background: linear-gradient(180deg, rgb(var(--surface)) 0%, rgb(var(--surface-soft)) 100%);
            padding: 1.6rem;
            box-shadow: 0 14px 34px rgb(var(--shadow-soft) / 0.04);
        }

        .checkout-tbc-renew-copy {
            max-width: 760px;
        }

        .checkout-tbc-renew-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 500;
            line-height: 1.45;
            color: rgb(var(--text-main));
        }

        .checkout-tbc-renew-text {
            margin-top: 0.45rem;
            font-size: 0.98rem;
            line-height: 1.55;
            color: rgb(var(--text-muted));
        }

        .checkout-tbc-renew-actions {
            margin-top: 1.35rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.9rem;
        }

        .checkout-method-button.is-tbc-brand {
            min-width: 220px;
            border-color: rgb(var(--primary));
            background: rgb(var(--primary));
            color: rgb(var(--surface));
            box-shadow: 0 12px 26px rgb(var(--shadow-primary) / 0.18);
        }

        .checkout-method-button.is-tbc-brand:hover {
            background: rgb(var(--primary-strong));
            border-color: rgb(var(--primary-strong));
            color: rgb(var(--surface));
        }

        .checkout-method-button.is-tbc-outline {
            min-width: 230px;
            border: 1px solid rgb(var(--text-main));
            background: rgb(var(--surface));
            color: rgb(var(--text-main));
        }

        .checkout-method-button.is-tbc-outline:hover {
            border-color: rgb(var(--primary));
            color: rgb(var(--primary));
            background: rgb(var(--surface-soft));
        }

        .checkout-tbc-renew-hidden {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .checkout-tbc-renew-card .checkout-feedback {
            margin-top: 1rem;
        }

        @media (max-width: 640px) {
            .checkout-tbc-renew-card {
                padding: 1.25rem;
                border-radius: 1.5rem;
            }

            .checkout-tbc-renew-title {
                font-size: 1rem;
            }

            .checkout-tbc-renew-text {
                font-size: 0.92rem;
            }

            .checkout-tbc-renew-actions {
                flex-direction: column;
            }

            .checkout-method-button.is-tbc-brand,
            .checkout-method-button.is-tbc-outline {
                width: 100%;
                min-width: 100%;
            }
        }
    </style>
@endsection

@php
    $formatDuration = static fn(int $days): string => match ($days) {
        7 => __('7 Day Plan'),
        30 => __('30 Day Plan'),
        90 => __('90 Day Plan'),
        default => __(':days Day Plan', ['days' => $days]),
    };
@endphp

@section('content')
    <div class="betguru-auth-bg min-h-screen" data-checkout-payment
        data-subscription-id="{{ $selectedPlan?->id }}"
        data-paypal-client-id="{{ $selectedPlan && IsPayPalConfigured() ? getPaypalClientId() : '' }}"
        data-paypal-create-url="{{ route('checkout.paypal.order') }}"
        data-paypal-capture-url="{{ route('checkout.paypal.capture') }}"
        data-paypal-saved-url="{{ route('checkout.paypal.saved-method.pay') }}"
        data-fitt-checkout-url="{{ route('fitt.checkout') }}"
        data-fitt-saved-url="{{ route('fitt.saved-card.pay') }}"
        data-bog-checkout-url="{{ route('checkout.bog.order') }}"
        data-bog-saved-url="{{ route('checkout.bog.saved-card.pay') }}">
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-8">
                @if (!$selectedPlan)
                    <section class="checkout-shell rounded-[2rem] border px-6 py-10 sm:px-8 lg:px-10 betguru-surface-glass">
                        <div class="checkout-content">
                            <div class="mx-auto max-w-3xl text-center">
                                <span class="betguru-pill">{{ __('Subscription Plans') }}</span>
                                <h1 class="mt-6 text-4xl font-bold tracking-tight sm:text-5xl theme-text-main">
                                    {{ __('Choose the best plan for you') }}
                                </h1>
                                <p class="mx-auto mt-4 max-w-2xl text-base leading-7 theme-text-muted">
                                    {{ __('Plans below are loaded directly from your subscription database. Choose one plan and continue to checkout.') }}
                                </p>
                            </div>

                            <div class="mx-auto mt-8 max-w-5xl checkout-steps">
                                <div class="checkout-step">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] theme-text-muted">
                                        {{ __('Step 1') }}</p>
                                    <p class="mt-2 text-base font-semibold theme-text-main">
                                        {{ __('Select your subscription plan') }}
                                    </p>
                                </div>
                                <div class="checkout-step">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] theme-text-muted">
                                        {{ __('Step 2') }}</p>
                                    <p class="mt-2 text-base font-semibold theme-text-main">
                                        {{ __('Review the selected plan summary') }}
                                    </p>
                                </div>
                                <div class="checkout-step">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] theme-text-muted">
                                        {{ __('Step 3') }}</p>
                                    <p class="mt-2 text-base font-semibold theme-text-main">
                                        {{ __('Complete payment securely') }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-10 checkout-plan-grid">
                                @forelse ($plans as $plan)
                                    <article class="checkout-plan-card">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="checkout-duration">
                                                    {{ $formatDuration((int) $plan->duration_days) }}</p>
                                                <h2 class="mt-3 text-3xl font-bold tracking-tight theme-text-main">
                                                    {{ $plan->label }}
                                                </h2>
                                            </div>

                                            @if ($plan->badge)
                                                <span class="checkout-badge">{{ $plan->badge }}</span>
                                            @endif
                                        </div>

                                        @if (get_current_country() == 'Georgia')
                                            <div class="space-y-4">
                                                <div class="checkout-price">
                                                    {{ number_format((float) $plan->price_for_local, 2) }}
                                                    <small>/ GEL</small>
                                                </div>
                                            </div>
                                        @else
                                            <div class="space-y-4">
                                                <div class="checkout-price">
                                                    {{ number_format((float) $plan->price_for_international, 2) }}
                                                    <small>/ EUR</small>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="checkout-chip-row">
                                            <span class="checkout-chip">Visa</span>
                                            <span class="checkout-chip">{{ __('Google Pay / Apple Pay') }}</span>
                                            <span class="checkout-chip">
                                                <span class="checkout-mastercard" aria-hidden="true"></span>
                                                Mastercard
                                            </span>
                                        </div>
                                        <div class="mt-auto space-y-3">
                                            <a href="{{ route('checkout.show', ['plan' => $plan->id]) }}"
                                                class="betguru-btn-primary">
                                                {{ __('Choose plan') }}
                                            </a>
                                        </div>
                                    </article>
                                @empty
                                    <div class="checkout-empty">
                                        <h2 class="text-2xl font-bold theme-text-main">
                                            {{ __('No active plans found') }}
                                        </h2>
                                        <p class="mt-3 text-sm theme-text-muted">
                                            {{ __('Activate at least one subscription plan from admin to show pricing here.') }}
                                        </p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </section>
                @else
                    <section class="checkout-shell rounded-[2rem] border px-6 py-8 sm:px-8 lg:px-10 betguru-surface-glass">
                        <div class="checkout-content">
                            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <span class="betguru-pill">{{ __('Secure Checkout') }}</span>
                                    <h1 class="mt-5 text-4xl font-bold tracking-tight sm:text-5xl theme-text-main">
                                        {{ __('Complete your payment') }}
                                    </h1>
                                    <p class="mt-4 max-w-2xl text-base leading-7 theme-text-muted">
                                        {{ __('Your plan is selected. Review the summary below and finish payment with your preferred method.') }}
                                    </p>
                                </div>

                                <div class="rounded-[1.5rem] p-5 betguru-card-elevated lg:min-w-[320px]">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] theme-text-muted">
                                        {{ __('Selected plan') }}
                                    </p>
                                    <p class="mt-2 text-2xl font-bold theme-text-main">
                                        {{ $selectedPlan->label }}
                                    </p>
                                    <p class="mt-2 text-sm theme-text-muted">
                                        {{ $formatDuration((int) $selectedPlan->duration_days) }}
                                    </p>
                                    <div class="mt-4 flex items-end gap-2">
                                        @if (get_current_country() == 'Georgia')
                                            <span class="text-3xl font-bold theme-text-main">
                                                {{ number_format((float) $selectedPlan->price_for_local, 2) }}
                                            </span>
                                            <span class="text-sm font-medium theme-text-muted">/ GEL</span>
                                        @else
                                            <span class="text-3xl font-bold theme-text-main">
                                                {{ number_format((float) $selectedPlan->price_for_international, 2) }}
                                            </span>
                                            <span class="text-sm font-medium theme-text-muted">/ EUR</span>
                                        @endif
                                    </div>
                                    <a href="{{ route('checkout.show') }}"
                                        class="mt-5 inline-flex text-sm font-semibold betguru-link">
                                        {{ __('Change plan') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="payment-section" class="grid gap-6 checkout-payment-grid lg:grid-cols-[0.92fr_1.08fr]">
                        <aside class="checkout-panel p-6 sm:p-8">
                            <p class="text-sm font-semibold uppercase tracking-[0.22em] theme-text-primary">
                                {{ __('Selected Plan') }}
                            </p>
                            <h2 class="mt-3 text-3xl font-bold tracking-tight theme-text-main">
                                {{ $selectedPlan->label }}
                            </h2>
                            <p class="mt-3 text-sm leading-6 theme-text-muted">
                                {{ __('This is the plan you picked above. If you want to change it, simply go back to the plan cards and select another option.') }}
                            </p>

                            <div class="mt-6 rounded-[1.5rem] p-5 betguru-card-soft">
                                <div class="checkout-summary-line">
                                    <span>{{ __('Plan duration') }}</span>
                                    <strong>{{ $formatDuration((int) $selectedPlan->duration_days) }}</strong>
                                </div>
                                <div class="checkout-summary-line">
                                    <span>{{ __('Amount') }}</span>
                                    @if (get_current_country() == 'Georgia')
                                        <strong>
                                            {{ number_format((float) $selectedPlan->price_for_local, 2) }}
                                            GEL
                                        </strong>
                                    @else
                                        <strong>
                                            {{ number_format((float) $selectedPlan->price_for_international, 2) }}
                                            EUR
                                        </strong>
                                    @endif
                                </div>
                                <div class="checkout-summary-line">
                                    <span>{{ __('Badge') }}</span>
                                    <strong>{{ $selectedPlan->badge ?: __('Standard secure checkout') }}</strong>
                                </div>
                            </div>

                            <div class="mt-6 rounded-[1.5rem] p-5 theme-primary-panel">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-10 w-10 items-center justify-center rounded-full theme-icon-surface-primary">
                                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor"
                                            stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.5 10.5V7.75a4.5 4.5 0 10-9 0v2.75m-1.25 0h11.5a1 1 0 011 1v7a1 1 0 01-1 1H6.25a1 1 0 01-1-1v-7a1 1 0 011-1z" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold theme-text-main">
                                            {{ __('Trust Badge') }}
                                        </p>
                                        <p class="text-xs theme-text-muted">
                                            {{ __('Encrypted and secure payment flow') }}
                                        </p>
                                    </div>
                                </div>

                                <p class="mt-4 text-sm leading-7 theme-text-soft">
                                    {{ __('Your payment information is processed securely. We do not store credit card details nor do we have access to your card information.') }}
                                </p>

                                <div class="mt-4 checkout-chip-row">
                                    <span class="checkout-chip">{{ __('Secure Payment') }}</span>
                                    <span class="checkout-chip">{{ __('Visa Secure') }}</span>
                                    <span class="checkout-chip">
                                        <span class="checkout-mastercard" aria-hidden="true"></span>
                                        {{ __('Mastercard ID Check') }}
                                    </span>
                                </div>
                            </div>
                        </aside>

                        <div class="checkout-panel p-6 sm:p-8">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.22em] theme-text-primary">
                                        {{ __('Secure Checkout') }}
                                    </p>
                                    <h2 class="mt-3 text-3xl font-bold tracking-tight theme-text-main">
                                        {{ __('Complete your payment') }}
                                    </h2>
                                    <p class="mt-3 text-sm leading-6 theme-text-muted">
                                        {{ __('PayPal is the main payment method for all users worldwide. Bank of Georgia is shown for Georgian cards, and TBC is available in the local card checkout section.') }}
                                    </p>
                                </div>

                                <a href="{{ route('checkout.show') }}"
                                    class="rounded-2xl px-4 py-2.5 text-sm font-semibold theme-panel-subtle">
                                    {{ __('Change plan') }}
                                </a>
                            </div>

                            <div class="mt-8 space-y-4">
                                @if (session('success'))
                                    <div class="checkout-feedback is-visible success">
                                        {{ session('success') }}
                                    </div>
                                @endif

                                @if (session('error'))
                                    <div class="checkout-feedback is-visible error">
                                        {{ session('error') }}
                                    </div>
                                @endif

                                {{-- @if (get_current_country() != 'Georgia') --}}
                                {{-- <div class="checkout-method-card">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h3 class="text-xl font-bold theme-text-main">
                                                PayPal
                                            </h3>
                                            <p class="mt-2 text-sm leading-6 theme-text-muted">
                                                {{ __('Use PayPal for international and local users who prefer it.') }}
                                            </p>
                                        </div>

                                        <div class="checkout-chip-row">
                                            <span class="checkout-chip">PayPal</span>
                                            <span class="checkout-chip">Visa</span>
                                            <span class="checkout-chip">
                                                <span class="checkout-mastercard" aria-hidden="true"></span>
                                                Mastercard
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-5 rounded-[1.25rem] border p-4 theme-surface-box">
                                        <div class="checkout-summary-line">
                                            <span>{{ __('Selected plan') }}</span>
                                            <strong>{{ $selectedPlan->label }}</strong>
                                        </div>
                                        <div class="checkout-summary-line">
                                            <span>{{ __('Amount') }}</span>
                                            <strong>
                                                {{ number_format((float) $selectedPlan->price_for_international, 2) }}
                                                {{ getPaypalCurrency() }}
                                            </strong>
                                        </div>
                                        <div class="checkout-summary-line">
                                            <span>{{ __('Access period') }}</span>
                                            <strong>{{ __(':days days', ['days' => $selectedPlan->duration_days]) }}</strong>
                                        </div>

                                        @if (IsPayPalConfigured())
                                            <div class="mt-5 checkout-consent-panel">
                                                <label class="checkout-consent-label">
                                                    <input id="paypal-auto-renew-consent" type="checkbox"
                                                        class="checkout-consent-checkbox">
                                                    <span>
                                                        {{ __(
                                                            'I agree that my PayPal subscription will stay on automatic renewal. After this successful payment, :amount :currency may be charged again every :days days until I cancel auto-renewal from my profile.',
                                                            [
                                                                'amount' => number_format((float) $selectedPlan->price_for_international, 2),
                                                                'currency' => getPaypalCurrency(),
                                                                'days' => $selectedPlan->duration_days,
                                                            ],
                                                        ) }}
                                                    </span>
                                                </label>
                                                <p class="checkout-consent-note">
                                                    {{ __('PayPal remains the main payment method for worldwide customers, and the recurring payment method can be managed later from your profile page.') }}
                                                </p>
                                            </div>
                                        @endif

                                        <div class="mt-5">
                                            @if (IsPayPalConfigured())
                                                <div id="paypal-button-shell" class="pointer-events-none opacity-60">
                                                    <div id="paypal-button-container"></div>
                                                </div>
                                            @else
                                                <div class="checkout-feedback is-visible error">
                                                    {{ __('PayPal credentials are not configured yet, so the PayPal button is not available right now.') }}
                                                </div>
                                            @endif
                                        </div>

                                        <div id="checkout-feedback" class="checkout-feedback"></div>
                                    </div>
                                </div> --}}
                                <div class="checkout-method-card">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h3 class="text-xl font-bold theme-text-main">
                                                PayPal
                                            </h3>

                                            <p class="mt-2 text-sm leading-6 theme-text-muted">
                                                {{ __('Pay securely using PayPal. Your PayPal account can be saved for future subscription payments.') }}
                                            </p>
                                        </div>

                                        <div class="checkout-chip-row">
                                            <span class="checkout-chip">
                                                PayPal
                                            </span>

                                            <span class="checkout-chip">
                                                Visa
                                            </span>

                                            <span class="checkout-chip">
                                                <span class="checkout-mastercard" aria-hidden="true"></span>

                                                Mastercard
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-5 rounded-[1.25rem] border p-4 theme-surface-box">
                                        <div class="checkout-summary-line">
                                            <span>
                                                {{ __('Selected plan') }}
                                            </span>

                                            <strong>
                                                {{ $selectedPlan->label }}
                                            </strong>
                                        </div>

                                        <div class="checkout-summary-line">
                                            <span>
                                                {{ __('Amount') }}
                                            </span>

                                            <strong>
                                                {{ number_format((float) $selectedPlan->price_for_international, 2) }}

                                                {{ getPaypalCurrency() }}
                                            </strong>
                                        </div>

                                        <div class="checkout-summary-line">
                                            <span>
                                                {{ __('Access period') }}
                                            </span>

                                            <strong>
                                                {{ __(':days days', [
                                                    'days' => $selectedPlan->duration_days,
                                                ]) }}
                                            </strong>
                                        </div>

                                        @if (IsPayPalConfigured())
                                            <div class="mt-5 checkout-consent-panel">
                                                <label class="checkout-consent-label">
                                                    <input id="paypal-auto-renew-consent" type="checkbox"
                                                        class="checkout-consent-checkbox">

                                                    <span>
                                                        {{ __(
                                                            'I agree that my PayPal payment method may be securely saved and that :amount :currency may be charged automatically every :days days until I turn off auto-renewal.',
                                                            [
                                                                'amount' => number_format((float) $selectedPlan->price_for_international, 2),

                                                                'currency' => getPaypalCurrency(),

                                                                'days' => $selectedPlan->duration_days,
                                                            ],
                                                        ) }}
                                                    </span>
                                                </label>

                                                <p class="checkout-consent-note">
                                                    {{ __('You can manage or delete your saved PayPal payment method later from your profile.') }}
                                                </p>
                                            </div>
                                        @endif

                                        @if ($savedPaypalMethod)
                                            <div class="mt-5 rounded-[1.25rem] border p-4 theme-surface-box">
                                                <div
                                                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <p class="text-sm font-semibold theme-text-main">
                                                            {{ __('Saved PayPal Account') }}
                                                        </p>

                                                        <p class="mt-1 text-sm theme-text-muted">
                                                            {{ $savedPaypalMethod->display_name ?: $savedPaypalMethod->email ?: __('Saved PayPal payment method') }}
                                                        </p>

                                                        @if ($savedPaypalMethod->display_name && $savedPaypalMethod->email)
                                                            <p class="mt-1 text-xs theme-text-muted">
                                                                {{ $savedPaypalMethod->email }}
                                                            </p>
                                                        @endif
                                                    </div>

                                                    @if ($savedPaypalMethod->is_default)
                                                        <span
                                                            class="rounded-full px-3 py-1 text-xs font-semibold theme-chip-success">
                                                            {{ __('Primary') }}
                                                        </span>
                                                    @endif
                                                </div>

                                                <div class="mt-4">
                                                    <button type="button" id="paypal-saved-method-button"
                                                        data-payment-method-id="{{ $savedPaypalMethod->id }}"
                                                        class="checkout-method-button is-primary">
                                                        {{ __('Pay with Saved PayPal') }}
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="my-5 flex items-center gap-3">
                                                <div class="h-px flex-1 bg-[rgb(var(--border))]"></div>

                                                <span
                                                    class="text-xs font-semibold uppercase tracking-[0.18em] theme-text-muted">
                                                    {{ __('Or use another PayPal account') }}
                                                </span>

                                                <div class="h-px flex-1 bg-[rgb(var(--border))]"></div>
                                            </div>
                                        @endif

                                        <div class="{{ $savedPaypalMethod ? '' : 'mt-5' }}">
                                            @if (IsPayPalConfigured())
                                                <div id="paypal-button-shell" class="pointer-events-none opacity-60">
                                                    <div id="paypal-button-container"></div>
                                                </div>
                                            @else
                                                <div class="checkout-feedback is-visible error">
                                                    {{ __('PayPal credentials are not configured yet, so PayPal payment is currently unavailable.') }}
                                                </div>
                                            @endif
                                        </div>

                                        <div id="checkout-feedback" class="checkout-feedback"></div>
                                    </div>
                                </div>
                                {{-- @elseif(get_current_country() == 'Georgia') --}}
                                <div class="checkout-method-card checkout-tbc-renew-card">
                                    <div class="checkout-tbc-renew-copy">
                                        <h3 class="checkout-tbc-renew-title">
                                            {{ __('Enable automatic renewal.') }}
                                        </h3>

                                        <p class="checkout-tbc-renew-text">
                                            {{ __('Automatic renewal is available only for Georgian-issued cards. International cards, Apple Pay and Google Pay are processed as one-time payments.') }}
                                        </p>
                                    </div>

                                    <div class="mt-5 checkout-consent-panel">
                                        <label class="checkout-consent-label">
                                            <input id="fitt-payment-consent" type="checkbox"
                                                class="checkout-consent-checkbox">

                                            <span>
                                                {{ __(
                                                    'I agree that my TBC / Flitt card may be securely saved and charged automatically every :days days until I turn off auto-renewal.',
                                                    [
                                                        'days' => $selectedPlan->duration_days,
                                                    ],
                                                ) }}
                                            </span>
                                        </label>

                                        <p class="checkout-consent-note">
                                            {{ __('You can manage or delete this saved card later from your profile.') }}
                                        </p>
                                    </div>

                                    <div class="checkout-tbc-renew-actions">
                                        @if ($savedTbcCard)
                                            <button type="button" id="fitt-saved-card-button"
                                                data-payment-method-id="{{ $savedTbcCard->id }}"
                                                disabled
                                                class="checkout-method-button is-tbc-outline">
                                                {{ __('Pay with saved card') }}

                                                @if ($savedTbcCard->masked_card)
                                                    — {{ $savedTbcCard->card_type }}
                                                    {{ $savedTbcCard->masked_card }}
                                                @endif
                                            </button>
                                        @else
                                            <button type="button" id="fitt-checkout-button"
                                                disabled
                                                class="checkout-method-button is-tbc-brand">
                                                {{ __('Pay with TBC / flitt') }}
                                            </button>
                                        @endif
                                    </div>

                                    <p id="fitt-feedback" class="checkout-feedback"></p>
                                </div>

                                <div class="checkout-method-card checkout-tbc-renew-card">
                                    <div class="checkout-tbc-renew-copy">
                                        <h3 class="checkout-tbc-renew-title">
                                            {{ __('Bank of Georgia') }}
                                        </h3>

                                        <p class="checkout-tbc-renew-text">
                                            {{ __(
                                                'Automatic renewal is always enabled for BOG card subscriptions. You can delete or change the saved card later from your profile.',
                                            ) }}
                                        </p>
                                    </div>

                                    <div class="mt-5 checkout-consent-panel">
                                        <label class="checkout-consent-label">
                                            <input id="bog-payment-consent" type="checkbox"
                                                class="checkout-consent-checkbox">

                                            <span>
                                                {{ __(
                                                    'I agree that my BOG card may be securely saved and charged automatically every :days days until I turn off auto-renewal.',
                                                    [
                                                        'days' => $selectedPlan->duration_days,
                                                    ],
                                                ) }}
                                            </span>
                                        </label>

                                        <p class="checkout-consent-note">
                                            {{ __('You can manage or delete this saved BOG card later from your profile.') }}
                                        </p>
                                    </div>

                                    <div class="checkout-tbc-renew-actions">
                                        @if ($savedBogCard)
                                            <button type="button" id="bog-saved-card-button"
                                                data-payment-method-id="{{ $savedBogCard->id }}"
                                                disabled
                                                class="checkout-method-button is-tbc-outline">
                                                {{ __('Pay with saved BOG card') }}

                                                @if ($savedBogCard->masked_card)
                                                    —
                                                    {{ strtoupper($savedBogCard->card_type) }}

                                                    {{ $savedBogCard->masked_card }}
                                                @endif
                                            </button>

                                            <button type="button" id="bog-checkout-button"
                                                disabled
                                                class="checkout-method-button is-tbc-brand">
                                                {{ __('Pay with a new BOG card') }}
                                            </button>
                                        @else
                                            <button type="button" id="bog-checkout-button"
                                                disabled
                                                class="checkout-method-button is-tbc-brand">
                                                {{ __('Pay with BOG') }}
                                            </button>
                                        @endif
                                    </div>

                                    <p id="bog-feedback" class="checkout-feedback"></p>
                                </div>
                            </div>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>
@endsection
