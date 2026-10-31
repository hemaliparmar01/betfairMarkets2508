@extends('users.layout.main')
@section('title', __('Horse Racing Fixtures · BF Markets'))
@section('content')
    @include('users.layout.sports')
    @include('users.sports.horseracing.horseracing_filter')
    <h1 class="mb-4 text-xl font-extrabold text-[var(--text-brand-70)] dark:text-[var(--text-on-primary)]">🏇 {{ __('Horse Racing Fixtures') }}</h1>
    <section id="horseracingDashboard" data-market-chart-url="{{ env('BETFAIR_API_KEY') }}" data-login-url="{{ route('login') }}" data-favorite-url="{{ route('favorites.toggle') }}" data-source-url="{{ route('horseracing.filter-horseracing') }}" data-source-method="POST" data-source-kind="fixtures" class="min-w-0 rounded-2xl border border-[var(--border-neutral-5-6)] bg-[rgb(var(--surface))] p-3 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]">
        @include('users.sports.horseracing.horseracingDetails')
        <div class="flex justify-center py-8 text-sm font-semibold text-[var(--text-slate-300)] {{ $hasMore ? '' : 'hidden' }}" data-horseracing-lazy-loader data-has-more="{{ $hasMore ? 'true' : 'false' }}" data-next-page="{{ $nextPage }}"><span data-lazy-loader-text>↓ {{ __('Scroll for more races') }} ↓</span></div>
    </section>
    @include('popup')
@endsection
@push('js')
    @vite('resources/users/js/sports/horseracing.js')
@endpush
