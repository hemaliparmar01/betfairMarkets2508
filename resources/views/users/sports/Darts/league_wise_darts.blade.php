@extends('users.layouts.app',['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('darts'),'moneywayUrl' => route('darts.moneyway.page')])

@section('title', 'BF Markets | League Fixtures')
@section('meta_description', 'BetGuru darts league fixtures overview.')

@push('css')
    @include('users.layouts.partials.fixtures.theme-styles')
    @include('users.layouts.partials.fixtures.leaguewise-styles')
@endpush

@section('content')
    @php
        $leagueTab = [['key' => 'fixtures', 'label' => 'Fixtures'], ['key' => 'moneyway', 'label' => 'Moneyway']];
        $marketTabs = [
            ['key' => 'live', 'label' => __('Live')],
            ['key' => 'upcoming', 'label' => __('Upcoming')],
            ['key' => 'finished', 'label' => __('Finished')],
            ['key' => 'favorites', 'label' => __('Favorites')],
        ];
    @endphp

    <div data-match-cards-page data-sport="darts" data-refresh-url='{{ route('darts.home.match-cards.partial', ['tab' => 'league_wise_page', 'league_id' => $league_id]) }}' class="betwatch-home">
        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => 'darts'])
        <section class="betwatch-main-shell">
            <div class="betwatch-main-grid">
                <aside class="countrywise-sidebar">
                    <article class="betwatch-leagues-card">
                        <h2 class="betwatch-leagues-title">{{ __('All leagues') }}</h2>

                        <div class="betwatch-filter-box">
                            <svg class="betwatch-filter-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3.5 5.5h13" />
                                <path d="M6.5 10h7" />
                                <path d="M8.75 14.5h2.5" />
                            </svg>

                            <input data-country-search type="text" class="betwatch-filter-input" placeholder="{{ __('Filter') }}">
                        </div>

                        @include('users.sports.Darts.partials.country', ['countries' => $countries])
                    </article>
                </aside>

                <div class="countrywise-content">
                    <article class="countrywise-card countrywise-hero">
                        <div class="countrywise-hero-top">
                            <span aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="h-[42px] w-[42px] text-[rgb(var(--primary))]">
                                    <path d="M8 21h8" />
                                    <path d="M12 17v4" />
                                    <path d="M7 4H4a1 1 0 0 0-1 1c0 3.1 2 5.7 4.8 6.6" />
                                    <path d="M17 4h3a1 1 0 0 1 1 1c0 3.1-2 5.7-4.8 6.6" />
                                    <path d="M8 4h8v4a4 4 0 0 1-8 0V4Z" />
                                </svg>
                            </span>

                            <h1 class="countrywise-title">{{ $league['league'] ?? 'Darts League' }}</h1>

                            <svg  class="betwatch-star-icon {{ (int) ($league['is_league_fav'] ?? 0) === 1 ? 'is-favorite' : '' }} ml-auto"
                                data-favorite-trigger
                                data-favorite-sport="darts"
                                data-favorite-type="league"
                                data-favorite-league-id="{{ $league['league_id'] ?? $league_id }}"
                                data-favorite-label="{{ $league['league'] ?? 'Darts League' }}"
                                viewBox="0 0 20 20" fill="none" stroke-width="1.7" stroke-linecap="round"
                                stroke-linejoin="round" aria-label="Toggle league favorite"
                               >
                                <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                            </svg>
                        </div>

                        <div class="countrywise-main-tabs">
                            @foreach ($leagueTab as $tab)
                                <button type="button" class="countrywise-main-tab"
                                    @if ($tab['key'] === 'moneyway')
                                        data-navigation-url="{{ route('darts.league.moneyway.page', ['league_id' => $league_id]) }}"
                                    @else
                                    @endif>
                                    {{ $tab['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </article>

                    <article class="countrywise-card countrywise-filter-bar">
                        <div class="countrywise-filter-pills">
                            @foreach ($marketTabs as $tab)
                                <button type="button" class="betwatch-toolbar-pill betwatch-ui-pill"
                                    data-match-tab="{{ $tab['key'] }}">
                                    {{ $tab['label'] }}
                                </button>
                            @endforeach
                        </div>

                        @include('users.layouts.partials.fixtures.darts-toolbar-day-head', [
                            'toolbarRoute' => route('darts.home.match-cards.partial', [
                                'tab' => 'league_wise_page',
                                'league_id' => $league_id,
                            ]),
                        ])
                    </article>


                    <div data-matchcards-container class="betwatch-center-column" data-darts-matchcards-container></div>
                    <div data-matchcards-sentinel class="betwatch-sentinel" data-darts-matchcards-sentinel></div>

                    @include('users.layouts.partials.fixtures.matchCard.card-loader')
                </div>
            </div>
        </section>
    </div>
    @include('popup')
@endsection


