@extends('users.layouts.app', ['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('home.en'), 'moneywayUrl' => route('football.moneyway.page')])

@section('title', 'BF Markets | ' . $pageTitle)
@section('meta_description', $pageSubtitle)

@push('css')
    @include('users.layouts.partials.fixtures.theme-styles')
    <style>
        .horse-racing-content > :first-child {
            margin-top: 0 !important;
        }

        .horse-racing-content p {
            margin: 0 0 1rem;
        }

        .horse-racing-content ol {
            list-style: decimal;
            padding-left: 1.5rem;
            margin: 0 0 1rem;
        }

        .horse-racing-content ul {
            list-style: disc;
            padding-left: 1.5rem;
            margin: 0 0 1rem;
        }

        .horse-racing-content li {
            margin-bottom: 0.5rem;
        }
    </style>
@endpush

@section('content')
    @php
        $access = checkAuthAndSubscription();
        $canViewHorseRacingContent = $access['has_active_subscription'] === true;
    @endphp

    <div class="betwatch-home">
        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => 'horse-racing'])

        <section class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-6xl">
                <div class="countrywise-card p-6 sm:p-8">
                    <a href="{{ route('horse-racing') }}" class="text-sm font-semibold theme-text-primary">
                        {{ __('Horse Racing') }}
                    </a>

                    <h1 class="mt-3 text-3xl font-bold theme-text-main sm:text-4xl">
                        {{ __($pageTitle) }}
                    </h1>

                    <p class="mt-3 max-w-3xl text-sm leading-7 theme-text-muted">
                        {{ __($pageSubtitle) }}
                    </p>
                </div>

                <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
                    <article class="countrywise-card p-6 sm:p-8">
                        @if ($canViewHorseRacingContent)
                            @if (filled($pageContent))
                                <div class="horse-racing-content text-sm leading-8 theme-text-soft">
                                    {!! $pageContent !!}
                                </div>
                            @else
                                <p class="text-sm font-semibold theme-text-muted">
                                    {{ __('This horse racing analysis will be updated soon.') }}
                                </p>
                            @endif
                        @else
                            <div class="rounded-2xl p-6 text-center theme-bg-soft">
                                <h2 class="text-xl font-bold theme-text-main">
                                    {{ __('Premium Horse Racing Content') }}
                                </h2>
                                <p class="mt-3 text-sm leading-7 theme-text-muted">
                                    {{ __('Please register, log in, or activate a subscription to view daily horse racing analysis.') }}
                                </p>
                                <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                                    <a href="{{ route('register') }}" class="rounded-2xl px-5 py-3 text-sm font-semibold text-white betguru-gradient-cta">
                                        {{ __('Start Free Trial') }}
                                    </a>
                                    <a href="{{ route('customer.login') }}" class="rounded-2xl px-5 py-3 text-sm font-semibold theme-border-soft theme-text-main">
                                        {{ __('Login') }}
                                    </a>
                                </div>
                            </div>
                        @endif
                    </article>

                    <aside class="countrywise-card p-6">
                        <h2 class="text-xl font-bold theme-text-main">{{ __('Race Charts') }}</h2>
                        <p class="mt-2 text-sm theme-text-muted">
                            {{ __('Race and rating charts will be displayed here soon.') }}
                        </p>
                        <div class="mt-4 flex min-h-[260px] items-center justify-center rounded-2xl border border-dashed theme-border-soft theme-bg-soft">
                            <span class="text-sm font-semibold theme-text-muted">
                                {{ __('Charts Placeholder') }}
                            </span>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>

    @unless ($canViewHorseRacingContent)
        @include('popup')
    @endunless
@endsection
