@php
    $resolvedMatchGroups = [];

    if (isset($matchGroups) && is_iterable($matchGroups)) {
        $resolvedMatchGroups = $matchGroups;
    } elseif (isset($group) && is_array($group)) {
        $resolvedMatchGroups = [$group];
    }
@endphp

@if (count($resolvedMatchGroups) === 0)
    <article class="betwatch-match-card mt-3">
        <div class="betwatch-match-row betwatch-match-row--empty">
            <span class="betwatch-teams__label betwatch-empty-label betwatch-empty-label--small font-semibold">{{ __('No Cricket matches available.') }}</span>
        </div>
    </article>
@endif

@foreach ($resolvedMatchGroups as $matchGroup)
    <article class="betwatch-match-card" data-match-card-shell>
        <div class="betwatch-match-head betwatch-match-head--two-way">
            <svg class="betwatch-star-icon {{ ($matchGroup['is_fav'] ?? 0) == 1 ? 'is-favorite' : '' }}"
                data-favorite-trigger
                data-favorite-sport="cricket"
                data-favorite-type="league"
                data-favorite-league-id="{{ $matchGroup['rows'][0]['league_id'] ?? '' }}"
                data-favorite-label="{{ $matchGroup['league_name'] ?? ($matchGroup['league'] ?? 'Unknown league') }}"
                viewBox="0 0 20 20" fill="none" stroke-width="1.7" stroke-linecap="round"
                stroke-linejoin="round" aria-label="Toggle league favorite">
                <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
            </svg>

            @php
                $firstRow = $matchGroup['rows'][0] ?? null;
            @endphp
            <div class="betwatch-match-league">
                @php $flagCode = strtolower($matchGroup['country_code'] ?? ''); @endphp
                @if($flagCode && $flagCode !== 'international')
                    <img class="betguru-flag-img" src="https://flagcdn.com/w40/{{ $flagCode }}.png" alt="{{ $matchGroup['country_name'] ?? 'Country' }} flag">
                @endif
                <a href="{{ route('cricket.country.page', ['country' => $matchGroup['country_code'] ?? 'international']) }}">
                    <span>{{ $matchGroup['country_name'] ?? 'International' }}</span>
                </a>
                <span> : </span>
                <a href="{{ $firstRow['url'] ?? route('cricket.league.page', ['league_id' => $firstRow['league_id'] ?? $firstRow['id'] ?? 1]) }}">
                    <span>{{ $matchGroup['league_name'] ?? ($matchGroup['league'] ?? 'Unknown league') }}</span>
                </a>
            </div>

            <span class="betwatch-odds-head">1</span>
            <span class="betwatch-odds-head">2</span>
        </div>

        @foreach ($matchGroup['rows'] ?? [] as $matchIndex => $row)
            @php
                $matchUrl = !empty($row['id'])
                    ? route('cricket.match.data', ['match_id' => (int) $row['id']])
                    : ($row['url'] ?? route('cricket.league.page', ['league_id' => $row['league_id'] ?? $row['id'] ?? 1]));
                $hasExtraMarkets = !empty($row['extra_markets'] ?? []);
                $matchKey = ($row['id'] ?? 'match') . '-' . $matchIndex;
                $matchLabel = $row['teams'] ?? 'Unknown Match';
            @endphp

            <div class="betwatch-match-row-shell {{ $hasExtraMarkets ? 'betwatch-match-row-shell--expandable' : '' }}">
                <div class="betwatch-match-row betwatch-match-row--two-way"
                    @if ($hasExtraMarkets)
                        data-collapse-trigger data-collapse-target="{{ $matchKey }}" aria-expanded="false"
                        tabindex="0"
                        role="button"
                    @endif
                >
                    <svg class="betwatch-star-icon {{ ($row['is_fav'] ?? 0) == 1 ? 'is-favorite' : '' }}"
                        data-favorite-trigger
                        data-favorite-sport="cricket"
                        data-favorite-type="match"
                        data-favorite-league-id="{{ $row['league_id'] ?? '' }}"
                        data-favorite-match-id="{{ $row['id'] ?? '' }}"
                        data-favorite-label="{{ $matchLabel }}"
                        viewBox="0 0 20 20" fill="none" stroke-width="1.7" stroke-linecap="round"
                        stroke-linejoin="round" aria-label="Toggle match favorite">
                        <path
                            d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                    </svg>

                    <div class="grid gap-0.5 leading-none">
                        <span class="betwatch-status {{ ($row['status_tone'] ?? 'default') === 'live' ? 'betwatch-status--live' : '' }}">
                            {{ $row['status'] ?? '' }}
                        </span>

                        @if (!empty($row['substatus']))
                            <span class="betwatch-score">{{ $row['substatus'] }}</span>
                        @endif
                    </div>

                    <span class="betwatch-teams">
                        <a href="{{ $matchUrl }}" class="betwatch-teams__label">
                            {{ $row['teams'] ?? 'Unknown Match' }}
                        </a>
                    </span>

                    @if ($checkAuthAndSubscription['display_odds'])
                        @forelse (($row['odds'] ?? []) as $odd)
                            @php
                                $oddTrend = $odd['trend'] ?? 'neutral';
                            @endphp
                            <span class="betwatch-odd betwatch-odd--{{ $oddTrend }}">
                                @if ($oddTrend === 'up')
                                    <span class="betwatch-trend">&uarr;</span>
                                @elseif ($oddTrend === 'down')
                                    <span class="betwatch-trend">&darr;</span>
                                @endif
                                <span>{{ $odd['value'] ?? '-' }}</span>
                            </span>
                        @empty
                            @for ($i = 0; $i < 2; $i++)
                                <span class="betwatch-odd betwatch-odd--neutral">
                                    <span>-</span>
                                </span>
                            @endfor
                        @endforelse
                    @else
                        @for ($i = 0; $i < 2; $i++)
                            <span class="betwatch-odd betwatch-odd--neutral">
                                @include('lock_icon')
                            </span>
                        @endfor
                    @endif
                </div>

                @if ($hasExtraMarkets && $checkAuthAndSubscription['display_odds'])
                    <div class="betwatch-market-collapse" data-collapse-panel="{{ $matchKey }}" hidden>
                        @foreach ($row['extra_markets'] as $market)
                            @php
                                $marketRunners = array_values((array) ($market['runners'] ?? []));
                                $selectionWidth = count($marketRunners) === 2 ? '6.5rem' : '7.6rem';
                            @endphp

                            <div class="betwatch-market-collapse__row betwatch-market-collapse__row--generic">
                                <svg class="betwatch-star-icon betwatch-market-collapse__star"
                                    data-favorite-trigger
                                    data-favorite-sport="cricket"
                                    data-favorite-type="match"
                                    data-favorite-league-id="{{ $row['league_id'] ?? '' }}"
                                    data-favorite-match-id="{{ $row['id'] ?? '' }}"
                                    data-favorite-label="{{ $matchLabel }}"
                                    viewBox="0 0 20 20"
                                    fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                                    aria-label="Toggle match favorite">
                                    <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                                </svg>

                                <a href="#" class="js-open-chart-modal"
                                    data-market-name='@json($market["market_name"] ?? ($market["name"] ?? "Market"))'
                                    data-match-id="{{ $market['market_id'] ?? '' }}"
                                    data-runner-ids='@json(collect($marketRunners)->pluck("runner_id")->values())'
                                    data-runner-labels='@json(collect($marketRunners)->map(fn ($runner) => $runner["label"] ?? ($runner["runner_name"] ?? "Option"))->values())'>
                                    <svg class="betwatch-market-collapse__chart-icon" viewBox="0 0 20 20" fill="none"
                                        stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                                        stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2.8 15.5h14.4" />
                                        <path d="M5 15.2V10.6" />
                                        <path d="M9 15.2V7.8" />
                                        <path d="M13 15.2V5.5" />
                                        <path d="M17 15.2V8.8" />
                                        <path d="m4.1 6.9 2.8-2.3 2.6 1.5 4.1-3.2 2.1 1.1" />
                                    </svg>
                                </a>

                                <div class="betwatch-market-collapse__name">
                                    <span>{{ $market['market_name'] ?? ($market['name'] ?? 'Market') }}</span>
                                </div>

                                <div class="betwatch-market-collapse__scroll-shell">
                                    <div class="betwatch-market-collapse__scroll-track betwatch-market-collapse__scroll-track--{{ count($marketRunners) === 2 ? 'two-way' : 'three-way' }}">
                                        @foreach ($marketRunners as $selection)
                                            @php
                                                $selectionDirection = ($selection['odd_direction'] ?? 'neutral') === 'same' ? 'neutral' : ($selection['odd_direction'] ?? 'neutral');
                                                $displayLabel = $selection['label'] ?? ($selection['runner_name'] ?? 'Option');
                                                $selectionVolume = isset($selection['volume']) ? (float) $selection['volume'] : (isset($selection['liquidity']) ? (float) $selection['liquidity'] : 0);
                                            @endphp

                                            <div class="betwatch-market-collapse__selection">
                                                <div class="betwatch-market-collapse__selection-grid">
                                                    <span class="betwatch-market-collapse__label betwatch-market-collapse__label--truncate">{{ $displayLabel }}:</span>

                                                    <div class="betwatch-market-collapse__values">
                                                        <span class="betwatch-market-collapse__volume">
                                                            €{{ number_format($selectionVolume, 2, '.', '') }}
                                                        </span>

                                                        <span class="betwatch-odd betwatch-odd--{{ $selectionDirection }}">
                                                            @if ($selectionDirection === 'up')
                                                                <span class="betwatch-trend">&uarr;</span>
                                                            @elseif ($selectionDirection === 'down')
                                                                <span class="betwatch-trend">&darr;</span>
                                                            @endif

                                                            <span>{{ $selection['odd'] ?? '-' }}</span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </article>
@endforeach
