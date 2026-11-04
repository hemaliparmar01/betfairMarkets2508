<div class="min-w-0 space-y-6" data-statistics-content>
    @foreach (['pregame-form' => 'Team Form', 'h2h' => 'Head-to-Head', 'streaks' => 'Team Streaks', 'overview' => 'Match Overview', 'season' => 'Team Season Statistics'] as $section => $title)
        <section aria-label="{{ __($title) }}" class="min-w-0">
            <h3 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-[rgb(var(--text-muted))] dark:text-[var(--text-slate-400)]">{{ __($title) }}</h3>
            @include('users.sports.football.partials.statisticsValues', ['section' => $section, 'event' => $event])
        </section>
    @endforeach
</div>
