@extends('users.layouts.app', ['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('home.en'), 'moneywayUrl' => route('football.moneyway.page')])

@section('title', 'BF Markets | Horse Racing')
@section('meta_description', 'BF Markets horse racing analysis, selections, expert tips, and market consensus.')

@push('css')
    @include('users.layouts.partials.fixtures.theme-styles')
@endpush

@section('content')
    <div class="betwatch-home">
        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => 'horse-racing'])

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-6xl">
                <div class="countrywise-card p-6 sm:p-8">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] theme-text-primary">
                        {{ __('Horse Racing') }}
                    </p>

                    <h1 class="mt-3 text-3xl font-bold theme-text-main sm:text-4xl">
                        {{ __('Horse Racing') }}
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-7 theme-text-muted">
                        {{ __('Daily race analysis, tactical selections, expert tips, and market consensus from BF Markets.') }}
                    </p>
                </div>

                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    <a href="{{ route('horse-racing.analysis') }}" class="countrywise-card p-6 transition hover:-translate-y-0.5">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] theme-text-primary">
                            {{ __('Race Analysis & Selections') }}
                        </p>
                        <h2 class="mt-3 text-2xl font-bold theme-text-main">
                            {{ __('Race Analysis & Selections') }}
                        </h2>
                        <p class="mt-3 text-sm leading-7 theme-text-muted">
                            {{ __('Detailed tactical previews and key horse selections.') }}
                        </p>
                    </a>

                    <a href="{{ route('horse-racing.consensus') }}" class="countrywise-card p-6 transition hover:-translate-y-0.5">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] theme-text-primary">
                            {{ __('Market Consensus & Expert Tips') }}
                        </p>
                        <h2 class="mt-3 text-2xl font-bold theme-text-main">
                            {{ __('Market Consensus & Expert Tips') }}
                        </h2>
                        <p class="mt-3 text-sm leading-7 theme-text-muted">
                            {{ __('Aggregated expert tips and global market consensus statistics.') }}
                        </p>
                    </a>
                </div>

                <div class="mt-6 countrywise-card p-6">
                    <h2 class="text-xl font-bold theme-text-main">{{ __('Race Charts') }}</h2>
                    <div class="mt-4 flex min-h-[220px] items-center justify-center rounded-2xl border border-dashed theme-border-soft theme-bg-soft">
                        <p class="text-sm font-semibold theme-text-muted">
                            {{ __('Race and rating charts will be displayed here soon.') }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
