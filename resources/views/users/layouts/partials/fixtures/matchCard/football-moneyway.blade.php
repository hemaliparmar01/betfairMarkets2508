@if (count($rows) === 0)
    <article class="betwatch-match-card mt-3">
        <div class="betwatch-match-row betwatch-match-row--empty">
            <span class="betwatch-teams__label betwatch-empty-label betwatch-empty-label--small font-semibold">{{ __('No Football matches available.') }}</span>
        </div>
    </article>
@endif

@foreach ($rows as $row)
    @php
        $columns = array_values(array_filter($row['options'] ?? []));
        $isCorrectScore = (bool) ($row['is_correct_score'] ?? false);
        $optionCount = max(count($columns), 1);
        $matchLabel = trim(($row['team_1'] ?? '') . ' - ' . ($row['team_2'] ?? ''), ' -');
        $statusText = $row['status'];
    @endphp

    <article class="betwatch-match-card"
        data-football-realtime-row
        data-football-match-id="{{ $row['api_match_id'] ?? '' }}"
        data-football-realtime-key="moneyway:{{ $row['api_match_id'] ?? '' }}:{{ $row['market_id'] ?? '' }}">
        <div class="betwatch-match-row betwatch-moneyway-row">
            <div class="betwatch-moneyway-icon-stack">
                <span class="betwatch-moneyway-info-icon">i</span>
                <svg class="betwatch-star-icon {{ ($row['is_market_fav'] ?? 0) == 1 ? 'is-favorite' : '' }}"
                    data-favorite-trigger
                    data-favorite-sport="football"
                    data-favorite-type="market"
                    data-favorite-league-id="{{ $row['league_id'] ?? '' }}"
                    data-favorite-match-id="{{ $row['match_id'] ?? '' }}"
                    data-favorite-market-id="{{ $row['market_id'] ?? '' }}"
                    data-favorite-label="{{ trim($matchLabel . ' - ' . ($row['market_name'] ?? ''), ' -') }}"
                    viewBox="0 0 20 20" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                    aria-label="Toggle match favorite">
                    <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                </svg>
            </div>

            <div class="betwatch-moneyway-status-stack">
                @if (($row['is_live'] ?? false) || ($row['is_ht'] ?? false))
                    {{-- <span class="betwatch-moneyway-status betwatch-moneyway-status--live"
                        data-football-live-clock
                        data-football-match-id="{{ $row['match_id'] ?? '' }}"
                        data-api-minute="{{ is_numeric($row['live_time'] ?? null) ? (int) $row['live_time'] : '' }}"
                        data-api-extra-time="{{ is_numeric($row['extra_time'] ?? null) ? (int) $row['extra_time'] : '' }}"
                        data-is-live="{{ ($row['is_live'] ?? false) ? '1' : '0' }}"
                        data-is-half-time="{{ ($row['is_ht'] ?? false) ? '1' : '0' }}"
                        data-is-finished="{{ ($row['finished'] ?? false) ? '1' : '0' }}"
                        data-server-status="{{ ($row['is_ht'] ?? false) ? 'HT' : (($row['finished'] ?? false) ? 'Finished' : ($row['status'] ?? '-')) }}">
                        {{ $row['status'] ?? '-' }}
                    </span> --}}
                    <span class="betwatch-moneyway-status betwatch-moneyway-status--live"
                        {{-- data-football-live-clock --}}
                        data-football-match-id="{{ $row['match_id'] ?? '' }}"
                        data-api-minute="{{ is_numeric($row['live_time'] ?? null) ? (int) $row['live_time'] : '' }}"
                        data-api-extra-time="{{ is_numeric($row['extra_time'] ?? null) ? (int) $row['extra_time'] : '' }}"
                        data-is-live="{{ ($row['is_live'] ?? false) ? '1' : 'sdas' }}"
                        data-is-half-time="{{ ($row['is_ht'] ?? false) ? '1' : 'yt' }}"
                        data-is-finished="{{ ($row['finished'] ?? false) ? '1' : 'vddd' }}">
                        {{ $row['status'] ?? '-' }}@if($statusText !== 'Live' && $statusText !== 'HT' && $statusText !== 'Finished' && $statusText !== 'Inplay')<span class="betwatch-live-minute-mark">'</span>@endif
                    </span>

                    <span class="betwatch-moneyway-status betwatch-moneyway-status--live">
                        {{ $row['score'] ?? '0-0' }}
                    </span>
                @else
                    <span class="betwatch-moneyway-status betwatch-moneyway-status--default"
                        data-football-live-clock
                        data-football-match-id="{{ $row['match_id'] ?? '' }}"
                        data-api-minute="{{ is_numeric($row['live_time'] ?? null) ? (int) $row['live_time'] : '' }}"
                        data-api-extra-time="{{ is_numeric($row['extra_time'] ?? null) ? (int) $row['extra_time'] : '' }}"
                        data-is-live="{{ ($row['is_live'] ?? false) ? '1' : '0' }}"
                        data-is-half-time="{{ ($row['is_ht'] ?? false) ? '1' : '0' }}"
                        data-is-finished="{{ ($row['finished'] ?? false) ? '1' : '0' }}"
                        data-server-status="{{ ($row['finished'] ?? false) ? 'Finished' : ($row['status'] ?? '-') }}">
                        {{ $row['status'] ?? '-' }}
                    </span>
                @endif
            </div>

            <div class="betwatch-moneyway-match-column">
                <div class="betwatch-moneyway-league-label">
                    {{ $row['country'] ?? 'International' }} : {{ $row['league'] ?? 'League' }}
                </div>

                <a href="{{ route('football.match.data',['match_id' => (int) $row['api_match_id']]) }}" class="betwatch-moneyway-match-link">
                    {{ $matchLabel }}
                </a>

                <div class="betwatch-moneyway-market-caption">
                    {{ $row['market_name'] ?? 'Market' }}
                </div>
            </div>

            <div class="{{ $isCorrectScore ? 'betwatch-moneyway-correct-score-scroll' : 'betwatch-moneyway-options betwatch-moneyway-options--'.min($optionCount, 3) }}">
                @if($checkAuthAndSubscription['display_odds'])
                    @foreach ($columns as $option)
                        @php
                            $optionDirection = is_numeric($option['odd'] ?? null)
                                ? ($option['direction'] ?? 'neutral')
                                : 'neutral';
                        @endphp
                        <div class="betwatch-moneyway-option">
                            <div class="betwatch-moneyway-option-label">{{ $option['label'] }}</div>
                            <div class="betwatch-moneyway-option-value">{{ $option['volume_text'] }}</div>
                            <div class="betwatch-moneyway-option-percent">{{ $option['volume_percent_text'] }}</div>

                            <div class="betwatch-moneyway-odd-value {{ $optionDirection === 'down' ? 'betwatch-moneyway-odd-value--down' : ($optionDirection === 'up' ? 'betwatch-moneyway-odd-value--up' : 'betwatch-moneyway-odd-value--neutral') }}">
                                <x-football-odd-hover :first-odd="$option['first_odd'] ?? null" :current-odd="$option['odd'] ?? null" :direction="$optionDirection">
                                    @if ($optionDirection === 'down')
                                        &darr;
                                    @elseif ($optionDirection === 'up')
                                        &uarr;
                                    @endif
                                    {{ $option['odd_text'] }}
                                </x-football-odd-hover>
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
        </div>
    </article>
@endforeach
