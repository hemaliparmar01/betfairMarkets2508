@php
    $marketBoardSport = $marketBoardSport ?? 'football';
@endphp

<style>
    .leaguewise-market-title-wrap {
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 6px;
}

.leaguewise-market-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    text-transform: uppercase;
}

.leaguewise-market-status--open {
    color: #15803d;
    background: rgba(34, 197, 94, 0.14);
}

.leaguewise-market-status--suspended {
    color: #dc2626;
    background: rgba(239, 68, 68, 0.14);
}

.leaguewise-market-status--closed {
    color: #64748b;
    background: rgba(100, 116, 139, 0.14);
}

.leaguewise-market-status--unknown {
    color: #b45309;
    background: rgba(245, 158, 11, 0.14);
}
</style>
<article class="countrywise-card leaguewise-market-board">
    @forelse (($league['markets'] ?? []) as $market)
        @php
            $marketTitle = $market['name'] ?? ($market['market_name'] ?? 'Market');
            $marketId = $market['api_market_id'];
            $isFavorite = $marketId > 0 ? checkMarketFavorite(['id' => $marketId], $marketBoardSport) : false;
            $isSingleMatchBoard = (bool) ($useMoneywayOddsStyle ?? false);
            $normalizedMarketTitle = strtolower(preg_replace('/\s+/', '', trim($marketTitle)));
            $isHalfTimeFullTime = $isSingleMatchBoard && $normalizedMarketTitle === 'halftime/fulltime';
            $isHalfTimeScore = $isSingleMatchBoard && $normalizedMarketTitle === 'halftimescore';
            $isCorrectScore = $isSingleMatchBoard && in_array($normalizedMarketTitle, ['correctscore', 'fulltimecorrectscore'], true);
            $usesExpandedRunnerScroller = $isHalfTimeFullTime || $isHalfTimeScore || $isCorrectScore;
            $halfTimeFullTimeLabels = ['1/1', '1/X', '1/2', 'X/1', 'X/X', 'X/2', '2/1', '2/X', '2/2'];
            $homeTeam = $league['team_1'] ?? ($league['parts'][0] ?? 'Team A');
            $awayTeam = $league['team_2'] ?? ($league['parts'][2] ?? 'Team B');
            $halfTimeFullTimeExplanations = [
                "{$homeTeam} wins the first half and wins the full match.",
                "{$homeTeam} wins the first half, but the full match ends in a draw.",
                "{$homeTeam} wins the first half, but {$awayTeam} wins the full match.",
                "The first half ends in a draw, but {$homeTeam} wins the full match.",
                'The first half ends in a draw and the full match also ends in a draw.',
                "The first half ends in a draw, but {$awayTeam} wins the full match.",
                "{$awayTeam} wins the first half, but {$homeTeam} wins the full match.",
                "{$awayTeam} wins the first half, but the full match ends in a draw.",
                "{$awayTeam} wins the first half and wins the full match.",
            ];
            $allMarketRunners = collect($market['runners'] ?? [])->filter(fn($runner) => is_array($runner));
            $marketRunners = (match (true) {
                $isHalfTimeFullTime => $allMarketRunners->take(9),
                $isHalfTimeScore => $allMarketRunners->take(10),
                $isCorrectScore => $allMarketRunners,
                default => $allMarketRunners->take(4),
            })->values();
            $panelRunners = $marketRunners->map(function ($runner, $runnerIndex) use ($marketTitle, $isHalfTimeFullTime, $isHalfTimeScore, $isCorrectScore, $halfTimeFullTimeLabels) {
                    $volume = isset($runner['volume']) ? (float) $runner['volume'] : 0;
                    $runnerLabel = ($isHalfTimeScore || $isCorrectScore)
                        ? ($runner['runner_name'] ?? $runner['label'] ?? null)
                        : ($runner['label'] ?? null);
                    return [
                        'market_id' => (int) ($runner['api_market_id'] ?? 0),
                        'runner_id' => (int) ($runner['api_runner_id'] ?? 0),
                        'label' => $isHalfTimeFullTime
                            ? ($halfTimeFullTimeLabels[$runnerIndex] ?? ($runner['label'] ?? 'Option'))
                            : resolveFootballRunnerLabel(
                                $marketTitle,
                                $runnerLabel,
                                ($isHalfTimeScore || $isCorrectScore) ? $runnerIndex : 1
                            ),
                        'money_text' => '€' . $volume,
                        'odd_text' => (string) ($runner['odd'] ?? '-'),
                    ];
                })->filter(fn($runner) => $runner['market_id'] > 0 && $runner['runner_id'] > 0)->values()->all();
            $optionCount = max($marketRunners->count(), 1);
        @endphp

        <div class="leaguewise-market-group" @if ($checkAuthAndSubscription['display_odds']) data-market-panel-group data-sport="{{ $marketBoardSport }}" data-panel-runners="{{ json_encode($panelRunners) }}" data-empty-label="{{ __('No charts available.') }}" @endif>
            <button type="button" class="leaguewise-market-header" data-market-panel-toggle aria-expanded="false">
                <svg class="betwatch-star-icon {{ $isFavorite ? 'is-favorite' : '' }}" data-favorite-trigger data-favorite-sport="{{ $marketBoardSport }}" data-favorite-type="market" data-favorite-league-id="{{ $league['league_id'] ?? '' }}" data-favorite-match-id="{{ $league['match_id'] ?? '' }}" data-favorite-market-id="{{ $marketId }}" data-favorite-label="{{ trim(($league['team_1'] ?? ($league['parts'][0] ?? 'Match')) . ' - ' . ($league['team_2'] ?? ($league['parts'][2] ?? '')) . ' - ' . $marketTitle, ' -') }}" viewBox="0 0 20 20" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-label="Toggle market favorite">
                    <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                </svg>

                <span class="leaguewise-market-title">{{ $marketTitle }}</span>
                <div class="leaguewise-market-summary {{ $usesExpandedRunnerScroller ? 'betwatch-moneyway-correct-score-scroll'.($isHalfTimeFullTime ? ' betwatch-moneyway-half-time-full-time-scroll' : '') : 'leaguewise-market-summary--'.min($optionCount, 4) }}">
                    @foreach ($marketRunners as $runnerIndex => $option)
                        @php
                            $rawVolume = isset($option['volume']) ? (float) $option['volume'] : 0;
                            $liquidity = isset($option['market_liquidity']) ? (float) $option['market_liquidity'] : 0;
                            $percent = $liquidity > 0 ? round(($rawVolume / $liquidity) * 100) . '%' : '0%';
                            $displayLabel = $isHalfTimeFullTime
                                ? ($halfTimeFullTimeLabels[$runnerIndex] ?? ($option['runner_name'] ?? 'Option'))
                                : resolveFootballRunnerLabel($marketTitle, $option['runner_name'] ?? null, $runnerIndex);
                            $volume = number_format(round($rawVolume), 0, '.', ' ');
                            $optionDirection = ($option['odd_direction'] ?? 'neutral') === 'same'
                                ? 'neutral'
                                : ($option['odd_direction'] ?? 'neutral');
                        @endphp
                        @if ($usesExpandedRunnerScroller)
                            <div class="betwatch-moneyway-option">
                                @if ($checkAuthAndSubscription['display_odds'])
                                    @if ($isHalfTimeFullTime)
                                        <div class="betwatch-half-time-full-time-heading">
                                            <span class="betwatch-half-time-full-time-notation">{{ $displayLabel }}</span>
                                            <span class="betwatch-half-time-full-time-title">{{ $option['runner_name'] ?? '' }}</span>
                                        </div>
                                        {{-- <div class="betwatch-half-time-full-time-explanation">
                                            {{ $halfTimeFullTimeExplanations[$runnerIndex] ?? '' }}
                                        </div> --}}
                                    @else
                                        <div class="betwatch-moneyway-option-label">{{ $displayLabel }}</div>
                                    @endif
                                    <div class="betwatch-moneyway-option-value">€{{ $volume }}</div>
                                    <div class="betwatch-moneyway-option-percent">{{ $percent }}</div>
                                    <div class="betwatch-moneyway-odd-value {{ $optionDirection === 'down' ? 'betwatch-moneyway-odd-value--down' : ($optionDirection === 'up' ? 'betwatch-moneyway-odd-value--up' : 'betwatch-moneyway-odd-value--neutral') }}">
                                        <x-football-odd-hover :first-odd="$option['first_odd'] ?? null" :current-odd="$option['odd'] ?? null" :direction="$optionDirection">
                                            @if ($optionDirection === 'down')
                                                &darr;
                                            @elseif ($optionDirection === 'up')
                                                &uarr;
                                            @endif
                                            {{ $option['odd'] ?? '-' }}
                                        </x-football-odd-hover>
                                    </div>
                                @else
                                    <div class="betwatch-moneyway-option-label">
                                        @include('lock_icon')
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="leaguewise-market-summary-option">
                                <div class="leaguewise-market-summary-inner">
                                    @if ($checkAuthAndSubscription['display_odds'])
                                        <div class="leaguewise-market-summary-label">
                                            {{ $displayLabel }}:
                                        </div>
                                        <div class="leaguewise-market-summary-values">
                                            <div class="leaguewise-market-summary-money">€{{ $volume }}</div>
                                            <div class="leaguewise-market-summary-percent">{{ $percent }}</div>
                                            <div class="leaguewise-market-summary-price">{{ $option['odd'] ?? '-' }}</div>
                                        </div>
                                    @else
                                        <span class="betwatch-odd betwatch-odd--neutral">
                                            @include('lock_icon')
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                <span class="leaguewise-market-toggle" data-market-panel-arrow aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="m5.5 7.5 4.5 4.5 4.5-4.5" />
                    </svg>
                </span>
            </button>

            <div class="leaguewise-market-panel" data-market-panel hidden>
                <div class="leaguewise-market-panel-state" data-market-panel-state>{{ __('Loading charts...') }}</div>
                <div class="leaguewise-market-panel-scroller">
                    <div class="leaguewise-market-panel-track" data-market-panel-track></div>
                </div>
            </div>
        </div>
    @empty
        <div class="leaguewise-market-group">
            <div class="px-[1.35rem] py-[1.1rem] text-center leaguewise-market-header--static">
                <span class="leaguewise-market-title">{{ __('No live market data available.') }}</span>
            </div>
        </div>
    @endforelse
</article>
