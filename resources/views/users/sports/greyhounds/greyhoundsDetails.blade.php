@php $append = $append ?? false; $checkAuthAndSubscription = checkAuthAndSubscription(); @endphp
@if (! $append)<div class="fixtures-container overflow-x-auto [scrollbar-width:thin]">@endif
@forelse ($data as $group)
    <h2 data-league-header="{{ $group['country'].'|'.($group['tournament_name'] ?? '') }}" class="mb-2 mt-5 border-b border-[var(--border-neutral-5-2)] pb-2 text-xs font-bold uppercase tracking-wide text-[var(--text-brand-60)] dark:border-[rgb(var(--border))] dark:text-[rgb(var(--text-soft))]">{{ $group['country'] }}@if (filled($group['tournament_name'] ?? null)) : {{ $group['tournament_name'] }}@endif</h2>
    @foreach ($group['matches'] as $race)
        @php
            $displayOdds = $race['details_available'] && $checkAuthAndSubscription['display_odds'];
            $choices = array_column($race['win_runners'], 'runner', 'runnerId');
            $prediction = ['match_id' => $race['id'], 'poll' => 'winner', 'choices' => $choices, 'counts' => $race['prediction_counts'], 'vote' => $race['prediction_vote'], 'locked' => ! $race['details_available'] || $race['status'] === 'Finished' || $race['timestamp'] <= time()];
            $viewRunners = array_slice($race['win_runners'], 0, 3);
            $payload = ['title' => $race['team1'].' · '.$race['team2'], 'team1' => $race['team1'], 'team2' => $race['team2'], 'available' => $race['details_available'], 'status' => $race['status'], 'minutes' => $race['minutes'], 'runners' => $displayOdds ? array_map(static fn (array $runner): array => array_intersect_key($runner, array_flip(['marketId','handicap','runner','runnerId','trap','initialBackOdds','initialBackOddsTimestamp','opening_odds_source','backOddsHistory','odds_history_incomplete','currentBackOdds','backSize','layOdds','laySize'])), $race['win_runners']) : [], 'indicators' => $displayOdds ? $race['indicators'] : [], 'locked' => ! $displayOdds];
        @endphp
        <div data-details-unlock-at="{{ $race['timestamp'] - 300 }}" data-match-id="{{ $race['id'] }}" data-greyhounds-match-url="{{ route('greyhounds.matches.show', $race['id']) }}" data-greyhounds-market="{{ json_encode($payload) }}" data-greyhounds-prediction="{{ json_encode($prediction) }}" class="event-row my-2 grid min-w-[880px] grid-cols-[28px_90px_minmax(280px,1fr)_100px_320px] items-center gap-3 rounded-xl border border-[var(--border-neutral-5)] bg-[var(--surface-row)] px-3 py-3 text-[var(--text-brand-70)] hover:bg-[var(--surface-neutral-0-16)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))] dark:text-[var(--text-on-primary)]">
            <button type="button" data-action="favorite" data-requires-login="{{ auth('customers')->check() ? 'false' : 'true' }}" data-login-url="{{ route('login') }}" data-sport="greyhounds" data-event-key="{{ $race['unique_key'] }}" data-event-id="{{ $race['id'] }}" data-team1="{{ $race['team1'] }}" data-team2="{{ $race['team2'] }}" data-start-timestamp="{{ $race['timestamp'] }}" aria-pressed="{{ $race['is_favorite'] ? 'true' : 'false' }}" @class(['fav-star cursor-pointer text-lg text-[var(--text-brand-15-2)]', '!text-[var(--text-warning)]' => $race['is_favorite']])><i class="fas fa-star"></i></button>
            <span class="whitespace-nowrap text-center text-xs font-semibold text-[rgb(var(--text-muted))]">{{ $race['minutes'] }}</span>
            <button type="button" data-action="open-match" class="text-center text-sm font-bold">{{ $race['team1'] }} · {{ $race['team2'] }} @if ($race['details_available'])<span class="text-[var(--text-amber-500)]" title="{{ $race['favorite_runner']['runner'] ?? '' }}">⭐</span>@endif</button>
            <div class="flex justify-center gap-3">
                <button type="button" class="cursor-pointer text-[var(--text-brand-40-2)] disabled:cursor-not-allowed disabled:opacity-30"
                    @if ($checkAuthAndSubscription['display_odds'])
                        data-action="market" title="{{ __('Runners and liquidity') }}"
                    @else
                        disabled title="{{ __('Statistics (requires subscription)') }}"
                    @endif
                    ><i class="fas fa-chart-bar"></i></button>
                <button type="button" data-action="chart" title="{{ __('Chart') }}" class="text-[var(--text-brand-40-2)]"><i class="fas fa-chart-line"></i></button>
                <button type="button" class="cursor-pointer text-[var(--text-brand-40-2)] disabled:cursor-not-allowed disabled:opacity-30"
                    @if ($checkAuthAndSubscription['display_odds'])
                        data-action="prediction" data-requires-login="{{ auth('customers')->check() ? 'false' : 'true' }}" data-login-url="{{ route('login') }}" title="{{ __('Predictions') }}"
                    @else
                        disabled title="{{ __('Prediction (requires subscription)') }}"
                    @endif
                    @disabled($race['status'] !== 'Prematch' || $prediction['locked'])><i class="fas fa-brain"></i></button>
            </div>
            <div class="flex gap-2">
                @if (! $race['details_available'])<span class="w-full text-center text-xs text-[rgb(var(--text-muted))]">{{ __('Details and odds open 5 minutes before start') }}</span>@else
                @foreach (array_pad($viewRunners, 3, []) as $runner)
                    <span @if ($displayOdds && ($runner['currentBackOdds'] ?? 0) > 0) tabindex="0" data-greyhounds-odds-tooltip="{{ ($runner['initialBackOdds'] ?? 0) > 1 ? number_format($runner['initialBackOdds'], 2) : __('Opening pending') }} → {{ number_format($runner['currentBackOdds'], 2) }}" @endif title="{{ $runner['runner'] ?? __('Unavailable') }}" class="inline-flex min-w-[96px] items-center justify-center gap-1 whitespace-nowrap rounded-xl border border-[var(--border-neutral-5-8)] bg-[rgb(var(--surface))] px-2 py-1 text-sm font-bold dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))]">@if ($displayOdds){{ ($runner['currentBackOdds'] ?? 0) > 0 ? number_format($runner['currentBackOdds'], 2) : '—' }}@if (($runner['odds_direction'] ?? '') === 'up')<span class="text-[var(--text-emerald-600)]">↑</span>@elseif (($runner['odds_direction'] ?? '') === 'down')<span class="text-[var(--text-red-500)]">↓</span>@endif @else @include('lock_icon') @endif</span>
                @endforeach
                @endif
            </div>
        </div>
    @endforeach
@empty
    @if (! $append)<p data-greyhounds-empty class="py-10 text-center text-sm text-[rgb(var(--text-muted))]">{{ __('No data available.') }}</p>@endif
@endforelse
@if (! $append)
</div>
<div id="greyhoundsModal" class="fixed inset-0 z-[10000] hidden items-center justify-center overflow-hidden bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="greyhoundsModalTitle"><div class="max-h-[calc(100dvh-2rem)] w-full max-w-3xl overflow-x-hidden overflow-y-auto overscroll-contain rounded-2xl bg-[rgb(var(--surface))] p-5 text-[var(--text-brand-70)] shadow-xl dark:bg-[rgb(var(--surface))] dark:text-[var(--text-on-primary)]" data-greyhounds-modal-panel></div></div>
@endif
