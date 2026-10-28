<div class="min-w-0 space-y-6" data-statistics-content>
    @foreach (['pregame-form' => 'Team Form', 'h2h' => 'Head-to-Head', 'streaks' => 'Team Streaks', 'overview' => 'Match Overview', 'season' => 'Team Season Statistics'] as $section => $title)
        <section aria-label="{{ __($title) }}" class="min-w-0">
            <h3 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-[#5a7d99] dark:text-slate-400">{{ __($title) }}</h3>
            @include('users.sports.football.partials.statisticsValues', ['section' => $section, 'event' => $event])
            @php
                $requestSections = $section === 'season' ? ['home-season', 'away-season'] : [$section];
                $requestFailed = array_any($requestSections, static fn (string $key): bool => isset($event['statistics_requests'][$key]) && ! $event['statistics_requests'][$key]['available']);
            @endphp
            @if ($requestFailed)
                <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">{{ __('The latest request was unsuccessful. Any previously received data is retained.') }}</p>
            @endif
        </section>
    @endforeach
</div>
