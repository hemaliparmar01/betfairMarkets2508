@php
    $league = $leagueMap[$leagueId] ?? reset($leagueMap);

    $checkedLeagueDefaults = collect($countries)
        ->pluck('key')
        ->mapWithKeys(fn ($key) => [$key => true])
        ->all();

    $chartTemplates = [
        [
            'left_axis' => ['3.5', '3.0', '2.5', '2.0', '1.5'],
            'right_axis' => ['18000', '14000', '10000', '4000', '0'],
            'line' => 'M6 122 C22 118,44 116,64 114 C82 111,98 106,114 98 C132 89,148 77,162 60 C172 47,180 26,188 10',
            'bars' => [
                ['x' => 34, 'top' => 116],
                ['x' => 86, 'top' => 95],
                ['x' => 128, 'top' => 70],
                ['x' => 166, 'top' => 46],
            ],
        ],
        [
            'left_axis' => ['3.0', '2.5', '2.0', '1.5', '1.0'],
            'right_axis' => ['16000', '12000', '8000', '4000', '0'],
            'line' => 'M6 30 C22 36,38 44,54 52 C74 62,94 70,112 84 C132 97,150 107,168 118 C176 122,182 125,188 126',
            'bars' => [
                ['x' => 26, 'top' => 18],
                ['x' => 74, 'top' => 62],
                ['x' => 124, 'top' => 84],
                ['x' => 172, 'top' => 108],
            ],
        ],
    ];
@endphp

<div data-simple-tabs data-sport="{{ $activeSport }}" class="countrywise-page leaguewise-page">
    @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => $activeSport])

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

                    @include('users.layouts.partials.fixtures.countryLists.shared', ['countries' => $countries])

                </article>
            </aside>

            <div class="countrywise-content leaguewise-content">
                <article class="countrywise-card leaguewise-summary-card">
                    <div class="leaguewise-summary-top">
                        <div class="leaguewise-breadcrumbs">
                            <span>{{ $league['sport'] }}</span>
                            <span class="leaguewise-breadcrumb-sep">></span>
                            <span class="leaguewise-breadcrumb-country">
                                @php $flagCode = strtolower($league['country_flag'] ?? ''); @endphp
                                @if($flagCode && $flagCode !== 'international')
                                    <img class="betguru-flag-img" src="https://flagcdn.com/w40/{{ $flagCode }}.png" alt="{{ $league['country_name'] ?? 'Country' }} flag">
                                @endif
                                <span>{{ $league['country'] }}</span>
                            </span>
                            <span class="leaguewise-breadcrumb-sep">></span>
                            <span>{{ $league['league'] }}</span>
                        </div>

                        <svg class="betwatch-star-icon leaguewise-summary-favorite" viewBox="0 0 20 20" fill="none"
                            stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path
                                d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                        </svg>
                    </div>

                    <div class="leaguewise-scoreboard">
                        <div class="leaguewise-team leaguewise-team--home">
                            <svg class="betwatch-star-icon" viewBox="0 0 20 20" fill="none" stroke-width="1.7"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path
                                    d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                            </svg>
                            <span class="leaguewise-team-name">{{ $league['home_team'] }}</span>
                        </div>

                        <div class="leaguewise-scorebox">
                            <div class="leaguewise-score">{{ $league['score'] }}</div>
                            <div class="leaguewise-minute">{{ $league['minute'] }}</div>
                        </div>

                        <div class="leaguewise-team leaguewise-team--away">
                            <span class="leaguewise-team-name">{{ $league['away_team'] }}</span>
                            <svg class="betwatch-star-icon" viewBox="0 0 20 20" fill="none" stroke-width="1.7"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path
                                    d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                            </svg>
                        </div>
                    </div>

                    <div class="countrywise-main-tabs leaguewise-tabs">
                        @foreach ($league['tabs'] as $tab)
                            <button type="button" class="countrywise-main-tab {{ $tab['key'] === 'overview' ? 'is-active' : '' }}"
                                data-simple-tab data-tab-group="league">
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>
                </article>

                <article class="countrywise-card leaguewise-market-board">
                    @foreach ($league['markets'] as $market)
                        <div class="leaguewise-market-group" data-static-market-group>
                            <button type="button" class="leaguewise-market-header" data-static-market-toggle
                                aria-expanded="false">
                                <svg class="betwatch-star-icon" viewBox="0 0 20 20" fill="none"
                                    stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                                    aria-hidden="true">
                                    <path
                                        d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                                </svg>

                                <span class="leaguewise-market-title">{{ $market['title'] }}</span>

                                <div class="leaguewise-market-summary leaguewise-market-summary--{{ min(count($market['options']), 4) }}">
                                    @foreach ($market['options'] as $option)
                                        <div class="leaguewise-market-summary-option">
                                            <div class="leaguewise-market-summary-money">
                                                {{ $option['money'] }}&#8364;
                                            </div>
                                            <div class="leaguewise-market-summary-meta">
                                                <span>{{ $option['label'] }}</span>
                                                <span>{{ $option['percent'] }}</span>
                                            </div>
                                            <div class="leaguewise-market-summary-price">{{ $option['price'] }}</div>
                                        </div>
                                    @endforeach
                                </div>

                                <span class="leaguewise-market-toggle" data-static-market-arrow
                                    aria-hidden="true">
                                    <svg viewBox="0 0 20 20" fill="none" stroke-width="1.8"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m5.5 7.5 4.5 4.5 4.5-4.5" />
                                    </svg>
                                </span>
                            </button>

                            <div class="leaguewise-market-panel" data-static-market-panel hidden>
                                <div class="leaguewise-market-graphs leaguewise-market-summary--{{ min(count($market['options']), 4) }}">
                                    @foreach ($market['options'] as $option)
                                        @php($chart = $chartTemplates[$loop->index % count($chartTemplates)])
                                        <div class="leaguewise-market-graph-card">
                                            <div class="leaguewise-market-label">
                                                {{ rtrim($option['label'], ':') }}
                                            </div>

                                            <div class="leaguewise-market-graph-shell">
                                                <div class="leaguewise-market-axis leaguewise-market-axis--left">
                                                    @foreach ($chart['left_axis'] as $tick)
                                                        <span>{{ $tick }}</span>
                                                    @endforeach
                                                </div>

                                                <div class="leaguewise-market-plot">
                                                    <svg class="leaguewise-market-chart" viewBox="0 0 194 132"
                                                        preserveAspectRatio="none" aria-hidden="true">
                                                        @foreach ($chart['bars'] as $bar)
                                                            <line class="leaguewise-market-chart-bar"
                                                                x1="{{ $bar['x'] }}" y1="128"
                                                                x2="{{ $bar['x'] }}"
                                                                y2="{{ $bar['top'] }}" />
                                                        @endforeach
                                                        <path class="leaguewise-market-chart-line"
                                                            d="{{ $chart['line'] }}" />
                                                    </svg>
                                                </div>

                                                <div class="leaguewise-market-axis leaguewise-market-axis--right">
                                                    @foreach ($chart['right_axis'] as $tick)
                                                        <span>{{ $tick }}</span>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div class="leaguewise-market-graph-foot">
                                                {{ $option['money'] }}&#8364; - {{ $option['price'] }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </article>
            </div>
        </div>
    </section>
</div>

