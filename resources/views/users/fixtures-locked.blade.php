@extends('users.layout.main')

@section('title', __('Fixtures · BF Markets'))

@section('content')
    @include('users.layout.sports')
    <section class="my-8 rounded-2xl border border-[var(--border-neutral-5-5)] bg-[var(--surface-neutral-0-10)] p-6 text-center dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))]" aria-labelledby="premiumHeading">
        <i class="fas fa-lock mb-3 text-2xl text-[rgb(var(--primary))]" aria-hidden="true"></i>
        <h1 id="premiumHeading" class="text-xl font-bold text-[rgb(var(--text-main))] dark:text-[var(--text-on-primary)]">{{ __('Registration and subscription required') }}</h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">{{ __('Register and activate your 7-day free trial (first-time users), or purchase a paid subscription to access Fixtures, Market Activity, Pinnacle Drops, Horse Racing and Greyhounds.') }}</p>
        <button type="button" data-premium-required class="mt-5 cursor-pointer rounded-xl bg-[var(--surface-heading)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--surface-primary)] dark:bg-[var(--button-primary)]">{{ __('View access options') }}</button>
    </section>
@endsection
