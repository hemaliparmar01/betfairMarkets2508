
@if (count($rows) === 0)
    <article class="betwatch-match-card mt-3">
        <div class="betwatch-match-row betwatch-match-row--empty">
            <span class="betwatch-teams__label betwatch-empty-label betwatch-empty-label--small font-semibold">{{ __('No Tennis matches available.') }}</span>
        </div>
    </article>
@endif

@foreach ($rows as $row)
    @php
        $columns = array_values(array_filter(array_slice($row['options'] ?? [], 0, 3)));
        $optionCount = max(count($columns), 1);
        $matchLabel = trim(($row['team_1'] ?? '') . ' - ' . ($row['team_2'] ?? ''), ' -');
    @endphp

    <article class="betwatch-match-card">
        <div
            class="betwatch-match-row betwatch-moneyway-row"
        >
            <div class="betwatch-moneyway-icon-stack">
                <span class="betwatch-moneyway-info-icon">i</span>
                <svg class="betwatch-star-icon {{ ($row['is_market_fav'] ?? 0) == 1 ? 'is-favorite' : '' }}"
                    data-favorite-trigger
                    data-favorite-sport="tennis"
                    data-favorite-type="market"
                    data-favorite-league-id="{{ $row['league_id'] ?? '' }}"
                    data-favorite-match-id="{{ $row['match_id'] ?? '' }}"
                    data-favorite-market-id="{{ $row['market_id'] ?? '' }}"
                    data-favorite-label="{{ trim($matchLabel . ' - ' . ($row['market_name'] ?? 'Market'), ' -') }}"
                    viewBox="0 0 20 20" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                    aria-label="Toggle market favorite">
                    <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                </svg>
            </div>

            <div class="betwatch-moneyway-status-stack">
                @if ($row['is_live'] ?? false)
                    <span class="betwatch-moneyway-status betwatch-moneyway-status--live">
                        {{ $row['status'] ?? '-' }}
                    </span>

                    @if (!empty($row['score']))
                        <span class="betwatch-moneyway-status betwatch-moneyway-status--live">
                            {{ $row['score'] }}
                        </span>
                    @endif
                @else
                    <span class="betwatch-moneyway-status betwatch-moneyway-status--default">
                        {{ $row['status'] ?? '-' }}
                    </span>
                @endif
            </div>

            <div class="betwatch-moneyway-match-column">
                <div class="betwatch-moneyway-league-label">
                    {{ $row['country'] ?? 'International' }} : {{ $row['league'] ?? 'League' }}
                </div>

                <div class="text-[0.76rem] font-bold leading-[1.05] text-[rgb(var(--text-main))]">
                    {{ $matchLabel }}
                </div>

                <div class="betwatch-moneyway-market-caption">
                    {{ $row['market_name'] ?? 'Market' }}
                </div>
            </div>

            <div class="betwatch-moneyway-options betwatch-moneyway-options--{{ min($optionCount, 3) }}">
                @if($checkAuthAndSubscription['display_odds'])
                    @foreach ($columns as $option)
                        <div class="betwatch-moneyway-option">
                            <div class="betwatch-moneyway-option-label">{{ $option['label'] }}</div>
                            <div class="betwatch-moneyway-option-value">{{ $option['volume_text'] }}</div>
                            <div class="betwatch-moneyway-option-percent">{{ $option['volume_percent_text'] }}</div>

                            <div class="betwatch-moneyway-odd-value {{ $option['direction'] === 'down' ? 'betwatch-moneyway-odd-value--down' : ($option['direction'] === 'up' ? 'betwatch-moneyway-odd-value--up' : 'betwatch-moneyway-odd-value--neutral') }}">
                                @if ($option['direction'] === 'down')
                                    &darr;
                                @elseif ($option['direction'] === 'up')
                                    &uarr;
                                @endif
                                {{ $option['odd_text'] }}
                            </div>
                        </div>
                    @endforeach
                @else
                    @for ($i = 0; $i < 2; $i++)
                        <div class="betwatch-moneyway-option-label">
                            @include('lock_icon')
                        </div>
                    @endfor
                @endif
        </div>
    </article>
@endforeach
