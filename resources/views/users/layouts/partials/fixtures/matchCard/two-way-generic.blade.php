<article class="betwatch-match-card">
    <div class="betwatch-match-head betwatch-match-head--two-way">
        <svg class="betwatch-star-icon" data-favorite-trigger data-favorite-sport="{{ $routeName }}"
            data-favorite-type="league" data-favorite-league-id="{{ $group['rows'][0]['league_id'] ?? ($group['league_id'] ?? '') }}"
            data-favorite-label="{{ $group['league'] ?? 'Unknown league' }}" viewBox="0 0 20 20" fill="none" stroke-width="1.7" stroke-linecap="round"
            stroke-linejoin="round" aria-label="Toggle league favorite">
            <path d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
        </svg>

        <div class="betwatch-match-league">
            @php $flagCode = strtolower($group['flag'] ?? ''); @endphp
            @if($flagCode && $flagCode !== 'international')
                <img class="betguru-flag-img" src="https://flagcdn.com/w40/{{ $flagCode }}.png" alt="{{ $group['country_name'] ?? $group['name'] ?? 'Country' }} flag">
            @endif
            <span>{{ $group['league'] }}</span>
        </div>

        <span class="betwatch-odds-head">1</span>
        <span class="betwatch-odds-head">2</span>
    </div>

    @foreach ($group['rows'] as $row)
        @php
            $leagueRouteName = $routeName . '.league.wise.display';
            $derivedLeagueId =
                $row['league_id'] ??
                $group['league_id'] ??
                \Illuminate\Support\Str::slug(trim(\Illuminate\Support\Str::after($group['league'], ':')));
            $matchLabel = $row['teams'] ?? 'Unknown Match';

            $targetUrl =
                $row['url'] ??
                (\Illuminate\Support\Facades\Route::has($leagueRouteName)
                    ? route($leagueRouteName, ['league_id' => $derivedLeagueId])
                    : route($routeName));
        @endphp
        <div class="betwatch-match-row betwatch-match-row--two-way">
            <svg class="betwatch-star-icon" data-favorite-trigger data-favorite-sport="{{ $routeName }}"
                data-favorite-type="match" data-favorite-league-id="{{ $row['league_id'] ?? ($group['league_id'] ?? '') }}"
                data-favorite-match-id="{{ $row['id'] ?? '' }}" data-favorite-label="{{ $matchLabel }}" viewBox="0 0 20 20" fill="none" stroke-width="1.7" stroke-linecap="round"
                stroke-linejoin="round" aria-label="Toggle match favorite">
                <path
                    d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
            </svg>

            <div class="grid gap-0.5 leading-none">
                <span class="betwatch-status {{ $row['status_tone'] === 'live' ? 'betwatch-status--live' : '' }}">
                    {{ $row['status'] }}
                </span>

                @if (!empty($row['substatus']))
                    <span class="betwatch-score">{{ $row['substatus'] }}</span>
                @endif
            </div>

            <span class="betwatch-teams">
                <a href="{{ $targetUrl }}" class="betwatch-teams__label">{{ $row['teams'] }}</a>
            </span>

            @foreach ($row['odds'] as $odd)
                <span class="betwatch-odd betwatch-odd--{{ $odd['trend'] }}">
                    @if ($odd['trend'] === 'up')
                        <span class="betwatch-trend">&uarr;</span>
                    @elseif ($odd['trend'] === 'down')
                        <span class="betwatch-trend">&darr;</span>
                    @endif
                    <span>{{ $odd['value'] }}</span>
                </span>
            @endforeach
        </div>
    @endforeach
</article>
