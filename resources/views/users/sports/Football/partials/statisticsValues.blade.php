@php
    $prematch = $event['prematch_statistics'] ?? [];
    $cardClasses = 'min-w-0 rounded-xl border border-[#e3ecf5] bg-white p-4 shadow-sm dark:border-[#2a3f50] dark:bg-[#1b2d3b]';
    $teamNames = ['home' => $event['team1'], 'away' => $event['team2']];
@endphp
@switch ($section)
    @case('pregame-form')
        <div class="grid min-w-0 gap-3 sm:grid-cols-2">
            @foreach ($teamNames as $side => $teamName)
                @php
                    $form = $prematch['pregame-form'][$side.'Team'] ?? [];
                    $results = is_array($form['form'] ?? null) ? array_slice($form['form'], -5) : [];
                @endphp
                <article class="{{ $cardClasses }}">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 dark:border-slate-700">
                        <div class="min-w-0"><span class="text-[10px] font-medium uppercase tracking-wide text-slate-400">{{ __($side === 'home' ? 'Home' : 'Away') }}</span><h4 class="break-words text-sm font-bold text-[#0b2a40] dark:text-slate-100">{{ $teamName }}</h4></div>
                        <span class="inline-flex shrink-0 items-center gap-2 rounded-full bg-[#e8f2fc] px-3 py-1.5 text-[#1a6b9c] dark:bg-[#263e50] dark:text-sky-300" title="{{ __('Average rating supplied by the provider') }}"><span class="text-[9px] font-bold uppercase">{{ __('Rating') }}</span><b class="text-sm tabular-nums">{{ is_numeric($form['avgRating'] ?? null) ? number_format((float) $form['avgRating'], 2) : '—' }}</b></span>
                    </div>
                    <dl class="my-3 grid grid-cols-2 gap-3 text-xs"><div><dt class="text-slate-500 dark:text-slate-400">{{ __('Position') }}</dt><dd class="mt-1 text-lg font-bold tabular-nums text-[#0b2a40] dark:text-slate-100">{{ is_scalar($form['position'] ?? null) && (string) $form['position'] !== '' ? $form['position'] : '—' }}</dd></div><div><dt class="text-slate-500 dark:text-slate-400">{{ __('Points') }}</dt><dd class="mt-1 text-lg font-bold tabular-nums text-[#0b2a40] dark:text-slate-100">{{ is_scalar($form['value'] ?? null) && (string) $form['value'] !== '' ? $form['value'] : '—' }}</dd></div></dl>
                    <p class="mb-2 text-[10px] text-slate-500 dark:text-slate-400">{{ __('Recent form · last 5 matches') }}</p>
                    <div class="flex flex-wrap gap-1.5" aria-label="{{ $teamName.' '.__('recent form') }}">
                        @forelse ($results as $result)
                            @php
                                $result = is_string($result) ? strtoupper(trim($result)) : '';
                                $resultLabel = match ($result) { 'W' => __('Win'), 'D' => __('Draw'), 'L' => __('Loss'), default => __('Not available') };
                            @endphp
                            <span @class(['inline-flex size-7 items-center justify-center rounded-[2px] text-[11px] font-bold', 'bg-emerald-600 text-white' => $result === 'W', 'bg-amber-400 text-amber-950' => $result === 'D', 'bg-red-500 text-white' => $result === 'L', 'bg-slate-100 text-slate-400 dark:bg-slate-700' => ! in_array($result, ['W', 'D', 'L'], true)]) title="{{ $resultLabel }}" aria-label="{{ $resultLabel }}">{{ in_array($result, ['W', 'D', 'L'], true) ? $result : '—' }}</span>
                        @empty
                            <span class="text-xs text-slate-400">{{ __('No data available') }}</span>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </div>
        @break
    @case('h2h')
        @php $duel = $prematch['h2h']['teamDuel'] ?? []; @endphp
        <div class="{{ $cardClasses }}">
            <dl class="grid grid-cols-3 divide-x divide-slate-100 text-center dark:divide-slate-700">
                @foreach (['homeWins' => $event['team1'], 'draws' => __('Draws'), 'awayWins' => $event['team2']] as $key => $label)
                    <div class="min-w-0 px-2"><dt class="min-h-8 break-words text-[10px] font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</dt><dd class="mt-1 text-3xl font-extrabold tabular-nums {{ $key === 'draws' ? 'text-slate-500 dark:text-slate-300' : 'text-[#1a6b9c] dark:text-sky-300' }}">{{ is_numeric($duel[$key] ?? null) ? $duel[$key] : '—' }}</dd><p class="mt-1 text-[10px] text-slate-400">{{ __($key === 'draws' ? 'Matches' : 'Wins') }}</p></div>
                @endforeach
            </dl>
            @if ($duel === [])<p class="mt-4 text-center text-xs text-slate-400">{{ __('No head-to-head data available') }}</p>@endif
        </div>
        @break
    @case('streaks')
        @php
            $streakGroups = [];
            foreach (['general', 'head2head'] as $group) {
                $items = $prematch['streaks'][$group] ?? [];
                $items = is_array($items) ? array_filter($items, static fn (mixed $item): bool => is_array($item) && $item !== []) : [];
                if ($items !== []) {
                    $streakGroups[$group] = $items;
                }
            }
        @endphp
        @if ($streakGroups === [])
            <p class="{{ $cardClasses }} text-xs text-slate-400">{{ __('No data available') }}</p>
        @else
            <div class="grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($streakGroups as $group => $streaks)
                    @foreach ($streaks as $streak)
                        @if (is_array($streak))
                            @php
                                $name = is_string($streak['name'] ?? null) ? $streak['name'] : __('Streak');
                                $negative = preg_match('/^(no wins|no goals scored|without clean sheet|first to concede|losses|without wins)\b/i', $name) === 1;
                                $positive = ! $negative && preg_match('/^(wins|no losses|clean sheets|more than [\d.]+ goals|both teams scor)/i', $name) === 1;
                                $teamTag = match ($streak['team'] ?? null) { 'home' => $event['team1'], 'away' => $event['team2'], 'both' => __('Both teams'), default => __('Team not specified') };
                            @endphp
                            <article @class(['min-w-0 rounded-xl border bg-white p-3 shadow-sm dark:bg-[#1b2d3b]', 'border-red-200 dark:border-red-900/60' => $negative, 'border-emerald-200 dark:border-emerald-900/60' => $positive, 'border-[#e3ecf5] dark:border-[#2a3f50]' => ! $negative && ! $positive])>
                                <div class="flex items-start justify-between gap-2"><h4 class="break-words text-xs font-bold text-[#0b2a40] dark:text-slate-100">{{ $name }}</h4><span @class(['mt-1 size-2 shrink-0 rounded-full', 'bg-red-400' => $negative, 'bg-emerald-500' => $positive, 'bg-slate-300' => ! $negative && ! $positive]) aria-hidden="true"></span></div>
                                <p class="my-2 break-words text-xl font-extrabold tabular-nums text-[#0b2a40] dark:text-slate-100">{{ is_scalar($streak['value'] ?? null) && (string) $streak['value'] !== '' ? $streak['value'] : '—' }}</p>
                                <p class="break-words text-[10px] text-slate-500 dark:text-slate-400">{{ $group === 'head2head' ? __('H2H').' · ' : '' }}{{ $teamTag }}</p>
                            </article>
                        @endif
                    @endforeach
                @endforeach
            </div>
        @endif
        @break
    @case('overview')
        @php
            $overview = $prematch['overview'] ?? [];
            $venue = is_array($overview['venue'] ?? null) ? ($overview['venue']['name'] ?? null) : ($overview['venue'] ?? null);
            $referee = is_array($overview['referee'] ?? null) ? ($overview['referee']['name'] ?? null) : ($overview['referee'] ?? null);
            $round = $overview['roundInfo']['name'] ?? $overview['Info']['round'] ?? $overview['roundInfo']['round'] ?? null;
            $starts = $overview['startTimestamp'] ?? $event['timestamp'] ?? null;
            $timezone = getCurrentUserTimezone();
            $startTime = is_numeric($starts) && (int) $starts > 0 ? \Carbon\Carbon::createFromTimestampUTC((int) $starts)->timezone($timezone)->format('d M Y · H:i').' '.$timezone : null;
        @endphp
        <dl class="{{ $cardClasses }} grid gap-4 text-xs sm:grid-cols-2">
            @foreach ([__('Venue') => $venue, __('Referee') => $referee, __('Date & Time') => $startTime, __('Round') => $round] as $label => $value)
                <div class="min-w-0"><dt class="mb-1 text-[10px] uppercase tracking-wide text-slate-400">{{ $label }}</dt><dd class="break-words font-semibold text-[#0b2a40] dark:text-slate-100">{{ is_scalar($value) && (string) $value !== '' ? $value : '—' }}</dd></div>
            @endforeach
        </dl>
        @break
    @case('season')
        <div class="grid min-w-0 gap-3 sm:grid-cols-2">
            @foreach ($teamNames as $side => $teamName)
                @php $season = $prematch[$side.'-season'] ?? []; @endphp
                <article class="{{ $cardClasses }}">
                    <h4 class="break-words text-sm font-bold text-[#0b2a40] dark:text-slate-100">{{ $teamName }}</h4>
                    @if (is_scalar($prematch['overview']['season']['name'] ?? null))<p class="mt-1 text-[10px] text-slate-400">{{ $prematch['overview']['season']['name'] }}</p>@endif
                    <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                        @foreach (['avgRating' => __('Avg. rating'), 'matches' => __('Matches'), 'awardedMatches' => __('Awarded matches')] as $key => $label)
                            <div class="min-w-0"><dd class="text-xl font-extrabold tabular-nums text-[#1a6b9c] dark:text-sky-300">{{ is_numeric($season[$key] ?? null) ? ($key === 'avgRating' ? number_format((float) $season[$key], 2) : $season[$key]) : '—' }}</dd><dt class="mt-1 break-words text-[9px] leading-4 text-slate-500 dark:text-slate-400">{{ $label }}</dt></div>
                        @endforeach
                    </dl>
                    @if ($season === [])<p class="mt-3 text-xs text-slate-400">{{ __('No season data available') }}</p>@endif
                </article>
            @endforeach
        </div>
        @break
@endswitch
