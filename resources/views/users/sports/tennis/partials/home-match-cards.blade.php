@if ($groups === [])
    <article class="betwatch-match-card" data-tennis-card>
        <div class="betwatch-match-row betwatch-match-row--empty">
            <span class="betwatch-teams__label betwatch-empty-label betwatch-empty-label--small">{{ $emptyLabel }}</span>
        </div>
    </article>
@else
    @foreach ($groups as $group)
        <article class="betwatch-match-card" data-tennis-card>
            <div class="betwatch-match-head betwatch-match-head--compact">
                <svg class="betwatch-star-icon {{ ((int) ($group['is_fav'] ?? 0) === 1) ? 'is-favorite' : '' }}"
                    data-favorite-trigger
                    data-favorite-sport="tennis"
                    data-favorite-type="league"
                    data-favorite-league-id="{{ $group['league_id'] ?? '' }}"
                    data-favorite-label="{{ $group['league_name'] ?? 'Unknown league' }}"
                    viewBox="0 0 20 20" fill="none" stroke-width="1.7" stroke-linecap="round"
                    stroke-linejoin="round" aria-label="Toggle league favorite">
                    <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                </svg>

                <div class="betwatch-match-league betwatch-match-league--compact">
                    @php $flagCode = strtolower($group['flag'] ?? ''); @endphp
                    @if($flagCode && $flagCode !== 'international')
                        <img class="betguru-flag-img" src="https://flagcdn.com/w40/{{ $flagCode }}.png" alt="{{ $group['country_name'] ?? $group['name'] ?? 'Country' }} flag">
                    @endif
                    <a href="{{ route('tennis.country.page', ['country' => $group['country_code'] ?? 'all']) }}">
                        <span>{{ $group['country_name'] ?? 'International' }}</span>
                    </a>
                    <span> : </span>
                    @if (!empty($group['league_id'] ?? null))
                        <a href="{{ route('tennis.league.page', ['league_id' => $group['league_id']]) }}">
                            <span>{{ $group['league_name'] ?? 'Unknown league' }}</span>
                        </a>
                    @else
                        <span>{{ $group['league_name'] ?? 'Unknown league' }}</span>
                    @endif
                </div>
            </div>

            @foreach (($group['rows'] ?? []) as $row)
                @php
                    $hasExtraMarkets = !empty($row['extra_markets'] ?? []);
                    $matchKey = ($row['match_id'] ?? 'match') . '-' . ($row['row_index'] ?? 0);
                @endphp
                <div class="betwatch-match-row-shell {{ $hasExtraMarkets ? 'betwatch-match-row-shell--expandable' : '' }} {{ ($row['row_index'] ?? 0) > 0 ? 'betwatch-match-row-shell--divided' : '' }}"
                    data-tennis-row-shell data-match-key="{{ $matchKey }}" data-open="0">
                    <div class="betwatch-match-row betwatch-tennis-row"
                        data-tennis-toggle-row
                        @if ($hasExtraMarkets) tabindex="0" role="button" @endif>
                        <svg class="betwatch-star-icon {{ ((int) ($row['is_match_fav'] ?? 0) === 1) ? 'is-favorite' : '' }}"
                            data-favorite-trigger
                            data-favorite-sport="tennis"
                            data-favorite-type="match"
                            data-favorite-league-id="{{ $row['league_id'] ?? '' }}"
                            data-favorite-match-id="{{ $row['match_id'] ?? '' }}"
                            data-favorite-label="{{ $row['favorite_label'] ?? '' }}"
                            viewBox="0 0 20 20" fill="none" stroke-width="1.7" stroke-linecap="round"
                            stroke-linejoin="round" aria-label="Toggle match favorite">
                            <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                        </svg>

                        <div class="betwatch-tennis-status-stack">
                            <span class="betwatch-status betwatch-tennis-status {{ !empty($row['is_live']) ? 'betwatch-status--live betwatch-tennis-status--live' : '' }}">
                                {{ __($row['status_label'] ?? 'Upcoming') }}
                            </span>
                        </div>

                        <div class="betwatch-tennis-team-stack">
                            <div class="betwatch-tennis-team-row">
                                <span class="betwatch-tennis-team-name {{ ((int) ($row['team_1_weight'] ?? 500)) > 500 ? 'font-bold' : 'font-medium' }}">{{ $row['team_1'] ?? 'Unknown Player 1' }}</span>
                            </div>
                            <div class="betwatch-tennis-team-row">
                                <span class="betwatch-tennis-team-name {{ ((int) ($row['team_2_weight'] ?? 500)) > 500 ? 'font-bold' : 'font-medium' }}">{{ $row['team_2'] ?? 'Unknown Player 2' }}</span>
                            </div>
                        </div>

                        @if ($checkAuthAndSubscription['display_odds'])
                            <div class="betwatch-tennis-score-stack">
                                <div class="betwatch-tennis-score-grid betwatch-tennis-score-grid--{{ min(max((int) ($row['cell_count'] ?? 4), 1), 6) }}">
                                    @foreach (($row['score_cells_v1'] ?? []) as $cellIndex => $cellValue)
                                        @php
                                            $isPointsCell = ($cellIndex === (($row['cell_count'] ?? 4) - 1)) && !empty($row['has_points_cell']);
                                        @endphp
                                        <span class="betwatch-tennis-score-cell {{ $cellIndex === 0 ? 'font-extrabold' : 'font-medium' }} {{ $isPointsCell && $cellValue !== '' ? 'betwatch-tennis-score-cell--highlight' : '' }}">{{ $cellValue }}</span>
                                    @endforeach
                                </div>

                                <div class="betwatch-tennis-score-grid betwatch-tennis-score-grid--{{ min(max((int) ($row['cell_count'] ?? 4), 1), 6) }}">
                                    @foreach (($row['score_cells_v2'] ?? []) as $cellIndex => $cellValue)
                                        @php
                                            $isPointsCell = ($cellIndex === (($row['cell_count'] ?? 4) - 1)) && !empty($row['has_points_cell']);
                                        @endphp
                                        <span class="betwatch-tennis-score-cell {{ $cellIndex === 0 ? 'font-extrabold' : 'font-medium' }} {{ $isPointsCell && $cellValue !== '' ? 'betwatch-tennis-score-cell--highlight' : '' }}">{{ $cellValue }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <span class="betwatch-odd betwatch-odd--neutral">
                                @include('lock_icon')
                            </span>
                        @endif
                    </div>

                    @if ($hasExtraMarkets && $checkAuthAndSubscription['display_odds'])
                        <div class="betwatch-market-collapse" data-tennis-market-collapse>
                            @foreach (($row['extra_markets'] ?? []) as $market)
                                @php
                                    $marketRunners = array_values((array) ($market['runners'] ?? []));
                                    $isThreeWayMarket = count($marketRunners) === 3;
                                @endphp

                                <div class="betwatch-market-collapse__row {{ $isThreeWayMarket ? 'betwatch-market-collapse__row--three-way' : 'betwatch-market-collapse__row--two-way' }}">
                                    <svg class="betwatch-star-icon betwatch-market-collapse__star"
                                        data-favorite-trigger
                                        data-favorite-sport="tennis"
                                        data-favorite-type="market"
                                        data-favorite-league-id="{{ $row['league_id'] ?? '' }}"
                                        data-favorite-match-id="{{ $row['match_id'] ?? '' }}"
                                        data-favorite-market-id="{{ $market['market_id'] ?? '' }}"
                                        data-favorite-label="{{ $row['favorite_label'] ?? '' }}"
                                        viewBox="0 0 20 20"
                                        fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                                        aria-label="Toggle market favorite">
                                        <path
                                            d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                                    </svg>

                                    <span class="betwatch-icon-box">
                                        <svg class="betwatch-market-collapse__chart-icon betwatch-chart-icon-muted" viewBox="0 0 20 20" fill="none"
                                            stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                                            stroke-linejoin="round" aria-hidden="true">
                                            <path d="M2.8 15.5h14.4" />
                                            <path d="M5 15.2V10.6" />
                                            <path d="M9 15.2V7.8" />
                                            <path d="M13 15.2V5.5" />
                                            <path d="M17 15.2V8.8" />
                                            <path d="m4.1 6.9 2.8-2.3 2.6 1.5 4.1-3.2 2.1 1.1" />
                                        </svg>
                                    </span>

                                    <div class="betwatch-market-collapse__name">
                                        <span>{{ $market['market_name'] ?? 'Market' }}</span>
                                    </div>

                                    @foreach ($marketRunners as $selectionKey => $selection)
                                        @php
                                            $selectionDirection = $selection['odd_direction'] ?? 'neutral';
                                            $selectionDirection = $selectionDirection === 'same' ? 'neutral' : $selectionDirection;
                                            $displayLabel = $selection['label'] ?? 'Option';

                                            if ($isThreeWayMarket) {
                                                $displayLabel = $selectionKey === 0 ? '1' : ($selectionKey === 1 ? 'X' : '2');
                                            }
                                        @endphp

                                        <div class="betwatch-market-collapse__selection">
                                            <div class="betwatch-market-collapse__selection-grid">
                                                <span class="betwatch-market-collapse__label">{{ $displayLabel }}:&nbsp;&nbsp;</span>

                                                <div class="betwatch-market-collapse__values">
                                                    <span class="betwatch-market-collapse__volume">
                                                        {{ $selection['liquidity'] ?? '$0.00' }}
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
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </article>
    @endforeach
@endif

