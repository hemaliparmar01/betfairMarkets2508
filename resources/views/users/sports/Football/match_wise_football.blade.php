@extends('users.layouts.app',['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('home.en'),'moneywayUrl' => route('football.moneyway.page')])

@section('title', 'BF Markets | Football Match')
@section('meta_description', 'BF Markets football match overview.')

@push('css')
    @include('users.layouts.partials.fixtures.theme-styles')
    @include('users.layouts.partials.fixtures.leaguewise-styles')
    <style>
        .matchwise-moneyway-market-board .leaguewise-market-summary-label {
            min-height: 0.75rem;
            font-size: 0.72rem;
            font-weight: 500;
            line-height: 1;
            color: var(--betwatch-page-text);
        }

        .matchwise-moneyway-market-board .leaguewise-market-summary-money {
            font-size: 0.88rem;
            font-weight: 700;
            line-height: 1;
        }

        .matchwise-moneyway-market-board .leaguewise-market-summary-percent {
            margin-top: 0.18rem;
            font-size: 0.66rem;
            line-height: 1;
            color: var(--betwatch-page-text-soft);
        }

        .matchwise-moneyway-market-board .leaguewise-market-summary-price {
            margin-top: 0.28rem;
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1;
        }

        .matchwise-moneyway-market-board .leaguewise-market-summary-price--down {
            color: var(--betwatch-page-danger);
        }

        .matchwise-moneyway-market-board .leaguewise-market-summary-price--up {
            color: var(--betwatch-page-success);
        }

        .matchwise-moneyway-market-board .leaguewise-market-summary-price--neutral {
            color: var(--betwatch-page-text);
        }

        .matchwise-moneyway-market-board .leaguewise-market-summary--wrapped {
            grid-template-columns: repeat(auto-fit, minmax(5.5rem, 1fr));
            column-gap: 0.75rem;
            row-gap: 1.15rem;
            padding-block: 0.35rem;
        }

        .matchwise-moneyway-market-board .leaguewise-market-summary--wrapped .leaguewise-market-summary-option,
        .matchwise-moneyway-market-board .leaguewise-market-summary--wrapped .leaguewise-market-summary-inner,
        .matchwise-moneyway-market-board .leaguewise-market-summary--wrapped .leaguewise-market-summary-values {
            min-width: 0;
            width: 100%;
        }

        .matchwise-moneyway-market-board .leaguewise-market-summary--wrapped .leaguewise-market-summary-inner {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: 0.3rem;
        }

        .matchwise-moneyway-market-board .leaguewise-market-summary--wrapped .leaguewise-market-summary-label {
            min-height: 2rem;
            text-align: center;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .matchwise-moneyway-market-board .betwatch-moneyway-correct-score-scroll {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: flex-start;
            gap: 18px;
            min-width: 0;
            max-width: 100%;
            width: 100%;
            min-height: 66px;
            overflow-x: auto;
            overflow-y: hidden;
            padding-bottom: 0.4rem;
            overscroll-behavior-inline: contain;
            scrollbar-width: thin;
        }

        .matchwise-moneyway-market-board .betwatch-moneyway-correct-score-scroll > .betwatch-moneyway-option {
            flex: 0 0 92px;
        }

        .matchwise-moneyway-market-board .betwatch-moneyway-half-time-full-time-scroll > .betwatch-moneyway-option {
            flex-basis: 17rem;
            min-height: 8rem;
            align-content: center;
            justify-items: stretch;
            padding-inline: 0.5rem;
            text-align: left;
        }

        .matchwise-moneyway-market-board .betwatch-half-time-full-time-heading {
            display: flex;
            align-items: baseline;
            gap: 0.45rem;
            min-width: 0;
        }

        .matchwise-moneyway-market-board .betwatch-half-time-full-time-notation {
            flex: 0 0 auto;
            font-size: 0.82rem;
            font-weight: 800;
            color: var(--betwatch-page-text);
        }

        .matchwise-moneyway-market-board .betwatch-half-time-full-time-title {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--betwatch-page-text);
        }

        .matchwise-moneyway-market-board .betwatch-half-time-full-time-explanation {
            min-height: 2.1rem;
            font-size: 0.66rem;
            line-height: 1.35;
            color: var(--betwatch-page-text-soft);
        }

        .matchwise-moneyway-market-board,
        .matchwise-moneyway-market-board .leaguewise-market-board,
        .matchwise-moneyway-market-board .leaguewise-market-group {
            box-sizing: border-box;
            width: 100%;
            min-width: 0;
            max-width: 100%;
            overflow-x: hidden;
        }

        .matchwise-moneyway-market-board .leaguewise-market-panel {
            position: relative;
            box-sizing: border-box;
            width: 100%;
            min-width: 0;
            max-width: 100%;
            contain: inline-size;
            overflow: hidden;
        }

        .matchwise-moneyway-market-board .leaguewise-market-panel-scroller {
            display: block;
            position: relative;
            box-sizing: border-box;
            width: 100%;
            min-width: 0;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            overscroll-behavior-inline: contain;
            -webkit-overflow-scrolling: touch;
        }

        .matchwise-moneyway-market-board .leaguewise-market-panel-track {
            width: max-content;
            min-width: 100%;
            max-width: none;
        }

        [data-match-details-page][data-sport="football"] .leaguewise-scoreboard {
            width: 100%;
            min-width: 0;
        }

        [data-match-details-page][data-sport="football"] .leaguewise-team {
            width: 100%;
            min-width: 0;
            max-width: 100%;
            justify-self: stretch;
        }

        [data-match-details-page][data-sport="football"] .leaguewise-team-name {
            min-width: 0;
            max-width: 100%;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
        }
    </style>
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
        $homeTeam = $match['team_1'];
        $awayTeam = $match['team_2'];
        $isLive = (bool) ($match['is_live'] ?? false);
        $isHalfTime = (bool) ($match['is_ht'] ?? false);
        $scoreText = $match['parts'][1] ?? '0 - 0';
    @endphp

    <div data-match-details-page data-sport="football" data-football-realtime-view="detail"
        data-football-realtime-row data-football-match-id="{{ $match['id'] ?? '' }}"
        data-football-realtime-key="detail:{{ $match['id'] ?? '' }}"
        data-football-detail-match-id="{{ $match['id'] ?? '' }}" class="countrywise-page leaguewise-page">
        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => 'football'])

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

                        @include('users.sports.Football.Layouts.country', ['countries' => $countries])
                    </article>
                </aside>

                <div class="countrywise-content leaguewise-content">
                    <article class="countrywise-card leaguewise-summary-card">
                        <div class="leaguewise-summary-top">
                            <div class="leaguewise-breadcrumbs">
                                <a href="{{ route('home.en') }}"><span>Football</span></a>
                                <span class="leaguewise-breadcrumb-sep">></span>

                                <a href="{{ route('football.country.page', ['country' => $match['country'] ?? 'international']) }}">
                                    <span class="leaguewise-breadcrumb-country">
                                        @php $flagCode = strtolower($match['country_flag'] ?? ''); @endphp
                                        @if($flagCode && $flagCode !== 'international')
                                            <img class="betguru-flag-img" src="https://flagcdn.com/w40/{{ $flagCode }}.png" alt="{{ $match['country_name'] ?? 'Country' }} flag">
                                        @endif
                                        <span>{{ $match['country_name'] ?? 'International' }}</span>
                                    </span>
                                </a>

                                <span class="leaguewise-breadcrumb-sep">></span>

                                <a href="{{ route('football.league.page', ['league_id' => $match['league_id'] ?? 0]) }}">
                                    <span>{{ $match['league'] ?? 'League' }}</span>
                                </a>
                            </div>

                            <svg class="betwatch-star-icon leaguewise-summary-favorite {{ (int) ($match['is_match_fav'] ?? 0) === 1 ? 'is-favorite' : '' }}"
                                data-favorite-trigger
                                data-favorite-sport="football"
                                data-favorite-type="match"
                                data-favorite-league-id="{{ $match['league_id'] ?? '' }}"
                                data-favorite-match-id="{{ $match['id'] ?? '' }}"
                                data-favorite-label="{{ trim($homeTeam . ' - ' . $awayTeam, ' -') }}"
                                viewBox="0 0 20 20" fill="none"
                                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                                aria-label="Toggle match favorite">
                                <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                            </svg>
                        </div>

                        <div class="leaguewise-scoreboard {{ $isLive ? 'leaguewise-scoreboard--live' : 'leaguewise-scoreboard--upcoming' }}">
                            <div class="leaguewise-team leaguewise-team--home">
                                <svg class="betwatch-star-icon {{ (int) ($match['is_team_v1_fav'] ?? 0) === 1 ? 'is-favorite' : '' }}"
                                    data-favorite-trigger
                                    data-favorite-sport="football"
                                    data-favorite-type="team"
                                    data-favorite-league-id="{{ $match['league_id'] ?? '' }}"
                                    data-favorite-match-id="{{ $match['id'] ?? '' }}"
                                    data-favorite-team-side="v1"
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
                                @if ($match['result_status'] == 'Live')
                                        @if ($match['status'] !== 'Live' && $match['status'] !== 'HT' && $match['status'] !== 'Finished' && $match['status'] !== 'Inplay')
                                            <span class="betwatch-status !text-[rgb(var(--danger))]">{{ trim($match['status']) }}<span class="betwatch-live-minute-mark">'</span>
                                        @else
                                            <span class="betwatch-status !text-[rgb(var(--danger))]">{{ trim($match['status']) }}
                                        @endif
                                    </span>
                                @else
                                    <span class="text-[0.73rem]">{{ $match['status'] }}</span>
                                @endif
                                {{-- <div class="leaguewise-minute"
                                    data-football-live-clock
                                    data-football-match-id="{{ $match['match_id'] ?? '' }}"
                                    data-api-minute="{{ is_numeric($match['live_time'] ?? null) ? (int) $match['live_time'] : '' }}"
                                    data-api-extra-time="{{ is_numeric($match['extra_time'] ?? null) ? (int) $match['extra_time'] : '' }}"
                                    data-is-live="{{ $isLive ? '1' : '0' }}"
                                    data-is-half-time="{{ $isHalfTime ? '1' : '0' }}"
                                    data-is-finished="{{ ($match['finished'] ?? false) ? '1' : '0' }}"
                                    data-server-status="{{ $match['status'] ?? 'Live' }}">
                                    {{ __($match['status'] ?? 'Live') }}
                                </div> --}}
                            </div>

                            <div class="leaguewise-team leaguewise-team--away">
                                <span class="leaguewise-team-name">{{ $awayTeam }}</span>
                                <svg class="betwatch-star-icon {{ (int) ($match['is_team_v2_fav'] ?? 0) === 1 ? 'is-favorite' : '' }}" data-favorite-trigger data-favorite-sport="football" data-favorite-type="team" data-favorite-league-id="{{ $match['league_id'] ?? '' }}" data-favorite-match-id="{{ $match['id'] ?? '' }}" data-favorite-team-side="v2" data-favorite-label="{{ $awayTeam }}" viewBox="0 0 20 20" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-label="Toggle away team favorite">
                                    <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                                </svg>
                                <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z"></path>
                            </div>
                        </div>

                        <div class="countrywise-main-tabs leaguewise-tabs">
                            @foreach ($match['tabs'] ?? [] as $tab)
                                <button type="button" class="countrywise-main-tab" data-match-page-tab="{{ $tab['key'] }}">{{ $tab['label'] }}</button>
                            @endforeach
                        </div>
                    </article>

                    <div data-match-overview-panel>
                        <div class="matchwise-moneyway-market-board">
                            @include('users.layouts.partials.fixtures.leaguewise-football-market-board', [
                                'league' => $match,
                                'useMoneywayOddsStyle' => true,
                            ])
                        </div>
                    </div>

                    @include('users.layouts.partials.fixtures.match-details', ['detailMarkets' => $detailMarkets, 'detailColumnOptions' => $detailColumnOptions])
                </div>
            </div>
        </section>
    </div>
    @include('popup')
@endsection

@push('js')
    @vite('resources/js/frontend/sports/football.js')
@endpush
