@extends('users.layout.main')

@section('title', __('Fixtures · BF Markets'))

@section('content')
    @include('users.layout.sports')
    <section class="my-4 min-w-0" data-fixtures-locked aria-labelledby="fixturesHeading">
        <h1 id="fixturesHeading" class="mb-3 text-xl font-extrabold text-[var(--text-brand-70)] dark:text-[var(--text-on-primary)]">{{ __('Fixtures') }}</h1>
        <div class="mb-4 rounded-xl border border-[var(--border-control)] bg-[var(--surface-control)] p-4 text-sm text-[rgb(var(--text-main))] dark:bg-[rgb(var(--surface-soft))]" role="status">
            <p>{{ __('Log in with an active trial or subscription to view fixtures and odds.') }}</p>
            <div class="mt-3 flex flex-wrap gap-3 font-semibold">
                <a href="{{ route('login') }}" class="text-[rgb(var(--primary))] underline">{{ __('Log in') }}</a>
                <a href="{{ route('register') }}" class="text-[rgb(var(--primary))] underline">{{ __('Register') }}</a>
                <a href="{{ route('subscriptions') }}" class="text-[rgb(var(--primary))] underline">{{ __('View subscriptions') }}</a>
            </div>
        </div>
        <div class="fixtures-container min-w-0 rounded-xl border border-[var(--border-control)] p-4" id="fixturesContainer" aria-label="{{ __('Fixtures') }}">
            <div class="grid grid-cols-3 gap-3 text-center text-xs font-semibold text-[rgb(var(--text-muted))] sm:grid-cols-5">
                <span>{{ __('Time') }}</span><span>{{ __('Match') }}</span><span>{{ __('Score') }}</span>
                <span class="hidden sm:block">{{ __('Back') }}</span><span class="hidden sm:block">{{ __('Lay') }}</span>
            </div>
            <div class="mt-3 grid grid-cols-3 items-center gap-3 rounded-xl border border-[var(--border-control)] bg-[var(--surface-control)] p-5 sm:grid-cols-5 dark:bg-[rgb(var(--surface-soft))]" data-locked-fixture-row>
                @foreach (['Time', 'Match', 'Score', 'Back', 'Lay'] as $field)
                    <span @class(['flex justify-center', 'hidden sm:flex' => in_array($field, ['Back', 'Lay'], true)]) role="img" aria-label="{{ __('Registration and subscription required') }}">@include('lock_icon')</span>
                @endforeach
            </div>
        </div>
    </section>
@endsection
