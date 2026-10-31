@extends('users.layout.main')

@section('title', $match['team1'].' vs '.$match['team2'].' · BF Markets')

@section('content')
    @include('users.layout.sports')

    <div class="my-5">
        <a href="{{ route('basketball.home') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[rgb(var(--primary))] hover:text-[rgb(var(--text-main))] dark:text-[rgb(var(--primary))]">
            <i class="fas fa-arrow-left"></i> {{ __('Back to matches') }}
        </a>
    </div>

    <section class="overflow-hidden rounded-2xl border border-[var(--border-neutral-5-6)] bg-[rgb(var(--surface))] shadow-sm dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))]">
        <div class="bg-gradient-to-r from-[var(--surface-heading)] to-[var(--surface-primary)] px-4 py-6 text-white sm:px-8">
            <div class="mb-3 text-center text-xs font-bold uppercase tracking-[0.14em] text-[var(--text-brand-5)]">{{ $match['tournament'] }}</div>
            <div class="grid grid-cols-[minmax(0,1fr)_48px_minmax(0,1fr)] items-center gap-2 text-center sm:grid-cols-[minmax(0,1fr)_72px_minmax(0,1fr)] sm:gap-4">
                <div class="min-w-0">
                    <h1 class="flex items-center justify-center gap-2 text-base font-bold sm:text-2xl">
                        @if ($match['home_team_logo'] ?? null)<img src="{{ $match['home_team_logo'] }}" alt="" class="size-7 shrink-0 object-contain" onerror="this.remove()">@endif
                        <span class="min-w-0 break-words">{{ $match['team1'] }}</span>
                    </h1>
                    @if ($match['status'] !== 'Prematch')<p class="mt-3 text-sm font-bold sm:text-xl"><span data-score-status="{{ $match['status'] }}" class="match-score match-score-on-dark">{{ $match['home_score_display'] }}</span></p>@endif
                </div>
                <strong class="text-lg text-[var(--text-brand-5)] sm:text-2xl">{{ __('vs') }}</strong>
                <div class="min-w-0">
                    <h1 class="flex items-center justify-center gap-2 text-base font-bold sm:text-2xl">
                        <span class="min-w-0 break-words">{{ $match['team2'] }}</span>
                        @if ($match['away_team_logo'] ?? null)<img src="{{ $match['away_team_logo'] }}" alt="" class="size-7 shrink-0 object-contain" onerror="this.remove()">@endif
                    </h1>
                    @if ($match['status'] !== 'Prematch')<p class="mt-3 text-sm font-bold sm:text-xl"><span data-score-status="{{ $match['status'] }}" class="match-score match-score-on-dark">{{ $match['away_score_display'] }}</span></p>@endif
                </div>
            </div>
            <div class="mt-4 text-center text-xs font-bold uppercase tracking-wide text-[var(--text-brand-5)]">{{ $match['status'] }} · {{ $match['minutes'] }}</div>
            @if ($match['match_status_description'] ?? null)<p class="mt-3 text-center text-sm text-[var(--text-brand-5)]">{{ $match['match_status_description'] }}</p>@endif
        </div>

        <div class="p-4 sm:p-7">
            @if ($match['status'] !== 'Prematch')
                @php
                    $periods = ['period1' => 'Q1', 'period2' => 'Q2', 'period3' => 'Q3', 'period4' => 'Q4'];
                    if (str_contains(strtolower($match['period_labels']['period1'] ?? ''), 'half')) { $periods = ['period1' => 'H1', 'period2' => 'H2']; }
                    foreach (['home_score_data', 'away_score_data'] as $side) {
                        foreach (array_keys($match[$side] ?? []) as $key) {
                            if (preg_match('/^overtime(\d*)$/', $key, $number)) { $periods[$key] = 'OT'.$number[1]; }
                        }
                    }
                @endphp
                <div class="mb-6 overflow-x-auto rounded-xl border border-[var(--border-neutral-5-6)] dark:border-[rgb(var(--border))]">
                    <table class="w-full min-w-[500px] text-center text-sm">
                        <caption class="px-4 py-3 text-left font-bold">{{ __('Period Scores') }}</caption>
                        <thead class="bg-[var(--surface-neutral-0-14)] dark:bg-[rgb(var(--bg))]"><tr><th class="px-3 py-2 text-left">{{ __('Team') }}</th>@foreach ($periods as $label)<th class="px-3 py-2">{{ __($label) }}</th>@endforeach<th class="px-3 py-2">{{ __('Total') }}</th></tr></thead>
                        <tbody>
                            @foreach (['home' => $match['team1'], 'away' => $match['team2']] as $side => $team)
                                @php $score = $match[$side.'_score_data'] ?? []; @endphp
                                <tr class="border-t border-[var(--border-neutral-5-6)] dark:border-[rgb(var(--border))]"><th class="whitespace-nowrap px-3 py-2 text-left">{{ $team }}</th>@foreach ($periods as $key => $label)@php $points = $score[$key] ?? null; @endphp<td class="px-3 py-2">{{ $points !== null && $points >= 0 ? $points : '—' }}</td>@endforeach<td data-score-status="{{ $match['status'] }}" class="match-score px-3 py-2 font-bold">{{ $score['current'] ?? $score['display'] ?? '—' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="mb-5 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 class="text-xl font-extrabold text-[rgb(var(--text-main))] dark:text-white">{{ __('Basketball Markets') }}</h2>
                    <p class="mt-1 text-sm text-[var(--text-slate-500)] dark:text-[var(--text-slate-400)]">{{ __('Back odds only · Initial price compared with current price') }}</p>
                </div>
                <span class="rounded-full bg-[var(--surface-hover)] px-3 py-1 text-xs font-bold text-[rgb(var(--primary))] dark:bg-[rgb(var(--surface-elevated))] dark:text-[rgb(var(--primary))]">{{ count($match['markets']) }} {{ __('markets') }}</span>
            </div>

            @forelse ($match['markets'] as $market)
                <section class="mb-5 overflow-hidden rounded-2xl border border-[var(--border-neutral-5-6)] bg-[rgb(var(--surface))] last:mb-0 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border-neutral-5-6)] bg-[var(--surface-neutral-0-14)] px-4 py-3 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] sm:px-5">
                        <h3 class="text-sm font-extrabold uppercase tracking-wide text-[var(--text-brand-60)] dark:text-[rgb(var(--text-soft))]">{{ $market['label'] }}</h3>
                        <span class="text-xs font-semibold text-[var(--text-slate-400)]">{{ count($market['runners']) }} {{ __('selections') }}</span>
                    </div>

                    <div class="hidden grid-cols-[minmax(150px,1.25fr)_90px_100px_115px_minmax(220px,1fr)] items-center gap-4 border-b border-[var(--border-neutral-5-7)] px-5 py-2 text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-slate-400)] dark:border-[rgb(var(--border))] md:grid">
                        <span>{{ __('Selection') }}</span>
                        <span>{{ __('Initial') }}</span>
                        <span>{{ __('Current') }}</span>
                        <span>{{ __('Movement') }}</span>
                        <span>{{ __('Back odds trend') }}</span>
                    </div>

                    <div class="divide-y divide-[var(--border-neutral-5-7)] dark:divide-[rgb(var(--border))]">
                        @foreach ($market['runners'] as $runner)
                            <article class="grid grid-cols-2 items-center gap-x-4 gap-y-3 px-4 py-4 md:grid-cols-[minmax(150px,1.25fr)_90px_100px_115px_minmax(220px,1fr)] md:px-5">
                                <div class="col-span-2 min-w-0 md:col-span-1">
                                    <span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-slate-400)] md:hidden">{{ __('Selection') }}</span>
                                    <div class="truncate text-sm font-extrabold text-[rgb(var(--text-main))] dark:text-white" title="{{ $runner['name'] }}">{{ $runner['name'] }}</div>
                                    @if ($checkAuthAndSubscription['display_odds'])
                                        @include('users.sports.oddsHistory', ['history' => $runner['history'] ?? [], 'incomplete' => $runner['history_incomplete'] ?? false])
                                    @endif
                                </div>

                                <div>
                                    <span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-slate-400)] md:hidden">{{ __('Initial') }}</span>
                                    <span class="font-mono text-base font-bold tabular-nums text-[var(--text-slate-500)] dark:text-[var(--text-slate-300)]">
                                        @if ($checkAuthAndSubscription['display_odds'])
                                            {{ $runner['initial_back_odds'] > 1 ? number_format($runner['initial_back_odds'], 2) : '—' }}
                                        @else
                                            @include('lock_icon')
                                        @endif
                                    </span>
                                </div>

                                <div class="text-right md:text-left">
                                    <span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-slate-400)] md:hidden">{{ __('Current') }}</span>
                                    <span class="inline-flex min-w-[72px] items-center justify-center gap-1 rounded-lg border border-[var(--border-brand-5-4)] bg-[var(--surface-neutral-0-15)] px-2.5 py-1.5 font-mono text-base font-extrabold tabular-nums text-[rgb(var(--text-main))] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-white">
                                        @if ($checkAuthAndSubscription['display_odds'])
                                            {{ number_format($runner['current_back_odds'], 2) }}
                                        @else
                                            @include('lock_icon')
                                        @endif
                                    </span>
                                </div>

                                <div class="col-span-2 md:col-span-1">
                                    <span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-[var(--text-slate-400)] md:hidden">{{ __('Movement') }}</span>
                                    @if ($checkAuthAndSubscription['display_odds'])
                                        @if ($runner['initial_back_odds'] > 1)
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-extrabold {{ $runner['direction'] < 0 ? 'bg-[var(--surface-red-50)] text-[var(--text-danger)] dark:bg-red-950/30' : ($runner['direction'] > 0 ? 'bg-[var(--surface-emerald-50)] text-[var(--text-success)] dark:bg-emerald-950/30' : 'bg-[var(--surface-slate-100)] text-[var(--text-slate-500)] dark:bg-[var(--surface-slate-700)]') }}">
                                            @if ($runner['direction'] < 0)
                                                <i class="fas fa-arrow-down"></i>
                                            @elseif ($runner['direction'] > 0)
                                                <i class="fas fa-arrow-up"></i>
                                            @else
                                                <i class="fas fa-minus"></i>
                                            @endif
                                            {{ $runner['change_percent'] > 0 ? '+' : '' }}{{ number_format($runner['change_percent'], 1) }}%
                                        </span>
                                        @else
                                            <span class="text-xs text-[var(--text-slate-400)]">{{ __('Waiting for opening odds') }}</span>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-extrabold">
                                            @include('lock_icon')
                                        </span>
                                    @endif
                                </div>

                                <div class="col-span-2 md:col-span-1">
                                    @if ($checkAuthAndSubscription['display_odds'])
                                        <img src="{{ env('BETFAIR_API_KEY') . '?marketId=' . $runner['match_id'] . '&selectionId=' . $runner['runner_id'] }}" alt="{{ __('Market Chart') }}">
                                    @else
                                        @include('lock_icon')
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="rounded-xl border border-dashed border-[var(--border-slate-300)] px-4 py-12 text-center text-sm text-[var(--text-slate-500)] dark:border-[var(--border-slate-600)] dark:text-[var(--text-slate-400)]">
                    {{ __('Market data is not available for this match yet.') }}
                </div>
            @endforelse
        </div>
    </section>
    @push('js')
        @vite('resources/users/js/sports/basketball.js')
        <script>
            (function() {
                let matchRefreshTimer = null;
                let matchRefreshInProgress = false;
                let matchRefreshQueued = false;

                async function refreshMatchDetail() {
                    if (matchRefreshInProgress || document.visibilityState !== 'visible') {
                        matchRefreshQueued = true;
                        return;
                    }

                    matchRefreshInProgress = true;

                    try {
                        const response = await fetch(window.location.href, {
                            headers: { 'Accept': 'text/html' },
                            cache: 'no-store',
                        });
                        if (!response.ok) throw new Error(`HTTP error: ${response.status}`);

                        const html = await response.text();
                        const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                        const nextMain = nextDocument.querySelector('main');
                        const currentMain = document.querySelector('main');

                        if (nextMain && currentMain) {
                            document.querySelector('[data-basketball-tooltip]')?.remove();
                            currentMain.innerHTML = nextMain.innerHTML;
                        }
                    } catch (error) {
                        console.error((globalThis.bfTranslations?.["Match detail refresh failed:"] ?? "Match detail refresh failed:"), error);
                    } finally {
                        matchRefreshInProgress = false;

                        if (matchRefreshQueued) {
                            matchRefreshQueued = false;
                            scheduleMatchDetailRefresh();
                        }
                    }
                }

                function scheduleMatchDetailRefresh() {
                    if (matchRefreshTimer !== null) return;

                    matchRefreshTimer = window.setTimeout(function() {
                        matchRefreshTimer = null;
                        refreshMatchDetail();
                    }, 500);
                }

                window.addEventListener('load', function() {
                    if (!window.Echo) return;

                    window.Echo.channel('sports.basketball.live')
                        .listen('.score.updated', scheduleMatchDetailRefresh);
                }, { once: true });

                document.addEventListener('visibilitychange', function() {
                    if (document.visibilityState === 'visible') {
                        scheduleMatchDetailRefresh();
                    }
                });
            })();
        </script>
    @endpush
@endsection
