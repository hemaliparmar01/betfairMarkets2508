@extends('users.layouts.app',['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('darts'),'moneywayUrl' => route('darts.moneyway.page')])

@section('title', 'BetGuru | Darts Match')
@section('meta_description', 'BetGuru darts match overview.')

@push('css')
    @include('users.layouts.partials.fixtures.theme-styles')
    @include('users.layouts.partials.fixtures.leaguewise-styles')
@endpush

@section('content')
    @php
        $detailColumnOptions = [
            ['key' => 'odd', 'label' => 'Odd', 'default' => true],
            ['key' => 'delta', 'label' => 'Odd +/-', 'default' => true],
            ['key' => 'volume', 'label' => 'Volume', 'default' => true],
            ['key' => 'aggregated', 'label' => 'Aggregated', 'default' => false],
            ['key' => 'share', 'label' => '% of total', 'default' => true],
        ];

        $detailMarkets = $detailMarkets ?? [];
        $homeTeam = $match['team_1'] ?? 'Home Team';
        $awayTeam = $match['team_2'] ?? 'Away Team';
        $isLive = (bool) ($match['is_live'] ?? false);
        $scoreText = $match['score_text'] ?? '0 - 0';
        $matchLeagueId = $match['league_id'] ?? null;
        $homeTeamFavoriteId = 'home:' . ($match['league_id'] ?? '0') . ':' . \Illuminate\Support\Str::slug($homeTeam);
        $awayTeamFavoriteId = 'away:' . ($match['league_id'] ?? '0') . ':' . \Illuminate\Support\Str::slug($awayTeam);
    @endphp

    <div data-match-details-page class="countrywise-page leaguewise-page">
        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => 'darts'])

        <section class="countrywise-main">
            <div class="countrywise-grid countrywise-grid--no-support">
                <aside class="countrywise-sidebar">
                    <article class="betwatch-leagues-card">
                        <h2 class="betwatch-leagues-title">{{ __('All leagues') }}</h2>

                        <div class="betwatch-filter-box">
                            <svg class="betwatch-filter-icon" viewBox="0 0 20 20" fill="none"
                                stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
                                stroke-linejoin="round" aria-hidden="true">
                                <path d="M3.5 5.5h13" />
                                <path d="M6.5 10h7" />
                                <path d="M8.75 14.5h2.5" />
                            </svg>

                            <input data-country-search type="text" class="betwatch-filter-input" placeholder="{{ __('Filter') }}">
                        </div>

                        @include('users.sports.Darts.partials.country', ['countries' => $countries])
                    </article>
                </aside>

                <div class="countrywise-content leaguewise-content">
                    <article class="countrywise-card leaguewise-summary-card">
                        <div class="leaguewise-summary-top">
                            <div class="leaguewise-breadcrumbs">
                                <a href="{{ route('darts') }}"><span>Darts</span></a>
                                <span class="leaguewise-breadcrumb-sep">></span>

                                <a href="{{ filled($match['country'] ?? null) ? route('darts.country.page', ['country' => $match['country']]) : '#' }}">
                                    <span class="leaguewise-breadcrumb-country">
                                        @php $flagCode = strtolower($match['country_flag'] ?? ''); @endphp
                                        @if($flagCode && $flagCode !== 'international')
                                            <img class="betguru-flag-img" src="https://flagcdn.com/w40/{{ $flagCode }}.png" alt="{{ $match['country_name'] ?? 'Country' }} flag">
                                        @endif
                                        <span>{{ $match['country_name'] ?? 'International' }}</span>
                                    </span>
                                </a>

                                <span class="leaguewise-breadcrumb-sep">></span>

                                <a href="{{ filled($matchLeagueId) ? route('darts.league.page', ['league_id' => $matchLeagueId]) : '#' }}">
                                    <span>{{ $match['league'] ?? 'League' }}</span>
                                </a>
                            </div>

                            <svg class="betwatch-star-icon leaguewise-summary-favorite {{ (int) ($match['is_match_fav'] ?? 0) === 1 ? 'is-favorite' : '' }}"
                                data-favorite-trigger
                                data-favorite-sport="darts"
                                data-favorite-type="match"
                                data-favorite-league-id="{{ $match['league_id'] ?? '' }}"
                                data-favorite-match-id="{{ $match['match_id'] ?? '' }}"
                                data-favorite-label="{{ trim($homeTeam . ' - ' . $awayTeam, ' -') }}"
                                viewBox="0 0 20 20" fill="none"
                                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                                aria-label="Toggle match favorite">
                                <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                            </svg>
                        </div>

                        <div class="leaguewise-scoreboard {{ $isLive ? 'leaguewise-scoreboard--live' : 'leaguewise-scoreboard--upcoming' }}">
                            <div class="leaguewise-team leaguewise-team--home">
                                <svg class="betwatch-star-icon"
                                    data-favorite-trigger
                                    data-favorite-sport="darts"
                                    data-favorite-type="team"
                                    data-favorite-league-id="{{ $match['league_id'] ?? '' }}"
                                    data-favorite-match-id="{{ $match['match_id'] ?? '' }}"
                                    data-favorite-team-id="{{ $homeTeamFavoriteId }}"
                                    data-favorite-label="{{ $homeTeam }}"
                                    viewBox="0 0 20 20" fill="none"
                                    stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                                    aria-label="Toggle home team favorite">
                                    <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                                </svg>

                                <span class="leaguewise-team-name">{{ $homeTeam }}</span>
                            </div>

                            <div class="leaguewise-scorebox">
                                <div class="leaguewise-score">{{ $scoreText }}</div>
                                <div class="leaguewise-minute">
                                    {{ __($match['status_label'] ?? 'Live') }}
                                </div>
                            </div>

                            <div class="leaguewise-team leaguewise-team--away">
                                <span class="leaguewise-team-name">{{ $awayTeam }}</span>

                                <svg class="betwatch-star-icon"
                                    data-favorite-trigger
                                    data-favorite-sport="darts"
                                    data-favorite-type="team"
                                    data-favorite-league-id="{{ $match['league_id'] ?? '' }}"
                                    data-favorite-match-id="{{ $match['match_id'] ?? '' }}"
                                    data-favorite-team-id="{{ $awayTeamFavoriteId }}"
                                    data-favorite-label="{{ $awayTeam }}"
                                    viewBox="0 0 20 20" fill="none"
                                    stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                                    aria-label="Toggle away team favorite">
                                    <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                                </svg>
                            </div>
                        </div>

                        <div class="countrywise-main-tabs leaguewise-tabs">
                            @foreach ($match['tabs'] ?? [] as $tab)
                                <button type="button" class="countrywise-main-tab"
                                    data-match-page-tab="{{ $tab['key'] }}">
                                    {{ $tab['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </article>

                    <div data-match-overview-panel>
                        @include('users.layouts.partials.fixtures.leaguewise-darts-market-board', ['league' => $match])
                    </div>

                    @include('users.layouts.partials.fixtures.match-details', ['detailMarkets' => $detailMarkets, 'detailColumnOptions' => $detailColumnOptions])

                </div>
            </div>
        </section>
    </div>
    @include('popup')
@endsection
