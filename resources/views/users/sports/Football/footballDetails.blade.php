@php
    $customerIsAuthenticated = auth('customers')->check();
    $isPrematchDrops = request()->input('type') === 'prematch_drops';
    $isMarketShock = request()->input('type') === 'market_shock';
    $shockCutoff = $isMarketShock ? time() - 1200 : 0;
    $checkAuthAndSubscription = $checkAuthAndSubscription ?? checkAuthAndSubscription();
    $data = array_filter($data, static fn (array $group): bool => ! empty($group['matches']));
@endphp
@unless ($append ?? false)
    <div class="fixtures-container flex min-w-0 flex-col space-y-2 [gap:4px] [margin-top:4px]" id="fixturesContainer">
    @endunless
    @if (count($data) === 0 && !($append ?? false))
        <div
            class="no-fixtures-message [padding:10px_0] [text-align:center] [font-size:13px] [color:var(--text-brand-60)] dark:[color:rgb(var(--text-soft))]">
            {{ __('No data available.') }}</div>
    @endif
    @if (! ($append ?? false) && count($data) > 0 && in_array(request()->input('type'), ['prematch_drops', 'market_shock'], true))
        <header class="football-alert-heading" @if ($isMarketShock) data-shock-heading @endif>
            <span class="football-alert-heading__icon" aria-hidden="true"><i class="fas {{ request()->input('type') === 'prematch_drops' ? 'fa-arrow-trend-down' : 'fa-bolt' }}"></i></span>
            <div>
                <h2>{{ request()->input('type') === 'prematch_drops' ? __('Prematch Drops') : __('Market Shock Detector') }} @if ($isPrematchDrops || $isMarketShock)<i class="fas fa-info-circle text-sm text-[var(--text-slate-500)]" title="{{ $isPrematchDrops ? __('Expand a match to see all markets and selections.') : __('Alerts are automatically removed after 20 minutes.') }}" aria-label="{{ $isPrematchDrops ? __('Expand a match to see all markets and selections.') : __('Alerts are automatically removed after 20 minutes.') }}"></i>@endif</h2>
                <p>{{ request()->input('type') === 'prematch_drops' ? __('Track ongoing price drops in real-time before the match starts') : __('Detects sudden, significant market shocks in real-time') }}</p>
            </div>
        </header>
    @endif
    @if ($isPrematchDrops && ! ($append ?? false) && count($data) > 0)
        <div data-prematch-columns>
            <span>{{ __('Status') }}</span><span>{{ __('Match / League') }}</span><span>{{ __('Market') }}</span><span>{{ __('Selection') }}</span>
            <span>{{ __('Odds Movement (Baseline → Current)') }}</span><span>{{ __('Drop %') }}</span><span>{{ __('Drop Started') }}</span><span>{{ __('Chart') }}</span>
        </div>
    @endif
    @foreach ($data as $league => $matches)
        <div class="league-header break-words [font-size:13px] font-bold uppercase [letter-spacing:0.5px] [color:var(--text-brand-60)] [padding:10px_0_6px_0] [border-bottom:2px_solid_var(--border-subtle)] [margin-top:8px] [transition:0.3s] bg-[rgb(var(--surface))] [z-index:2] dark:[color:rgb(var(--text-soft))] dark:[border-bottom-color:rgb(var(--border))] dark:[background:rgb(var(--surface))] [&:first-of-type]:[margin-top:0] max-[480px]:text-[11px]"
            data-league-header="{{ $league }}">
            <span class="inline-flex items-center gap-1.5">
                @if ($matches['tournament_logo'] ?? null)
                    <img src="{{ $matches['tournament_logo'] }}" alt="" width="18" height="18" loading="lazy"
                        decoding="async" onerror="this.remove()" class="h-[18px] w-[18px] shrink-0 object-contain">
                @endif
                <span>{{ $matches['tournament_country'] }} : {{ $matches['tournament_name'] }}</span>
            </span>
        </div>
        @foreach ($matches['matches'] as $event)
            @php
                $liveStats = $event['live_stats'] ?? [];
                $preferIncidents = ($event['card_counts_from_timeline'] ?? false) || ($event['card_counts_updated_at'] ?? 0) > ($event['card_stats_updated_at'] ?? 0);
                if (!isset($liveStats['corners']) && isset($event['corners']['home'], $event['corners']['away'])) {
                    $liveStats['corners'] = $event['corners'];
                }
                if (
                    ($preferIncidents || !isset($liveStats['yellow_cards'])) &&
                    isset($event['card_counts']['home']['yellow'], $event['card_counts']['away']['yellow'])
                ) {
                    $liveStats['yellow_cards'] = [
                        'home' => $event['card_counts']['home']['yellow'],
                        'away' => $event['card_counts']['away']['yellow'],
                    ];
                }
                if (
                    ($preferIncidents || !isset($liveStats['red_cards'])) &&
                    isset($event['card_counts']['home']['total_red'], $event['card_counts']['away']['total_red'])
                ) {
                    $liveStats['red_cards'] = [
                        'home' => $event['card_counts']['home']['total_red'],
                        'away' => $event['card_counts']['away']['total_red'],
                    ];
                }
                if (isset($liveStats['accurate_passes'], $liveStats['total_passes'])) {
                    $liveStats['pass_accuracy'] = [
                        'home' =>
                            $liveStats['total_passes']['home'] > 0
                                ? round(
                                    ($liveStats['accurate_passes']['home'] / $liveStats['total_passes']['home']) * 100,
                                    1,
                                )
                                : 0,
                        'away' =>
                            $liveStats['total_passes']['away'] > 0
                                ? round(
                                    ($liveStats['accurate_passes']['away'] / $liveStats['total_passes']['away']) * 100,
                                    1,
                                )
                                : 0,
                    ];
                }
            @endphp
            <div @if ($isPrematchDrops) data-prematch-expanded="false" @endif data-match-url="{{ route('football.matches.show', $event['sportsApiPro_match_id']) }}"
                data-sportApi-match-id="{{ $event['sportsApiPro_match_id'] }}"
                data-betfair-match-id="{{ $event['betfair_match_id'] }}"
                data-home-score="{{ $event['home_score'] ?? '' }}" data-away-score="{{ $event['away_score'] ?? '' }}"
                data-goal-signal="{{ $event['goal_highlight_until'] ?? 0 }}" data-match-status="{{ __($event['status']) }}" data-goal-highlight-seconds="{{ config('services.fixtures.goal_highlight_seconds', 40) }}"
                class="event-row {{ request()->input('type') === 'prematch_drops' ? 'football-alert football-alert--prematch' : (request()->input('type') === 'market_shock' ? 'football-alert football-alert--shock' : '') }} grid min-w-0 cursor-pointer grid-cols-[minmax(0,1fr)_148px_360px] items-center [background:var(--surface-row)] [border-radius:12px] [padding:5px_12px_5px_16px] [border:1px_solid_var(--border-neutral-5)] [gap:4px_12px] [transition:0.06s] [min-height:44px] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] dark:[&:hover]:[background:rgb(var(--bg))] [&:hover]:[background:var(--surface-neutral-0-16)] [&:hover]:[border-color:var(--border-brand-10)] max-[1024px]:grid-cols-[148px_minmax(0,1fr)] max-[1024px]:gap-2 max-[480px]:grid-cols-1 max-[480px]:p-2.5">
                @if (request()->input('type') === 'market_shock')
                    <div class="football-alert-league">
                        @if ($matches['tournament_logo'] ?? null)
                            <img src="{{ $matches['tournament_logo'] }}" alt="" width="18" height="18" loading="lazy" decoding="async" onerror="this.remove()">
                        @endif
                        <span>{{ $matches['tournament_name'] }}</span>
                    </div>
                @endif
                @if ($isPrematchDrops)
                    <span data-prematch-status title="{{ __('Drop classification is not supplied by the current data') }}"><span aria-hidden="true">●</span> —</span>
                @endif
                <div
                    class="event-left relative flex min-w-0 flex-nowrap items-center gap-2 overflow-hidden max-[1024px]:col-span-2 max-[480px]:col-span-1">
                    <button type="button"
                        class="fav-star [color:var(--text-brand-15-2)] [font-size:15px] cursor-pointer [background:none] border-0 [width:24px] [transition:0.2s] text-center dark:[color:rgb(var(--primary))] dark:[&.active-fav]:[color:rgb(var(--warning))] [&.active-fav]:[color:var(--text-warning-5-2)] disabled:cursor-wait disabled:opacity-60 {{ $event['is_favorite'] ?? false ? 'active-fav' : '' }}"
                        data-action="favorite" data-requires-login="{{ $customerIsAuthenticated ? 'false' : 'true' }}" data-login-url="{{ route('login') }}" data-sport="football" data-event-key="{{ $event['unique_key'] }}"
                        data-event-id="{{ $event['sportsApiPro_match_id'] }}" data-team1="{{ $event['team1'] }}"
                        data-team2="{{ $event['team2'] }}" data-start-timestamp="{{ $event['timestamp'] }}"
                        aria-pressed="{{ $event['is_favorite'] ?? false ? 'true' : 'false' }}"
                        aria-label="Favorite {{ $event['team1'] }} vs {{ $event['team2'] }}"><i
                            class="fas fa-star"></i></button>
                    @if ($isPrematchDrops)
                        <span data-prematch-sport aria-label="{{ __('Football') }}">⚽</span>
                        <div data-prematch-match>
                            <strong>{{ $event['team1'] }} {{ __('vs') }} {{ $event['team2'] }}</strong>
                            <div>
                                @if ($matches['tournament_logo'] ?? null)<img src="{{ $matches['tournament_logo'] }}" alt="" width="14" height="14" loading="lazy">@endif
                                <span>{{ $matches['tournament_country'] ? $matches['tournament_country'].' · ' : '' }}{{ $matches['tournament_name'] }}</span>
                                <time><i class="far fa-clock" aria-hidden="true"></i> {{ __($event['minutes']) }}</time>
                            </div>
                        </div>
                    @else
                    @php $color = $event['status'] == 'Live' ? 'var(--text-danger-15-2)' : 'var(--text-secondary)'; @endphp
                    <span style="color: {{ $color }}"
                        class="event-time w-[52px] shrink-0 [font-size:13px] font-medium [transition:color_0.3s] text-right dark:[color:rgb(var(--text-soft))]">{{ __($event['minutes']) }}
                        @if ($event['status'] === 'Live' && preg_match('/^\d+(?:\+\d*)?$/', (string) $event['minutes']) === 1)
                            <span class="minute-tick-blink">'</span>
                        @endif
                    </span>
                    @php
                        $homeCards = $event['card_counts']['home'] ?? [];
                        $awayCards = $event['card_counts']['away'] ?? [];
                        $homeCards['yellow'] =
                            $liveStats['yellow_cards']['home'] ?? $homeCards['yellow'] ?? (($event['cards']['home'] ?? null) === 'yellow' ? 1 : null);
                        $awayCards['yellow'] =
                            $liveStats['yellow_cards']['away'] ?? $awayCards['yellow'] ?? (($event['cards']['away'] ?? null) === 'yellow' ? 1 : null);
                        $homeCards['total_red'] = $liveStats['red_cards']['home'] ?? $homeCards['total_red'] ?? null;
                        $awayCards['total_red'] = $liveStats['red_cards']['away'] ?? $awayCards['total_red'] ?? null;
                    @endphp
                    <div
                        class="team-score-group grid min-w-0 flex-1 grid-cols-[28px_28px_28px_minmax(0,180px)_44px_minmax(0,180px)_28px_28px_28px] items-center justify-center gap-x-0.5 overflow-hidden whitespace-nowrap font-semibold text-[15px] text-[rgb(var(--text-main))] max-[768px]:grid-cols-[24px_24px_24px_minmax(0,1fr)_40px_minmax(0,1fr)_24px_24px_24px] max-[480px]:text-[12px]">
                        @if ($checkAuthAndSubscription['display_odds'])
                        <span
                            class="text-center text-xs text-[var(--text-brand-45-3)] max-[480px]:text-[10px] {{ ($event['corners']['home'] ?? null) === null || ($event['corners']['home'] ?? 0) > 0 ? '' : 'invisible' }}"
                            title="{{ __('Home corners') }}"><i class="fas fa-flag"></i>
                            {{ $event['corners']['home'] ?? '—' }}</span>
                        <span
                            class="text-center text-xs text-[var(--text-warning)] max-[480px]:text-[10px] {{ ($homeCards['yellow'] ?? null) === null || ($homeCards['yellow'] ?? 0) > 0 ? '' : 'invisible' }}"
                            title="{{ __('Home yellow cards') }}"><i class="fas fa-square"></i>
                            {{ $homeCards['yellow'] ?? '—' }}</span>
                        <span
                            class="text-center text-xs text-[var(--text-danger)] max-[480px]:text-[10px] {{ ($homeCards['total_red'] ?? null) === null || ($homeCards['total_red'] ?? 0) > 0 ? '' : 'invisible' }}"
                            title="Direct red: {{ $homeCards['direct_red'] ?? 0 }}, second yellow: {{ $homeCards['second_yellow'] ?? 0 }}"><i
                                class="fas fa-square"></i> {{ $homeCards['total_red'] ?? '—' }}</span>
                        @else
                            <span aria-hidden="true"></span>
                            <span aria-hidden="true"></span>
                            <span aria-hidden="true"></span>
                        @endif

                        <span
                            data-goal-team="home" class="team-name flex w-full min-w-0 items-center justify-end gap-1 pr-1 text-right font-semibold [transition:color_0.3s] dark:text-[var(--text-on-primary)]"
                            title="{{ $event['team1'] }}">
                            @if ($event['home_team_logo'] ?? null)
                                <img src="{{ $event['home_team_logo'] }}" alt="" width="18" height="18"
                                    loading="lazy" decoding="async" onerror="this.remove()"
                                    class="h-[18px] w-[18px] shrink-0 object-contain">
                            @endif
                            <span class="min-w-0 truncate">{{ $event['team1'] }}</span>
                        </span>
                        <span
                            data-score-status="{{ __($event['status']) }}" class="score-badge match-score block w-[44px] min-w-[44px] max-w-[44px] justify-self-center whitespace-nowrap px-1 text-center text-[16px] font-bold tabular-nums [font-variant-numeric:tabular-nums] [transition:color_0.3s] max-[480px]:text-[14px]">{{ $event['score'] ?? '—' }}</span>
                        <span
                            data-goal-team="away" class="team-name away flex w-full min-w-0 items-center justify-start gap-1 pl-1 text-left font-normal text-[var(--text-secondary)] [transition:color_0.3s] dark:text-[rgb(var(--text-soft))]"
                            title="{{ $event['team2'] }}">
                            <span class="min-w-0 truncate">{{ $event['team2'] }}</span>
                            @if ($event['away_team_logo'] ?? null)
                                <img src="{{ $event['away_team_logo'] }}" alt="" width="18" height="18"
                                    loading="lazy" decoding="async" onerror="this.remove()"
                                    class="h-[18px] w-[18px] shrink-0 object-contain">
                            @endif
                        </span>

                        @if ($checkAuthAndSubscription['display_odds'])
                        <span
                            class="text-center text-xs text-[var(--text-warning)] max-[480px]:text-[10px] {{ ($awayCards['yellow'] ?? null) === null || ($awayCards['yellow'] ?? 0) > 0 ? '' : 'invisible' }}"
                            title="{{ __('Away yellow cards') }}"><i class="fas fa-square"></i>
                            {{ $awayCards['yellow'] ?? '—' }}</span>
                        <span
                            class="text-center text-xs text-[var(--text-danger)] max-[480px]:text-[10px] {{ ($awayCards['total_red'] ?? null) === null || ($awayCards['total_red'] ?? 0) > 0 ? '' : 'invisible' }}"
                            title="Direct red: {{ $awayCards['direct_red'] ?? 0 }}, second yellow: {{ $awayCards['second_yellow'] ?? 0 }}"><i
                                class="fas fa-square"></i> {{ $awayCards['total_red'] ?? '—' }}</span>
                        <span
                            class="text-center text-xs text-[var(--text-brand-45-3)] max-[480px]:text-[10px] {{ ($event['corners']['away'] ?? null) === null || ($event['corners']['away'] ?? 0) > 0 ? '' : 'invisible' }}"
                            title="{{ __('Away corners') }}">{{ $event['corners']['away'] ?? '—' }} <i
                                class="fas fa-flag"></i></span>
                        @else
                            <span aria-hidden="true"></span>
                            <span aria-hidden="true"></span>
                            <span aria-hidden="true"></span>
                        @endif
                    </div>
                    @endif
                </div>
                <div
                    class="event-actions flex w-[148px] items-center justify-end [gap:2px] max-[1024px]:w-[148px] max-[1024px]:justify-start max-[480px]:w-full max-[480px]:justify-center">
                    <button type="button"
                        class="live-stat-icon bg-transparent border-0 [color:var(--text-live)] [font-size:15px] [padding:4px_4px] cursor-pointer [border-radius:8px] [width:28px] text-center [transition:0.2s] disabled:cursor-not-allowed disabled:opacity-30 dark:[color:rgb(var(--primary))] dark:[&:hover]:[background:rgb(var(--surface))] dark:[&:hover]:[color:var(--text-on-primary)] [&:hover]:[background:var(--surface-neutral-5-7)] [&:hover]:[color:var(--text-danger-30)]"
                        @if ($checkAuthAndSubscription['display_odds']) data-action="livestats" data-match-id="{{ $event['sportsApiPro_match_id'] }}"
                            aria-expanded="false" aria-controls="live-stats-{{ $event['sportsApiPro_match_id'] }}"
                            title="{{ __('Live Statistics') }}"
                        @else
                            disabled title="{{ __('Live Statistics (requires subscription)') }}"
                        @endif
                        @disabled($event['status'] !== 'Live')>
                        <i class="fas fa-wave-square"></i>
                    </button>
                    <button type="button"
                        class="action-icon w-7 cursor-pointer rounded-lg border-0 bg-transparent px-1 py-1 text-center text-[15px] text-[var(--text-brand-45)] transition hover:bg-[var(--surface-neutral-5-7)] hover:text-[rgb(var(--text-main))] disabled:cursor-not-allowed disabled:opacity-30 dark:text-[rgb(var(--primary))] dark:hover:bg-[rgb(var(--surface))] dark:hover:text-[var(--text-on-primary)]"
                        aria-label="{{ __('Quick Stats') }}"
                        @if ($checkAuthAndSubscription['display_odds']) data-action="quickstats" data-match-id="{{ $event['sportsApiPro_match_id'] }}"
                            aria-expanded="false" aria-controls="quick-stats-{{ $event['sportsApiPro_match_id'] }}"
                            title="{{ __('Quick Stats') }}"
                        @else
                            disabled title="{{ __('Quick Stats (requires subscription)') }}"
                        @endif
                        @disabled($event['status'] !== 'Live')>
                        <i class="fas fa-chart-pie" aria-hidden="true"></i>
                    </button>
                    <button type="button"
                        class="action-icon bg-transparent border-0 [color:var(--text-brand-45)] [font-size:15px] [padding:4px_4px] cursor-pointer [border-radius:8px] [width:28px] text-center [transition:0.2s] dark:[color:rgb(var(--primary))] dark:[&:hover]:[background:rgb(var(--surface))] dark:[&:hover]:[color:var(--text-on-primary)] [&:hover]:[background:var(--surface-neutral-5-7)] [&:hover]:[color:rgb(var(--text-main))] [&.disabled]:[opacity:0.3] [&.disabled]:[pointer-events:none]"
                        data-action="chart" data-chart-url="{{ route('football.matches.show', $event['sportsApiPro_match_id']) }}" title="{{ __('Markets & odds charts') }}" aria-label="{{ __('Markets & odds charts') }}"><i class="fas fa-chart-simple"></i></button>
                    <button type="button"
                        class="action-icon bg-transparent border-0 [color:var(--text-brand-45)] [font-size:15px] [padding:4px_4px] cursor-pointer [border-radius:8px] [width:28px] text-center [transition:0.2s] dark:[color:rgb(var(--primary))] disabled:cursor-not-allowed disabled:opacity-30 dark:[&:hover]:[background:rgb(var(--surface))] dark:[&:hover]:[color:var(--text-on-primary)] [&:hover]:[background:var(--surface-neutral-5-7)] [&:hover]:[color:rgb(var(--text-main))] [&.disabled]:[opacity:0.3] [&.disabled]:[pointer-events:none]"
                        @if ($checkAuthAndSubscription['display_odds'])
                            data-action="stats" data-match-id="{{ $event['sportsApiPro_match_id'] }}" aria-expanded="false" aria-controls="statistics-{{ $event['sportsApiPro_match_id'] }}" title="{{ __('Statistics') }}"
                        @else
                            disabled title="{{ __('Statistics (requires subscription)') }}"
                        @endif
                        ><i class="fas fa-chart-bar"></i></button>
                    <button type="button"
                        class="action-icon bg-transparent border-0 [color:var(--text-brand-45)] [font-size:15px] [padding:4px_4px] cursor-pointer [border-radius:8px] [width:28px] text-center [transition:0.2s] disabled:cursor-not-allowed disabled:opacity-30 dark:[color:rgb(var(--primary))] dark:[&:hover]:[background:rgb(var(--surface))] dark:[&:hover]:[color:var(--text-on-primary)] [&:hover]:[background:var(--surface-neutral-5-7)] [&:hover]:[color:rgb(var(--text-main))]"
                        @if ($checkAuthAndSubscription['display_odds'])
                            data-action="prediction" aria-expanded="false" aria-controls="prediction-{{ $event['sportsApiPro_match_id'] }}" data-requires-login="{{ $customerIsAuthenticated ? 'false' : 'true' }}" data-login-url="{{ route('login') }}" data-match-id="{{ $event['sportsApiPro_match_id'] }}"
                        @else
                            disabled title="{{ __('Prediction (requires subscription)') }}"
                        @endif
                        title="{{ __('Prediction') }}" @disabled($event['status'] !== 'Prematch')><i class="fas fa-brain"></i></button>
                </div>
                @if (($event['timeline'] ?? null) !== null)
                    <div class="w-[360px] min-w-0 max-[1024px]:w-full max-[480px]:col-span-1">
                        @if ($checkAuthAndSubscription['display_odds'])
                            @php $timeline = $event['timeline']; @endphp
                            <div data-football-timeline="{{ $event['sportsApiPro_match_id'] }}"
                                data-timeline-clock-limit="{{ $timeline['clock_limit'] }}" data-timeline-minute="{{ $timeline['minute'] }}"
                                data-timeline-duration="{{ $timeline['duration'] }}"
                                data-timeline-at="{{ $timeline['calculated_at'] }}"
                                data-timeline-second="{{ $timeline['seconds_into_minute'] }}"
                                data-timeline-running="{{ $timeline['running'] ? 'true' : 'false' }}"
                                data-timeline-period-limit="{{ $timeline['period_limit'] }}"
                                data-timeline-label="{{ $timeline['minute_label'] }}"
                                data-timeline-score="{{ $event['score'] }}"
                                class="relative min-w-0 overflow-hidden rounded-lg border border-[var(--border-neutral-5-10)] bg-[var(--surface-muted)] text-[var(--text-brand-55)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-[rgb(var(--text-main))]">
                                <div id="timeline-panel-{{ $event['sportsApiPro_match_id'] }}" data-timeline-panel
                                    class="hidden rounded-t-md bg-[var(--surface-brand-25)] text-center text-xs text-white" role="region"
                                    aria-label="{{ __('Match timeline details') }}">
                                    <div class="relative grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-1 bg-[var(--surface-brand-20)] px-5 py-2">
                                        <span class="min-w-0 break-words text-right">{{ $event['team1'] }}</span>
                                        <span class="rounded bg-[var(--surface-neutral-80)] px-1.5 py-1">
                                            <strong data-timeline-detail-score data-score-status="{{ __($event['status']) }}" class="match-score match-score-on-dark block whitespace-nowrap">{{ $event['score'] }}</strong>
                                            <span data-timeline-detail-minute>{{ $timeline['minute_label'] }}</span>
                                        </span>
                                        <span class="min-w-0 break-words text-left">{{ $event['team2'] }}</span>
                                        <button type="button" data-timeline-close class="absolute right-0.5 top-0.5 h-5 w-5 cursor-pointer rounded text-base hover:bg-black/10"
                                            aria-label="{{ __('Close timeline details') }}">&times;</button>
                                    </div>
                                    <div data-timeline-detail-event class="hidden flex-wrap items-center justify-center gap-1 px-2 py-1.5">
                                        <span data-timeline-detail-icon aria-hidden="true"></span>
                                        <span data-timeline-detail-player class="break-words"></span>
                                        <span data-timeline-detail-kind class="text-[10px]"></span>
                                    </div>
                                    <div data-timeline-goals class="space-y-1 px-2 py-1.5">
                                        @forelse (array_filter($timeline['events'], static fn (array $incident): bool => $incident['kind'] === 'goal') as $goal)
                                            <div class="flex items-center justify-center gap-1">
                                                <span aria-hidden="true">⚽</span>
                                                @if ($goal['player_surname'] !== '')<span>{{ $goal['player_surname'] }}</span>@endif
                                                @if ($goal['minute_label'] !== '')<span>{{ $goal['minute_label'] }}</span>@endif
                                            </div>
                                        @empty
                                            @if (! ($timeline['goals_pending'] ?? false))<span>{{ __('No goals yet') }}</span>@endif
                                        @endforelse
                                    </div>
                                </div>
                                <div class="relative mx-3 h-16" style="height: {{ $timeline['height'] }}px">
                                    <button type="button" data-timeline-toggle aria-expanded="false"
                                        aria-controls="timeline-panel-{{ $event['sportsApiPro_match_id'] }}"
                                        aria-label="{{ __('Show match timeline for :home vs :away', ['home' => $event['team1'], 'away' => $event['team2']]) }}"
                                        class="absolute inset-0 h-full w-full cursor-pointer rounded bg-transparent outline-none focus-visible:ring-2 focus-visible:ring-[var(--border-cyan-300)]"></button>
                                    <span class="pointer-events-none absolute inset-x-0 top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-[var(--surface-neutral-10-2)] dark:bg-[rgb(var(--surface-soft))]" aria-hidden="true"></span>
                                    <span data-timeline-progress class="pointer-events-none absolute left-0 top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-[var(--surface-success-40)] transition-[width] duration-300 dark:bg-[var(--status-success-fill)]"
                                        style="width: {{ $timeline['progress'] }}%" aria-hidden="true"></span>
                                    <span class="pointer-events-none absolute top-1/2 h-3 w-px -translate-x-1/2 -translate-y-1/2 bg-[var(--surface-brand-25-2)]" style="left: {{ 45 / $timeline['duration'] * 100 }}%" aria-hidden="true"></span>
                                    <div class="pointer-events-none absolute inset-x-0 bottom-0.5 flex justify-between text-[9px] leading-none text-[var(--text-brand-35-2)] dark:text-[rgb(var(--text-soft))]" aria-hidden="true">
                                        <span>0'</span><span class="absolute -translate-x-1/2" style="left: {{ 45 / $timeline['duration'] * 100 }}%">45'</span><span class="ml-auto">{{ $timeline['duration'] }}'</span>
                                    </div>
                                    @if ($timeline['finished'])
                                        <span class="absolute left-0 top-0.5 text-[9px] font-semibold">{{ __('FT') }}</span>
                                    @endif
                                    @foreach ($timeline['events'] as $incident)
                                        @if ($incident['time_known'])
                                            @include('users.sports.football.partials.timelineEvent', ['incident' => $incident, 'positioned' => true])
                                        @endif
                                    @endforeach
                                </div>
                                @if ($timeline['untimed_events']['home'] !== [] || $timeline['untimed_events']['away'] !== [])
                                    <div data-timeline-untimed class="border-t border-[var(--border-neutral-5-10)] px-2 py-1 text-[9px] text-[var(--text-brand-35-2)] dark:border-[rgb(var(--border))] dark:text-[rgb(var(--text-soft))]">
                                        @foreach ($timeline['untimed_events'] as $side => $incidents)
                                            @if ($incidents !== [])
                                                <div class="flex min-w-0 items-start gap-1" data-timeline-untimed-side="{{ $side }}">
                                                    <div class="flex min-w-0 flex-wrap gap-0.5">
                                                        @foreach ($incidents as $incident)
                                                            @include('users.sports.football.partials.timelineEvent', ['incident' => $incident, 'positioned' => false])
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="flex min-h-8 items-center justify-center">@include('lock_icon')</div>
                        @endif
                    </div>
                @else
                    <div class="odds-group flex min-h-8 w-[360px] min-w-0 flex-col justify-center gap-2 max-[1024px]:w-full max-[480px]:col-span-1">
                        @if ($checkAuthAndSubscription['display_odds'])
                            @if ($isPrematchDrops)
                                <button type="button" data-prematch-toggle="{{ $event['sportsApiPro_match_id'] }}" aria-expanded="false" aria-controls="prematch-markets-{{ $event['sportsApiPro_match_id'] }}" aria-label="{{ __('Expand or collapse markets') }}"><i class="fas fa-chevron-down" aria-hidden="true"></i></button>
                                <div data-prematch-markets="{{ $event['sportsApiPro_match_id'] }}" id="prematch-markets-{{ $event['sportsApiPro_match_id'] }}" tabindex="0" aria-label="{{ __('Markets for :home vs :away', ['home' => $event['team1'], 'away' => $event['team2']]) }}">
                                    <div data-prematch-groups>
                            @endif
                            @forelse ($event['odds_markets'] ?? [] as $market)
                                <div data-football-odds-source="{{ $market['source'] }}">
                                    <div class="football-market-label mb-1 flex min-w-0 items-center gap-1 text-[10px] leading-tight text-[var(--text-brand-45-3)] dark:text-[rgb(var(--text-soft))]">
                                        <span class="shrink-0 font-bold">{{ $market['source'] }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span class="truncate" title="{{ $market['label'] }}">{{ $market['label'] }}</span>
                                    </div>
                                    <div @class([
                                        'grid gap-2 max-[480px]:gap-1.5',
                                        'grid-cols-1' => count($market['runners']) === 1,
                                        'grid-cols-2' => count($market['runners']) === 2,
                                        'grid-cols-3' => count($market['runners']) >= 3,
                                    ])>
                                        @foreach ($market['runners'] as $odd)
                                            @continue($isMarketShock && (int) ($odd['detected_at'] ?? 0) <= $shockCutoff)
                                            @if ($isPrematchDrops || $isMarketShock)
                                            @php
                                                $history = $odd['history'] ?? [];
                                                $intermediateOdds = count($history) > 2 ? $history[count($history) - 2]['odds'] : null;
                                                if ($intermediateOdds == $odd['previousBackOdds'] || $intermediateOdds == $odd['currentBackOdds']) {
                                                    $intermediateOdds = null;
                                                }
                                                $movementPrevious = $intermediateOdds ?? $odd['previousBackOdds'];
                                                $movementDirection = $movementPrevious === null || $odd['currentBackOdds'] === null || $odd['currentBackOdds'] == $movementPrevious
                                                    ? 'same' : ($odd['currentBackOdds'] < $movementPrevious ? 'down' : 'up');
                                                $dropPercent = $odd['previousBackOdds'] !== null && $odd['currentBackOdds'] !== null
                                                    ? (($odd['previousBackOdds'] - $odd['currentBackOdds']) / $odd['previousBackOdds']) * 100 : null;
                                            @endphp
                                            @endif
                                            <div class="odd-box relative min-w-0 cursor-default rounded-[10px] border border-[var(--border-neutral-5-11)] bg-[rgb(var(--surface))] px-2 py-1 text-center font-semibold text-[rgb(var(--text-main))] shadow-[0_1px_2px_var(--shadow-neutral-100-3)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))] dark:text-[var(--text-on-primary)] dark:[&_.tooltip]:bg-[rgb(var(--surface))] dark:[&_.tooltip]:text-[var(--text-on-primary)] [&_.tooltip]:invisible [&_.tooltip]:absolute [&_.tooltip]:bottom-[110%] [&_.tooltip]:left-1/2 [&_.tooltip]:z-10 [&_.tooltip]:-translate-x-1/2 [&_.tooltip]:whitespace-nowrap [&_.tooltip]:rounded-md [&_.tooltip]:bg-[var(--surface-heading)] [&_.tooltip]:px-2 [&_.tooltip]:py-1 [&_.tooltip]:text-[11px] [&_.tooltip]:font-normal [&_.tooltip]:text-white [&_.tooltip]:opacity-0 [&:hover_.tooltip]:visible [&:hover_.tooltip]:opacity-100"
                                                title="{{ $odd['runner'] }}" @if ($isMarketShock) data-shock-alert="{{ $event['sportsApiPro_match_id'].'|'.$market['source'].'|'.$odd['alert_id'].'|'.$odd['detected_at'] }}" data-shock-detected-at="{{ (int) $odd['detected_at'] }}" data-shock-expires-at="{{ (int) $odd['detected_at'] + 1200 }}" @endif>
                                                @if ($isMarketShock)
                                                    <span data-shock-badge><i class="fas fa-bolt" aria-hidden="true"></i> {{ __('SHOCK DETECTED') }}</span>
                                                    <a data-shock-match href="{{ route('football.matches.show', $event['sportsApiPro_match_id']) }}"><span aria-label="{{ __('Football') }}">⚽</span> {{ $event['team1'] }} {{ __('vs') }} {{ $event['team2'] }}</a>
                                                    <div data-shock-league>
                                                        @if ($matches['tournament_logo'] ?? null)<img src="{{ $matches['tournament_logo'] }}" alt="" width="14" height="14" loading="lazy">@else<i class="fas fa-flag" aria-hidden="true"></i>@endif
                                                        <span>{{ $matches['tournament_name'] }}</span>
                                                    </div>
                                                    <div data-shock-times><time><i class="far fa-clock" aria-hidden="true"></i> {{ \Carbon\Carbon::createFromTimestamp($event['timestamp'])->isToday() ? __('Today') : \Carbon\Carbon::createFromTimestamp($event['timestamp'])->format('d M') }} {{ __($event['minutes']) }}</time><span aria-hidden="true">|</span><time datetime="{{ \Carbon\Carbon::createFromTimestampUTC($odd['detected_at'])->toIso8601String() }}">{{ __('Detected:') }} {{ \Carbon\Carbon::createFromTimestampUTC($odd['detected_at'])->diffForHumans(short: true) }}</time></div>
                                                    <div data-shock-market><span>{{ __('Market') }}</span><strong>{{ $market['label'] }}</strong><small>{{ $market['source'] }}</small></div>
                                                @endif
                                                @if (in_array(request()->input('type'), ['prematch_drops', 'market_shock'], true))
                                                    <span class="football-alert-field-label">{{ __('Selection') }}</span>
                                                @endif
                                                <span class="football-alert-selection block truncate text-[9px] font-medium leading-tight text-[var(--text-brand-45-3)] dark:text-[rgb(var(--text-soft))]">{{ $odd['runner'] }}</span>
                                                @if (in_array(request()->input('type'), ['prematch_drops', 'market_shock'], true))
                                                    <span class="football-alert-field-label">{{ __('Odds Movement') }}</span>
                                                @endif
                                                <span class="football-alert-movement flex items-center justify-center gap-1 whitespace-nowrap text-[15px] tabular-nums">
                                                    @if (in_array(request()->input('type'), ['prematch_drops', 'market_shock'], true) && $odd['previousBackOdds'] !== null && $odd['currentBackOdds'] !== null)
                                                        <span class="football-alert-previous">{{ number_format($odd['previousBackOdds'], 2) }}</span>
                                                        @if ($isPrematchDrops)
                                                            @if ($intermediateOdds !== null)
                                                                <i class="fas {{ $intermediateOdds < $odd['previousBackOdds'] ? 'fa-arrow-down text-[var(--text-red-600)]' : 'fa-arrow-up text-[var(--text-green-600)]' }}" aria-hidden="true"></i>
                                                                <span class="football-alert-previous">{{ number_format($intermediateOdds, 2) }}</span>
                                                            @endif
                                                            <i class="fas {{ $movementDirection === 'down' ? 'fa-arrow-down text-[var(--text-red-600)]' : ($movementDirection === 'up' ? 'fa-arrow-up text-[var(--text-green-600)]' : 'fa-minus text-[var(--text-slate-400)]') }}" aria-hidden="true"></i>
                                                        @else
                                                            <span class="football-alert-arrow" aria-hidden="true">→</span>
                                                        @endif
                                                    @endif
                                                    @if ($odd['currentBackOdds'] !== null)
                                                        @if ($isPrematchDrops)<span data-prematch-current="{{ $movementDirection }}">@elseif ($isMarketShock)<span data-shock-current="{{ $odd['odds_direction'] }}">@endif{{ number_format($odd['currentBackOdds'], 2) }}@if ($isPrematchDrops || $isMarketShock)</span>@endif
                                                        @if ($odd['odds_direction'] === 'down')
                                                            <i class="fas fa-arrow-down text-xs text-[var(--text-danger)]" aria-label="{{ __('Odds decreased') }}"></i>
                                                        @elseif ($odd['odds_direction'] === 'up')
                                                            <i class="fas fa-arrow-up text-xs text-[var(--text-success)]" aria-label="{{ __('Odds increased') }}"></i>
                                                        @endif
                                                        @if ($odd['previousBackOdds'] !== null)
                                                            <span class="tooltip">{{ number_format($odd['previousBackOdds'], 2) }} → {{ number_format($odd['currentBackOdds'], 2) }}</span>
                                                        @endif
                                                    @else
                                                        <span aria-label="{{ __('Odds unavailable') }}">—</span>
                                                    @endif
                                                </span>
                                                @if ($isMarketShock)
                                                    <div data-shock-movement="{{ $odd['odds_direction'] }}"><i class="fas {{ $odd['odds_direction'] === 'down' ? 'fa-arrow-down' : ($odd['odds_direction'] === 'up' ? 'fa-arrow-up' : 'fa-minus') }}" aria-hidden="true"></i> {{ $dropPercent !== null ? sprintf('%+.1f%%', -$dropPercent) : '—' }}</div>
                                                    <span data-shock-countdown aria-live="off">{{ __('Expires in') }} —</span>
                                                    <button type="button" data-shock-chart data-action="chart" data-chart-url="{{ route('football.matches.show', $event['sportsApiPro_match_id']) }}"><i class="fas fa-chart-bar" aria-hidden="true"></i> {{ __('Chart') }}</button>
                                                @endif
                                                @if ($isPrematchDrops)
                                                    <span data-prematch-drop data-label="{{ __('Drop %') }}">{{ $dropPercent !== null ? sprintf('%+.1f%%', -$dropPercent) : '—' }}</span>
                                                    <time data-prematch-started data-label="{{ __('Drop Started') }}" @if ($odd['detected_at'] ?? null) datetime="{{ \Carbon\Carbon::createFromTimestampUTC($odd['detected_at'])->toIso8601String() }}" @endif>{{ ($odd['detected_at'] ?? null) ? \Carbon\Carbon::createFromTimestampUTC($odd['detected_at'])->diffForHumans(short: true) : '—' }}</time>
                                                    <button type="button" data-action="chart" data-chart-url="{{ route('football.matches.show', $event['sportsApiPro_match_id']) }}" title="{{ __('Markets & odds charts') }}" aria-label="{{ __('Chart for :selection', ['selection' => $odd['runner']]) }}"><i class="fas fa-chart-bar" aria-hidden="true"></i></button>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <span class="text-center text-xs text-[var(--text-brand-35-2)] dark:text-[rgb(var(--text-soft))]">{{ __('Odds unavailable') }}</span>
                            @endforelse
                            @if ($isPrematchDrops)
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="flex min-h-8 items-center justify-center">@include('lock_icon')</div>
                        @endif
                    </div>
                @endif
            </div>
            @if ($checkAuthAndSubscription['display_odds'])
                @include('users.sports.football.partials.statistics', ['event' => $event])
            @endif
            @if ($checkAuthAndSubscription['display_odds'] && $event['status'] === 'Live')
                @include('users.sports.football.partials.quickStats', ['event' => $event, 'liveStats' => $liveStats])
                <div id="live-stats-{{ $event['sportsApiPro_match_id'] }}"
                    data-live-stats-panel="{{ $event['sportsApiPro_match_id'] }}" role="dialog" aria-modal="true"
                    aria-label="Live statistics for {{ $event['team1'] }} vs {{ $event['team2'] }}"
                    class="fixed inset-0 z-[80] hidden overflow-hidden bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6">
                    <div
                        class="relative mx-auto my-4 w-full max-w-4xl max-h-[calc(100dvh-3.5rem)] overflow-x-hidden overflow-y-auto overscroll-contain rounded-2xl sm:max-h-[calc(100dvh-7rem)] border border-[var(--border-neutral-5-6)] bg-[rgb(var(--surface))] shadow-2xl dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] sm:my-8">
                        <div
                            class="relative bg-gradient-to-r from-[var(--surface-brand-65-2)] to-[var(--surface-brand-35)] px-4 py-5 text-white sm:px-7">
                            <button type="button" data-close-live-stats
                                class="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-[rgb(var(--surface))]/15 text-lg text-white transition hover:bg-[rgb(var(--surface))]/25 focus:outline-none focus:ring-2 focus:ring-[var(--text-on-primary)]/70"
                                aria-label="{{ __('Close live statistics') }}"><i class="fas fa-times"></i></button>
                            <div
                                class="mb-3 text-center text-[11px] font-bold uppercase tracking-[0.14em] text-[var(--text-brand-5)]">
                                {{ $matches['tournament_name'] }} {{ __('· Live Statistics') }}</div>
                            <div class="flex flex-wrap items-center justify-center gap-3 text-center sm:gap-6">
                                <span
                                    class="max-w-[36%] text-sm font-semibold sm:text-base">{{ $event['team1'] }}</span>
                                <span
                                    data-score-status="{{ __($event['status']) }}" class="match-score match-score-on-dark text-2xl font-extrabold tracking-wider sm:text-4xl">{{ $event['score'] }}</span>
                                <span
                                    class="max-w-[36%] text-sm font-semibold sm:text-base">{{ $event['team2'] }}</span>
                            </div>
                            <div
                                class="mt-2 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-wider">
                                <span class="h-2 w-2 animate-pulse rounded-full bg-[var(--surface-red-500)]"></span>
                                {{ __($event['minutes']) }}@if (preg_match('/^\d+(?:\+\d*)?$/', (string) $event['minutes']) === 1)
                                    <span class="minute-tick-blink">'</span>
                                @endif
                            </div>
                        </div>

                        <div class="p-4 sm:p-7">
                            @include('users.sports.football.partials.statisticsTable', ['event' => $event])
                        </div>
                    </div>
                </div>
            @endif
            @if ($event['status'] === 'Prematch')
                @php
                    $predictionPolls = [
                        '1x2' => [
                            'label' => 'Match Result',
                            'options' => [
                                '1' => ['label' => '1', 'sublabel' => $event['team1']],
                                'X' => ['label' => 'X', 'sublabel' => 'Draw'],
                                '2' => ['label' => '2', 'sublabel' => $event['team2']],
                            ],
                        ],
                        'uo' => [
                            'label' => 'Total Goals',
                            'options' => [
                                'UNDER' => ['label' => 'Under 2.5', 'sublabel' => '2 goals or less'],
                                'OVER' => ['label' => 'Over 2.5', 'sublabel' => '3 goals or more'],
                            ],
                        ],
                    ];
                @endphp
                <div id="prediction-{{ $event['sportsApiPro_match_id'] }}"
                    data-prediction-panel="{{ $event['sportsApiPro_match_id'] }}" role="dialog" aria-modal="true"
                    aria-label="Predictions for {{ $event['team1'] }} vs {{ $event['team2'] }}"
                    class="fixed inset-0 z-[80] hidden overflow-hidden bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6">
                    <div
                        class="relative mx-auto my-4 w-full max-w-[700px] max-h-[calc(100dvh-3.5rem)] overflow-x-hidden overflow-y-auto overscroll-contain rounded-2xl sm:max-h-[calc(100dvh-7rem)] bg-[rgb(var(--surface))] shadow-2xl dark:bg-[rgb(var(--bg))] sm:my-8">
                        <div
                            class="relative bg-gradient-to-br from-[var(--surface-brand-80-2)] to-[var(--surface-brand-75-2)] px-5 py-6 text-center text-white sm:px-7">
                            <button type="button" data-close-prediction
                                class="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-[rgb(var(--surface))]/10 text-lg text-white transition hover:bg-[rgb(var(--surface))]/20 focus:outline-none focus:ring-2 focus:ring-[var(--text-on-primary)]/70"
                                aria-label="{{ __('Close predictions') }}"><i class="fas fa-times"></i></button>
                            <div class="mb-4 text-xs font-semibold uppercase tracking-[0.15em] text-[var(--text-success-25)]">
                                {{ $matches['tournament_name'] }}</div>
                            <div class="flex items-center justify-center gap-3 text-base font-bold sm:text-xl">
                                <span>{{ $event['team1'] }}</span>
                                <span class="font-light text-[var(--text-slate-400)]">—</span>
                                <span>{{ $event['team2'] }}</span>
                            </div>
                            <div class="mt-2 text-sm text-[var(--text-slate-400)]">{{ date('H:i', $event['timestamp']) }}</div>
                        </div>

                        <div class="p-5 sm:p-7">
                            <h2
                                class="mb-2 flex items-center gap-2 text-sm font-bold text-[var(--text-brand-80)] before:h-[18px] before:w-1 before:rounded-full before:bg-[var(--surface-success-25)] before:content-[''] dark:text-white">
                                {{ __('Community Predictions') }}</h2>
                            <p class="mb-5 text-xs text-[var(--text-slate-500)] dark:text-[var(--text-slate-400)]">{{ __('Share your opinion before the match starts') }}</p>
                            <span
                                class="mb-5 inline-block rounded-full border border-[var(--border-success-25)] bg-[var(--surface-neutral-0-20)] px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-[var(--text-brand-80)] dark:bg-[var(--status-success-surface)] dark:text-[rgb(var(--success))]">{{ __('⚽ Football') }}</span>

                            <div class="space-y-7">
                                @foreach ($predictionPolls as $pollKey => $poll)
                                    @php
                                        $userVote = $event['prediction_votes'][$pollKey] ?? null;
                                        $counts = $event['predictions'][$pollKey] ?? [];
                                        $totalVotes = array_sum($counts);
                                        $highestCount = $counts === [] ? 0 : max($counts);
                                    @endphp
                                    <section data-prediction-poll="{{ $pollKey }}">
                                        <div class="mb-3 text-[13px] font-semibold text-[var(--text-slate-600)] dark:text-[var(--text-slate-300)]">
                                            {{ $poll['label'] }}</div>

                                        @if ($userVote === null)
                                            <div class="flex gap-3 max-[480px]:gap-2">
                                                @foreach ($poll['options'] as $optionValue => $option)
                                                    <button type="button" data-prediction-option data-requires-login="{{ $customerIsAuthenticated ? 'false' : 'true' }}" data-login-url="{{ route('login') }}"
                                                        data-match-id="{{ $event['sportsApiPro_match_id'] }}"
                                                        data-poll="{{ $pollKey }}"
                                                        data-value="{{ $optionValue }}"
                                                        class="flex-1 select-none rounded-xl border-2 border-[var(--border-slate-200)] bg-[var(--surface-slate-50)] px-3 py-4 text-center transition hover:-translate-y-0.5 hover:border-[var(--border-success-25)] hover:bg-[var(--surface-neutral-0-20)] dark:border-[var(--border-slate-700)] dark:bg-[rgb(var(--bg))] dark:hover:border-[var(--status-success-border)] dark:hover:bg-[var(--status-success-surface)] max-[480px]:px-2 max-[480px]:py-3">
                                                        <span
                                                            class="block text-[15px] font-semibold text-[var(--text-brand-80)] dark:text-white max-[480px]:text-[13px]">{{ __($option['label']) }}</span>
                                                        <span
                                                            class="mt-1 block text-[11px] uppercase tracking-wide text-[var(--text-slate-500)] dark:text-[var(--text-slate-400)]">{{ $option['sublabel'] }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="space-y-2.5">
                                                @foreach ($poll['options'] as $optionValue => $option)
                                                    @php
                                                        $count = (int) ($counts[$optionValue] ?? 0);
                                                        $percent =
                                                            $totalVotes > 0
                                                                ? (int) round(($count / $totalVotes) * 100)
                                                                : 0;
                                                        $isUserVote = $userVote === $optionValue;
                                                        $isWinner = $count > 0 && $count === $highestCount;
                                                    @endphp
                                                    <div class="flex items-center gap-3">
                                                        <span
                                                            class="min-w-[80px] text-[13px] font-semibold text-[var(--text-brand-80)] dark:text-white max-[480px]:min-w-[60px] max-[480px]:text-[11px]">{{ __($option['label']) }}</span>
                                                        <div
                                                            class="h-[26px] flex-1 overflow-hidden rounded-full bg-[var(--surface-slate-200)] dark:bg-[var(--surface-slate-700)]">
                                                            <div class="flex h-full min-w-10 items-center justify-end rounded-full px-2.5 text-right transition-[width] duration-500 {{ $isUserVote ? 'bg-gradient-to-r from-[var(--surface-brand-80-2)] to-[var(--surface-brand-70)]' : ($isWinner ? 'bg-gradient-to-r from-[var(--surface-success-50)] to-[var(--surface-success-35-2)]' : 'bg-gradient-to-r from-[var(--surface-success-25)] to-[var(--surface-success-15-2)]') }}"
                                                                style="width: {{ max(10, $percent) }}%">
                                                                <span
                                                                    class="text-xs font-bold text-white">{{ $percent }}%</span>
                                                            </div>
                                                        </div>
                                                        <span
                                                            class="min-w-[45px] text-right text-xs text-[var(--text-slate-500)] dark:text-[var(--text-slate-400)]">{{ $count }}</span>
                                                    </div>
                                                @endforeach
                                                <div
                                                    class="mt-4 border-t border-[var(--border-slate-200)] pt-4 text-center text-xs text-[var(--text-slate-500)] dark:border-[var(--border-slate-700)] dark:text-[var(--text-slate-400)]">
                                                    {{ __('Total votes:') }} {{ $totalVotes }}</div>
                                            </div>
                                        @endif
                                    </section>
                                @endforeach
                            </div>
                        </div>
                        <div class="px-7 pb-6 text-center text-[11px] text-[var(--text-slate-400)]">{{ __('Community opinion · Not an official prediction') }}</div>
                    </div>
                </div>
            @endif
        @endforeach
    @endforeach
    @if ($isPrematchDrops && ! ($append ?? false) && count($data) > 0)
        <footer data-prematch-legend><span><i class="text-[var(--text-green-600)]">●</i> {{ __('NEW – Drop just detected') }}</span><span><i class="text-[var(--text-amber-500)]">●</i> {{ __('ACTIVE – Drop is continuing') }}</span><span><i class="text-[var(--text-red-600)]">●</i> {{ __('WEAK – Drop is weakening or reversing') }}</span></footer>
    @endif
    @if ($isMarketShock && ! ($append ?? false) && count($data) > 0)
        <footer data-shock-footer><i class="fas fa-info-circle" aria-hidden="true"></i> {{ __('Alerts are automatically removed after 20 minutes.') }}</footer>
    @endif
    @unless ($append ?? false)
    </div>
@endunless

@push('js')
    @vite('resources/users/js/sports/football.js')
@endpush
