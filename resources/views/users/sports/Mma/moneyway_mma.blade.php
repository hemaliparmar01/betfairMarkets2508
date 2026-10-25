@extends('users.layouts.app',['activeHeaderUrl' => 'moneyway', 'fixtuesUrl' => route('mma'),'moneywayUrl' => route('mma.moneyway.page')])

@section('title', 'BetGuru | MMA Moneyway')
@section('meta_description', 'BetGuru mma moneyway overview.')

@push('css')
    @include('users.layouts.partials.fixtures.theme-styles')
    <style>
        .betwatch-moneyway-layout {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 20px;
        }

        .betwatch-moneyway-filter-card,
        .betwatch-moneyway-results-card {
            background: rgb(var(--surface));
            border: 1px solid rgb(var(--border));
            border-radius: 24px;
        }

        .betwatch-moneyway-filter-card {
            padding: 1.45rem 0.95rem 0.95rem;
        }

        .betwatch-moneyway-filter-title {
            font-size: 0.82rem;
            font-weight: 700;
            margin: 0;
        }

        .betwatch-moneyway-filter-group {
            margin-bottom: 0.60rem;
        }

        .betwatch-moneyway-filter-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .betwatch-moneyway-filter-reset {
            font-size: 0.82rem;
            color: rgb(var(--primary));
        }

        .betwatch-moneyway-filter-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 500;
            text-align: center;
            color: rgb(var(--text-main));
        }

        .betwatch-moneyway-range-shell {
            position: relative;
            height: 24px;
        }

        .betwatch-moneyway-range-track,
        .betwatch-moneyway-range-fill {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            height: 4px;
            border-radius: 999px;
        }

        .betwatch-moneyway-range-track {
            left: 0;
            right: 0;
            background: rgb(var(--border-strong));
        }

        .betwatch-moneyway-range-fill {
            background: rgb(var(--text-main));
        }

        .betwatch-moneyway-range {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 24px;
            margin: 0;
            background: transparent;
            pointer-events: none;
            -webkit-appearance: none;
            appearance: none;
        }

        .betwatch-moneyway-range::-webkit-slider-runnable-track {
            height: 4px;
            background: transparent;
        }

        .betwatch-moneyway-range::-moz-range-track {
            height: 4px;
            background: transparent;
        }

        .betwatch-moneyway-range::-webkit-slider-thumb {
            width: 16px;
            height: 16px;
            border: 0;
            border-radius: 50%;
            background: rgb(var(--text-main));
            cursor: pointer;
            pointer-events: auto;
            -webkit-appearance: none;
            appearance: none;
            margin-top: -6px;
        }

        .betwatch-moneyway-range::-moz-range-thumb {
            width: 16px;
            height: 16px;
            border: 0;
            border-radius: 50%;
            background: rgb(var(--text-main));
            cursor: pointer;
            pointer-events: auto;
        }

        .betwatch-moneyway-range--min {
            z-index: 2;
        }

        .betwatch-moneyway-range--max {
            z-index: 3;
        }

        .betwatch-moneyway-filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .betwatch-moneyway-filter-input {
            width: 100%;
            border: 1px solid rgb(var(--border));
            border-radius: 14px;
            padding: 0.6rem;
            font-size: 0.82rem;
            color: rgb(var(--text-main));
        }

        .betwatch-moneyway-market-menu {
            position: relative;
        }

        .betwatch-moneyway-market-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            width: 260px;
            background: rgb(var(--surface));
            border: 1px solid rgb(var(--border));
            border-radius: 16px;
            padding: 12px;
            box-shadow: 0 20px 40px rgb(var(--shadow-soft) / 0.12);
            z-index: 50;
        }

        .betwatch-moneyway-market-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 4px;
            font-size: 0.8rem;
        }

        .betwatch-moneyway-switch {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-top: 0.8rem;
            font-size: 0.82rem;
        }

        .betwatch-moneyway-switch-button {
            width: 40px;
            height: 22px;
            border-radius: 999px;
            background: rgb(var(--border-strong));
            position: relative;
            transition: 0.2s ease;
        }

        .betwatch-moneyway-switch-button::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 3px;
            width: 16px;
            height: 17px;
            border-radius: 50%;
            background: rgb(var(--surface));
            transition: 0.2s ease;
        }

        .betwatch-moneyway-switch-button.is-active {
            background: rgb(var(--text-main));
        }

        .betwatch-moneyway-switch-button.is-active::after {
            left: 21px;
        }

        @media (max-width: 991px) {
            .betwatch-moneyway-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $moneywayTabs = [
            ['key' => 'live', 'label' => __('Live')],
            ['key' => 'upcoming', 'label' => __('Upcoming')],
            ['key' => 'finished', 'label' => __('Finished')],
            ['key' => 'favorites', 'label' => __('Favorites')],
            ['key' => 'by_time', 'label' => __('By time')],
        ];
    @endphp

    <div data-moneyway-page data-match-cards-page data-sport="mma" data-request-method="POST" data-page-size="10" data-refresh-url="{{ route('mma.moneyway.match-cards.partial') }}" data-country-id="{{ $country_id ?? '' }}" data-league-id="{{ (int) ($league_id ?? 0) }}" class="betwatch-home">
        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => 'mma'])

        <section class="betwatch-main-shell">
            <div class="betwatch-moneyway-layout">
                <aside>
                    @include("users.layouts.partials.fixtures.moneyway-filter")

                    <article  class="betwatch-leagues-card mt-5">
                        <h2 class="betwatch-leagues-title">{{ __('All leagues') }}</h2>

                        <div class="betwatch-filter-box">
                            <svg class="betwatch-filter-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3.5 5.5h13" />
                                <path d="M6.5 10h7" />
                                <path d="M8.75 14.5h2.5" />
                            </svg>

                            <input data-country-search type="text" class="betwatch-filter-input" placeholder="{{ __('Filter') }}">
                        </div>

                        @include('users.sports.Mma.partials.country', ['countries' => $countries])
                    </article>
                </aside>

                <section>
                    <article class="betwatch-toolbar betwatch-moneyway-results-card">
                        @include('users.layouts.partials.fixtures.mma-toolbar-day-head', [
                            'toolbarRoute' => route('mma.moneyway.match-cards.partial'),
                            'requestMethod' => 'POST',
                            'requestPayload' => array_filter([
                                'country_id' => $country_id ?? null,
                                'league_id' => $league_id ?? null,
                            ]),
                        ])
                        @include("users.layouts.partials.fixtures.moneyway-tabs")
                    </article>


                    <div data-matchcards-container class="betwatch-center-column" data-mma-matchcards-container></div>
                    <div data-matchcards-sentinel class="betwatch-sentinel" data-mma-matchcards-sentinel></div>

                    @include('users.layouts.partials.fixtures.matchCard.card-loader')
                </section>
            </div>
        </section>
    </div>
    @include('popup')
@endsection
