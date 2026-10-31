@extends('users.layout.main')
@section('title', __('Tennis Fixtures · BF Markets'))
@section('content')
    @include('users.layout.sports')
    @include('users.sports.tennis.tennis_filter')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-extrabold text-[var(--text-brand-70)] dark:text-[var(--text-on-primary)]">🎾 {{ __('Tennis Fixtures') }}</h1>
        <label class="flex items-center gap-2 text-sm font-semibold text-[var(--text-secondary-strong)]">{{ __('Market:') }}
            <select id="marketDropdown" class="market-dropdown max-w-[260px] rounded-lg border border-[var(--border-control)] bg-[var(--surface-row)] p-2 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))] dark:text-[var(--text-on-primary)]">
                @foreach ($getAllMarkets['options'] as $option)
                    <option value="{{ $option['value'] }}" @selected(request('market_name', 'MATCH_ODDS') === $option['value'])>{{ __($option['label']) }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <section id="tennisDashboard" data-login-url="{{ route('login') }}" data-favorite-url="{{ route('favorites.toggle') }}" data-source-url="{{ route('tennis.filter-tennis') }}" data-source-method="POST" data-source-kind="fixtures" class="min-w-0 rounded-2xl border border-[var(--border-neutral-5-6)] bg-[rgb(var(--surface))] p-3 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]">
        @include('users.sports.tennis.tennisDetails', ['data' => $data])
        <div class="flex justify-center py-8 text-sm font-semibold text-[var(--text-slate-300)] {{ $hasMore ? '' : 'hidden' }}" data-tennis-lazy-loader data-has-more="{{ $hasMore ? 'true' : 'false' }}" data-next-page="{{ $nextPage }}">
            <span data-lazy-loader-text>↓ {{ __('Scroll for more matches') }} ↓</span>
        </div>
    </section>
    @include('popup')
@endsection
@push('js')
    @vite('resources/users/js/sports/tennis.js')
@endpush
