@php
    $append = $append ?? false;
    $checkAuthAndSubscription = checkAuthAndSubscription();
    $authenticated = auth('customers')->check();
@endphp
@if (! $append)
<div class="fixtures-container overflow-x-auto [scrollbar-width:thin]">
@endif
@forelse ($data as $group)
    <h2 data-league-header="{{ ($group['tournament_country'] ?? '').'|'.$group['tournament_name'].'|'.$group['status'] }}" class="mb-2 mt-5 flex items-center gap-2 border-b border-[var(--border-neutral-5-2)] pb-2 text-xs font-bold uppercase tracking-wide text-[var(--text-brand-60)] dark:border-[rgb(var(--border))] dark:text-[rgb(var(--text-soft))]">
        @if ($group['tournament_logo'] ?? null)<img src="{{ $group['tournament_logo'] }}" alt="" class="size-5 object-contain" onerror="this.remove()">@endif
        @if (filled($group['tournament_country'] ?? null)){{ $group['tournament_country'] }} : @endif{{ $group['tournament_name'] }}
    </h2>
    @foreach ($group['matches'] as $event)
        @php
            $choices = ['1' => $event['team1'], '2' => $event['team2']];
            if ($event['prediction_has_draw']) { $choices['X'] = 'Tie'; }
            $prediction = ['match_id' => (string) $event['sportsApiPro_match_id'], 'choices' => $choices, 'counts' => $event['prediction_counts'], 'vote' => $event['prediction_vote'], 'locked' => $event['status'] !== 'Prematch'];
            $highlight = (int) ($event['incident_highlight_until'] ?? 0) > time();
            $completedQuarters = $event['status'] === 'Finished' ? 4 : 0;
            if ($event['status'] === 'Live') {
                $completedQuarters = ['1st Quarter' => 0, '2nd Quarter' => 1, '3rd Quarter' => 2, '4th Quarter' => 3, 'Halftime' => 2, 'Overtime' => 4][$event['minutes_status'] ?? ''] ?? 0;
                if (($event['minutes_status'] ?? '') === 'Quarter Break') {
                    if (preg_match('/\b([1-4])(?:st|nd|rd|th)?\s*quarter\b|\bQ([1-4])\b/i', (string) ($event['match_status_description'] ?? ''), $quarterMatch)) {
                        $completedQuarters = (int) ($quarterMatch[1] ?: $quarterMatch[2]);
                    } elseif (is_numeric($event['match_time']['played'] ?? null) && ($event['match_time']['periodLength'] ?? 0) > 0) {
                        $completedQuarters = min(4, max(0, (int) floor($event['match_time']['played'] / $event['match_time']['periodLength'])));
                    }
                }
            }
            $quarterScores = [];
            for ($quarter = 1; $quarter <= $completedQuarters; $quarter++) {
                $homeQuarterScore = $event['home_score_data']['period'.$quarter] ?? null;
                $awayQuarterScore = $event['away_score_data']['period'.$quarter] ?? null;
                if (is_numeric($homeQuarterScore) && is_numeric($awayQuarterScore) && $homeQuarterScore >= 0 && $awayQuarterScore >= 0) {
                    $quarterScores[] = 'Q'.$quarter.': '.$homeQuarterScore.'-'.$awayQuarterScore;
                }
            }
        @endphp
        <div data-basketball-match-url="{{ route('basketball.matches.show', $event['sportsApiPro_match_id']) }}" data-match-id="{{ $event['sportsApiPro_match_id'] }}" data-match-status="{{ __($event['status']) }}" data-basketball-stats="{{ json_encode(['team1' => $event['team1'], 'team2' => $event['team2'], 'home' => $event['home_score_data'] ?? [], 'away' => $event['away_score_data'] ?? [], 'statistics' => $event['live_stats'] ?? [], 'periods' => $event['period_labels'] ?? [], 'tournament' => $group['tournament_name'], 'phase' => $event['minutes_status'] ?? null, 'minutes' => $event['minutes'] ?? null]) }}" data-basketball-prediction="{{ json_encode($prediction) }}" @if ($highlight) data-incident-highlight-until="{{ $event['incident_highlight_until'] }}" @endif
            @class(['event-row my-2 grid min-w-[1060px] grid-cols-[28px_100px_minmax(460px,1fr)_82px_310px] items-center gap-3 rounded-xl border border-[var(--border-neutral-5)] bg-[var(--surface-row)] px-3 py-2.5 text-[var(--text-brand-70)] hover:bg-[var(--surface-neutral-0-16)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))] dark:text-[var(--text-on-primary)]', '!bg-[var(--surface-goal)] dark:!bg-[var(--status-accent-surface)]' => $highlight])>
            <button type="button" data-action="favorite" data-requires-login="{{ $authenticated ? 'false' : 'true' }}" data-login-url="{{ route('login') }}" data-sport="basketball" data-event-key="{{ $event['unique_key'] }}" data-event-id="{{ $event['sportsApiPro_match_id'] }}" data-team1="{{ $event['team1'] }}" data-team2="{{ $event['team2'] }}" data-start-timestamp="{{ $event['timestamp'] }}" aria-pressed="{{ $event['is_favorite'] ? 'true' : 'false' }}" @class(['fav-star cursor-pointer border-0 bg-transparent text-lg text-[var(--text-brand-15-2)]', '!text-[var(--text-warning)]' => $event['is_favorite']])><i class="fas fa-star"></i></button>
            <span class="text-center text-xs font-semibold {{ $event['status'] === 'Live' ? 'text-[var(--text-danger-15-2)]' : 'text-[rgb(var(--text-muted))]' }}">{{ __($event['minutes']) }}@if ($highlight)<strong class="block text-[var(--text-red-600)]" data-incident-indicator>{{ $event['last_incident']['text'] ?? 'Update' }}</strong>@endif</span>
            <button type="button" data-action="open-match" class="grid min-w-0 grid-cols-[minmax(0,1fr)_68px_minmax(0,1fr)] items-center gap-x-2 gap-y-0.5 text-sm">
                <span class="flex min-w-0 items-center justify-end gap-1.5 text-right font-bold">@if ($event['home_team_logo'] ?? null)<img src="{{ $event['home_team_logo'] }}" alt="" class="size-5 shrink-0 object-contain" onerror="this.remove()">@endif<span class="break-words">{{ $event['team1'] }}</span></span>
                <strong data-score-status="{{ __($event['status']) }}" class="match-score text-center text-xs">{{ $event['score'] }}</strong>
                <span class="flex min-w-0 items-center gap-1.5 text-left"><span class="break-words">{{ $event['team2'] }}</span>@if ($event['away_team_logo'] ?? null)<img src="{{ $event['away_team_logo'] }}" alt="" class="size-5 shrink-0 object-contain" onerror="this.remove()">@endif</span>
                @if ($quarterScores !== [])<span aria-label="{{ __('Completed quarter scores') }}" class="col-span-3 whitespace-nowrap text-center text-[11px] font-normal leading-3 text-[var(--text-strong)]">{{ implode(' | ', $quarterScores) }}</span>@endif
            </button>
            <div class="flex items-center justify-center gap-4">
                <button type="button" class="cursor-pointer text-[var(--text-brand-40-2)] disabled:cursor-not-allowed disabled:opacity-30"
                    @if ($checkAuthAndSubscription['display_odds'])
                        data-action="stats" title="{{ __('Live match statistics') }}"
                    @else
                        disabled title="{{ __('Statistics (requires subscription)') }}"
                    @endif
                    ><i class="fas fa-chart-bar"></i></button>
                <button type="button" class="cursor-pointer text-[var(--text-brand-40-2)] disabled:cursor-not-allowed disabled:opacity-30"
                    @if ($checkAuthAndSubscription['display_odds'])
                        data-action="prediction" data-requires-login="{{ $authenticated ? 'false' : 'true' }}" data-login-url="{{ route('login') }}" title="{{ __('Predictions') }}"
                    @else
                        disabled title="{{ __('Prediction (requires subscription)') }}"
                    @endif
                    @disabled($event['status'] !== 'Prematch')><i class="fas fa-brain"></i></button>
            </div>
            <div class="flex min-w-0 gap-2 overflow-x-auto [scrollbar-width:thin]">
                @foreach ($event['markets'] !== [] ? $event['markets'] : [[], []] as $runner)
                    <span @if ($checkAuthAndSubscription['display_odds'] && (float) ($runner['currentBackOdds'] ?? 0) > 0) tabindex="0" data-basketball-odds-tooltip="{{ ($runner['initialBackOdds'] ?? 0) > 1 ? number_format($runner['initialBackOdds'], 2) : '—' }} {{ ($runner['odds_direction'] ?? '') === 'up' ? '<<' : '>>' }} {{ number_format($runner['currentBackOdds'], 2) }}" aria-label="{{ $runner['runner'] ?? 'Odds' }}" @else title="{{ $runner['runner'] ?? 'Unavailable' }}" @endif class="inline-flex min-w-[96px] shrink-0 items-center justify-center gap-1 whitespace-nowrap rounded-xl border border-[var(--border-neutral-5-8)] bg-[rgb(var(--surface))] px-2 py-1 text-sm font-bold dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))]">
                        @if ($checkAuthAndSubscription['display_odds'])
                            {{ (float) ($runner['currentBackOdds'] ?? 0) > 0 ? number_format($runner['currentBackOdds'], 2) : '—' }}
                            @if (($runner['odds_direction'] ?? '') === 'up')<span class="text-[var(--text-emerald-600)]">↑</span>@elseif (($runner['odds_direction'] ?? '') === 'down')<span class="text-[var(--text-red-500)]">↓</span>@endif
                        @else
                            @include('lock_icon')
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    @endforeach
@empty
    @if (! $append)<p class="py-10 text-center text-sm text-[rgb(var(--text-muted))]" data-basketball-empty>{{ __('No data available.') }}</p>@endif
@endforelse
@if (! $append)
</div>
<div id="basketballModal" class="fixed inset-0 z-[10000] hidden items-center justify-center overflow-hidden bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="basketballModalTitle"><div class="max-h-[calc(100dvh-2rem)] w-full max-w-3xl overflow-x-hidden overflow-y-auto overscroll-contain rounded-2xl bg-[rgb(var(--surface))] p-5 text-[var(--text-brand-70)] shadow-xl dark:bg-[rgb(var(--surface))] dark:text-[var(--text-on-primary)] data-[statistics=true]:flex data-[statistics=true]:max-w-[900px] data-[statistics=true]:flex-col data-[statistics=true]:overflow-hidden data-[statistics=true]:rounded-xl data-[statistics=true]:p-0" data-basketball-modal-panel></div></div>
@endif
