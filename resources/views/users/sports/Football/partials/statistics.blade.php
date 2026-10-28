<div id="statistics-{{ $event['sportsApiPro_match_id'] }}" data-statistics-panel="{{ $event['sportsApiPro_match_id'] }}"
    role="dialog" aria-modal="true" aria-labelledby="statistics-title-{{ $event['sportsApiPro_match_id'] }}" tabindex="-1"
    class="fixed inset-0 z-[80] hidden overflow-hidden bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6">
    <div class="relative mx-auto my-4 w-full max-w-[850px] max-h-[calc(100dvh-3.5rem)] overflow-x-hidden overflow-y-auto overscroll-contain rounded-2xl sm:max-h-[calc(100dvh-7rem)] border border-[#dbe7f2] bg-slate-50 shadow-2xl dark:border-[#2a3f50] dark:bg-[#162532] sm:my-8">
        <header class="sticky top-0 z-10 bg-[#0b2a40] px-5 py-4 pr-14 text-white">
            <h2 id="statistics-title-{{ $event['sportsApiPro_match_id'] }}" class="text-sm font-bold">{{ __('Pre-match Statistics') }}</h2>
            <p class="mt-1 break-words text-xs text-[#b0c8dd]">{{ $event['team1'] }} <span class="px-1">vs</span> {{ $event['team2'] }}</p>
            <button type="button" data-close-statistics aria-label="{{ __('Close statistics') }}" class="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-white/15 hover:bg-white/25"><i class="fas fa-times" aria-hidden="true"></i></button>
        </header>
        <div class="p-5" data-statistics-body>
            @include('users.sports.football.partials.statisticsContent', ['event' => $event])
        </div>
    </div>
</div>
