@php $marketActivitySport = $marketActivitySport ?? 'football'; @endphp

@section('title', request()->routeIs('football.pinnacle-odds') ? 'Pinnacle Odds · BF Markets' : 'Market Activity Terminal · BF Markets')

@section('content')
@php $checkAuthAndSubscription = $checkAuthAndSubscription ?? checkAuthAndSubscription(); @endphp
@if (! $checkAuthAndSubscription['display_odds'])
    @include('users.layout.sports')
    <section class="my-8 rounded-2xl border border-[var(--border-neutral-5-5)] bg-[var(--surface-neutral-0-10)] p-6 text-center dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))]" aria-labelledby="premiumHeading">
        <i class="fas fa-lock mb-3 text-2xl text-[rgb(var(--primary))]" aria-hidden="true"></i>
        <h1 id="premiumHeading" class="text-xl font-bold text-[rgb(var(--text-main))] dark:text-[var(--text-on-primary)]">{{ __('Registration and subscription required') }}</h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">{{ __('Register and activate your 7-day free trial (first-time users), or purchase a paid subscription to access Market Activity, Pinnacle Drops, Horse Racing and Greyhounds.') }}</p>
        <button type="button" data-premium-required class="mt-5 cursor-pointer rounded-xl bg-[var(--surface-heading)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--surface-primary)] dark:bg-[var(--button-primary)]">{{ __('View access options') }}</button>
    </section>
@elseif (request()->routeIs('football.pinnacle-odds'))
    @php $pinnacleArchive = $pinnacleArchive ?? false; @endphp
    @push('head')
        <style>
            #pinnacleDashboard .drops-table { width:100%; table-layout:fixed; border-collapse:collapse; }
            #pinnacleDashboard .drops-table th, #pinnacleDashboard .drops-table td { padding:12px 10px; text-align:left; vertical-align:middle; overflow-wrap:anywhere; }
            #pinnacleDashboard .drops-table th { font-size:10px; color:rgb(var(--text-muted)); text-transform:uppercase; background:var(--surface-neutral-0-18); border-bottom:2px solid var(--border-neutral-5-2); }
            #pinnacleDashboard .drops-table td { border-bottom:1px solid var(--border-neutral-5-12); }
            #pinnacleDashboard .drops-table .drop-group td { padding:0; }
            #pinnacleDashboard .drop-odds { display:inline-flex; flex-wrap:wrap; align-items:center; gap:3px; font-variant-numeric:tabular-nums; }
            #pinnacleDashboard .drop-row { cursor:pointer; }
            #pinnacleDashboard .drop-row:focus-visible { outline:2px solid var(--border-primary); outline-offset:-2px; }
            #pinnacleDashboard .drop-row:hover { background:var(--surface-brand-20-2); }
            #pinnacleDashboard .drop-flash td { animation:pinnacleDropFlash 1.5s ease-out; }
            @keyframes pinnacleDropFlash { from { background-color:var(--surface-warning-0-4); } to { background-color:transparent; } }
            .dark-mode #pinnacleDashboard .drops-table th { background:rgb(var(--bg)); color:rgb(var(--text-soft)); border-color:rgb(var(--border)); }
            .dark-mode #pinnacleDashboard .drops-table td { border-color:rgb(var(--border)); }
            @media(max-width:640px) {
                #pinnacleDashboard .drops-table th, #pinnacleDashboard .drops-table td { padding:9px 4px; }
                #pinnacleDashboard .drops-table th { font-size:8px; }
                #pinnacleDashboard .drops-table td { font-size:10px; }
                #pinnacleDashboard .drop-odds { gap:2px; }
                #pinnacleDashboard .drops-simple col:nth-child(1) { width:14% !important; }
                #pinnacleDashboard .drops-simple col:nth-child(2) { width:42% !important; }
                #pinnacleDashboard .drops-simple col:nth-child(3) { width:27% !important; }
                #pinnacleDashboard .drops-simple col:nth-child(4) { width:17% !important; }
                #pinnacleDashboard .drops-advanced th:nth-child(5) { font-size:7px; white-space:nowrap; }
                #pinnacleDashboard .drops-advanced col:nth-child(1) { width:12% !important; }
                #pinnacleDashboard .drops-advanced col:nth-child(2) { width:29% !important; }
                #pinnacleDashboard .drops-advanced col:nth-child(3) { width:17% !important; }
                #pinnacleDashboard .drops-advanced col:nth-child(4), #pinnacleDashboard .drops-advanced col:nth-child(5) { width:10% !important; }
                #pinnacleDashboard .drops-advanced col:nth-child(6) { width:22% !important; }
                #pinnacleDashboard .drops-advanced col:nth-child(7), #pinnacleDashboard .drops-advanced thead th:nth-child(7), #pinnacleDashboard .drops-advanced .drop-row td:nth-child(7) { display:none; width:0 !important; }
                #pinnacleDashboard .drop-row td:first-child, #pinnacleDashboard .drops-advanced .drop-row td:nth-child(4), #pinnacleDashboard .drops-advanced .drop-row td:nth-child(5) { white-space:nowrap; }

            }
            #pinnacleDashboard .drop-start-soon { color:var(--text-amber-700); }
            #pinnacleDashboard .drop-betfair-value { color:var(--text-emerald-700); font-weight:800; }
            .dark-mode #pinnacleDashboard .drop-start-soon { color:var(--text-amber-400); }
            .dark-mode #pinnacleDashboard .drop-betfair-value { color:var(--text-emerald-400); }
            @keyframes pinnacleAlertPulse {
                0%, 100% { opacity:1; }
                50% { opacity:.65; }
            }
            @keyframes pinnacleArrowDown {
                0% { transform:translateY(-2px); opacity:.65; }
                65% { transform:translateY(2px); opacity:1; }
                100% { transform:translateY(2px); opacity:.65; }
            }
            @media (prefers-reduced-motion: no-preference) {
                #pinnacleDashboard .drop-animate .drop-start-soon,
                #pinnacleDashboard .drop-animate .drop-betfair-value { animation:pinnacleAlertPulse 2.8s ease-in-out infinite; }
                #pinnacleDashboard .drop-animate .drop-down-arrow { display:inline-block; animation:pinnacleArrowDown 1.8s ease-in-out infinite; }
            }

            @media(prefers-reduced-motion:reduce) { #pinnacleDashboard .drop-flash td { animation:none; } }
        </style>
    @endpush
    <section id="pinnacleDashboard" class="my-5 min-w-0" data-source-url="{{ route('football.pinnacle-odds') }}" data-timezone="{{ getCurrentUserTimezone() }}">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="flex items-center gap-2 text-2xl font-extrabold text-[rgb(var(--text-main))] dark:text-[var(--text-on-primary)] max-[480px]:text-xl">
                        <i class="fas fa-arrow-trend-down text-[var(--text-danger-15)]"></i> {{ __('Pinnacle Odds Drops & Market Movement') }}
                    </h1>
                    <button type="button" aria-haspopup="dialog" aria-controls="pinnacleDropsInfo" aria-label="{{ __('Why track Pinnacle Odds Drops?') }}" class="inline-flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-full border border-[var(--border-control)] text-[rgb(var(--text-muted))] focus-visible:outline-2" onclick="document.getElementById('pinnacleDropsInfo').showModal()"><i class="fas fa-info-circle" aria-hidden="true"></i></button>
                </div>
                <p class="mt-1 text-sm text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">{{ __('Realtime Pinnacle odds drops compared with Betfair prices') }}</p>
            </div>
            <span id="pinnacleConnection" class="inline-flex items-center gap-2 rounded-full border border-[var(--border-amber-200)] bg-[var(--surface-amber-50)] px-3 py-1.5 text-xs font-bold text-[var(--text-amber-700)]">
                <span class="size-2 rounded-full bg-current"></span><span data-status-text>{{ __('Connecting') }}</span>
            </span>
        </div>


        <dialog id="pinnacleDropsInfo" aria-labelledby="pinnacleDropsInfoTitle" class="m-auto max-h-[85dvh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl border border-[var(--border-control)] bg-[rgb(var(--surface))] p-5 text-[rgb(var(--text-main))] shadow-xl backdrop:bg-black/50 dark:border-[rgb(var(--border))] dark:text-[var(--text-on-primary)]">
            <div class="mb-4 flex items-start justify-between gap-3">
                <h2 id="pinnacleDropsInfoTitle" class="text-lg font-bold">{{ __('Why track Pinnacle Odds Drops?') }}</h2>
                <form method="dialog">
                    <button type="submit" aria-label="{{ __('Close') }}" class="cursor-pointer rounded px-2 text-xl focus-visible:outline-2">×</button>
                </form>
            </div>
            <div class="space-y-3 text-sm leading-relaxed">
                <p>{{ __('Pinnacle is globally recognized as the benchmark for "sharp" bookmaking. A sudden drop in Pinnacle odds indicates strong information impact on the market (Smart Money flow or key news).') }}</p>
                <h3 class="font-bold">{{ __('Key Use Cases & Strategies:') }}</h3>
                <div>
                    <h4 class="font-semibold">{{ __('Capturing Arbitrage & Value Windows:') }}</h4>
                    <p>{{ __('When Pinnacle drops its price on an outcome, soft bookmakers and exchanges often react with a delay. This creates a temporary price discrepancy between soft bookies\' higher odds and Pinnacle’s lower odds—allowing you to secure high-value bets or lock in arbitrage opportunities.') }}</p>
                </div>
                <div>
                    <h4 class="font-semibold">{{ __('Steam Chasing:') }}</h4>
                    <p>{{ __('This strategy aligns with "Smart Money" flow. A rapid odds drop (e.g., ≥5%) usually signals that professional bettors or sharp models perceive an increased probability for that outcome. Traders immediately follow the market move in the same direction before other sportsbooks adjust their lines.') }}</p>
                </div>
                <div>
                    <h4 class="font-semibold">{{ __('Betfair Trading:') }}</h4>
                    <p>{{ __('Pinnacle movements can influence market movements on Betfair exchange, giving you an edge to trade positions (Back/Lay) before kickoff.') }}</p>
                </div>
                <p class="rounded-lg border border-[var(--border-control)] p-3">{{ __('⚠️ Disclaimer: An odds drop does not guarantee a winning outcome. It simply highlights market sentiment and liquidity flow. Upsets and unexpected results happen regularly in sports.') }}</p>
            </div>
        </dialog>

        <nav class="mb-4 flex flex-wrap gap-2" aria-label="{{ __('Pinnacle odds history') }}">
            <a href="{{ route('football.pinnacle-odds') }}" @if (! $pinnacleArchive) aria-current="page" @endif class="cursor-pointer rounded-lg border border-[var(--border-control)] px-4 py-2 text-sm font-bold {{ ! $pinnacleArchive ? 'bg-[var(--surface-heading)] text-white' : 'text-[rgb(var(--text-muted))]' }}">{{ __('Real-Time Drops') }}</a>
            <span class="inline-flex items-center gap-2">
                <a href="{{ route('football.pinnacle-odds', ['archive' => 1]) }}" @if ($pinnacleArchive) aria-current="page" @endif class="cursor-pointer rounded-lg border border-[var(--border-control)] px-4 py-2 text-sm font-bold {{ $pinnacleArchive ? 'bg-[var(--surface-heading)] text-white' : 'text-[rgb(var(--text-muted))]' }}">{{ __('Daily Archive') }}</a>
                <button type="button" data-archive-info aria-label="{{ __('Archive information') }}" aria-expanded="false" class="inline-flex size-9 cursor-pointer items-center justify-center self-center rounded-full border border-[var(--border-control)] text-sm font-bold text-[rgb(var(--text-muted))] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--border-primary)]" onmouseenter="window.showOddsTooltip(event, 'archive')" onmouseleave="window.hideOddsTooltip()" onfocus="window.showOddsTooltip(event, 'archive')" onblur="window.hideOddsTooltip()" onkeydown="if (event.key === 'Escape') { window.hideOddsTooltip(); }" onclick="if (this.getAttribute('aria-expanded') === 'true') { window.hideOddsTooltip(); } else { window.showOddsTooltip(event, 'archive'); this.setAttribute('aria-expanded', 'true'); }">ⓘ</button>
            </span>
        </nav>
        @if ($pinnacleArchive)
            <p class="mb-4 text-sm text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">{{ $pinnacleArchiveStart->format('d M Y, H:i') }} – {{ $pinnacleArchiveEnd->format('d M Y, H:i') }} GET (UTC+4) · {{ __('Archive resets daily at 6:00 AM Georgia time (GET / UTC+4).') }}</p>
            <div class="mb-4 grid gap-3 sm:grid-cols-2" data-archive-summary>
                <div class="rounded-xl border border-[var(--border-control)] bg-[var(--surface-muted)] p-4 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))]">
                    <h2 class="text-sm font-bold text-[rgb(var(--text-muted))]">{{ __('Total daily alerts by sport') }}</h2>
                    <p class="mt-1 text-2xl font-extrabold text-[rgb(var(--text-main))]">{{ number_format($pinnacleArchiveStats['total'] ?? 0) }}</p>
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm text-[rgb(var(--text-muted))]">
                        @forelse ($pinnacleArchiveStats['sports'] ?? [] as $sport)
                            <span>{{ __(ucwords(str_replace('-', ' ', $sport->sport))) }}: <strong class="text-[rgb(var(--text-main))]">{{ number_format($sport->total) }}</strong></span>
                        @empty
                            <span>{{ __('No data available') }}</span>
                        @endforelse
                    </div>
                    <p class="mt-3 text-xs text-[rgb(var(--text-muted))]">{{ __('Includes all sports, markets, and live/prematch alerts in this archive day.') }}</p>
                </div>
                <div class="rounded-xl border border-[var(--border-control)] bg-[var(--surface-muted)] p-4 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))]">
                    <h2 class="text-sm font-bold text-[rgb(var(--text-muted))]">{{ __('Betfair Higher Odds Ratio') }}</h2>
                    <p class="mt-1 text-2xl font-extrabold text-[rgb(var(--text-main))]">{{ number_format($pinnacleArchiveStats['higher'] ?? 0) }} / {{ number_format($pinnacleArchiveStats['total'] ?? 0) }} <span class="text-base">({{ ($pinnacleArchiveStats['compared'] ?? 0) > 0 && ($pinnacleArchiveStats['total'] ?? 0) > 0 ? number_format($pinnacleArchiveStats['higher'] / $pinnacleArchiveStats['total'] * 100, 1).'%' : '—' }})</span></p>
                    <p class="mt-3 text-sm text-[rgb(var(--text-muted))]">{{ __('Alerts where recorded Betfair odds were higher than Pinnacle at the drop.') }}</p>
                    <p class="mt-2 text-xs text-[rgb(var(--text-muted))]">{{ __('Compared: :count', ['count' => number_format($pinnacleArchiveStats['compared'] ?? 0)]) }} · {{ __('Drop-time quote unavailable: :count', ['count' => number_format(($pinnacleArchiveStats['total'] ?? 0) - ($pinnacleArchiveStats['compared'] ?? 0))]) }}</p>
                </div>
            </div>
        @endif

        <div class="mb-4 flex gap-2 overflow-x-auto pb-2 [scrollbar-width:thin]" data-sport-filters>
            @foreach ([['all','🎯','All'],['soccer','⚽','Soccer'],['tennis','🎾','Tennis'],['basketball','🏀','Basketball'],['ice-hockey','🏒','Ice Hockey'],['baseball','⚾','Baseball'],['cricket','🏏','Cricket'],['esports','🎮','Esports']] as [$value, $icon, $label])
                <button type="button" data-sport="{{ $value }}" class="pinnacle-sport {{ ($pinnacleArchive ? request('sport', 'all') : 'all') === $value ? 'border-[var(--border-heading)] bg-[var(--surface-heading)] text-white' : 'border-[var(--border-control)] bg-[rgb(var(--surface))] text-[var(--text-secondary-strong)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))] dark:text-[rgb(var(--text-soft))]' }} inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-2 text-xs font-bold">
                    <span>{{ $icon }} {{ __($label) }}</span><span class="rounded-full bg-black/10 px-1.5 py-0.5 text-[10px]" data-count>0</span>
                </button>
            @endforeach
        </div>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--border-neutral-5-6)] bg-[var(--surface-muted)] p-3 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))]">
            <div class="flex rounded-lg border border-[var(--border-control)] bg-[rgb(var(--surface))] p-1 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]" data-mode-switch>
                @foreach (['all' => 'All', 'prematch' => 'Prematch', 'live' => 'Live'] as $value => $label)
                    <button type="button" data-mode="{{ $value }}" class="rounded-md px-3 py-1.5 text-xs font-bold {{ ($pinnacleArchive ? request('mode', 'all') : 'all') === $value ? 'bg-[var(--surface-heading)] text-white' : 'text-[rgb(var(--text-muted))]' }}">{{ __($label) }}</button>
                @endforeach
            </div>
            <div class="flex rounded-lg border border-[var(--border-control)] bg-[rgb(var(--surface))] p-1 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]" role="group" aria-label="{{ __('Market filters') }}">
                @foreach (['main' => __('Main Markets'), 'handicap' => __('Handicaps'), 'all' => __('All')] as $value => $label)
                    <button type="button" data-market-filter="{{ $value }}" aria-pressed="{{ ($pinnacleArchive ? request('market_filter', 'all') : 'all') === $value ? 'true' : 'false' }}" class="rounded-md px-3 py-1.5 text-xs font-bold {{ ($pinnacleArchive ? request('market_filter', 'all') : 'all') === $value ? 'bg-[var(--surface-primary)] text-white' : 'text-[rgb(var(--text-muted))]' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="flex rounded-lg border border-[var(--border-control)] bg-[rgb(var(--surface))] p-1 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]" data-view-switch>
                <button type="button" data-view="simple" class="rounded-md px-3 py-1.5 text-xs font-bold {{ ! $pinnacleArchive || request('view', 'simple') === 'simple' ? 'bg-[var(--surface-primary)] text-white' : 'text-[rgb(var(--text-muted))]' }}">{{ __('Simple') }}</button>
                <button type="button" data-view="advanced" class="rounded-md px-3 py-1.5 text-xs font-bold {{ $pinnacleArchive && request('view') === 'advanced' ? 'bg-[var(--surface-primary)] text-white' : 'text-[rgb(var(--text-muted))]' }}">{{ __('Advanced') }}</button>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('Time to Start') }}">
            @foreach (['all' => 'All', 'today' => 'Today', '1h' => 'Starting in 1 Hour', '3h' => 'Starting in 3 Hours', 'future' => 'Upcoming / Future Days'] as $value => $label)
                <button type="button" data-time-filter="{{ $value }}" aria-pressed="{{ ($pinnacleArchive ? request('time_filter', 'all') : 'all') === $value ? 'true' : 'false' }}" class="rounded-md px-3 py-1.5 text-xs font-bold {{ ($pinnacleArchive ? request('time_filter', 'all') : 'all') === $value ? 'bg-[var(--surface-primary)] text-white' : 'text-[rgb(var(--text-muted))]' }}">{{ __($label) }}</button>
            @endforeach
        </div>

        <div class="mb-4 flex flex-wrap items-start gap-3 text-sm">
        <div class="pinnacle-higher-filter">
            <label class="flex cursor-pointer items-center gap-2 font-semibold">
                <input type="checkbox" data-pinnacle-higher>
                <span><span class="text-[#FFB800]">Betfair</span> &gt; <span class="text-[#FF6600]">Pinnacle</span> {{ __('Drop') }}</span>
                @if ($pinnacleArchive) <span>{{ __('(at drop time)') }}</span> @endif
            </label>
            <details class="pinnacle-filter-info">
                <summary aria-label="{{ __('About the Betfair odds filter') }}" aria-describedby="pinnacle-filter-help">i</summary>
            </details>
            <span id="pinnacle-filter-help" role="tooltip" class="pinnacle-filter-help">{{ $pinnacleArchive ? __("Shows only alerts where Betfair's odds at the time of the drop were higher than Pinnacle's newly dropped odds for the exact same match and market.") : __("Shows only alerts where Betfair's current odds are higher than Pinnacle's newly dropped odds for the exact same match and market.") }}</span>
        </div>
        <details class="pinnacle-league-picker" data-pinnacle-league-picker>
            <summary class="cursor-pointer font-semibold">{{ __('Country & League') }}</summary>
            @if ($pinnacleLeagueOptions !== [])
                <label class="mt-2 flex items-center gap-2 font-semibold">
                    <input type="checkbox" data-pinnacle-select-all>
                    {{ __('Select all') }}
                </label>
            @endif
            <div class="mt-2 max-h-64 space-y-2 overflow-y-auto" data-pinnacle-leagues>
                @forelse ($pinnacleLeagueOptions as ['country' => $country, 'leagues' => $leagues])
                    <fieldset>
                        <label class="flex items-center gap-2 font-semibold">
                            <input type="checkbox" data-pinnacle-country="{{ $country }}">
                            {{ $country !== '' ? $country : __('Unknown country') }}
                        </label>
                        @foreach ($leagues as $key => $league)
                            <label class="ms-5 flex items-center gap-2">
                                <input type="checkbox" value="{{ $key }}" data-pinnacle-league>
                                {{ $league }}
                            </label>
                        @endforeach
                    </fieldset>
                @empty
                    <p data-no-pinnacle-leagues>{{ __('No leagues are available yet.') }}</p>
                @endforelse
            </div>
        </details>
        </div>

        <div class="overflow-hidden rounded-2xl border border-[var(--border-neutral-5-6)] bg-[rgb(var(--surface))] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]">
            <div class="flex flex-wrap items-center justify-between gap-3 bg-[var(--surface-heading)] px-4 py-3 text-white">
                <h2 class="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wide"><span class="size-2 rounded-full bg-[var(--surface-danger-0-3)]"></span>{{ $pinnacleArchive ? __('Daily Archive') : __('Real-Time Pinnacle Odds Drops') }}</h2>
                <span class="text-xs font-semibold text-white/70" data-alert-meta>{{ __('All') }} · ODDS-DROP</span>
            </div>
            <div class="min-w-0" data-alerts></div>
        </div>
        @if ($pinnacleArchive)
            <div class="mt-4 text-center">
                <button type="button" data-load-archive class="{{ ($pinnacleArchiveHasMore ?? false) ? '' : 'hidden' }} cursor-pointer rounded-lg bg-[var(--surface-heading)] px-5 py-2.5 text-sm font-bold text-white disabled:cursor-wait disabled:opacity-50">{{ __('Load more history') }}</button>
            </div>
        @endif
    </section>

    <div id="pinnacleModal" class="fixed inset-0 z-[10000] hidden items-center justify-center overflow-hidden bg-black/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="pinnacleModalTitle">
        <div class="max-h-[calc(100dvh-2rem)] w-full max-w-3xl overflow-x-hidden overflow-y-auto overscroll-contain rounded-2xl bg-[rgb(var(--surface))] p-5 shadow-2xl dark:bg-[rgb(var(--surface))]" data-modal-panel></div>
    </div>

    @push('js')
        <script>
            (() => {
                const root = document.getElementById('pinnacleDashboard');
                const modal = document.getElementById('pinnacleModal');
                if (!root || !modal) return;

                const state = { alerts: @json($pinnacleAlerts ?? []), archive: @json($pinnacleArchive), archiveDayEndsAt: @json($pinnacleArchiveEnd->timestamp * 1000), archiveHasMore: @json($pinnacleArchiveHasMore ?? false), archiveCursor: @json($pinnacleArchiveCursor ?? null), sport: @json($pinnacleArchive ? request('sport', 'all') : 'all'), mode: @json($pinnacleArchive ? request('mode', 'all') : 'all'), view: @json($pinnacleArchive ? request('view', 'simple') : 'simple'), marketFilter: @json($pinnacleArchive ? request('market_filter', 'all') : 'all'), timeFilter: @json($pinnacleArchive ? request('time_filter', 'all') : 'all'), connected: @json($pinnacleFeedConnected ?? false), feeds: @json($pinnacleFeeds ?? []), openAlertKey: null, collapsedGroups: new Set(), flashes: new Map() };
                const matchStartLabels = {{ Illuminate\Support\Js::from(['minute' => __('Starts in :count min'), 'minutes' => __('Starts in :count mins'), 'hour' => __('Starts in :count hour'), 'hours' => __('Starts in :count hours'), 'day' => __('Starts in :count day'), 'days' => __('Starts in :count days'), 'now' => __('Starting now')]) }};
                const sportKey = value => { const key = String(value || '').toLowerCase().trim().replace(/\s+/g, '-'); return key === 'hockey' ? 'ice-hockey' : key; };
                const matchDayFormat = new Intl.DateTimeFormat('en-CA', { timeZone: root.dataset.timezone || 'UTC', year: 'numeric', month: '2-digit', day: '2-digit' });
                const matchStartFormat = new Intl.DateTimeFormat(document.documentElement.lang === 'ka' ? 'ka-GE' : 'en-GB', { timeZone: root.dataset.timezone || 'UTC', year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
                const number = value => value === null || value === undefined || value === '' ? null : Number(value);
                const price = value => Number.isFinite(number(value)) ? number(value).toFixed(2) : '—';
                const nvpPrice = value => number(value) > 1 ? price(value) : '—';
                const marketLine = alert => alert.point ?? alert.points ?? alert.hdp;
                const marketLabel = alert => {
                    const section = String(alert.sect || 'Market');
                    const line = marketLine(alert);
                    const label = /^(spread|spreads|asian handicap)$/i.test(section) ? (globalThis.bfTranslations?.["Asian Handicap"] ?? "Asian Handicap") : /^(total|totals|o\/u)$/i.test(section) && sportKey(alert.sport) === 'soccer' ? 'Over/Under' : section;
                    return `${globalThis.bfTranslations?.[label] ?? label}${line !== null && line !== undefined && line !== '' ? ` ${line}` : ''}${label === 'Over/Under' ? (globalThis.bfTranslations?.[" Goals"] ?? " Goals") : ''}${Number(alert.period) > 0 ? ` · ${globalThis.bfTranslations?.Period ?? 'Period'} ${alert.period}` : ''}`;
                };
                const marketPosition = alert => {
                    const outcome = String(alert.outcome || '—');
                    const line = marketLine(alert);
                    if (line === null || line === undefined || line === '' || /[0-9]/.test(outcome)) return globalThis.bfTranslations?.[outcome] ?? outcome;
                    if (/^(over|under)$/i.test(outcome)) return `${globalThis.bfTranslations?.[outcome] ?? outcome} ${line}${sportKey(alert.sport) === 'soccer' ? (globalThis.bfTranslations?.[" Goals"] ?? " Goals") : ''}`;
                    if (/spread|handicap/i.test(alert.sect || '')) {
                        const away = /^(away|2)$/i.test(outcome) || outcome.toLowerCase() === String(alert.away ?? '').toLowerCase();
                        const handicap = Number(line) * (away && alert.point == null ? -1 : 1);
                        const display = Number.isFinite(handicap) ? `${handicap > 0 ? '+' : ''}${handicap}` : line;
                        return `${globalThis.bfTranslations?.[outcome] ?? outcome} (${display})`;
                    }
                    return globalThis.bfTranslations?.[outcome] ?? outcome;
                };
                const matchKey = alert => `${sportKey(alert.sport)}|${alert.parent_id ?? alert.id ?? ''}|${alert.home ?? ''}|${alert.away ?? ''}|${isLive(alert)}`;
                const escapeHtml = value => { const el = document.createElement('div'); el.textContent = String(value ?? ''); return el.innerHTML; };
                const drop = alert => { const from = number(alert.from_price); const to = number(alert.to_price); return from > 0 && to > 0 ? ((from - to) / from) * 100 : 0; };
                const fairPrice = alert => {
                    const provided = number(alert.nvp);
                    if (provided > 1 && Number.isFinite(provided)) return provided;
                    if (!alert.outcome) return null;
                    const selection = (alert.full_market || []).find(item => String(item.outcome || '').toLowerCase() === String(alert.outcome || '').toLowerCase());
                    const matched = number(selection?.nvp);
                    return matched > 1 && Number.isFinite(matched) ? matched : null;
                };
                const betfairPrice = alert => { const value = number(alert.bf_back); return Number.isFinite(value) && value > 1 ? value : null; };
                const pinnacleEdge = alert => { const current = number(alert.to_price); const nvp = fairPrice(alert); return Number.isFinite(current) && current > 1 && nvp > 1 ? ((current / nvp) - 1) * 100 : null; };
                const edge = alert => { const bf = betfairPrice(alert); const nvp = fairPrice(alert); return bf !== null && nvp > 1 ? ((bf / nvp) - 1) * 100 : null; };
                const edgeText = value => value === null || !Number.isFinite(value) ? '—' : `${value > 0 ? '+' : ''}${value.toFixed(1)}%`;
                const edgeClass = value => value === null || !Number.isFinite(value) ? 'text-[var(--text-slate-400)]' : value > 0 ? 'text-[var(--text-emerald-600)] dark:text-[var(--text-emerald-400)]' : value < 0 ? 'text-[var(--text-danger-edge)] dark:text-[var(--text-red-400)]' : 'text-[var(--text-slate-500)]';
                const betfairEdgeText = alert => betfairPrice(alert) === null ? 'N/A' : edge(alert) === null ? (globalThis.bfTranslations?.["— (fair price pending)"] ?? "— (fair price pending)") : edgeText(edge(alert));
                const alertIdentity = alert => alert.alert_key || [matchKey(alert), alert.sect, marketLine(alert), alert.period, alert.outcome, alert.alerted, alert.from_price, alert.to_price].join('|');
                const selectionKey = alert => {
                    const section = String(alert.sect ?? '').trim().toLowerCase().replaceAll('_', ' ');
                    const outcome = String(alert.outcome ?? '').trim().toLowerCase();
                    const side = ({ '1': 'home', '2': 'away', x: 'draw', 'the draw': 'draw' })[outcome] ?? outcome;
                    const market = /^(money line|moneyline|1x2|match odds)$/.test(section) ? 'moneyline' : section;
                    const line = marketLine(alert);
                    return JSON.stringify([market, Number(alert.period ?? 0), line == null || line === '' ? null : number(line), side]);
                };
                const monitorTimestamp = alert => {
                    const timestamp = number(alert.monitor_received_at ?? alert.alerted);
                    return timestamp && timestamp < 100000000000 ? timestamp * 1000 : timestamp;
                };
                const alertVersion = alert => `${alertIdentity(alert)}|${alert.alerted}|${alert.from_price}|${alert.to_price}`;
                const receiveAlerts = alerts => {
                    if (state.archive) { state.alerts = alerts; state.flashes.clear(); return; }
                    const now = Date.now();
                    const retained = new Map(state.alerts.filter(alert => now - monitorTimestamp(alert) < 420000)
                        .map(alert => [alertIdentity(alert), alert]));
                    for (const alert of alerts) {
                        const key = alertIdentity(alert);
                        const previous = retained.get(key);
                        if (!previous || alertVersion(previous) !== alertVersion(alert)) state.flashes.set(key, now + 1500);
                        retained.set(key, previous ? { ...alert, monitor_received_at: previous.monitor_received_at ?? alert.monitor_received_at } : alert);
                    }
                    for (const [key, expires] of state.flashes) if (expires <= now) state.flashes.delete(key);
                    state.alerts = [...retained.values()];
                };
                const isLive = alert => alert.is_live === true || alert.is_live === 1 || alert.is_live === '1' || String(alert.status || '').toLowerCase() === 'live';
                const alertTime = value => { const raw = number(value); if (!raw) return '—'; const time = raw < 100000000000 ? raw * 1000 : raw; const seconds = Math.max(0, Math.floor((Date.now() - time) / 1000)); return seconds < 60 ? `${seconds}${globalThis.bfTranslations?.['s ago'] ?? 's ago'}` : seconds < 3600 ? `${Math.floor(seconds / 60)}${globalThis.bfTranslations?.['m ago'] ?? 'm ago'}` : `${Math.floor(seconds / 3600)}${globalThis.bfTranslations?.['h ago'] ?? 'h ago'}`; };

                const marketPriority = alert => {
                    const section = String(alert.sect || '').trim().toLowerCase();
                    if (/^(money[ _]?line|1x2|match odds)$/.test(section) && Number(alert.period ?? 0) === 0) return 0;
                    if (/^(total|totals|o\/u)(?:\s|$)/.test(section) && Number(alert.period ?? 0) === 0) {
                        const line = marketLine(alert) ?? (String(alert.outcome ?? '').match(/\d+(?:\.\d+)?/) ?? section.match(/\d+(?:\.\d+)?/))?.[0];
                        if ([0.5, 1.5, 2.5, 3.5, 4.5].includes(Number(line))) return 1;
                    }
                    if (/^(btts|both teams to score)$/.test(section) && Number(alert.period ?? 0) === 0) return 2;
                    return /^(spreads?|asian handicap|handicap)(?:\s|$)/.test(section) ? 3 : 4;
                };
                const matchesMarketFilter = alert => state.marketFilter === 'all' || (state.marketFilter === 'handicap' ? marketPriority(alert) === 3 : marketPriority(alert) < 3);
                const leaguePicker = root.querySelector('[data-pinnacle-league-picker]');
                const higherOnly = root.querySelector('[data-pinnacle-higher]');
                const leagueList = leaguePicker.querySelector('[data-pinnacle-leagues]');
                const knownLeagues = new Set(Array.from(leagueList.querySelectorAll('[data-pinnacle-league]'), input => input.value));
                const countryInputs = new Map(Array.from(leagueList.querySelectorAll('[data-pinnacle-country]'), input => [input.dataset.pinnacleCountry, input]));
                const syncLeagueOptions = () => {
                    const selectAll = leaguePicker.querySelector('[data-pinnacle-select-all]');
                    for (const { country, league, league_filter_key: key } of state.alerts) {
                        if (!league || !key || knownLeagues.has(key)) continue;
                        let countryInput = countryInputs.get(country);
                        if (!countryInput) {
                            const fieldset = document.createElement('fieldset');
                            fieldset.innerHTML = '<label class="flex items-center gap-2 font-semibold"><input type="checkbox" data-pinnacle-country></label>';
                            countryInput = fieldset.querySelector('input');
                            countryInput.dataset.pinnacleCountry = country;
                            countryInput.checked = selectAll?.checked ?? false;
                            countryInput.parentElement.append(country || @json(__('Unknown country')));
                            leagueList.append(fieldset);
                            countryInputs.set(country, countryInput);
                        }
                        const label = document.createElement('label');
                        label.className = 'ms-5 flex items-center gap-2';
                        label.innerHTML = '<input type="checkbox" data-pinnacle-league>';
                        const input = label.querySelector('input');
                        input.value = key;
                        input.checked = countryInput.checked;
                        label.append(league);
                        countryInput.closest('fieldset').append(label);
                        knownLeagues.add(key);
                        leagueList.querySelector('[data-no-pinnacle-leagues]')?.remove();
                    }
                };
                const filtered = (now = Date.now()) => {
                    const selectAll = leaguePicker.querySelector('[data-pinnacle-select-all]');
                    const leagues = new Set(selectAll?.checked ? [] : Array.from(leaguePicker.querySelectorAll('[data-pinnacle-league]:checked'), input => input.value));
                    const today = state.timeFilter === 'today' || state.timeFilter === 'future' ? matchDayFormat.format(now) : null;
                    const matching = state.alerts.filter(alert => {
                        if (leagues.size && !leagues.has(alert.league_filter_key)) return false;
                        if (state.archive && higherOnly?.checked && !(betfairPrice(alert) > number(alert.to_price) && number(alert.to_price) > 1)) return false;
                        const received = monitorTimestamp(alert);
                        if (!state.archive && (!Number.isFinite(received) || received <= 0 || now - received >= 420000)) return false;
                        if (!matchesMarketFilter(alert) || (state.mode !== 'all' && (state.mode === 'live') !== isLive(alert))) return false;
                        if (!state.timeFilter || state.timeFilter === 'all') return true;
                        const start = number(alert.start_timestamp) * 1000;
                        if (!Number.isFinite(start) || start <= 0 || start > 8640000000000000) return false;
                        if (state.timeFilter === 'today') return matchDayFormat.format(start) === today;
                        if (isLive(alert) || start < now) return false;
                        if (state.timeFilter === 'future') return matchDayFormat.format(start) !== today;
                        return start - now <= (state.timeFilter === '1h' ? 3600000 : 10800000);
                    }).sort((a, b) => state.archive ? (number(b.alerted) || 0) - (number(a.alerted) || 0) : monitorTimestamp(b) - monitorTimestamp(a));
                    if (state.archive) return matching;
                    const seen = new Set();
                    return matching.filter(alert => {
                        const key = JSON.stringify([matchKey(alert), selectionKey(alert)]);
                        if (seen.has(key)) return false;
                        seen.add(key);
                        return !higherOnly?.checked || (number(alert.to_price) > 1 && betfairPrice(alert) > number(alert.to_price));
                    });
                };
                const modeLabels = { all: @json(__('All')), live: @json(__('Live')), prematch: @json(__('Prematch')) };
                const render = () => {
                    syncLeagueOptions();
                    const now = Date.now();
                    const groups = new Map();
                    const sportCounts = new Map();
                    const alerts = filtered(now);
                    for (const alert of alerts) {
                        const sport = sportKey(alert.sport);
                        sportCounts.set(sport, (sportCounts.get(sport) || 0) + 1);
                        if (state.sport !== 'all' && sport !== state.sport) continue;
                        const key = matchKey(alert);
                        if (!groups.has(key)) groups.set(key, []);
                        groups.get(key).push(alert);
                    }
                    root.querySelectorAll('[data-sport]').forEach(button => {
                        button.querySelector('[data-count]').textContent = button.dataset.sport === 'all' ? alerts.length : (sportCounts.get(button.dataset.sport) || 0);
                        button.querySelector('[data-count]').hidden = Boolean(state.archive);
                    });
                    root.querySelector('[data-alert-meta]').textContent = `${state.archive ? (globalThis.bfTranslations?.['Daily Archive'] ?? 'Daily Archive') + ' · ' : ''}${modeLabels[state.mode]} · ODDS-DROP`;

                    if (!groups.size) {
                        root.querySelector('[data-alerts]').innerHTML = `<div class="px-5 py-14 text-center text-sm font-semibold text-[var(--text-muted-soft)]"><div class="mb-2 text-4xl opacity-50">🔍</div>{{ __('No alerts match the current filters.') }}</div>`;
                        return;
                    }

                    const advanced = state.view === 'advanced';
                    const columns = advanced ? 7 : 4;
                    const info = (label, type) => `${label} <button type="button" aria-label="About ${label}" class="inline-flex size-3.5 items-center justify-center rounded-full bg-[var(--surface-neutral-5-8)] text-[10px] font-bold normal-case text-[var(--text-muted-soft)]" onmouseenter="showOddsTooltip(event, '${type}')" onmouseleave="hideOddsTooltip()" onfocus="showOddsTooltip(event, '${type}')" onblur="hideOddsTooltip()"${type === 'moneyline' ? ` onclick="event.stopPropagation(); showOddsTooltip(event, 'moneyline')"` : ''}>i</button>`;
                    const header = `<table class="drops-table ${advanced ? 'drops-advanced' : 'drops-simple'} text-xs text-[rgb(var(--text-main))] dark:text-[var(--text-on-primary)]"><caption class="sr-only">${(globalThis.bfTranslations?.["Pinnacle odds drops, grouped by match"] ?? "Pinnacle odds drops, grouped by match")}</caption><colgroup>${(advanced ? [8,32,17,8,10,18,7] : [10,46,25,19]).map(width => `<col style="width:${width}%">`).join('')}</colgroup><thead><tr><th scope="col">${(globalThis.bfTranslations?.["Drop"] ?? "Drop")}</th><th scope="col">${(globalThis.bfTranslations?.["Match / Market"] ?? "Match / Market")}</th><th scope="col">${(globalThis.bfTranslations?.["Pinnacle Odds"] ?? "Pinnacle Odds")}</th>${advanced ? `<th scope="col">${info('NVP', 'nvp')}</th>` : ''}<th scope="col">Betfair</th>${advanced ? `<th scope="col">${info((globalThis.bfTranslations?.["Edge %"] ?? "Edge %"), 'edge')}</th><th scope="col">${(globalThis.bfTranslations?.["Time"] ?? "Time")}</th>` : ''}</tr></thead>`;
                    let animatedRows = 0;
                    const html = [...groups.values()].map((alerts, groupIndex) => {
                        const first = alerts[0];
                        const groupKey = matchKey(first);
                        const matchStart = number(first.start_timestamp) * 1000;
                        const secondsUntilStart = (matchStart - now) / 1000;
                        let countdown = '';
                        if (Number.isFinite(matchStart) && matchStart > 0 && matchStart <= 8640000000000000 && secondsUntilStart >= 0 && !isLive(first)) {
                            if (secondsUntilStart === 0) {
                                countdown = matchStartLabels.now;
                            } else {
                                const unit = secondsUntilStart < 3600 ? 'minute' : secondsUntilStart < 86400 ? 'hour' : 'day';
                                const count = unit === 'minute' ? Math.ceil(secondsUntilStart / 60) : Math.floor(secondsUntilStart / (unit === 'hour' ? 3600 : 86400));
                                countdown = matchStartLabels[unit + (count === 1 ? '' : 's')].replace(':count', count);
                            }
                        }
                        const collapsed = state.collapsedGroups.has(groupKey);
                        const animateGroup = !state.archive && !collapsed && animatedRows < 20;
                        const countdownHtml = !state.archive && secondsUntilStart <= 3600
                            ? escapeHtml(countdown).replace(/Starting|Starts|იწყება/u, '<span class="drop-start-soon">$&</span>')
                            : escapeHtml(countdown);
                        const rows = (state.archive ? alerts.sort((a, b) => marketPriority(a) - marketPriority(b)) : alerts).map(alert => {
                            const alertIndex = state.alerts.indexOf(alert);
                            const from = number(alert.from_price); const to = number(alert.to_price);
                            const betfairOdds = betfairPrice(alert);
                            const marketName = escapeHtml(marketLabel(alert));
                            const down = to < from;
                            const animateRow = !state.archive && !collapsed && animatedRows++ < 20;
                            const hasBetfairValue = !state.archive && to > 1 && betfairOdds !== null && betfairOdds > to;
                            const flash = (state.flashes.get(alertIdentity(alert)) || 0) > Date.now();
                            return `<tr tabindex="0" role="button" aria-label="View ${marketName}: ${escapeHtml(marketPosition(alert))}" data-alert-index="${alertIndex}" data-alert-key="${escapeHtml(alertIdentity(alert))}" class="drop-row ${animateRow ? 'drop-animate' : ''} ${flash ? 'drop-flash' : ''}">
                                <td class="font-extrabold text-[var(--text-danger-edge)] dark:text-[var(--text-red-400)]">−${Math.abs(drop(alert)).toFixed(1)}%</td>
                                <td><strong class="block font-bold text-[rgb(var(--text-main))] dark:text-[var(--text-on-primary)]">${marketPriority(alert) === 0 ? info(marketName, 'moneyline') : marketName}</strong><span class="mt-0.5 block text-[11px] text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">${escapeHtml(marketPosition(alert))}</span><small class="mt-0.5 block text-[9px] uppercase text-[var(--text-muted-soft)]">${isLive(alert) ? 'LIVE' : 'PREMATCH'} · ${escapeHtml(alert.sport || '')}${advanced ? `<span class="min-[641px]:hidden"> · ${alertTime(alert.alerted)}</span>` : ` · ${alertTime(alert.alerted)}`}</small></td>
                                <td class="font-bold"><span class="drop-odds"><span>${price(from)}</span><span aria-hidden="true">→</span><b>${price(to)}</b>${from !== to ? `<span class="${down ? 'drop-down-arrow text-[var(--text-danger-edge)]' : 'text-[var(--text-emerald-600)]'}" aria-label="Odds ${down ? 'decreased' : 'increased'}">${down ? '↓' : '↑'}</span>` : ''}</span>${alert.pinnacle_selection ? `<small class="block font-normal">${escapeHtml(alert.pinnacle_selection)}</small>` : ''}</td>
                                ${advanced ? `<td class="text-[var(--text-slate-700)] dark:text-[var(--text-slate-300)]">${nvpPrice(fairPrice(alert))}</td>` : ''}
                                <td class="font-semibold text-[var(--text-brand-50-3)] dark:text-[rgb(var(--text-soft))]"${betfairOdds === null ? ` tabindex="0" onmouseenter="showOddsTooltip(event, 'betfair')" onmouseleave="hideOddsTooltip()" onfocus="showOddsTooltip(event, 'betfair')" onblur="hideOddsTooltip()"` : ''}>${hasBetfairValue ? `<span class="drop-betfair-value">${price(betfairOdds)}</span>` : price(betfairOdds)}${alert.bf_selection ? `<small class="block font-normal">${escapeHtml(alert.bf_selection)}</small>` : ''}</td>
                                ${advanced ? `<td class="text-[10px] leading-relaxed"><span class="block ${edgeClass(pinnacleEdge(alert))}">${(globalThis.bfTranslations?.["Pinnacle:"] ?? "Pinnacle:")} ${edgeText(pinnacleEdge(alert))}</span><span class="block ${edgeClass(edge(alert))}">BF: ${betfairEdgeText(alert)}</span></td><td class="text-[9px] text-[var(--text-muted-soft)]">${alertTime(alert.alerted)}</td>` : ''}
                            </tr>`;
                        }).join('');
                        return `<tbody data-group-key="${escapeHtml(groupKey)}"><tr class="drop-group ${animateGroup ? 'drop-animate' : ''}"><td colspan="${columns}"><button type="button" data-group="${groupIndex}" data-group-key="${escapeHtml(groupKey)}" aria-expanded="${!collapsed}" aria-controls="pinnacle-group-${groupIndex}" class="flex w-full items-center justify-between gap-3 border-y border-[var(--border-neutral-5-6)] bg-[var(--surface-neutral-0-21)] px-4 py-3 text-left dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]"><span class="min-w-0"><strong class="block text-sm font-extrabold text-[rgb(var(--text-main))] dark:text-[var(--text-on-primary)]">${first.home && first.away ? `${escapeHtml(first.home)} v ${escapeHtml(first.away)}` : escapeHtml(globalThis.bfTranslations?.["No data available"] ?? "No data available")}</strong><small class="mt-1 block text-xs font-semibold text-[var(--text-emerald-700)] dark:text-[var(--text-emerald-400)]">${escapeHtml(first.league || '')}</small><small class="mt-1 block text-xs font-semibold text-[var(--text-amber-700)] dark:text-[var(--text-amber-400)]">${Number.isFinite(matchStart) && matchStart > 0 && matchStart <= 8640000000000000 ? escapeHtml(matchStartFormat.format(matchStart)) : '—'}${countdown ? ' · ' + countdownHtml : ''}</small>${first.bf_home && first.bf_away ? `<small class="block text-[10px]">Betfair · ${escapeHtml(first.bf_home)} v ${escapeHtml(first.bf_away)}</small>` : ''}</span><span class="shrink-0 text-[10px] font-semibold text-[var(--text-brand-50-3)] dark:text-[rgb(var(--text-soft))]">${alerts.length} alert${alerts.length === 1 ? '' : 's'} <span data-group-toggle class="ml-2 text-base font-bold" aria-hidden="true">${collapsed ? '↓' : '↑'}</span></span></button></td></tr></tbody><tbody id="pinnacle-group-${groupIndex}" data-group-body="${groupIndex}" class="${collapsed ? 'hidden' : ''}">${rows}</tbody>`;
                    }).join('');
                    const target = root.querySelector('[data-alerts]');
                    if (globalThis.updateFixtureRows) {
                        const template = document.createElement('template');
                        template.innerHTML = header + html + '</table>';
                        globalThis.updateFixtureRows(target, template.content);
                    } else target.innerHTML = header + html + '</table>';

                };

                const openModal = alert => {
                    state.openAlertKey = alert.alert_key;
                    const dropValue = drop(alert); const pinnacleEdgeValue = pinnacleEdge(alert); const edgeValue = edge(alert);
                    const recent = state.alerts.filter(item => matchKey(item) === matchKey(alert)).sort((a, b) => (number(b.alerted) || 0) - (number(a.alerted) || 0)).slice(0, 5);
                    modal.querySelector('[data-modal-panel]').innerHTML = `<div class="flex items-start justify-between gap-4"><div><h2 id="pinnacleModalTitle" class="text-xl font-extrabold text-[var(--text-brand-70)] dark:text-[var(--text-on-primary)]">${alert.home && alert.away ? `${escapeHtml(alert.home)} v ${escapeHtml(alert.away)}` : escapeHtml(globalThis.bfTranslations?.["No data available"] ?? "No data available")}</h2><p class="mt-1 text-xs font-bold uppercase text-[var(--text-brand-35)]">${isLive(alert) ? 'LIVE' : 'PREMATCH'} · ${escapeHtml(alert.sport)} · ${escapeHtml(alert.league)}</p></div><button type="button" data-close-modal class="flex size-9 shrink-0 items-center justify-center rounded-full border border-[var(--border-control)] text-[rgb(var(--text-muted))]">✕</button></div><div class="mt-5 grid gap-3 rounded-xl border border-[var(--border-neutral-5-6)] bg-[var(--surface-muted)] p-4 sm:grid-cols-2 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))]"><div class="text-3xl font-black text-[var(--text-danger-15)]">−${Math.abs(dropValue).toFixed(1)}%</div><div class="grid grid-cols-2 gap-3 text-xs"><span>Pinnacle<br><b>${price(alert.from_price)} → ${price(alert.to_price)}</b></span><span>NVP<br><b>${nvpPrice(fairPrice(alert))}</b></span><span>${(globalThis.bfTranslations?.["Pinnacle Edge"] ?? "Pinnacle Edge")}<br><b class="${edgeClass(pinnacleEdgeValue)}">${edgeText(pinnacleEdgeValue)}</b></span><span>${(globalThis.bfTranslations?.["Betfair Back / Lay"] ?? "Betfair Back / Lay")}<br><b class="text-[rgb(var(--primary))]">${price(betfairPrice(alert))} / ${price(alert.bf_lay)}</b></span><span>${(globalThis.bfTranslations?.["Betfair Sizes"] ?? "Betfair Sizes")}<br><b>${price(alert.bf_back_size)} / ${price(alert.bf_lay_size)}</b></span><span>${(globalThis.bfTranslations?.["Betfair Edge"] ?? "Betfair Edge")}<br><b class="${edgeClass(edgeValue)}">${betfairEdgeText(alert)}</b></span></div></div><h3 class="mb-2 mt-5 text-xs font-extrabold uppercase tracking-wide text-[var(--text-brand-35)]">${(globalThis.bfTranslations?.["Full Market"] ?? "Full Market")}</h3><div class="overflow-x-auto"><table class="w-full min-w-[520px] text-sm"><thead class="border-b border-[var(--border-neutral-5-6)] text-left text-[10px] uppercase text-[var(--text-brand-35)]"><tr><th class="p-2">Outcome</th><th class="p-2 text-right">Pinnacle</th><th class="p-2 text-right">NVP</th><th class="p-2 text-right">Betfair</th><th class="p-2 text-right">${(globalThis.bfTranslations?.["Edge"] ?? "Edge")}</th></tr></thead><tbody>${(alert.full_market?.length ? alert.full_market : [alert]).map(selection => `<tr class="border-b border-[var(--border-neutral-5)]"><td class="p-2 font-bold">${escapeHtml(selection.outcome || '—')}</td><td class="p-2 text-right font-bold text-[var(--text-danger-15)]">${price(selection.to_price)}</td><td class="p-2 text-right">${nvpPrice(selection.nvp)}</td><td class="p-2 text-right font-bold text-[rgb(var(--primary))]">${price(betfairPrice(selection))}</td><td class="p-2 text-right font-bold ${edgeClass(edge(selection))}">${betfairEdgeText(selection)}</td></tr>`).join('')}</tbody></table></div><h3 class="mb-2 mt-5 text-xs font-extrabold uppercase tracking-wide text-[var(--text-brand-35)]">${(globalThis.bfTranslations?.["Recent Alerts"] ?? "Recent Alerts")}</h3><div class="divide-y divide-[var(--border-neutral-5)]">${recent.map(item => `<div class="flex items-center justify-between gap-3 py-2 text-xs"><span class="text-[var(--text-brand-35)]">${alertTime(item.alerted)}</span><span class="flex-1">${escapeHtml(marketLabel(item))} · ${price(item.from_price)} → ${price(item.to_price)}</span><b class="${edgeClass(edge(item))}">${edgeText(edge(item))}</b></div>`).join('')}</div>`;
                    modal.classList.remove('hidden'); modal.classList.add('flex');
                    document.body.classList.add('!overflow-hidden');
                    document.documentElement.classList.add('!overflow-hidden');
                };

                root.addEventListener('click', event => {
                    if (event.target.closest('[data-load-archive]')) { refresh(true); return; }
                    const sportButton = event.target.closest('[data-sport]');
                    const switchButton = event.target.closest('[data-mode], [data-view]');
                    const groupButton = event.target.closest('[data-group]');
                    const alertButton = event.target.closest('[data-alert-index]');
                    const marketButton = event.target.closest('[data-market-filter], [data-time-filter]');
                    if (state.archive && (sportButton || marketButton || switchButton?.dataset.mode)) {
                        const url = new URL(root.dataset.sourceUrl, window.location.origin);
                        url.searchParams.set('archive', '1');
                        url.searchParams.set('sport', sportButton?.dataset.sport ?? state.sport);
                        url.searchParams.set('mode', switchButton?.dataset.mode ?? state.mode);
                        url.searchParams.set('view', state.view);
                        url.searchParams.set('market_filter', marketButton?.dataset.marketFilter ?? state.marketFilter);
                        url.searchParams.set('time_filter', marketButton?.dataset.timeFilter ?? state.timeFilter);
                        window.location.assign(url);
                        return;
                    }
                    if (sportButton) { state.sport = sportButton.dataset.sport; root.querySelectorAll('[data-sport]').forEach(button => { const active = button === sportButton; button.classList.toggle('border-[var(--border-heading)]', active); button.classList.toggle('bg-[var(--surface-heading)]', active); button.classList.toggle('text-white', active); button.classList.toggle('border-[var(--border-control)]', !active); button.classList.toggle('bg-[rgb(var(--surface))]', !active); button.classList.toggle('text-[var(--text-secondary-strong)]', !active); }); render(); }
                    if (marketButton) {
                        const timeFilter = marketButton.dataset.timeFilter;
                        state[timeFilter ? 'timeFilter' : 'marketFilter'] = timeFilter || marketButton.dataset.marketFilter;
                        root.querySelectorAll(timeFilter ? '[data-time-filter]' : '[data-market-filter]').forEach(button => {
                            const active = button === marketButton;
                            button.setAttribute('aria-pressed', String(active));
                            button.classList.toggle('bg-[var(--surface-primary)]', active);
                            button.classList.toggle('text-white', active);
                            button.classList.toggle('text-[rgb(var(--text-muted))]', !active);
                        });
                        render();
                    }
                    if (switchButton) { const key = switchButton.dataset.mode ? 'mode' : 'view'; state[key] = switchButton.dataset[key]; switchButton.parentElement.querySelectorAll('button').forEach(button => { button.classList.remove('bg-[var(--surface-heading)]','bg-[var(--surface-primary)]','text-white'); button.classList.add('text-[rgb(var(--text-muted))]'); }); switchButton.classList.remove('text-[rgb(var(--text-muted))]'); switchButton.classList.add(key === 'mode' ? 'bg-[var(--surface-heading)]' : 'bg-[var(--surface-primary)]','text-white'); render(); }
                    if (groupButton) {
                        const key = groupButton.dataset.groupKey;
                        if (state.collapsedGroups.has(key)) state.collapsedGroups.delete(key);
                        else state.collapsedGroups.add(key);
                        render();
                    }
                    if (alertButton) { openModal(state.alerts[Number(alertButton.dataset.alertIndex)]); scheduleRefresh(); }
                });
                root.addEventListener('keydown', event => { if (event.target.matches('[data-alert-index]') && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); event.target.click(); } });
                const closeModal = () => {
                    if (modal.classList.contains('hidden')) return;
                    state.openAlertKey = null;
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    if (!document.querySelector('dialog[open], #subscriptionModal, [aria-modal="true"]:not(.hidden), .panel-expanded')) {
                        document.body.classList.remove('!overflow-hidden');
                        document.documentElement.classList.remove('!overflow-hidden');
                    }
                };
                modal.addEventListener('click', event => { if (event.target === modal || event.target.closest('[data-close-modal]')) closeModal(); });
                document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(); });

                const status = root.querySelector('#pinnacleConnection');
                const updateStatus = () => {
                    if (state.archive) {
                        status.className = 'inline-flex items-center gap-2 rounded-full border border-[var(--border-control)] px-3 py-1.5 text-xs font-bold text-[rgb(var(--text-muted))]';
                        status.querySelector('[data-status-text]').textContent = globalThis.bfTranslations?.['Daily Archive'] ?? 'Daily Archive';
                        return;
                    }
                    const connections = state.mode === 'all' ? Object.values(state.feeds) : [state.feeds[state.mode]];
                    const connection = connections.includes('connected') ? 'connected' : connections.includes('connecting') ? 'connecting' : 'disconnected';
                    const colors = connection === 'connected' ? (globalThis.bfTranslations?.["border-[var(--border-emerald-200)] bg-[var(--surface-emerald-50)] text-[var(--text-emerald-700)]"] ?? "border-[var(--border-emerald-200)] bg-[var(--surface-emerald-50)] text-[var(--text-emerald-700)]") : connection === 'connecting' ? (globalThis.bfTranslations?.["border-[var(--border-amber-200)] bg-[var(--surface-amber-50)] text-[var(--text-amber-700)]"] ?? "border-[var(--border-amber-200)] bg-[var(--surface-amber-50)] text-[var(--text-amber-700)]") : (globalThis.bfTranslations?.["border-[var(--border-red-200)] bg-[var(--surface-red-50)] text-[var(--text-red-700)]"] ?? "border-[var(--border-red-200)] bg-[var(--surface-red-50)] text-[var(--text-red-700)]");
                    status.className = `inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold ${colors}`;
                    status.querySelector('[data-status-text]').textContent = connection === 'connected' ? (globalThis.bfTranslations?.Live ?? 'Live') : connection === 'connecting' ? (globalThis.bfTranslations?.Connecting ?? 'Connecting') : (globalThis.bfTranslations?.Disconnected ?? 'Disconnected');
                };
                let refreshing = false;
                let refreshAgain = false;
                let refreshTimer = null;
                const refresh = async (append = false) => {
                    if (refreshing) { refreshAgain = true; return; }
                    refreshing = true;
                    const loadButton = root.querySelector('[data-load-archive]');
                    if (state.archive && loadButton) loadButton.disabled = true;
                    try {
                        const url = new URL(root.dataset.sourceUrl, window.location.origin);
                        if (state.archive) {
                            for (const [key, value] of Object.entries({ archive: '1', sport: state.sport, mode: state.mode, view: state.view, market_filter: state.marketFilter, time_filter: state.timeFilter })) url.searchParams.set(key, value);
                            if (append && state.archiveCursor) {
                                url.searchParams.set('before_time', state.archiveCursor.time);
                                url.searchParams.set('before_key', state.archiveCursor.key);
                            }
                        }
                        const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        const payload = await response.json();
                        receiveAlerts(state.archive && append ? [...state.alerts, ...(payload.data ?? [])] : (payload.data ?? []));
                        if (state.archive) {
                            state.archiveHasMore = Boolean(payload.has_more);
                            state.archiveCursor = payload.next_cursor ?? null;
                            if (loadButton) loadButton.classList.toggle('hidden', !state.archiveHasMore);
                        }
                        state.feeds = payload.feeds ?? {};
                        updateStatus();
                        render();
                        if (state.openAlertKey) {
                            const alert = state.alerts.find(item => item.alert_key === state.openAlertKey);
                            if (alert) openModal(alert);
                        }
                    } catch (error) {
                        state.feeds = { live: 'disconnected', prematch: 'disconnected' };
                        updateStatus();
                        render();
                    } finally {
                        refreshing = false;
                        if (state.archive && loadButton) loadButton.disabled = false;
                        if (refreshAgain) { refreshAgain = false; scheduleRefresh(); }
                    }
                };
                const scheduleRefresh = () => {
                    if (state.archive) return;
                    if (refreshTimer !== null) return;
                    refreshTimer = window.setTimeout(() => { refreshTimer = null; refresh(); }, 250);
                };
                let realtimeAttempts = 0;
                const bindRealtime = () => {
                    if (!window.Echo) {
                        if (realtimeAttempts++ < 40) window.setTimeout(bindRealtime, 250);
                        return;
                    }
                    window.Echo.channel('sports.pinnacle.drops').listen('.drops.updated', scheduleRefresh);
                    window.Echo.channel('sports.football.live').listen('.score.updated', scheduleRefresh);
                };
                root.addEventListener('change', event => {
                    if (event.target === higherOnly || leaguePicker?.contains(event.target)) render();
                });
                root.addEventListener('click', event => { if (event.target.closest('[data-mode]')) updateStatus(); });
                updateStatus();
                render();
                if (!state.archive) {
                    bindRealtime();
                    document.addEventListener('visibilitychange', () => { if (!document.hidden) scheduleRefresh(); });
                }
                window.setInterval(() => {
                    if (document.hidden) return;
                    if (!state.archive) scheduleRefresh();
                    else if (Date.now() >= state.archiveDayEndsAt) window.location.reload();
                }, 30000);

                const tooltipTexts = {
                    archive: @json(__('Outdated odds drop data is automatically moved to the archive and stored for 24 hours.')),
                    betfair: @json(__('Betfair odds unavailable (Low liquidity or market not open yet)')),
                    moneyline: @json(__('Pinnacle: Moneyline = Betfair: Match Odds')),
                    nvp: (globalThis.bfTranslations?.["NVP (No-Vig Price) is the 'fair' or 'true' odds for an outcome. It is calculated by removing the bookmaker's margin (vigorish) from the Pinnacle odds. It represents the true probability of the event without the bookmaker's built-in profit."] ?? "NVP (No-Vig Price) is the 'fair' or 'true' odds for an outcome. It is calculated by removing the bookmaker's margin (vigorish) from the Pinnacle odds. It represents the true probability of the event without the bookmaker's built-in profit."),

                    edge: (globalThis.bfTranslations?.["Edge % represents the percentage difference between the current odds (Pinnacle or Betfair) and the fair NVP odds. A positive (+) Edge % means you are getting better odds than the fair price (Value Bet). A negative (-) Edge % means the odds are lower than the fair price."] ?? "Edge % represents the percentage difference between the current odds (Pinnacle or Betfair) and the fair NVP odds. A positive (+) Edge % means you are getting better odds than the fair price (Value Bet). A negative (-) Edge % means the odds are lower than the fair price.")
                };

                window.showOddsTooltip = function(event, type) {
                let tooltip = document.getElementById('odds-info-tooltip');

                if (!tooltip) {
                    tooltip = document.createElement('div');
                    tooltip.id = 'odds-info-tooltip';
                    tooltip.setAttribute('role', 'tooltip');
                    tooltip.className = 'fixed z-[99999] w-[280px] max-w-[calc(100vw-24px)] rounded-md bg-[var(--surface-brand-75-3)] p-3 text-left text-[11px] font-normal normal-case tracking-normal leading-relaxed text-white shadow-xl pointer-events-none';
                    document.body.appendChild(tooltip);
                }

                tooltip.textContent = state.archive && type === 'betfair' ? (globalThis.bfTranslations?.['Betfair odds at the time of the drop were unavailable.'] ?? 'Betfair odds at the time of the drop were unavailable.') : tooltipTexts[type];
                event.currentTarget.setAttribute('aria-describedby', 'odds-info-tooltip');

                // Reset previous positioning before measuring
                tooltip.style.display = 'block';
                tooltip.style.visibility = 'hidden';
                tooltip.style.left = '0px';
                tooltip.style.top = '0px';

                const anchor = type === 'betfair' ? event.currentTarget.firstElementChild : event.currentTarget;
                const rect = anchor.getBoundingClientRect();
                const width = tooltip.offsetWidth;
                const height = tooltip.offsetHeight;

                // Center on the anchor while keeping the tooltip inside the viewport.
                const left = Math.max(
                    12,
                    Math.min(
                        rect.left + rect.width / 2 - width / 2,
                        window.innerWidth - width - 12
                    )
                );

                // Prefer above the anchor when there is room.
                const gap = type === 'betfair' ? 4 : 8;
                const top = rect.top - height - gap >= 8 ? rect.top - height - gap : Math.min(rect.bottom + gap, window.innerHeight - height - 8);

                tooltip.style.left = `${left}px`;
                tooltip.style.top = `${top}px`;
                tooltip.style.visibility = 'visible';
                if (type === 'archive' && !window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches) {
                    tooltip.animate?.([{ opacity: 0 }, { opacity: 1 }], { duration: 120, easing: 'ease-out' });
                }
            };

            window.hideOddsTooltip = function() {
                const tooltip = document.getElementById('odds-info-tooltip');

                if (tooltip) {
                    tooltip.style.display = 'none';
                }
                root.querySelector('[data-archive-info]')?.setAttribute('aria-expanded', 'false');
            };
            })();
        </script>
    @endpush
@else
@php
    $marketRowsOnly ??= request()->expectsJson();
    $raceActivity = in_array($marketActivitySport, ['greyhounds', 'horseracing'], true);
    if ($marketActivitySport !== 'football') {
        $icons = ['cricket' => '🏏', 'icehockey' => '🏒', 'americanfootball' => '🏈', 'baseball' => '⚾', 'basketball' => '🏀', 'tennis' => '🎾', 'greyhounds' => '🐕', 'horseracing' => '🏇'];
        $marketActivites = array_map(static function (array $row) use ($marketActivitySport, $raceActivity, $icons, $checkAuthAndSubscription): array {
            $available = $row['details_available'] ?? true;
            $locked = ! $available || ! $checkAuthAndSubscription['display_odds'];
            $selections = $available ? array_values($raceActivity ? ($row['win_runners'] ?? []) : ($row['runners'] ?? [])) : [];
            $favorite = $raceActivity ? ($row['favorite_runner'] ?? []) : ($row['favorite'] ?? []);
            $favoriteIndex = array_find_key($selections, static fn (array $runner): bool => (string) ($runner['runnerId'] ?? '') === (string) ($favorite['runnerId'] ?? '')) ?? 0;
            $runners = array_map(static fn (array $runner): array => [
                'name' => $runner['runner'] ?? '—',
                'back' => $locked ? 0 : (float) ($runner['currentBackOdds'] ?? $runner['backOdds'] ?? 0),
                'lay' => $locked ? 0 : (float) ($runner['layOdds'] ?? 0),
                'backSize' => $locked ? 0 : (float) ($runner['backSize'] ?? 0),
                'laySize' => $locked ? 0 : (float) ($runner['laySize'] ?? 0),
            ], $selections);
            $indicator = $row['indicators'] ?? [];
            return [
                'id' => (string) $row['id'], 'sport' => $marketActivitySport, 'sportIcon' => $icons[$marketActivitySport],
                'comp' => $row['tournament'] ?? '', 'country' => $row['country'] ?? null,
                'match' => $row['team1'].($raceActivity ? ' · ' : ' vs ').$row['team2'],
                'status' => strtoupper($row['status']), 'time' => $row['status'] === 'Finished' ? '' : ($row['minutes'] ?? ''), 'score' => $row['score'] ?? '',
                'market' => $raceActivity ? 'win_market' : $row['marketType'], 'marketLabel' => $raceActivity ? 'Win Market' : $row['marketName'],
                'runners' => $runners ?: [['name' => '—', 'back' => 0, 'lay' => 0, 'backSize' => 0, 'laySize' => 0]], 'favIdx' => $favoriteIndex,
                'totalBackSize' => array_sum(array_column($runners, 'backSize')), 'totalLaySize' => array_sum(array_column($runners, 'laySize')),
                'backPercent' => $indicator['back_percent'] ?? null, 'layPercent' => $indicator['lay_percent'] ?? null,
                'deltaWindows' => $indicator['delta_windows'] ?? [], 'lastMarketUpdate' => $indicator['updated_at'] ?? null, 'freshUntil' => $indicator['fresh_until'] ?? null, 'stale' => $indicator['stale'] ?? true,
                'balance' => $indicator['balance'] ?? 'unknown', 'momentum' => $indicator['momentum'] ?? 'unknown',
                'delta' => isset($indicator['delta']) && ! $locked ? (($indicator['delta'] > 0 ? '+' : '').number_format((float) $indicator['delta'], 1).' pp') : '—', 'volatility' => $indicator['volatility'] ?? 'unknown',
                'locked' => $locked, 'available' => $available,
                'timestamp' => $row['timestamp'] ?? null,
                'unlockAt' => $available ? null : (int) $row['timestamp'] - 300,
            ];
        }, $marketActivites);
    }
    if (! $checkAuthAndSubscription['display_odds']) {
        $marketActivites = array_map(static function (array $event): array {
            $event['locked'] = true;
            $event['runners'] = array_map(static fn (array $runner): array => array_replace($runner, ['back' => 0, 'lay' => 0, 'backSize' => 0, 'laySize' => 0]), $event['runners']);
            $event['totalBackSize'] = 0;
            $event['totalLaySize'] = 0;
            return $event;
        }, $marketActivites);
    }
    $marketTags = [
        'match_odds' => [
            'label' => 'Match Odds',
            'cls' => 'mo',
        ],
        'ou_0_5' => [
            'label' => 'O/U 0.5',
            'cls' => 'ou',
        ],
        'ou_1_5' => [
            'label' => 'O/U 1.5',
            'cls' => 'ou',
        ],
        'ou_2_5' => [
            'label' => 'O/U 2.5',
            'cls' => 'ou',
        ],
        'ou_3_5' => [
            'label' => 'O/U 3.5',
            'cls' => 'ou',
        ],
        'ou_4_5' => [
            'label' => 'O/U 4.5',
            'cls' => 'ou',
        ],
        'btts' => [
            'label' => 'BTTS',
            'cls' => 'btts',
        ],
        'first_half' => [
            'label' => 'First Half',
            'cls' => 'fh',
        ],
        'fh_ou_0_5' => [
            'label' => 'FH O/U 0.5',
            'cls' => 'fh',
        ],
        'fh_ou_1_5' => [
            'label' => 'FH O/U 1.5',
            'cls' => 'fh',
        ],
        'correct_score' => [
            'label' => 'Correct Score',
            'cls' => 'cs',
        ],
        'win_market' => [
            'label' => 'Win Market',
            'cls' => 'win',
        ],
    ];
    $speedOptions = [
        ['value' => 140, 'label' => '⚡ Pro 140-177ms', 'pro' => true],
        ['value' => 1000, 'label' => '1s'],
        ['value' => 2000, 'label' => '2s'],
        ['value' => 3000, 'label' => '3s'],
        ['value' => 5000, 'label' => '5s'],
        ['value' => 8000, 'label' => '8s'],
        ['value' => 10000, 'label' => '10s'],
    ];
    $marketOptions = [
        ['value' => 'active', 'label' => 'Active Markets'],
        ['value' => 'match_odds', 'label' => 'Match Odds'],
        ['value' => 'ou_0_5', 'label' => 'Over/Under 0.5'],
        ['value' => 'ou_1_5', 'label' => 'Over/Under 1.5'],
        ['value' => 'ou_2_5', 'label' => 'Over/Under 2.5'],
        ['value' => 'ou_3_5', 'label' => 'Over/Under 3.5'],
        ['value' => 'ou_4_5', 'label' => 'Over/Under 4.5'],
        ['value' => 'btts', 'label' => 'BTTS (Yes/No)'],
        ['value' => 'first_half', 'label' => 'First Half'],
        ['value' => 'fh_ou_0_5', 'label' => 'First Half O/U 0.5'],
        ['value' => 'fh_ou_1_5', 'label' => 'First Half O/U 1.5'],
    ];
    if ($marketActivitySport !== 'football') {
        $marketOptions = [['value' => '', 'label' => 'Active Markets'], ...($getAllMarkets['options'] ?? [])];
        if ($raceActivity) {
            $marketOptions[] = ['value' => 'WIN', 'label' => 'Win Market'];
        }
        foreach ($marketActivites as $event) {
            $marketTags[$event['market']] = ['label' => $event['marketLabel'], 'cls' => 'mo'];
        }
        if ($marketActivitySport !== 'greyhounds' && (! $marketRowsOnly || request()->boolean('_market_rows'))) {
            $marketRowVersions = [];
            $locale = app()->getLocale();
            foreach ($marketActivites as $event) {
                $marketRowVersions[$event['id']] = hash('xxh3', serialize([$locale, $marketTags[$event['market']], $event]));
            }
            if ($marketRowsOnly && count($marketRowVersions) === count($marketActivites)) {
                $marketRowOrder = array_column($marketActivites, 'id');
                $known = request()->query('_market_versions', '');
                $known = is_string($known) && strlen($known) <= 4096 ? json_decode($known, true) : null;
                if (is_array($known) && count($known) <= 100) {
                    $marketActivites = array_values(array_filter($marketActivites, static fn (array $event): bool => ($known[$event['id']] ?? null) !== $marketRowVersions[$event['id']]));
                }
            }
        }
    }
@endphp

    @unless ($marketRowsOnly ?? false)
    <div class="page-header my-[18px] flex min-w-0 flex-wrap items-center justify-between gap-3 [display:flex] [align-items:center] [justify-content:space-between] [flex-wrap:wrap] [gap:12px] [margin:18px_0_14px] [margin:28px_0_8px] max-[480px]:items-start">
        <h1 class="page-title [font-size:24px] [font-weight:800] [color:rgb(var(--text-main))] [display:flex] [align-items:center] [gap:10px] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[font-size:22px] max-[768px]:[font-size:18px] max-[768px]:[&_i]:[font-size:18px] max-[480px]:[font-size:16px] [font-size:32px] [font-weight:700] [letter-spacing:-0.5px] [gap:12px] [&_i]:[font-size:28px] max-[768px]:[font-size:24px] max-[480px]:[font-size:20px] max-[480px]:[gap:8px] max-[480px]:[&_i]:[font-size:20px] max-[768px]:[font-size:22px] max-[768px]:[gap:10px] max-[768px]:[&_i]:[font-size:22px] max-[480px]:[font-size:19px] max-[1024px]:[font-size:28px] max-[480px]:[font-size:21px]"><i class="fas fa-bolt"></i> {{ __('Real-Time Sports Market Activity') }}</h1>
        <div class="header-indicators flex flex-wrap items-center gap-2 [gap:8px] max-[480px]:w-full">
            <button type="button"
                class="collapse-toggle inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold   [gap:6px] [padding:6px_14px] [border-radius:30px] [font-size:12.5px]  [background:var(--surface-control)] [border:1px_solid_var(--border-control)] [color:var(--text-secondary)] cursor-pointer [transition:0.2s] select-none [font-family:inherit] dark:[background:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] [&:hover]:[border-color:var(--border-primary)] [&:hover]:[color:rgb(var(--primary))] dark:[&:hover]:[border-color:rgb(var(--border))] dark:[&:hover]:[color:rgb(var(--primary))] [&_i]:[transition:transform_0.3s]"
                id="collapseToggle" title="{{ __('Collapse/expand filters') }}">
                <i class="fas fa-chevron-up"></i>
                <span>{{ __('Filters') }}</span>
            </button>
            <span class="live-indicator inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold   [gap:6px] [padding:4px_12px] [border-radius:30px] [background:var(--surface-neutral-5)] [border:1px_solid_var(--border-success-10)] [color:var(--text-success)] [font-size:12px]  dark:[background:var(--status-success-surface)] dark:[border-color:var(--status-success-border)] dark:[color:rgb(var(--success))]">
                <span class="live-dot [width:8px] [height:8px] [border-radius:50%] [background:var(--surface-success)] [animation:pulse_1.6s_infinite] dark:[background:var(--status-success-fill)]"></span>
                {{ __('Live') }}
            </span>
            <span class="feed-indicator inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold   [gap:6px] [padding:4px_12px] [border-radius:30px] [background:var(--surface-control)] [border:1px_solid_var(--border-control)] [font-size:11.5px]  [color:rgb(var(--text-muted))] [font-variant-numeric:tabular-nums] dark:[background:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] [&_.feed-dot]:[width:6px] [&_.feed-dot]:[height:6px] [&_.feed-dot]:[border-radius:50%] [&_.feed-dot]:[background:var(--surface-success)] [&_.feed-dot]:[animation:pulse_1s_infinite] dark:[&_.feed-dot]:[background:var(--status-success-fill)]">
                <span class="feed-dot"></span>
                {{ __('WS Feed') }} · <span class="feed-latency [color:rgb(var(--primary))] dark:[color:rgb(var(--primary))]" id="latencyDisplay">154</span> {{ __('ms') }}
            </span>
        </div>
    </div>

    <!-- COLLAPSIBLE CONTROLS WRAPPER -->
    <div class="controls-wrapper overflow-hidden  [transition:max-height_0.35s_ease,_opacity_0.25s_ease] [max-height:800px] opacity-100" id="controlsWrapper">

        <!-- SPEED SELECTOR -->
        <div class="speed-bar flex flex-wrap items-center gap-2 border-b py-3    [gap:8px] [padding:10px_0_12px] [border-bottom:1px_solid_var(--border-subtle)] [margin-bottom:12px] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))] max-[768px]:[gap:6px] max-[768px]:flex-nowrap max-[768px]:overflow-x-auto max-[768px]:pb-2 max-[768px]:[&_.speed-label]:shrink-0 max-[768px]:[&_.speed-pill]:shrink-0" id="speedBar">
            <span class="speed-label inline-flex items-center [gap:6px] [font-size:12px] font-bold [color:rgb(var(--text-muted))] uppercase [letter-spacing:0.4px] [padding-right:6px] dark:[color:rgb(var(--text-soft))] [&_i]:[color:rgb(var(--primary))] [&_i]:[font-size:13px] dark:[&_i]:[color:rgb(var(--primary))]"><i class="fas fa-tachometer-alt"></i> {{ __('Speed:') }}</span>
            @foreach ($speedOptions as $speed)
                <button type="button" class="speed-pill {{ ($speed['pro'] ?? false) ? 'pro' : '' }} {{ $speed['value'] === 3000 ? 'active' : '' }} inline-flex items-center [gap:5px] [padding:6px_13px] [border-radius:20px] [font-size:12px] font-bold [background:var(--surface-control)] [border:1px_solid_var(--border-control)] [color:rgb(var(--text-muted))] cursor-pointer [transition:0.2s] select-none [font-variant-numeric:tabular-nums] [font-family:inherit] dark:[background:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] [&:hover]:[border-color:var(--border-primary)] [&:hover]:[color:rgb(var(--primary))] [&:hover]:[transform:translateY(-1px)] dark:[&:hover]:[border-color:rgb(var(--border))] dark:[&:hover]:[color:rgb(var(--primary))] [&.active]:[background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] [&.active]:[border-color:var(--border-primary)] [&.active]:[color:var(--text-on-primary)] [&.active]:[box-shadow:0_4px_12px_var(--shadow-brand-40-5)] dark:[&.active]:[background:linear-gradient(135deg,_var(--button-primary),_var(--button-primary))] dark:[&.active]:[border-color:rgb(var(--border))] [&.pro]:[border-color:var(--border-warning)] [&.pro]:[color:var(--text-warning-30)] [&.pro]:[background:linear-gradient(135deg,_var(--surface-neutral-0-5),_var(--surface-warning-0))] dark:[&.pro]:[background:linear-gradient(135deg,_var(--status-warning-surface),_var(--status-warning-surface))] dark:[&.pro]:[border-color:var(--status-warning-border)] dark:[&.pro]:[color:rgb(var(--warning))] [&.pro.active]:[background:linear-gradient(135deg,_var(--surface-warning-30),_var(--surface-warning))] [&.pro.active]:[border-color:var(--border-warning)] [&.pro.active]:[color:var(--text-on-primary)] [&.pro.active]:[box-shadow:0_4px_12px_var(--shadow-warning-10-2)] max-[768px]:[padding:5px_10px] max-[768px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[font-size:10px]" data-speed="{{ $speed['value'] }}">{{ $speed['label'] }}</button>
            @endforeach
        </div>

        @include('users.layout.sports')

        @include('users.sports.'.$marketActivitySport.'.'.$marketActivitySport.'_filter')

        <!-- MARKET CONTAINER (football only) -->
        <div class="market-container flex flex-wrap items-center gap-2.5 border-b py-3   [gap:10px] [padding:10px_0_14px] [border-bottom:1px_solid_var(--border-subtle)] [margin-bottom:14px] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))] [&[hidden]]:[display:none] max-[768px]:[gap:6px] max-[480px]:items-stretch max-[480px]:[&_.market-container-label]:w-full max-[480px]:[&_.market-select]:w-full max-[480px]:[&_.market-select]:min-w-0" id="marketContainer">
            <span class="market-container-label inline-flex items-center [gap:6px] [font-size:12px] font-bold [color:rgb(var(--text-muted))] uppercase [letter-spacing:0.4px] dark:[color:rgb(var(--text-soft))] [&_i]:[color:rgb(var(--primary))] [&_i]:[font-size:13px] dark:[&_i]:[color:rgb(var(--primary))]"><i class="fas fa-filter"></i> {{ __('Market:') }}</span>
            <select class="market-select [appearance:none] [-webkit-appearance:none] [background-size:14px] [border:1px_solid_var(--border-control)] [border-radius:30px] [padding:8px_38px_8px_16px] [font-size:13px] [font-weight:700] [color:var(--text-secondary)] [cursor:pointer] [transition:0.2s] [min-width:200px] [&:hover]:[border-color:var(--border-primary)] [&:focus]:[outline:none] [&:focus]:[border-color:var(--border-primary)] [&:focus]:[box-shadow:0_0_0_3px_var(--shadow-brand-40)] dark:[background-color:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] max-[768px]:[font-size:12px] max-[768px]:[min-width:170px] max-[768px]:[padding:7px_34px_7px_14px]" id="marketSelect">
                @foreach ($marketOptions as $marketOption)
                    <option value="{{ $marketOption['value'] }}">{{ $marketOption['label'] }}</option>
                @endforeach
            </select>
            <div id="timeToStart" class="flex min-w-0 max-[768px]:basis-full flex-1 items-center gap-2 overflow-x-auto whitespace-nowrap py-1" role="group" aria-label="{{ __('Time to Start') }}">
                <span class="shrink-0 text-xs font-bold text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]"><i class="fas fa-clock"></i> {{ __('Time to Start:') }}</span>
                @foreach ([600 => 'Starts in 10m', 1200 => 'Starts in 20m', 1800 => 'Starts in 30m', 3600 => 'Starts in 1h', 7200 => 'Starts in 2h', 10800 => 'Starts in 3h', 21600 => 'Starts in 6h', 0 => 'All'] as $seconds => $label)
                    <button type="button" data-start-seconds="{{ $seconds }}" aria-pressed="{{ $seconds === 0 ? 'true' : 'false' }}" class="{{ $seconds === 0 ? 'active' : '' }} shrink-0 cursor-pointer rounded-full border border-[var(--border-control)] bg-[var(--surface-control)] px-3 py-1.5 text-xs font-semibold text-[var(--text-secondary-strong)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))] dark:text-[rgb(var(--text-soft))] [&.active]:border-[var(--border-primary)] [&.active]:bg-linear-to-br [&.active]:from-[var(--surface-heading)] [&.active]:to-[var(--surface-primary)] [&.active]:text-white">{{ __($label) }}</button>
                @endforeach
            </div>
        </div>

    </div>

    <div class="terminal grid min-w-0 gap-4 [grid-template-columns:1fr] [gap:16px] [transition:grid-template-columns_0.35s_ease] [&.with-panel]:[grid-template-columns:1fr_460px] [&.panel-expanded]:[grid-template-columns:1fr_0px] [&.panel-expanded_.detail-panel]:[position:fixed] [&.panel-expanded_.detail-panel]:[top:0] [&.panel-expanded_.detail-panel]:[left:0] [&.panel-expanded_.detail-panel]:[right:0] [&.panel-expanded_.detail-panel]:[bottom:0] [&.panel-expanded_.detail-panel]:[width:100vw] [&.panel-expanded_.detail-panel]:[height:100dvh] [&.panel-expanded_.detail-panel]:[max-height:100dvh] [&.panel-expanded_.detail-panel]:[z-index:99999] [&.panel-expanded_.detail-panel]:[border-radius:0] [&.panel-expanded_.detail-panel]:[overflow:hidden] [&.panel-expanded_#detailContent]:max-h-dvh [&.panel-expanded_#detailContent]:overflow-y-auto [&.panel-expanded_#detailContent]:overscroll-contain [&.panel-expanded_.detail-panel]:[animation:expandIn_0.3s_ease] max-[1200px]:[&.with-panel]:[grid-template-columns:1fr] [&.panel-expanded_.detail-body]:[max-width:1200px] [&.panel-expanded_.detail-body]:[margin:0_auto] [&.panel-expanded_.detail-body]:[padding:24px_32px] max-[1024px]:[&.with-panel]:[grid-template-columns:1fr] max-[480px]:[&.panel-expanded_.detail-body]:[padding:16px]" id="terminal">
        <div class="events-table-wrap min-w-0 overflow-x-auto rounded-[14px] [border-radius:14px] [border:1px_solid_var(--border-neutral-5)] [transition:border-color_0.3s] dark:[border-color:rgb(var(--border))] max-[768px]:overflow-visible max-[768px]:border-0 max-[768px]:[&_.events-table]:!min-w-0 max-[768px]:[&_.events-table]:block max-[768px]:[&_thead]:hidden max-[768px]:[&_tbody]:grid max-[768px]:[&_tbody]:gap-3 max-[768px]:[&_tbody]:bg-transparent max-[768px]:[&_tbody_tr]:grid max-[768px]:[&_tbody_tr]:grid-cols-2 max-[768px]:[&_tbody_tr]:overflow-hidden max-[768px]:[&_tbody_tr]:rounded-xl max-[768px]:[&_tbody_tr]:border max-[768px]:[&_tbody_tr]:border-[var(--border-neutral-5-13)] max-[768px]:[&_tbody_tr]:bg-[rgb(var(--surface))] max-[768px]:dark:[&_tbody_tr]:border-[rgb(var(--border))] max-[768px]:dark:[&_tbody_tr]:bg-[rgb(var(--surface-soft))] max-[768px]:[&_tbody_td]:flex max-[768px]:[&_tbody_td]:min-w-0 max-[768px]:[&_tbody_td]:items-center max-[768px]:[&_tbody_td]:justify-between max-[768px]:[&_tbody_td]:gap-2 max-[768px]:[&_tbody_td]:whitespace-normal max-[768px]:[&_tbody_td]:border-b max-[768px]:[&_tbody_td]:border-[var(--border-neutral-5-3)] max-[768px]:[&_tbody_td]:px-3! max-[768px]:[&_tbody_td]:py-2! max-[768px]:[&_tbody_td]:before:shrink-0 max-[768px]:[&_tbody_td]:before:text-[10px] max-[768px]:[&_tbody_td]:before:font-bold max-[768px]:[&_tbody_td]:before:uppercase max-[768px]:[&_tbody_td]:before:tracking-wide max-[768px]:[&_tbody_td]:before:text-[var(--text-muted-soft)] max-[768px]:[&_tbody_td]:before:content-[attr(data-label)] max-[768px]:[&_tbody_td.col-match]:col-span-2 max-[768px]:[&_tbody_td.col-match]:text-right max-[768px]:[&_tbody_td.col-odds]:text-right max-[768px]:[&_tbody_td:last-child]:col-span-2 max-[480px]:[&_tbody_tr]:grid-cols-1 max-[480px]:[&_tbody_td.col-match]:col-span-1 max-[480px]:[&_tbody_td:last-child]:col-span-1" id="eventsWrap">
            <table class="events-table table-fixed w-full min-w-[1800px] border-separate text-[13px] [border-collapse:separate] [border-spacing:0] [font-size:13px] bg-[rgb(var(--surface))] [transition:background_0.3s] dark:[background:rgb(var(--surface-soft))] [&_thead]:[background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] [&_thead]:[color:var(--text-on-primary)] [&_thead]:[position:sticky] [&_thead]:[top:0] [&_thead]:[z-index:5] [&_thead_th]:[padding:10px_12px] [&_thead_th]:[text-align:left] [&_thead_th]:[font-weight:700] [&_thead_th]:[font-size:11px] [&_thead_th]:[letter-spacing:0.5px] [&_thead_th]:[text-transform:uppercase] [&_thead_th]:[white-space:nowrap] [&_tbody_td]:[padding:10px_12px] [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)] [&_tbody_td]:[color:var(--text-secondary-strong)] [&_tbody_td]:[white-space:nowrap] [&_tbody_td]:[transition:color_0.3s,_border-color_0.3s] dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))] dark:[&_tbody_td]:[color:rgb(var(--text-soft))] [&_tbody_tr]:[cursor:pointer] [&_tbody_tr]:[transition:background_0.2s] [&_tbody_tr:hover]:[background:var(--surface-muted)] dark:[&_tbody_tr:hover]:[background:rgb(var(--surface-elevated))] [&_tbody_tr.selected]:[background:var(--surface-hover)] dark:[&_tbody_tr.selected]:[background:rgb(var(--surface-elevated))] [&_td.col-odds]:[text-align:center] [&_td.col-odds]:[padding:6px_4px]! max-[768px]:[font-size:12px] max-[768px]:[min-width:1000px] max-[768px]:[&_thead_th]:[padding:8px_10px] max-[768px]:[&_tbody_td]:[padding:8px_10px]">
                <colgroup>
                    <col style="width:8%">
                    <col style="width:5%">
                    <col style="width:13%">
                    <col style="width:10%">
                    <col style="width:11%">
                    <col style="width:6%">
                    <col style="width:6%">
                    <col style="width:6%">
                    <col style="width:6%">
                    <col style="width:6%">
                    <col style="width:4%">
                    <col style="width:5%">
                    <col style="width:5%">
                    <col style="width:9%">
                </colgroup>
                <thead>
                    <tr>
                        <th>{{ __('TIME')}}</th>
                        <th>{{ __('COMP')}}</th>
                        <th>{{ __('MATCH')}}</th>
                        <th>{{ __('MARKET')}}</th>
                        <th>{{ __('FAV')}}</th>
                        <th>{{ __('BACK')}}</th>
                        <th>{{ __('SIZE')}}</th>
                        <th>{{ __('LAY')}}</th>
                        <th>{{ __('SIZE')}}</th>
                        <th title="{{ __('Resting Back interest versus Lay interest') }}">{{ __('BAL')}}</th>
                        <th title="{{ __('Back interest change over 1 minute, in percentage points') }}">{{ __('MOM')}}</th>
                        <th title="{{ __('Back interest change over 5 minutes, in percentage points') }}">{{ __('Δ')}}</th>
                        <th>{{ __('VOL')}}</th>
                        <th>{{ __('ACTION')}}</th>
                    </tr>
                </thead>
                <tbody id="eventsBody" class="[&_tr[hidden]]:!hidden">
    @endunless
                @fragment($marketActivitySport.'MarketRows')
                    @php $previousCountry = null; @endphp
                    @foreach ($marketActivites as $event)
                        @if ($marketActivitySport === 'greyhounds' && $previousCountry !== $event['country'])
                            <tr data-market-country="{{ $event['country'] }}"><td colspan="14" class="bg-[var(--surface-control)] font-bold dark:bg-[rgb(var(--surface-soft))]">{{ $event['country'] }}</td></tr>
                            @php $previousCountry = $event['country']; @endphp
                        @endif
                        @php
                            $eventDisplayOdds = $checkAuthAndSubscription['display_odds'] && ! ($event['locked'] ?? false);
                            $favorite = $event['runners'][$event['favIdx']];
                            $marketTag = $marketTags[$event['market']] ?? ['label' => $event['market'], 'cls' => 'mo'];
                            $balanceClass = ['back' => 'ind-back-heavy', 'lay' => 'ind-lay-heavy', 'balanced' => 'ind-balanced'][$event['balance']] ?? 'ind-balanced';
                            $balanceLabel = ['back' => 'Back ↑', 'lay' => 'Lay ↓', 'balanced' => 'Balanced →'][$event['balance']] ?? '—';
                            $momentumClass = ['strong_back' => 'ind-mom-strong-back', 'back' => 'ind-mom-back', 'strong_lay' => 'ind-mom-strong-lay', 'lay' => 'ind-mom-lay', 'neutral' => 'ind-mom-neutral'][$event['momentum']] ?? 'ind-mom-neutral';
                            $momentumLabel = ['strong_back' => '↑↑', 'back' => '↑', 'strong_lay' => '↓↓', 'lay' => '↓', 'neutral' => '→'][$event['momentum']] ?? '—';
                            $volatilityClass = ['low' => 'ind-vol-low', 'medium' => 'ind-vol-med', 'high' => 'ind-vol-high'][$event['volatility']] ?? 'ind-vol-med';
                            $volatilityLabel = ['low' => 'Low', 'medium' => 'Med', 'high' => 'High'][$event['volatility']] ?? '—';
                        @endphp
                        <tr data-id="{{ $event['id'] }}" @unless ($eventDisplayOdds) class="cursor-default!" aria-disabled="true" @endunless>
                            <td data-label="{{ __('Time') }}" class="col-time font-bold [color:rgb(var(--text-main))] dark:[color:var(--text-on-primary)] [&_.status-live]:[color:var(--text-danger)] [&_.status-live]:[font-weight:800] [&_.status-live]:[margin-right:4px] dark:[&_.status-live]:[color:rgb(var(--danger))] [&_.status-up]:[color:rgb(var(--primary))] [&_.status-up]:[font-weight:700] [&_.status-up]:[margin-right:4px] dark:[&_.status-up]:[color:rgb(var(--primary))]">
                                @if ($event['status'] === 'LIVE')<span class="status-live">{{ __('● LIVE') }}</span>@elseif ($event['status'] === 'FINISHED')<span>{{ __('FT') }}</span>@endif
                                @if ($event['status'] === 'LIVE' && str_ends_with((string) $event['time'], "'"))
                                    {{ substr((string) $event['time'], 0, -1) }}<span class="minute-tick-blink">'</span>
                                @else
                                    {{ in_array($event['status'], ['LIVE', 'FINISHED'], true) ? $event['time'] : preg_replace('/^(?:UP\s+)?in\s+/i', '', (string) $event['time']) }}
                                @endif
                            </td>
                            <td data-label="{{ __('Competition') }}" class="col-comp [font-size:11.5px] [color:var(--text-muted-soft)] dark:[color:rgb(var(--primary))]"><span class="compact-name" title="{{ $event['comp'] }}">@if (! empty($event['competition_logo']))<img src="{{ $event['competition_logo'] }}" alt="{{ $event['comp'] }}" class="inline-block h-5 w-5 object-contain">@else{{ $event['comp'] }}@endif</span></td>
                            <td data-label="{{ __('Match') }}" class="col-match font-semibold [color:rgb(var(--text-main))] dark:[color:var(--text-on-primary)] [&_.fav-star]:[color:var(--text-warning)] [&_.fav-star]:[margin-left:4px] [&_.fav-star]:[font-size:12px]"><span class="compact-name" title="{{ $event['match'] }}">{{ $event['match'] }}</span>@if (in_array($event['sport'], ['horseracing', 'greyhounds'], true)) <span class="fav-star [color:var(--text-brand-15-2)] [font-size:15px] cursor-pointer [background:none] border-0 [width:24px] [transition:0.2s] text-center dark:[color:rgb(var(--primary))] dark:[&.active-fav]:[color:rgb(var(--warning))] [&.active-fav]:[color:var(--text-warning-5-2)]">⭐</span>@endif</td>
                            <td data-label="{{ __('Market') }}" class="col-market font-semibold [font-size:12px]"><span class="market-tag {{ $marketTag['cls'] }} inline-flex items-center [gap:4px] [padding:3px_10px] [border-radius:20px] [font-size:11px] font-bold whitespace-nowrap [&.mo]:[background:linear-gradient(135deg,_var(--surface-hover),_var(--surface-brand-5-2))] [&.mo]:[color:rgb(var(--primary))] [&.mo]:[border:1px_solid_var(--border-brand-5-2)] dark:[&.mo]:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[&.mo]:[color:rgb(var(--primary))] dark:[&.mo]:[border-color:rgb(var(--border))] [&.ou]:[background:linear-gradient(135deg,_var(--surface-neutral-0-5),_var(--surface-warning-0))] [&.ou]:[color:var(--text-warning-30)] [&.ou]:[border:1px_solid_var(--border-warning-5)] dark:[&.ou]:[background:linear-gradient(135deg,_var(--status-warning-surface),_var(--status-warning-surface))] dark:[&.ou]:[color:rgb(var(--warning))] dark:[&.ou]:[border-color:var(--status-warning-border)] [&.btts]:[background:linear-gradient(135deg,_var(--surface-neutral-5),_var(--surface-neutral-5-2))] [&.btts]:[color:var(--text-success)] [&.btts]:[border:1px_solid_var(--border-success-10)] dark:[&.btts]:[background:linear-gradient(135deg,_var(--status-success-surface),_var(--status-success-surface))] dark:[&.btts]:[color:rgb(var(--success))] dark:[&.btts]:[border-color:var(--status-success-border)] [&.cs]:[background:linear-gradient(135deg,_var(--surface-neutral-0-4),_var(--surface-neutral-5-9))] [&.cs]:[color:var(--text-lay)] [&.cs]:[border:1px_solid_var(--border-accent-5)] dark:[&.cs]:[background:linear-gradient(135deg,_var(--status-accent-surface),_var(--status-accent-surface))] dark:[&.cs]:[color:var(--text-accent)] dark:[&.cs]:[border-color:var(--status-accent-border)] [&.fh]:[background:linear-gradient(135deg,_var(--surface-neutral-0-22),_var(--surface-neutral-5-10))] [&.fh]:[color:var(--text-accent-40)] [&.fh]:[border:1px_solid_var(--border-accent-5-2)] dark:[&.fh]:[background:linear-gradient(135deg,_var(--status-accent-surface),_var(--status-accent-surface))] dark:[&.fh]:[color:var(--text-accent)] dark:[&.fh]:[border-color:var(--status-accent-border)] [&.win]:[background:linear-gradient(135deg,_var(--surface-neutral-0),_var(--surface-danger-0-4))] [&.win]:[color:var(--text-danger)] [&.win]:[border:1px_solid_var(--border-danger-5)] dark:[&.win]:[background:linear-gradient(135deg,_var(--status-danger-surface),_var(--status-danger-surface))] dark:[&.win]:[color:rgb(var(--danger))] dark:[&.win]:[border-color:var(--status-danger-border)]" title="{{ $event['marketName'] ?? $event['marketLabel'] ?? $marketTag['label'] }}">{{ $marketTag['label'] }}</span></td>
                            <td data-label="{{ __('Favourite') }}" class="col-fav font-bold [font-size:12.5px] [color:rgb(var(--text-main))] dark:[color:var(--text-on-primary)] [&_.fav-icon]:[color:var(--text-warning)] [&_.fav-icon]:[font-size:11px] [&_.fav-icon]:[margin-right:4px]"><span class="fav-icon">{{ $event['market'] === 'correct_score' ? '⚽' : '⭐' }}</span><span class="compact-name" title="{{ $favorite['name'] }}">{{ $event['market'] === 'correct_score' ? ($event['marketDisplayScore'] ?? $event['score'] ?? '—') : $favorite['name'] }}</span></td>
                            <td data-label="{{ __('Back') }}" class="col-odds"><span class="odds-cell odds-cell-back [background:var(--surface-back)] [color:rgb(var(--text-main))] [border:1px_solid_var(--border-brand-15-3)] dark:[background:var(--button-primary)] dark:[color:rgb(var(--primary))] dark:[border-color:rgb(var(--border))] [text-align:center] [font-weight:800] [font-size:13.5px] [border-radius:4px] [padding:5px_8px]! [min-width:55px] [display:inline-block] [transition:background_0.4s_ease,_color_0.4s_ease] [margin:0_auto] [font-variant-numeric:tabular-nums] max-[768px]:[font-size:12.5px] max-[768px]:[padding:4px_6px]! max-[768px]:[min-width:50px]">
                                    @if ($eventDisplayOdds)
                                        {{ number_format($favorite['back'], 2) }}
                                    @else
                                        <span class="inline-flex items-center justify-center" aria-label="{{ __('Subscription required') }}">@if (($event['available'] ?? true) === false)<span title="{{ __('Available five minutes before start') }}">—</span>@else @include('lock_icon') @endif</span>
                                    @endif
                                </span></td>
                            <td data-label="{{ __('Back Size') }}" class="col-odds"><span class="size-cell size-cell-back [background:var(--surface-brand-5-3)] [color:rgb(var(--primary))] dark:[background:var(--button-primary)] dark:[color:rgb(var(--primary))] [text-align:center] [font-weight:700] [font-size:12px] [border-radius:4px] [padding:5px_8px]! [min-width:60px] [display:inline-block] [margin:0_auto] [font-variant-numeric:tabular-nums] [transition:background_0.4s_ease,_color_0.4s_ease] max-[768px]:[font-size:11px] max-[768px]:[padding:4px_6px]! max-[768px]:[min-width:50px]">
                                    @if ($eventDisplayOdds)
                                        {{ $event['totalBackSize'] >= 1000 ? number_format($event['totalBackSize'] / 1000, 1).'K' : $event['totalBackSize'] }}
                                    @else
                                        <span class="inline-flex items-center justify-center" aria-label="{{ __('Subscription required') }}">@if (($event['available'] ?? true) === false)<span title="{{ __('Available five minutes before start') }}">—</span>@else @include('lock_icon') @endif</span>
                                    @endif
                                </span></td>
                            <td data-label="{{ __('Lay') }}" class="col-odds"><span class="odds-cell odds-cell-lay [background:var(--surface-lay)] [color:var(--text-danger-70)] [border:1px_solid_var(--border-danger-10-2)] dark:[background:var(--status-danger-surface)] dark:[color:rgb(var(--danger))] dark:[border-color:var(--status-danger-border)] [text-align:center] [font-weight:800] [font-size:13.5px] [border-radius:4px] [padding:5px_8px]! [min-width:55px] [display:inline-block] [transition:background_0.4s_ease,_color_0.4s_ease] [margin:0_auto] [font-variant-numeric:tabular-nums] max-[768px]:[font-size:12.5px] max-[768px]:[padding:4px_6px]! max-[768px]:[min-width:50px]">
                                    @if ($eventDisplayOdds)
                                        {{ number_format($favorite['lay'], 2) }}
                                    @else
                                        <span class="inline-flex items-center justify-center" aria-label="{{ __('Subscription required') }}">@if (($event['available'] ?? true) === false)<span title="{{ __('Available five minutes before start') }}">—</span>@else @include('lock_icon') @endif</span>
                                    @endif
                                </span></td>
                            <td data-label="{{ __('Lay Size') }}" class="col-odds"><span class="size-cell size-cell-lay [background:var(--surface-danger-0-5)] [color:var(--text-danger-35)] dark:[background:var(--status-danger-surface)] dark:[color:rgb(var(--text-main))] [text-align:center] [font-weight:700] [font-size:12px] [border-radius:4px] [padding:5px_8px]! [min-width:60px] [display:inline-block] [margin:0_auto] [font-variant-numeric:tabular-nums] [transition:background_0.4s_ease,_color_0.4s_ease] max-[768px]:[font-size:11px] max-[768px]:[padding:4px_6px]! max-[768px]:[min-width:50px]">
                                    @if ($eventDisplayOdds)
                                        {{ $event['totalLaySize'] >= 1000 ? number_format($event['totalLaySize'] / 1000, 1).'K' : $event['totalLaySize'] }}
                                    @else
                                        <span class="inline-flex items-center justify-center" aria-label="{{ __('Subscription required') }}">@if (($event['available'] ?? true) === false)<span title="{{ __('Available five minutes before start') }}">—</span>@else @include('lock_icon') @endif</span>
                                    @endif
                                </span></td>
                            <td data-label="{{ __('Balance') }}">
                                @if ($eventDisplayOdds)
                                    <span class="ind [&.ind-back-heavy]:[background:var(--surface-hover)] [&.ind-back-heavy]:[color:rgb(var(--primary))] dark:[&.ind-back-heavy]:[background:rgb(var(--surface-elevated))] dark:[&.ind-back-heavy]:[color:rgb(var(--primary))] [&.ind-lay-heavy]:[background:var(--surface-neutral-0-4)] [&.ind-lay-heavy]:[color:var(--text-lay)] dark:[&.ind-lay-heavy]:[background:var(--status-accent-surface)] dark:[&.ind-lay-heavy]:[color:var(--text-accent)] [&.ind-balanced]:[background:var(--surface-neutral)] [&.ind-balanced]:[color:var(--text-brand-35)] dark:[&.ind-balanced]:[background:rgb(var(--surface))] dark:[&.ind-balanced]:[color:rgb(var(--text-soft))] [&.ind-mom-strong-back]:[background:var(--surface-hover)] [&.ind-mom-strong-back]:[color:rgb(var(--primary))] [&.ind-mom-strong-back]:[font-weight:800] [&.ind-mom-back]:[background:var(--surface-hover)] [&.ind-mom-back]:[color:rgb(var(--primary))] [&.ind-mom-neutral]:[background:var(--surface-neutral)] [&.ind-mom-neutral]:[color:var(--text-brand-35)] [&.ind-mom-lay]:[background:var(--surface-neutral-0-4)] [&.ind-mom-lay]:[color:var(--text-lay)] [&.ind-mom-strong-lay]:[background:var(--surface-neutral-0-4)] [&.ind-mom-strong-lay]:[color:var(--text-lay)] [&.ind-mom-strong-lay]:[font-weight:800] dark:[&.ind-mom-strong-back]:[background:rgb(var(--surface-elevated))] dark:[&.ind-mom-strong-back]:[color:rgb(var(--primary))] dark:[&.ind-mom-back]:[background:rgb(var(--surface-elevated))] dark:[&.ind-mom-back]:[color:rgb(var(--primary))] dark:[&.ind-mom-neutral]:[background:rgb(var(--surface))] dark:[&.ind-mom-neutral]:[color:rgb(var(--text-soft))] dark:[&.ind-mom-lay]:[background:var(--status-accent-surface)] dark:[&.ind-mom-lay]:[color:var(--text-accent)] dark:[&.ind-mom-strong-lay]:[background:var(--status-accent-surface)] dark:[&.ind-mom-strong-lay]:[color:var(--text-accent)] [&.ind-delta-pos]:[background:var(--surface-neutral-5)] [&.ind-delta-pos]:[color:var(--text-success)] dark:[&.ind-delta-pos]:[background:var(--status-success-surface)] dark:[&.ind-delta-pos]:[color:rgb(var(--success))] [&.ind-delta-neg]:[background:var(--surface-neutral-0)] [&.ind-delta-neg]:[color:var(--text-danger)] dark:[&.ind-delta-neg]:[background:var(--status-danger-surface)] dark:[&.ind-delta-neg]:[color:rgb(var(--danger))] [&.ind-delta-zero]:[background:var(--surface-neutral)] [&.ind-delta-zero]:[color:var(--text-brand-35)] dark:[&.ind-delta-zero]:[background:rgb(var(--surface))] dark:[&.ind-delta-zero]:[color:rgb(var(--text-soft))] [&.ind-vol-low]:[background:var(--surface-neutral-5)] [&.ind-vol-low]:[color:var(--text-success)] dark:[&.ind-vol-low]:[background:var(--status-success-surface)] dark:[&.ind-vol-low]:[color:rgb(var(--success))] [&.ind-vol-med]:[background:var(--surface-neutral-0-5)] [&.ind-vol-med]:[color:var(--text-warning-30)] dark:[&.ind-vol-med]:[background:var(--status-warning-surface)] dark:[&.ind-vol-med]:[color:rgb(var(--warning))] [&.ind-vol-high]:[background:var(--surface-neutral-0)] [&.ind-vol-high]:[color:var(--text-danger)] dark:[&.ind-vol-high]:[background:var(--status-danger-surface)] dark:[&.ind-vol-high]:[color:rgb(var(--danger))] {{ $balanceClass }} inline-flex items-center [gap:4px] [padding:3px_8px] [border-radius:12px] [font-size:11px] font-bold whitespace-nowrap [transition:0.3s]">{{ $balanceLabel }}</span>
                                @else
                                    <span class="inline-flex items-center justify-center" aria-label="{{ __('Subscription required') }}">@if (($event['available'] ?? true) === false)<span title="{{ __('Available five minutes before start') }}">—</span>@else @include('lock_icon') @endif</span>
                                @endif
                            </td>
                            <td data-label="{{ __('Momentum') }}">
                                @if ($eventDisplayOdds)
                                    <span class="ind [&.ind-back-heavy]:[background:var(--surface-hover)] [&.ind-back-heavy]:[color:rgb(var(--primary))] dark:[&.ind-back-heavy]:[background:rgb(var(--surface-elevated))] dark:[&.ind-back-heavy]:[color:rgb(var(--primary))] [&.ind-lay-heavy]:[background:var(--surface-neutral-0-4)] [&.ind-lay-heavy]:[color:var(--text-lay)] dark:[&.ind-lay-heavy]:[background:var(--status-accent-surface)] dark:[&.ind-lay-heavy]:[color:var(--text-accent)] [&.ind-balanced]:[background:var(--surface-neutral)] [&.ind-balanced]:[color:var(--text-brand-35)] dark:[&.ind-balanced]:[background:rgb(var(--surface))] dark:[&.ind-balanced]:[color:rgb(var(--text-soft))] [&.ind-mom-strong-back]:[background:var(--surface-hover)] [&.ind-mom-strong-back]:[color:rgb(var(--primary))] [&.ind-mom-strong-back]:[font-weight:800] [&.ind-mom-back]:[background:var(--surface-hover)] [&.ind-mom-back]:[color:rgb(var(--primary))] [&.ind-mom-neutral]:[background:var(--surface-neutral)] [&.ind-mom-neutral]:[color:var(--text-brand-35)] [&.ind-mom-lay]:[background:var(--surface-neutral-0-4)] [&.ind-mom-lay]:[color:var(--text-lay)] [&.ind-mom-strong-lay]:[background:var(--surface-neutral-0-4)] [&.ind-mom-strong-lay]:[color:var(--text-lay)] [&.ind-mom-strong-lay]:[font-weight:800] dark:[&.ind-mom-strong-back]:[background:rgb(var(--surface-elevated))] dark:[&.ind-mom-strong-back]:[color:rgb(var(--primary))] dark:[&.ind-mom-back]:[background:rgb(var(--surface-elevated))] dark:[&.ind-mom-back]:[color:rgb(var(--primary))] dark:[&.ind-mom-neutral]:[background:rgb(var(--surface))] dark:[&.ind-mom-neutral]:[color:rgb(var(--text-soft))] dark:[&.ind-mom-lay]:[background:var(--status-accent-surface)] dark:[&.ind-mom-lay]:[color:var(--text-accent)] dark:[&.ind-mom-strong-lay]:[background:var(--status-accent-surface)] dark:[&.ind-mom-strong-lay]:[color:var(--text-accent)] [&.ind-delta-pos]:[background:var(--surface-neutral-5)] [&.ind-delta-pos]:[color:var(--text-success)] dark:[&.ind-delta-pos]:[background:var(--status-success-surface)] dark:[&.ind-delta-pos]:[color:rgb(var(--success))] [&.ind-delta-neg]:[background:var(--surface-neutral-0)] [&.ind-delta-neg]:[color:var(--text-danger)] dark:[&.ind-delta-neg]:[background:var(--status-danger-surface)] dark:[&.ind-delta-neg]:[color:rgb(var(--danger))] [&.ind-delta-zero]:[background:var(--surface-neutral)] [&.ind-delta-zero]:[color:var(--text-brand-35)] dark:[&.ind-delta-zero]:[background:rgb(var(--surface))] dark:[&.ind-delta-zero]:[color:rgb(var(--text-soft))] [&.ind-vol-low]:[background:var(--surface-neutral-5)] [&.ind-vol-low]:[color:var(--text-success)] dark:[&.ind-vol-low]:[background:var(--status-success-surface)] dark:[&.ind-vol-low]:[color:rgb(var(--success))] [&.ind-vol-med]:[background:var(--surface-neutral-0-5)] [&.ind-vol-med]:[color:var(--text-warning-30)] dark:[&.ind-vol-med]:[background:var(--status-warning-surface)] dark:[&.ind-vol-med]:[color:rgb(var(--warning))] [&.ind-vol-high]:[background:var(--surface-neutral-0)] [&.ind-vol-high]:[color:var(--text-danger)] dark:[&.ind-vol-high]:[background:var(--status-danger-surface)] dark:[&.ind-vol-high]:[color:rgb(var(--danger))] {{ $momentumClass }} inline-flex items-center [gap:4px] [padding:3px_8px] [border-radius:12px] [font-size:11px] font-bold whitespace-nowrap [transition:0.3s]">{{ $momentumLabel }}</span>
                                @else
                                    <span class="inline-flex items-center justify-center" aria-label="{{ __('Subscription required') }}">@if (($event['available'] ?? true) === false)<span title="{{ __('Available five minutes before start') }}">—</span>@else @include('lock_icon') @endif</span>
                                @endif
                            </td>
                            <td data-label="{{ __('Delta') }}">
                                @if ($eventDisplayOdds)
                                    <span class="ind [&.ind-back-heavy]:[background:var(--surface-hover)] [&.ind-back-heavy]:[color:rgb(var(--primary))] dark:[&.ind-back-heavy]:[background:rgb(var(--surface-elevated))] dark:[&.ind-back-heavy]:[color:rgb(var(--primary))] [&.ind-lay-heavy]:[background:var(--surface-neutral-0-4)] [&.ind-lay-heavy]:[color:var(--text-lay)] dark:[&.ind-lay-heavy]:[background:var(--status-accent-surface)] dark:[&.ind-lay-heavy]:[color:var(--text-accent)] [&.ind-balanced]:[background:var(--surface-neutral)] [&.ind-balanced]:[color:var(--text-brand-35)] dark:[&.ind-balanced]:[background:rgb(var(--surface))] dark:[&.ind-balanced]:[color:rgb(var(--text-soft))] [&.ind-mom-strong-back]:[background:var(--surface-hover)] [&.ind-mom-strong-back]:[color:rgb(var(--primary))] [&.ind-mom-strong-back]:[font-weight:800] [&.ind-mom-back]:[background:var(--surface-hover)] [&.ind-mom-back]:[color:rgb(var(--primary))] [&.ind-mom-neutral]:[background:var(--surface-neutral)] [&.ind-mom-neutral]:[color:var(--text-brand-35)] [&.ind-mom-lay]:[background:var(--surface-neutral-0-4)] [&.ind-mom-lay]:[color:var(--text-lay)] [&.ind-mom-strong-lay]:[background:var(--surface-neutral-0-4)] [&.ind-mom-strong-lay]:[color:var(--text-lay)] [&.ind-mom-strong-lay]:[font-weight:800] dark:[&.ind-mom-strong-back]:[background:rgb(var(--surface-elevated))] dark:[&.ind-mom-strong-back]:[color:rgb(var(--primary))] dark:[&.ind-mom-back]:[background:rgb(var(--surface-elevated))] dark:[&.ind-mom-back]:[color:rgb(var(--primary))] dark:[&.ind-mom-neutral]:[background:rgb(var(--surface))] dark:[&.ind-mom-neutral]:[color:rgb(var(--text-soft))] dark:[&.ind-mom-lay]:[background:var(--status-accent-surface)] dark:[&.ind-mom-lay]:[color:var(--text-accent)] dark:[&.ind-mom-strong-lay]:[background:var(--status-accent-surface)] dark:[&.ind-mom-strong-lay]:[color:var(--text-accent)] [&.ind-delta-pos]:[background:var(--surface-neutral-5)] [&.ind-delta-pos]:[color:var(--text-success)] dark:[&.ind-delta-pos]:[background:var(--status-success-surface)] dark:[&.ind-delta-pos]:[color:rgb(var(--success))] [&.ind-delta-neg]:[background:var(--surface-neutral-0)] [&.ind-delta-neg]:[color:var(--text-danger)] dark:[&.ind-delta-neg]:[background:var(--status-danger-surface)] dark:[&.ind-delta-neg]:[color:rgb(var(--danger))] [&.ind-delta-zero]:[background:var(--surface-neutral)] [&.ind-delta-zero]:[color:var(--text-brand-35)] dark:[&.ind-delta-zero]:[background:rgb(var(--surface))] dark:[&.ind-delta-zero]:[color:rgb(var(--text-soft))] [&.ind-vol-low]:[background:var(--surface-neutral-5)] [&.ind-vol-low]:[color:var(--text-success)] dark:[&.ind-vol-low]:[background:var(--status-success-surface)] dark:[&.ind-vol-low]:[color:rgb(var(--success))] [&.ind-vol-med]:[background:var(--surface-neutral-0-5)] [&.ind-vol-med]:[color:var(--text-warning-30)] dark:[&.ind-vol-med]:[background:var(--status-warning-surface)] dark:[&.ind-vol-med]:[color:rgb(var(--warning))] [&.ind-vol-high]:[background:var(--surface-neutral-0)] [&.ind-vol-high]:[color:var(--text-danger)] dark:[&.ind-vol-high]:[background:var(--status-danger-surface)] dark:[&.ind-vol-high]:[color:rgb(var(--danger))] {{ str_starts_with($event['delta'], '+') ? 'ind-delta-pos' : (str_starts_with($event['delta'], '-') ? 'ind-delta-neg' : 'ind-delta-zero') }} inline-flex items-center [gap:4px] [padding:3px_8px] [border-radius:12px] [font-size:11px] font-bold whitespace-nowrap [transition:0.3s]">{{ $event['delta'] }}</span>
                                @else
                                    <span class="inline-flex items-center justify-center" aria-label="{{ __('Subscription required') }}">@if (($event['available'] ?? true) === false)<span title="{{ __('Available five minutes before start') }}">—</span>@else @include('lock_icon') @endif</span>
                                @endif
                            </td>
                            <td data-label="{{ __('Volatility') }}">
                                @if ($eventDisplayOdds)
                                    <span class="ind [&.ind-back-heavy]:[background:var(--surface-hover)] [&.ind-back-heavy]:[color:rgb(var(--primary))] dark:[&.ind-back-heavy]:[background:rgb(var(--surface-elevated))] dark:[&.ind-back-heavy]:[color:rgb(var(--primary))] [&.ind-lay-heavy]:[background:var(--surface-neutral-0-4)] [&.ind-lay-heavy]:[color:var(--text-lay)] dark:[&.ind-lay-heavy]:[background:var(--status-accent-surface)] dark:[&.ind-lay-heavy]:[color:var(--text-accent)] [&.ind-balanced]:[background:var(--surface-neutral)] [&.ind-balanced]:[color:var(--text-brand-35)] dark:[&.ind-balanced]:[background:rgb(var(--surface))] dark:[&.ind-balanced]:[color:rgb(var(--text-soft))] [&.ind-mom-strong-back]:[background:var(--surface-hover)] [&.ind-mom-strong-back]:[color:rgb(var(--primary))] [&.ind-mom-strong-back]:[font-weight:800] [&.ind-mom-back]:[background:var(--surface-hover)] [&.ind-mom-back]:[color:rgb(var(--primary))] [&.ind-mom-neutral]:[background:var(--surface-neutral)] [&.ind-mom-neutral]:[color:var(--text-brand-35)] [&.ind-mom-lay]:[background:var(--surface-neutral-0-4)] [&.ind-mom-lay]:[color:var(--text-lay)] [&.ind-mom-strong-lay]:[background:var(--surface-neutral-0-4)] [&.ind-mom-strong-lay]:[color:var(--text-lay)] [&.ind-mom-strong-lay]:[font-weight:800] dark:[&.ind-mom-strong-back]:[background:rgb(var(--surface-elevated))] dark:[&.ind-mom-strong-back]:[color:rgb(var(--primary))] dark:[&.ind-mom-back]:[background:rgb(var(--surface-elevated))] dark:[&.ind-mom-back]:[color:rgb(var(--primary))] dark:[&.ind-mom-neutral]:[background:rgb(var(--surface))] dark:[&.ind-mom-neutral]:[color:rgb(var(--text-soft))] dark:[&.ind-mom-lay]:[background:var(--status-accent-surface)] dark:[&.ind-mom-lay]:[color:var(--text-accent)] dark:[&.ind-mom-strong-lay]:[background:var(--status-accent-surface)] dark:[&.ind-mom-strong-lay]:[color:var(--text-accent)] [&.ind-delta-pos]:[background:var(--surface-neutral-5)] [&.ind-delta-pos]:[color:var(--text-success)] dark:[&.ind-delta-pos]:[background:var(--status-success-surface)] dark:[&.ind-delta-pos]:[color:rgb(var(--success))] [&.ind-delta-neg]:[background:var(--surface-neutral-0)] [&.ind-delta-neg]:[color:var(--text-danger)] dark:[&.ind-delta-neg]:[background:var(--status-danger-surface)] dark:[&.ind-delta-neg]:[color:rgb(var(--danger))] [&.ind-delta-zero]:[background:var(--surface-neutral)] [&.ind-delta-zero]:[color:var(--text-brand-35)] dark:[&.ind-delta-zero]:[background:rgb(var(--surface))] dark:[&.ind-delta-zero]:[color:rgb(var(--text-soft))] [&.ind-vol-low]:[background:var(--surface-neutral-5)] [&.ind-vol-low]:[color:var(--text-success)] dark:[&.ind-vol-low]:[background:var(--status-success-surface)] dark:[&.ind-vol-low]:[color:rgb(var(--success))] [&.ind-vol-med]:[background:var(--surface-neutral-0-5)] [&.ind-vol-med]:[color:var(--text-warning-30)] dark:[&.ind-vol-med]:[background:var(--status-warning-surface)] dark:[&.ind-vol-med]:[color:rgb(var(--warning))] [&.ind-vol-high]:[background:var(--surface-neutral-0)] [&.ind-vol-high]:[color:var(--text-danger)] dark:[&.ind-vol-high]:[background:var(--status-danger-surface)] dark:[&.ind-vol-high]:[color:rgb(var(--danger))] {{ $volatilityClass }} inline-flex items-center [gap:4px] [padding:3px_8px] [border-radius:12px] [font-size:11px] font-bold whitespace-nowrap [transition:0.3s]">{{ $volatilityLabel }}</span>
                                @else
                                    <span class="inline-flex items-center justify-center" aria-label="{{ __('Subscription required') }}">@if (($event['available'] ?? true) === false)<span title="{{ __('Available five minutes before start') }}">—</span>@else @include('lock_icon') @endif</span>
                                @endif
                            </td>
                            <td data-label="{{ __('Action') }}">
                                <button type="button" data-market-details aria-expanded="false" aria-label="{{ __('Toggle secondary market statistics') }}" class="market-details-toggle rounded-full border border-[var(--border-control)] px-2 py-1 text-xs font-semibold">{{ __('Details') }}</button>
                                @if ($eventDisplayOdds)
                                    <button type="button" class="open-btn inline-flex items-center [gap:4px] [padding:5px_12px] [border-radius:20px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white [font-size:11.5px] font-bold border-0 cursor-pointer [transition:transform_0.2s,_box-shadow_0.2s] [font-family:inherit] whitespace-nowrap dark:[background:linear-gradient(135deg,_var(--button-primary),_var(--button-primary))] [&:hover]:[transform:translateY(-1px)] [&:hover]:[box-shadow:0_6px_16px_var(--shadow-brand-40-2)]" data-id="{{ $event['id'] }}">{{ __('Open') }} →</button>
                                @else
                                    <span class="inline-flex items-center justify-center" aria-label="{{ __('Subscription required') }}">@if (($event['available'] ?? true) === false)<span title="{{ __('Available five minutes before start') }}">—</span>@else @include('lock_icon') @endif</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($marketActivites === [] && empty($marketRowOrder))
                        <tr data-market-empty><td colspan="14" class="py-10 text-center">{{ __('No data available.') }}</td></tr>
                    @endif
                    @unless ($compactMarketRows ?? false)
                    <script type="application/json" data-market-server-time>{{ time() }}</script>
                    <script type="application/json" data-market-events>@json($marketActivites)</script>
                    @if ($marketRowsOnly && isset($marketRowOrder, $marketRowVersions))
                        <script type="application/json" data-market-order>@json($marketRowOrder)</script>
                        <script type="application/json" data-market-versions>@json((object) $marketRowVersions)</script>
                    @endif
                    @endunless
                @endfragment
    @unless ($marketRowsOnly ?? false)
                </tbody>
            </table>
            <div
                class="flex min-w-full items-center justify-center py-10 text-sm font-semibold text-[var(--text-slate-300)] dark:text-[var(--text-slate-600)] {{ $hasMore ? '' : 'hidden' }}"
                data-market-lazy-loader
                data-has-more="{{ $hasMore ? 'true' : 'false' }}"
                data-next-page="{{ $nextPage }}"
                @if (! $hasMore) hidden @endif
            >
                <button type="button" data-market-loader-text>↓ {{ __('Scroll for more matches') }} ↓</button>
            </div>
        </div>

        <div class="detail-panel overflow-y-auto overscroll-contain rounded-2xl border hidden [background:var(--surface-muted)] [border:1px_solid_var(--border-neutral-5)] [border-radius:16px] p-0 overflow-hidden [transition:0.3s] [height:fit-content] [position:sticky] [top:20px] [max-height:calc(100dvh_-_40px)] [overflow-y:auto] dark:[background:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] [&.open]:[display:block] [&.open]:[animation:slideIn_0.3s_ease] max-[1024px]:[position:static] max-[480px]:[padding:0]" id="detailPanel">
            <div class="empty-panel flex flex-col items-center justify-center [padding:60px_20px] text-center [color:var(--text-muted-soft)] [&_i]:[font-size:40px] [&_i]:[margin-bottom:12px] [&_i]:[opacity:0.5] [&_p]:[font-size:14px]" id="emptyPanel">
                <i class="fas fa-mouse-pointer"></i>
                <p>{{ __('Select an event to view detailed data') }}</p>
            </div>
            <div id="detailContent" style="display:none;"></div>
        </div>
    </div>

    <button type="button" class="scroll-top fixed [right:20px] [bottom:20px] [width:45px] [height:45px] border-0 [border-radius:50%] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white [font-size:20px] cursor-pointer [z-index:9999] hidden items-center justify-center [box-shadow:0_4px_16px_var(--shadow-brand-40-2)] [transition:0.2s] [&.visible]:[display:flex] [&:hover]:[transform:translateY(-3px)]" id="scrollTop" aria-label="{{ __('Scroll to top') }}">↑</button>

    @include('popup')

    @push('js')
        <script>
            (function() {
                // ===== COLLAPSE TOGGLE =====
                const collapseToggle = document.getElementById('collapseToggle');
                if (localStorage.getItem('bf-markets-collapsed') === 'true') {
                    document.body.classList.add('collapsed');
                    document.getElementById('controlsWrapper').classList.add('hidden');
                }
                collapseToggle.addEventListener('click', function() {
                    document.body.classList.toggle('collapsed');
                    const isCollapsed = document.body.classList.contains('collapsed');
                    document.getElementById('controlsWrapper').classList.toggle('hidden', isCollapsed);
                    localStorage.setItem('bf-markets-collapsed', isCollapsed ? 'true' : 'false');
                    if (typeof ym !== 'undefined') {
                        ym(109087271, 'reachGoal', 'market_activity_collapse_toggle', {
                            state: isCollapsed ? 'collapsed' : 'expanded'
                        });
                    }
                });

                // ===== SIMULATION =====
                const SIM = {
                    tickInterval: 3000,
                    isProMode: false,
                    latency: 154,
                    previousRunners: {},
                    timerId: null
                };

                function rand(min, max) {
                    return Math.random() * (max - min) + min;
                }

                function randInt(min, max) {
                    return Math.floor(rand(min, max + 1));
                }

                function r2(n) {
                    return Math.round(n * 100) / 100;
                }

                function fmtSize(n) {
                    return n >= 1000
                        ? (n / 1000).toFixed(1) + 'K'
                        : Number(n.toFixed(2)).toString();
                }

                function fmtDelta(prev, curr) {
                    if (prev === 0) return '0%';
                    const pct = ((curr - prev) / prev) * 100;
                    if (Math.abs(pct) < 0.05) return '0%';
                    return (pct > 0 ? '+' : '') + pct.toFixed(1) + '%';
                }

                // ===== MARKET TAGS =====
                const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char]));
                const MARKET_TAGS = @json($marketTags);
                const events = JSON.parse(document.querySelector('[data-market-events]').textContent);
                const displayOdds = @json((bool) $checkAuthAndSubscription['display_odds']);

                // ===== SIMULATE =====
                function simulateTick() {
                    const numUpdates = SIM.isProMode ? randInt(2, 5) : randInt(1, 3);
                    const shuffled = events.slice().sort(function() {
                        return Math.random() - 0.5;
                    });
                    for (let i = 0; i < numUpdates && i < shuffled.length; i++) {
                        updateEvent(shuffled[i]);
                    }
                    SIM.latency = SIM.isProMode ? randInt(140, 177) : randInt(80, 150);
                    const latEl = document.getElementById('latencyDisplay');
                    if (latEl) latEl.textContent = SIM.latency;
                    updateVisibleCells();
                    if (currentEventId !== null) updateDetailPanel();
                }

                function updateEvent(ev) {
                    if (!SIM.previousRunners[ev.id]) {
                        SIM.previousRunners[ev.id] = ev.runners.map(function(r) {
                            return {
                                back: r.back,
                                lay: r.lay,
                                backSize: r.backSize,
                                laySize: r.laySize
                            };
                        });
                    }

                    ev.runners.forEach(function(runner) {
                        const backDrift = rand(-0.025, 0.025);
                        const layDrift = rand(-0.025, 0.025);

                        let volatilityFactor = 1;
                        if (ev.sport === 'horseracing' || ev.sport === 'greyhounds') volatilityFactor = 1.6;
                        else if (ev.sport === 'football' && ev.status === 'LIVE') volatilityFactor = 1.4;
                        else if (ev.sport === 'tennis') volatilityFactor = 1.2;

                        let speedFactor = 1;
                        if (SIM.tickInterval >= 5000) speedFactor = 1.8;
                        else if (SIM.tickInterval >= 3000) speedFactor = 1.4;
                        else if (SIM.tickInterval >= 2000) speedFactor = 1.2;

                        let newBack = r2(runner.back * (1 + backDrift * volatilityFactor * speedFactor));
                        let newLay = r2(runner.lay * (1 + layDrift * volatilityFactor * speedFactor));
                        if (newLay <= newBack) newLay = r2(newBack + Math.max(0.01, newBack * 0.01));
                        newBack = Math.max(1.01, Math.min(50, newBack));
                        newLay = Math.max(1.02, Math.min(60, newLay));

                        const backSizeChange = rand(-0.12, 0.16) * speedFactor;
                        const laySizeChange = rand(-0.12, 0.16) * speedFactor;
                        let newBackSize = Math.round(runner.backSize * (1 + backSizeChange));
                        let newLaySize = Math.round(runner.laySize * (1 + laySizeChange));
                        newBackSize = Math.max(500, Math.min(200000, newBackSize));
                        newLaySize = Math.max(500, Math.min(200000, newLaySize));

                        runner.back = newBack;
                        runner.lay = newLay;
                        runner.backSize = newBackSize;
                        runner.laySize = newLaySize;
                    });

                    let totalBack = 0,
                        totalLay = 0;
                    ev.runners.forEach(function(r) {
                        totalBack += r.backSize;
                        totalLay += r.laySize;
                    });
                    const ratio = totalBack / (totalBack + totalLay);
                    if (ratio > 0.58) ev.balance = 'back';
                    else if (ratio < 0.42) ev.balance = 'lay';
                    else ev.balance = 'balanced';

                    const favRunner = ev.runners[ev.favIdx];
                    const prevFav = SIM.previousRunners[ev.id][ev.favIdx];
                    if (prevFav) ev.delta = fmtDelta(prevFav.backSize, favRunner.backSize);

                    const deltaNum = parseFloat(ev.delta.replace('%', '').replace('+', ''));
                    if (deltaNum > 10) ev.momentum = 'strong_back';
                    else if (deltaNum > 3) ev.momentum = 'back';
                    else if (deltaNum < -10) ev.momentum = 'strong_lay';
                    else if (deltaNum < -3) ev.momentum = 'lay';
                    else ev.momentum = 'neutral';

                    const absDelta = Math.abs(deltaNum);
                    if (absDelta > 15) ev.volatility = 'high';
                    else if (absDelta > 5) ev.volatility = 'medium';
                    else ev.volatility = 'low';

                    SIM.previousRunners[ev.id] = ev.runners.map(function(r) {
                        return {
                            back: r.back,
                            lay: r.lay,
                            backSize: r.backSize,
                            laySize: r.laySize
                        };
                    });
                }

                // ===== HELPERS =====
                function balanceClass(bal) {
                    return bal === 'back' ? 'ind-back-heavy' : bal === 'lay' ? 'ind-lay-heavy' : 'ind-balanced';
                }

                function balanceText(bal) {
                    if (bal === 'unknown') return '—';
                    return bal === 'back' ? (globalThis.bfTranslations?.["Back ↑"] ?? "Back ↑") : bal === 'lay' ? (globalThis.bfTranslations?.["Lay ↓"] ?? "Lay ↓") : (globalThis.bfTranslations?.["Balanced →"] ?? "Balanced →");
                }

                function momentumClass(mom) {
                    if (mom === 'strong_back') return 'ind-mom-strong-back';
                    if (mom === 'back') return 'ind-mom-back';
                    if (mom === 'strong_lay') return 'ind-mom-strong-lay';
                    if (mom === 'lay') return 'ind-mom-lay';
                    return 'ind-mom-neutral';
                }

                function momentumText(mom) {
                    if (mom === 'unknown') return '—';
                    if (mom === 'strong_back') return '↑↑';
                    if (mom === 'back') return '↑';
                    if (mom === 'strong_lay') return '↓↓';
                    if (mom === 'lay') return '↓';
                    return '→';
                }

                function deltaClass(d) {
                    return d.startsWith('+') ? 'ind-delta-pos' : d.startsWith('-') ? 'ind-delta-neg' : 'ind-delta-zero';
                }

                function volClass(v) {
                    return v === 'low' ? 'ind-vol-low' : v === 'high' ? 'ind-vol-high' : 'ind-vol-med';
                }

                function volText(v) {
                    if (v === 'unknown') return '—';
                    return v === 'low' ? (globalThis.bfTranslations?.["Low"] ?? "Low") : v === 'high' ? (globalThis.bfTranslations?.["High"] ?? "High") : 'Med';
                }

                function timeDisplay(ev) {
                    if (ev.status === 'LIVE') return `<span class="status-live">${(globalThis.bfTranslations?.["● LIVE"] ?? "● LIVE")}</span> ` + ev.time;
                    return '<span class="status-up">▲ UP</span> ' + ev.time;
                }

                function getMarketTag(marketKey) {
                    const tag = MARKET_TAGS[marketKey] || {
                        label: marketKey,
                        cls: 'mo'
                    };
                    return '<span class="market-tag ' + tag.cls + ' inline-flex items-center [gap:4px] [padding:3px_10px] [border-radius:20px] [font-size:11px] font-bold whitespace-nowrap [&.mo]:[background:linear-gradient(135deg,_var(--surface-hover),_var(--surface-brand-5-2))] [&.mo]:[color:rgb(var(--primary))] [&.mo]:[border:1px_solid_var(--border-brand-5-2)] dark:[&.mo]:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[&.mo]:[color:rgb(var(--primary))] dark:[&.mo]:[border-color:rgb(var(--border))] [&.ou]:[background:linear-gradient(135deg,_var(--surface-neutral-0-5),_var(--surface-warning-0))] [&.ou]:[color:var(--text-warning-30)] [&.ou]:[border:1px_solid_var(--border-warning-5)] dark:[&.ou]:[background:linear-gradient(135deg,_var(--status-warning-surface),_var(--status-warning-surface))] dark:[&.ou]:[color:rgb(var(--warning))] dark:[&.ou]:[border-color:var(--status-warning-border)] [&.btts]:[background:linear-gradient(135deg,_var(--surface-neutral-5),_var(--surface-neutral-5-2))] [&.btts]:[color:var(--text-success)] [&.btts]:[border:1px_solid_var(--border-success-10)] dark:[&.btts]:[background:linear-gradient(135deg,_var(--status-success-surface),_var(--status-success-surface))] dark:[&.btts]:[color:rgb(var(--success))] dark:[&.btts]:[border-color:var(--status-success-border)] [&.cs]:[background:linear-gradient(135deg,_var(--surface-neutral-0-4),_var(--surface-neutral-5-9))] [&.cs]:[color:var(--text-lay)] [&.cs]:[border:1px_solid_var(--border-accent-5)] dark:[&.cs]:[background:linear-gradient(135deg,_var(--status-accent-surface),_var(--status-accent-surface))] dark:[&.cs]:[color:var(--text-accent)] dark:[&.cs]:[border-color:var(--status-accent-border)] [&.fh]:[background:linear-gradient(135deg,_var(--surface-neutral-0-22),_var(--surface-neutral-5-10))] [&.fh]:[color:var(--text-accent-40)] [&.fh]:[border:1px_solid_var(--border-accent-5-2)] dark:[&.fh]:[background:linear-gradient(135deg,_var(--status-accent-surface),_var(--status-accent-surface))] dark:[&.fh]:[color:var(--text-accent)] dark:[&.fh]:[border-color:var(--status-accent-border)] [&.win]:[background:linear-gradient(135deg,_var(--surface-neutral-0),_var(--surface-danger-0-4))] [&.win]:[color:var(--text-danger)] [&.win]:[border:1px_solid_var(--border-danger-5)] dark:[&.win]:[background:linear-gradient(135deg,_var(--status-danger-surface),_var(--status-danger-surface))] dark:[&.win]:[color:rgb(var(--danger))] dark:[&.win]:[border-color:var(--status-danger-border)]">' + escapeHtml(tag.label) + '</span>';
                }

                function getFavDisplay(ev) {
                    if (ev.market === 'correct_score') {
                        return '<span class="fav-icon">⚽</span>' + (ev.marketDisplayScore || ev.score || '—');
                    }
                    const fav = ev.runners[ev.favIdx];
                    return '<span class="fav-icon">⭐</span>' + fav.name;
                }

                // ===== RENDER TABLE =====
                const eventsBody = document.getElementById('eventsBody');

                const marketLazyLoader = document.querySelector('[data-market-lazy-loader]');
                const terminal = document.getElementById('terminal');
                const detailPanel = document.getElementById('detailPanel');
                const emptyPanel = document.getElementById('emptyPanel');
                const detailContent = document.getElementById('detailContent');
                const marketContainer = document.getElementById('marketContainer');
                const marketSelect = document.getElementById('marketSelect');
                let currentEventId = null;
                let activeMarketFilter = marketSelect?.value ?? 'active';
                let activeFootballFilter = document.querySelector('.filter-pill.active')?.dataset.filter || 'all';

                let activeStartSeconds = 0;
                let serverClockOffset = Number(eventsBody.querySelector('[data-market-server-time]').textContent) * 1000 - Date.now();
                const serverNow = () => (Date.now() + serverClockOffset) / 1000;

                function matchesStartWindow(event, now) {
                    if (!activeStartSeconds) return true;
                    if (event.internal_backup || event.hidden || event.status === 'FINISHED') return false;
                    const remaining = Number(event.timestamp) - now;
                    return ['UP', 'PREMATCH', 'UPCOMING'].includes(event.status)
                        && Number.isFinite(remaining) && remaining >= 0 && remaining <= activeStartSeconds;
                }

                function applyStartFilter() {
                    const now = serverNow();
                    const visibleIds = new Set(events.filter(event => matchesStartWindow(event, now)).map(event => String(event.id)));
                    let countryRow = null;
                    let countryVisible = false;
                    for (const row of eventsBody.querySelectorAll('tr')) {
                        if (row.hasAttribute('data-market-country')) {
                            if (countryRow) countryRow.hidden = !countryVisible;
                            countryRow = row; countryVisible = false;
                        } else if (row.dataset.id !== undefined) {
                            const visible = visibleIds.has(row.dataset.id);
                            if (row.hidden === visible) row.hidden = !visible;
                            countryVisible ||= visible;
                        }
                    }
                    if (countryRow) countryRow.hidden = !countryVisible;
                    let empty = eventsBody.querySelector('[data-market-empty]');
                    if (!visibleIds.size && !empty) {
                        empty = document.createElement('tr');
                        empty.dataset.marketEmpty = '';
                        empty.innerHTML = @json('<td colspan="14" class="py-10 text-center">'.__('No data available.').'</td>');
                        eventsBody.append(empty);
                    }
                    if (empty) empty.hidden = visibleIds.size > 0;
                }

                document.getElementById('timeToStart').addEventListener('click', event => {
                    const button = event.target.closest('[data-start-seconds]');
                    if (!button) return;
                    activeStartSeconds = Number(button.dataset.startSeconds);
                    document.querySelectorAll('[data-start-seconds]').forEach(item => {
                        item.classList.toggle('active', item === button);
                        item.setAttribute('aria-pressed', String(item === button));
                    });
                    applyStartFilter();
                    scheduleAvailability();
                });

                let marketLoading = false;
                let marketHasMore = marketLazyLoader?.dataset.hasMore === 'true';
                let marketNextPage = Number(marketLazyLoader?.dataset.nextPage || 2);
                let marketLoadedPages = 1;
                const compactMarketActivity = @json($marketActivitySport !== 'greyhounds');
                let marketRowVersions = @json((object) ($marketRowVersions ?? []));
                let marketRequest = null;
                let marketObserver = null;
                let marketLoadPending = false;

                function updateMarketLoader(hasMore, nextPage) {
                    const previousPage = marketNextPage;
                    marketNextPage = Number(nextPage);
                    marketHasMore = Boolean(hasMore) && Number.isSafeInteger(marketNextPage) && marketNextPage > 1;
                    if (!marketLazyLoader) return;
                    marketLazyLoader.classList.toggle('hidden', !marketHasMore);
                    marketLazyLoader.dataset.hasMore = marketHasMore ? 'true' : 'false';
                    marketLazyLoader.hidden = !marketHasMore;
                    marketLazyLoader.dataset.nextPage = marketHasMore ? String(marketNextPage) : '';
                    if (marketObserver && previousPage !== marketNextPage) {
                        marketObserver.unobserve(marketLazyLoader);
                        if (marketHasMore) marketObserver.observe(marketLazyLoader);
                    }
                    marketLazyLoader.querySelector('[data-market-loader-text]').textContent = (globalThis.bfTranslations?.["↓ Scroll for more matches ↓"] ?? "↓ Scroll for more matches ↓");
                }

                let racingFeedMonitor = null;
                function receiveMarketRows(payload, append = false, flashChanges = false) {
                    racingFeedMonitor?.update(payload.feed);
                    const template = document.createElement('template');
                    template.innerHTML = `<table><tbody>${payload.html}</tbody></table>`;
                    const body = template.content.querySelector('tbody');
                    const serverTime = body.querySelector('[data-market-server-time]');
                    const embeddedEvents = body.querySelector('[data-market-events]');
                    const embeddedOrder = body.querySelector('[data-market-order]');
                    const embeddedVersions = body.querySelector('[data-market-versions]');
                    const order = embeddedOrder ? JSON.parse(embeddedOrder.textContent) : payload.order;
                    const versions = embeddedVersions ? JSON.parse(embeddedVersions.textContent) : payload.versions;
                    embeddedOrder?.remove();
                    embeddedVersions?.remove();
                    serverClockOffset = Number(payload.server_time ?? serverTime?.textContent) * 1000 - Date.now();
                    const data = embeddedEvents ? JSON.parse(embeddedEvents.textContent) : payload.data;
                    serverTime?.remove();
                    embeddedEvents?.remove();
                    if (versions) {
                        marketRowVersions = append ? { ...marketRowVersions, ...versions } : versions;
                    } else if (!append) {
                        marketRowVersions = {};
                    }
                    if (append) {
                        eventsBody.querySelector('[data-market-empty]')?.remove();
                        const firstCountry = body.querySelector('tr[data-market-country]');
                        const lastCountry = [...eventsBody.querySelectorAll('[data-market-country]')].at(-1);
                        if (firstCountry && firstCountry.dataset.marketCountry === lastCountry?.dataset.marketCountry) firstCountry.remove();
                        eventsBody.append(...body.children);
                        events.push(...data);
                    } else {
                        replaceMarketActivityRows(data, body, flashChanges, order);
                    }
                    applyStartFilter();
                    scheduleAvailability();
                }

                function replaceMarketActivityRows(data, body, flashChanges, order) {
                    const selectedEventId = currentEventId;
                    const detailWasOpen = detailPanel.classList.contains('open');
                    const detailWasExpanded = terminal.classList.contains('panel-expanded');

                    const previousEvents = new Map(events.map(event => [String(event.id), event]));
                    const previousRows = new Map(Array.from(eventsBody.querySelectorAll('tr[data-id]'), row => [row.dataset.id, row]));
                    const incomingEvents = new Map(data.map(event => [String(event.id), event]));
                    for (const incoming of body.querySelectorAll('tr[data-id]')) {
                        const row = previousRows.get(incoming.dataset.id);
                        if (!row) continue;
                        const previous = previousEvents.get(incoming.dataset.id);
                        const next = incomingEvents.get(incoming.dataset.id);
                        const sameSelection = previous && next && !previous.locked && !next.locked
                            && previous.runners[previous.favIdx]?.name === next.runners[next.favIdx]?.name;
                        Array.from(incoming.cells).forEach((cell, index) => {
                            const existing = row.cells[index];
                            if (!existing) return;
                            const changed = existing.textContent.trim().replace(/\s+/g, ' ') !== cell.textContent.trim().replace(/\s+/g, ' ');
                            const target = existing.querySelector('.odds-cell, .size-cell') || existing;
                            if (existing.innerHTML !== cell.innerHTML && (changed || !target.classList.contains('flash-yellow') && !target.classList.contains('flash-yellow-alt'))) {
                                existing.replaceChildren(...cell.childNodes);
                            }
                            if (flashChanges && sameSelection && changed && index >= 5 && index <= 12) {
                                const value = existing.querySelector('.odds-cell, .size-cell') || existing;
                                const alternate = value.classList.contains('flash-yellow');
                                value.classList.remove('flash-yellow', 'flash-yellow-alt');
                                value.classList.add(alternate ? 'flash-yellow-alt' : 'flash-yellow');
                            }
                        });
                        const selected = row.classList.contains('selected');
                        const detailsExpanded = row.classList.contains('analytics-expanded');
                        row.className = incoming.className;
                        row.classList.toggle('selected', selected);
                        row.classList.toggle('analytics-expanded', detailsExpanded);
                        row.querySelector('[data-market-details]')?.setAttribute('aria-expanded', String(detailsExpanded));
                        if (incoming.hasAttribute('aria-disabled')) row.setAttribute('aria-disabled', incoming.getAttribute('aria-disabled'));
                        else row.removeAttribute('aria-disabled');
                        @if ($marketActivitySport !== 'football')
                        incoming.replaceWith(row);
                        @endif
                    }
                    if (order) {
                        data = order.map(id => incomingEvents.get(String(id)) ?? previousEvents.get(String(id)));
                    }
                    events.splice(0, events.length, ...data);
                    const scrollLeft = document.getElementById('eventsWrap').scrollLeft;
                    const incomingRows = Array.from(body.children, incoming => previousRows.get(incoming.dataset.id) ?? incoming);
                    const rowsById = order?.length ? new Map(incomingRows.map(row => [row.dataset.id, row])) : null;
                    const rows = rowsById ? order.map(id => rowsById.get(String(id)) ?? previousRows.get(String(id))) : incomingRows;
                    rows.forEach((row, index) => {
                        if (eventsBody.children[index] !== row) eventsBody.insertBefore(row, eventsBody.children[index] ?? null);
                    });
                    Array.from(eventsBody.children).slice(rows.length).forEach(row => row.remove());
                    document.getElementById('eventsWrap').scrollLeft = scrollLeft;

                    if (detailWasOpen && events.some(function(ev) { return String(ev.id) === String(selectedEventId) && !ev.locked; })) {
                        const scrollContainer = detailWasExpanded ? detailContent : detailPanel;
                        const scrollTop = scrollContainer.scrollTop;
                        const termsOpen = !document.getElementById('termsSection')?.classList.contains('collapsed');
                        if (!order || (incomingEvents.has(String(selectedEventId)) && JSON.stringify(previousEvents.get(String(selectedEventId))) !== JSON.stringify(incomingEvents.get(String(selectedEventId))))) openDetail(selectedEventId);
                        if (detailWasExpanded && !terminal.classList.contains('panel-expanded')) toggleExpand();
                        if (termsOpen) document.getElementById('termsSection')?.classList.remove('collapsed');
                        scrollContainer.scrollTop = scrollTop;
                    } else if (!detailWasOpen || @json($marketActivitySport !== 'football') || incomingEvents.get(String(selectedEventId))?.locked) {
                        currentEventId = null;
                        detailPanel.classList.remove('open');
                        terminal.classList.remove('with-panel', 'panel-expanded');
                        if (!document.querySelector('dialog[open], #subscriptionModal, [aria-modal="true"]:not(.hidden), .panel-expanded')) {
                            document.body.classList.remove('!overflow-hidden');
                            document.documentElement.classList.remove('!overflow-hidden');
                        }
                        detailContent.style.display = 'none';
                        emptyPanel.style.display = 'flex';
                    }
                }

                function marketActivityUrl(page, perPage = 20) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('page', page);
                    url.searchParams.set('per_page', perPage);
                    url.searchParams.set('type', activeFootballFilter);
                    url.searchParams.set('market_name', activeMarketFilter);
                    if (compactMarketActivity) {
                        url.searchParams.set('_market_rows', '1');
                        url.searchParams.delete('_market_versions');
                    }

                    return url;
                }

                let refreshPending = false;
                let resetPending = false;
                async function reloadMarketActivity(reset = false, flashChanges = false) {
                    if (marketLoading) {
                        refreshPending = true;
                        resetPending ||= reset;
                        if (reset) marketRequest?.abort();
                        return;
                    }
                    if (document.hidden) { refreshPending = true; resetPending ||= reset; return; }

                    marketLoading = 'refresh';
                    try {
                        const loadedRowCount = reset ? 20 : Math.max(20, marketLoadedPages * 20);
                        const url = marketActivityUrl(1, loadedRowCount);
                        if (compactMarketActivity && !reset && Object.keys(marketRowVersions).length <= 100) {
                            url.searchParams.set('_market_versions', JSON.stringify(marketRowVersions));
                        }
                        marketRequest = new AbortController();
                        const response = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            signal: marketRequest?.signal,
                        });
                        if (!response.ok) throw new Error(`HTTP error: ${response.status}`);

                        const payload = await response.json();
                        if (marketRequest?.signal.aborted) return;
                        receiveMarketRows(payload, false, !reset && flashChanges);
                        if (reset) marketLoadedPages = 1;
                        updateMarketLoader(payload.has_more, marketLoadedPages + 1);
                    } catch (error) {
                        if (error.name === 'AbortError') return;
                        racingFeedMonitor?.failed();
                        console.error((globalThis.bfTranslations?.["Market activity filter request failed:"] ?? "Market activity filter request failed:"), error);
                    } finally {
                        marketRequest = null;
                        marketLoading = false;
                        const pendingBounds = marketLoadPending && marketHasMore && !resetPending && !document.hidden
                            ? marketLazyLoader.getBoundingClientRect() : null;
                        marketLoadPending = false;
                        if (!resetPending && pendingBounds && pendingBounds.top <= window.innerHeight + 200 && pendingBounds.bottom >= -200) {
                            loadMoreMarketActivity();
                        } else if (refreshPending && !document.hidden) {
                            const reset = resetPending; refreshPending = false; resetPending = false;
                            reloadMarketActivity(reset);
                        }
                    }
                }

                async function loadMoreMarketActivity() {
                    if (!marketHasMore) return;
                    if (marketLoading) {
                        if (marketLoading === 'refresh') marketLoadPending = true;
                        return;
                    }

                    marketLoading = 'append';
                    marketLazyLoader.querySelector('[data-market-loader-text]').textContent = (globalThis.bfTranslations?.["Loading matches…"] ?? "Loading matches…");

                    try {
                        marketRequest = new AbortController();
                        const response = await fetch(marketActivityUrl(marketNextPage), {
                            headers: { 'Accept': 'application/json' },
                            signal: marketRequest?.signal,
                        });
                        if (!response.ok) throw new Error(`HTTP error: ${response.status}`);

                        const payload = await response.json();
                        if (marketRequest?.signal.aborted) return;
                        receiveMarketRows(payload, true);
                        marketLoadedPages++;
                        updateMarketLoader(payload.has_more, payload.next_page);
                    } catch (error) {
                        if (error.name === 'AbortError') return;
                        console.error((globalThis.bfTranslations?.["Market activity request failed:"] ?? "Market activity request failed:"), error);
                        updateMarketLoader(marketHasMore, marketNextPage);
                    } finally {
                        marketRequest = null;
                        marketLoading = false;
                        if (refreshPending && !document.hidden) {
                            const reset = resetPending; refreshPending = false; resetPending = false;
                            reloadMarketActivity(reset);
                        }
                    }
                }

                document.querySelectorAll('.filter-pill').forEach(function(pill) {
                    pill.addEventListener('click', function() {
                        document.querySelectorAll('.filter-pill').forEach(function(item) {
                            item.classList.remove('active');
                        });
                        pill.classList.add('active');
                        activeFootballFilter = pill.dataset.filter || 'all';
                        reloadMarketActivity(true);
                    });
                });

                marketSelect?.addEventListener('change', function() {
                    activeMarketFilter = marketSelect.value;
                    reloadMarketActivity(true);
                });

                if (marketLazyLoader) {
                    marketObserver = new IntersectionObserver(function(entries) {
                        if (entries[0]?.isIntersecting) loadMoreMarketActivity();
                    }, { rootMargin: '200px 0px' });
                    marketObserver.observe(marketLazyLoader);
                    marketLazyLoader.querySelector('[data-market-loader-text]').addEventListener('click', loadMoreMarketActivity);
                }

                eventsBody.addEventListener('animationend', event => {
                    if (event.animationName === 'marketYellowFlash' || event.animationName === 'marketYellowFlashAlternate') {
                        event.target.classList.remove('flash-yellow', 'flash-yellow-alt');
                    }
                });
                let receivedLiveSnapshot = false;
                function refreshLiveMarketActivity() {
                    const flashChanges = receivedLiveSnapshot;
                    receivedLiveSnapshot = true;
                    reloadMarketActivity(false, flashChanges);
                }
                let marketRealtimeRefreshTimer = null;
                document.addEventListener('DOMContentLoaded', function() {
                    @if ($raceActivity)
                    racingFeedMonitor = window.watchRacingFeed(document.getElementById('eventsWrap').parentElement, () => {
                        if (marketRealtimeRefreshTimer !== null) return;
                        marketRealtimeRefreshTimer = window.setTimeout(() => { marketRealtimeRefreshTimer = null; refreshLiveMarketActivity(); }, 100);
                    }, @json($marketActivitySport));
                    return;
                    @endif
                    if (!window.Echo) return;

                    window.Echo.channel(@json('sports.'.$marketActivitySport.'.live'))
                        .listen('.score.updated', function() {
                            if (marketRealtimeRefreshTimer !== null) return;

                            marketRealtimeRefreshTimer = window.setTimeout(function() {
                                marketRealtimeRefreshTimer = null;
                                refreshLiveMarketActivity();
                            }, SIM.tickInterval);
                        });
                }, { once: true });

                let availabilityTimer = null;
                function scheduleAvailability() {
                    window.clearTimeout(availabilityTimer);
                    const now = serverNow();
                    const boundaries = [];
                    for (const event of events) {
                        if (event.unlockAt > now) boundaries.push({ at: event.unlockAt, reload: true });
                        if (!event.stale && event.lastMarketUpdate && event.freshUntil > now) boundaries.push({ at: event.freshUntil, reload: false });
                        if (activeStartSeconds && ['UP', 'PREMATCH', 'UPCOMING'].includes(event.status) && Number(event.timestamp) > now) {
                            const entry = Number(event.timestamp) - activeStartSeconds;
                            boundaries.push({ at: entry > now ? entry : Number(event.timestamp), reload: false });
                        }
                    }
                    const next = boundaries.reduce((first, boundary) => !first || boundary.at < first.at ? boundary : first, null);
                    if (next) availabilityTimer = window.setTimeout(() => {
                        applyStartFilter();
                        const staleRows = new Map(Array.from(eventsBody.querySelectorAll('tr[data-id]'), row => [row.dataset.id, row]));
                        for (const event of events) {
                            if (event.lastMarketUpdate && serverNow() >= event.freshUntil) {
                                event.stale = true;
                                event.balance = 'unknown';
                                event.momentum = 'unknown';
                                event.delta = '—';
                                const row = staleRows.get(String(event.id));
                                for (const index of [9, 10, 11]) {
                                    const indicator = row?.cells[index]?.querySelector('.ind');
                                    if (indicator) { indicator.textContent = '—'; indicator.classList.remove('ind-back-heavy', 'ind-lay-heavy', 'ind-mom-strong-back', 'ind-mom-back', 'ind-mom-strong-lay', 'ind-mom-lay', 'ind-delta-pos', 'ind-delta-neg'); }
                                }
                            }
                        }
                        if (currentEventId !== null && detailPanel.classList.contains('open')) {
                            const expanded = terminal.classList.contains('panel-expanded');
                            const scrollContainer = expanded ? detailContent : detailPanel;
                            const scrollTop = scrollContainer.scrollTop;
                            openDetail(currentEventId);
                            if (expanded && !terminal.classList.contains('panel-expanded')) toggleExpand();
                            scrollContainer.scrollTop = scrollTop;
                        }
                        if (next.reload) reloadMarketActivity();
                        scheduleAvailability();
                    }, Math.min(2147483647, Math.max(1, Math.ceil((next.at - now) * 1000) + 100)));

                }
                scheduleAvailability();
                document.addEventListener('visibilitychange', () => { if (!document.hidden) reloadMarketActivity(); });
                document.getElementById('speedBar').addEventListener('click', event => {
                    const button = event.target.closest('[data-speed]');
                    if (!button) return;
                    SIM.tickInterval = Number(button.dataset.speed);
                    SIM.isProMode = SIM.tickInterval === 140;
                    document.querySelectorAll('#speedBar [data-speed]').forEach(item => item.classList.toggle('active', item === button));
                });

                eventsBody.addEventListener('click', function(e) {
                    const detailsToggle = e.target.closest('[data-market-details]');
                    if (detailsToggle) {
                        const row = detailsToggle.closest('tr[data-id]');
                        const expanded = row.classList.toggle('analytics-expanded');
                        detailsToggle.setAttribute('aria-expanded', String(expanded));
                        return;
                    }
                    if (!displayOdds) return;

                    const row = e.target.closest('tr[data-id]');
                    if (row && eventsBody.contains(row)) openDetail(row.dataset.id);
                });

                function openDetail(id) {
                    if (!displayOdds) return;

                    const ev = events.find(function(e) {
                        return String(e.id) === String(id);
                    });
                    if (!ev || ev.locked) return;
                    const updating = @json($marketActivitySport === 'football') && String(currentEventId) === String(id) && detailPanel.classList.contains('open');
                    currentEventId = id;

                    document.querySelectorAll('.events-table tbody tr').forEach(function(r) {
                        r.classList.toggle('selected', String(r.dataset.id) === String(id));
                    });

                    const backPct = Number.isFinite(ev.backPercent) ? ev.backPercent : null;
                    const layPct = Number.isFinite(ev.layPercent) ? ev.layPercent : null;
                    const stale = ev.stale || !ev.lastMarketUpdate || serverNow() >= ev.freshUntil;
                    const deltaWindows = [60, 300, 900].map(seconds => `${seconds / 60}m: ${!stale && ev.deltaWindows?.[seconds] != null ? (ev.deltaWindows[seconds] > 0 ? '+' : '') + Number(ev.deltaWindows[seconds]).toFixed(1) + ' pp' : '—'}`).join(' · ');

                    let runnersHtml = '';
                    ev.runners.forEach(function(r, idx) {
                        const isFav = idx === ev.favIdx;
                        runnersHtml += `
                    <div class="runner-row grid [grid-template-columns:1fr_auto_auto] [gap:10px] items-center [padding:10px_12px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:10px] [font-size:12.5px] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))]">
                        <div class="runner-name font-bold [color:rgb(var(--text-main))] flex items-center [gap:5px] dark:[color:var(--text-on-primary)] [&_.fav-star]:[color:var(--text-warning)] [&_.fav-star]:[font-size:13px]">${escapeHtml(r.name)}${isFav ? ' <span class="fav-star [color:var(--text-brand-15-2)] [font-size:15px] cursor-pointer [background:none] border-0 [width:24px] [transition:0.2s] text-center dark:[color:rgb(var(--primary))] dark:[&.active-fav]:[color:rgb(var(--warning))] [&.active-fav]:[color:var(--text-warning-5-2)]">⭐</span>' : ''}</div>
                        <div class="runner-back [background:var(--surface-back)] [color:rgb(var(--text-main))] [border:1px_solid_var(--border-brand-15-3)] [border-radius:4px] [padding:5px_10px] font-extrabold text-center [min-width:70px] [font-variant-numeric:tabular-nums]">@if ($checkAuthAndSubscription['display_odds'])${r.back.toFixed(2)}@else @include('lock_icon') @endif<span class="runner-size [font-size:10px] [opacity:0.75] font-semibold block [margin-top:2px]">@if ($checkAuthAndSubscription['display_odds'])${fmtSize(r.backSize)}@else @include('lock_icon') @endif</span></div>
                        <div class="runner-lay [background:var(--surface-lay)] [color:var(--text-danger-70)] [border:1px_solid_var(--border-danger-10-2)] [border-radius:4px] [padding:5px_10px] font-extrabold text-center [min-width:70px] [font-variant-numeric:tabular-nums]">@if ($checkAuthAndSubscription['display_odds'])${r.lay.toFixed(2)}@else @include('lock_icon') @endif<span class="runner-size [font-size:10px] [opacity:0.75] font-semibold block [margin-top:2px]">@if ($checkAuthAndSubscription['display_odds'])${fmtSize(r.laySize)}@else @include('lock_icon') @endif</span></div>
                    </div>
                `;
                    });

                    let scoreHtml = ev.score ? `<span>· <span class="match-score match-score-on-dark" data-score-status="${escapeHtml(ev.status)}">${escapeHtml(ev.score)}</span></span>` : '';
                    let metaHtml =
                        `${escapeHtml(ev.sportIcon)} ${escapeHtml(ev.comp)} · ${ev.status === 'LIVE' ? (globalThis.bfTranslations?.["● LIVE"] ?? "● LIVE") : ev.status === 'FINISHED' ? 'FT' : (globalThis.bfTranslations?.["▲ UPCOMING"] ?? "▲ UPCOMING")} · ${escapeHtml(ev.time)} ${scoreHtml}`;

                    const detailHtml = `
                        <div class="detail-header [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white [padding:10px_16px] [position:sticky] [top:0] [z-index:10] max-[480px]:[padding:8px_12px]">
                            <div class="detail-header-top flex items-center justify-between [gap:10px]">
                                <div class="detail-title [font-size:14.5px] font-extrabold [flex:1] min-w-0 whitespace-nowrap overflow-hidden [text-overflow:ellipsis] max-[480px]:[font-size:13px]">${escapeHtml(ev.match)}</div>
                                <div class="detail-header-actions flex [gap:6px] shrink-0">
                                    <button class="detail-action-btn [background:var(--surface-neutral-0-23)] border-0 text-white [width:28px] [height:28px] [border-radius:50%] [font-size:13px] cursor-pointer flex items-center justify-center [transition:background_0.2s,_transform_0.2s] [font-family:inherit] [&:hover]:[background:var(--surface-neutral-0-24)] [&:hover]:[transform:scale(1.08)] [&.expand-active]:[background:var(--surface-neutral-0-25)]" id="expandBtn" title="Expand"><i class="fas fa-expand"></i></button>
                                    <button class="detail-action-btn [background:var(--surface-neutral-0-23)] border-0 text-white [width:28px] [height:28px] [border-radius:50%] [font-size:13px] cursor-pointer flex items-center justify-center [transition:background_0.2s,_transform_0.2s] [font-family:inherit] [&:hover]:[background:var(--surface-neutral-0-24)] [&:hover]:[transform:scale(1.08)] [&.expand-active]:[background:var(--surface-neutral-0-25)]" onclick="closeDetail()" title="${(globalThis.bfTranslations?.["Close"] ?? "Close")}"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            <div class="detail-meta [font-size:11.5px] [opacity:0.9] flex items-center [gap:8px] flex-wrap [margin-top:2px] max-[480px]:[font-size:10.5px]">${metaHtml}</div>
                        </div>
                        <div class="detail-body [padding:16px_20px] max-[480px]:[padding:14px_16px]">
                            <p class="market-book-status mb-3 text-xs text-[rgb(var(--text-muted))]">${escapeHtml(@json(__('Unmatched interest at the best available prices; not matched trading volume.')))} ${stale ? escapeHtml(@json(__('Stale — showing last received values.'))) : ''}${ev.lastMarketUpdate ? ' ' + escapeHtml(@json(__('Last update:'))) + ' ' + new Date(ev.lastMarketUpdate * 1000).toLocaleTimeString() : ''}</p>
                            <div class="detail-section [margin-bottom:20px] [&:last-child]:[margin-bottom:0]">
                                <div class="detail-section-title [font-size:11px] font-extrabold uppercase [letter-spacing:0.6px] [color:rgb(var(--primary))] [margin-bottom:10px] flex items-center [gap:6px] dark:[color:rgb(var(--primary))]"><i class="fas fa-users"></i> Runners</div>
                                <div class="runners-list flex flex-col [gap:8px]">${runnersHtml}</div>
                            </div>
                            <div class="detail-section [margin-bottom:20px] [&:last-child]:[margin-bottom:0]">
                                <div class="detail-section-title [font-size:11px] font-extrabold uppercase [letter-spacing:0.6px] [color:rgb(var(--primary))] [margin-bottom:10px] flex items-center [gap:6px] dark:[color:rgb(var(--primary))]"><i class="fas fa-water"></i> ${(globalThis.bfTranslations?.["Liquidity Distribution"] ?? "Liquidity Distribution")}</div>
                                <div class="liquidity-bar relative flex h-[26px] overflow-hidden rounded-lg text-[11px] font-bold mt-1.5" role="img" aria-label="Back ${(backPct === null ? '—' : backPct.toFixed(0) + '%')}, Lay ${(layPct === null ? '—' : layPct.toFixed(0) + '%')}">
                                    <div class="liq-back min-w-0 shrink-0 [background:linear-gradient(135deg,_var(--surface-primary),_var(--surface-brand-30))] [transition:width_0.6s_ease]" style="width:${backPct ?? 0}%;" aria-hidden="true"></div>
                                    <div class="liq-lay min-w-0 shrink-0 [background:linear-gradient(135deg,_var(--surface-lay-2),_var(--surface-accent-10))] [transition:width_0.6s_ease]" style="width:${layPct ?? 0}%;" aria-hidden="true"></div>
                                    <div class="pointer-events-none absolute inset-0 flex items-center justify-between gap-2 px-2.5 text-white whitespace-nowrap leading-none tabular-nums" aria-hidden="true"><span>${(globalThis.bfTranslations?.["Back"] ?? "Back")} ${(backPct === null ? '—' : backPct.toFixed(0) + '%')}</span><span>${(globalThis.bfTranslations?.["Lay"] ?? "Lay")} ${(layPct === null ? '—' : layPct.toFixed(0) + '%')}</span></div>
                                </div>
                            </div>
                            <div class="detail-section [margin-bottom:20px] [&:last-child]:[margin-bottom:0]">
                                <div class="detail-section-title [font-size:11px] font-extrabold uppercase [letter-spacing:0.6px] [color:rgb(var(--primary))] [margin-bottom:10px] flex items-center [gap:6px] dark:[color:rgb(var(--primary))]"><i class="fas fa-chart-line"></i> ${(globalThis.bfTranslations?.["Indicators"] ?? "Indicators")}</div>
                                <div class="indicators-grid grid [grid-template-columns:repeat(3,_1fr)] [gap:8px] max-[480px]:[grid-template-columns:repeat(3,_1fr)] max-[480px]:[gap:6px]">
                                    <div class="indicator-box bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:10px] [padding:10px_8px] text-center [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.ind-label]:[font-size:9.5px] [&_.ind-label]:[font-weight:800] [&_.ind-label]:[text-transform:uppercase] [&_.ind-label]:[letter-spacing:0.5px] [&_.ind-label]:[color:var(--text-muted-soft)] [&_.ind-label]:[margin-bottom:4px] [&_.ind-value]:[font-size:13px] [&_.ind-value]:[font-weight:800] [&_.ind-value]:[color:rgb(var(--text-main))] dark:[&_.ind-value]:[color:var(--text-on-primary)] [&_.ind-value.back-heavy]:[color:rgb(var(--primary))] dark:[&_.ind-value.back-heavy]:[color:rgb(var(--primary))] [&_.ind-value.lay-heavy]:[color:var(--text-lay)] dark:[&_.ind-value.lay-heavy]:[color:var(--text-accent)] [&_.ind-value.balanced]:[color:var(--text-brand-35)] dark:[&_.ind-value.balanced]:[color:rgb(var(--text-soft))] [&_.ind-value.vol-low]:[color:var(--text-success)] dark:[&_.ind-value.vol-low]:[color:rgb(var(--success))] [&_.ind-value.vol-high]:[color:var(--text-danger)] dark:[&_.ind-value.vol-high]:[color:rgb(var(--danger))] max-[480px]:[padding:8px_6px] max-[480px]:[&_.ind-value]:[font-size:12px]"><div class="ind-label">${(globalThis.bfTranslations?.["Balance"] ?? "Balance")}</div><div class="ind-value ${ev.balance === 'back' ? 'back-heavy' : (ev.balance === 'lay' ? 'lay-heavy' : 'balanced')}">${balanceText(stale ? 'unknown' : ev.balance)}</div></div>
                                    <div class="indicator-box bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:10px] [padding:10px_8px] text-center [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.ind-label]:[font-size:9.5px] [&_.ind-label]:[font-weight:800] [&_.ind-label]:[text-transform:uppercase] [&_.ind-label]:[letter-spacing:0.5px] [&_.ind-label]:[color:var(--text-muted-soft)] [&_.ind-label]:[margin-bottom:4px] [&_.ind-value]:[font-size:13px] [&_.ind-value]:[font-weight:800] [&_.ind-value]:[color:rgb(var(--text-main))] dark:[&_.ind-value]:[color:var(--text-on-primary)] [&_.ind-value.back-heavy]:[color:rgb(var(--primary))] dark:[&_.ind-value.back-heavy]:[color:rgb(var(--primary))] [&_.ind-value.lay-heavy]:[color:var(--text-lay)] dark:[&_.ind-value.lay-heavy]:[color:var(--text-accent)] [&_.ind-value.balanced]:[color:var(--text-brand-35)] dark:[&_.ind-value.balanced]:[color:rgb(var(--text-soft))] [&_.ind-value.vol-low]:[color:var(--text-success)] dark:[&_.ind-value.vol-low]:[color:rgb(var(--success))] [&_.ind-value.vol-high]:[color:var(--text-danger)] dark:[&_.ind-value.vol-high]:[color:rgb(var(--danger))] max-[480px]:[padding:8px_6px] max-[480px]:[&_.ind-value]:[font-size:12px]"><div class="ind-label">${(globalThis.bfTranslations?.["Momentum"] ?? "Momentum")} · 1m</div><div class="ind-value ${ev.momentum === 'back' || ev.momentum === 'strong_back' ? 'back-heavy' : (ev.momentum === 'lay' || ev.momentum === 'strong_lay' ? 'lay-heavy' : 'balanced')}">${momentumText(stale ? 'unknown' : ev.momentum)}</div></div>
                                    <div class="indicator-box bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:10px] [padding:10px_8px] text-center [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.ind-label]:[font-size:9.5px] [&_.ind-label]:[font-weight:800] [&_.ind-label]:[text-transform:uppercase] [&_.ind-label]:[letter-spacing:0.5px] [&_.ind-label]:[color:var(--text-muted-soft)] [&_.ind-label]:[margin-bottom:4px] [&_.ind-value]:[font-size:13px] [&_.ind-value]:[font-weight:800] [&_.ind-value]:[color:rgb(var(--text-main))] dark:[&_.ind-value]:[color:var(--text-on-primary)] [&_.ind-value.back-heavy]:[color:rgb(var(--primary))] dark:[&_.ind-value.back-heavy]:[color:rgb(var(--primary))] [&_.ind-value.lay-heavy]:[color:var(--text-lay)] dark:[&_.ind-value.lay-heavy]:[color:var(--text-accent)] [&_.ind-value.balanced]:[color:var(--text-brand-35)] dark:[&_.ind-value.balanced]:[color:rgb(var(--text-soft))] [&_.ind-value.vol-low]:[color:var(--text-success)] dark:[&_.ind-value.vol-low]:[color:rgb(var(--success))] [&_.ind-value.vol-high]:[color:var(--text-danger)] dark:[&_.ind-value.vol-high]:[color:rgb(var(--danger))] max-[480px]:[padding:8px_6px] max-[480px]:[&_.ind-value]:[font-size:12px]"><div class="ind-label" title="${escapeHtml(deltaWindows)}">${(globalThis.bfTranslations?.["Delta"] ?? "Delta")} · 5m</div><div class="ind-value ${ev.delta.startsWith('+') ? 'vol-low' : (ev.delta.startsWith('-') ? 'vol-high' : 'balanced')}">${stale ? '—' : ev.delta}</div></div>
                                    <div class="indicator-box bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:10px] [padding:10px_8px] text-center [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.ind-label]:[font-size:9.5px] [&_.ind-label]:[font-weight:800] [&_.ind-label]:[text-transform:uppercase] [&_.ind-label]:[letter-spacing:0.5px] [&_.ind-label]:[color:var(--text-muted-soft)] [&_.ind-label]:[margin-bottom:4px] [&_.ind-value]:[font-size:13px] [&_.ind-value]:[font-weight:800] [&_.ind-value]:[color:rgb(var(--text-main))] dark:[&_.ind-value]:[color:var(--text-on-primary)] [&_.ind-value.back-heavy]:[color:rgb(var(--primary))] dark:[&_.ind-value.back-heavy]:[color:rgb(var(--primary))] [&_.ind-value.lay-heavy]:[color:var(--text-lay)] dark:[&_.ind-value.lay-heavy]:[color:var(--text-accent)] [&_.ind-value.balanced]:[color:var(--text-brand-35)] dark:[&_.ind-value.balanced]:[color:rgb(var(--text-soft))] [&_.ind-value.vol-low]:[color:var(--text-success)] dark:[&_.ind-value.vol-low]:[color:rgb(var(--success))] [&_.ind-value.vol-high]:[color:var(--text-danger)] dark:[&_.ind-value.vol-high]:[color:rgb(var(--danger))] max-[480px]:[padding:8px_6px] max-[480px]:[&_.ind-value]:[font-size:12px]"><div class="ind-label">${(globalThis.bfTranslations?.["Volatility"] ?? "Volatility")}</div><div class="ind-value vol-${ev.volatility === 'low' ? 'low' : (ev.volatility === 'high' ? 'high' : 'med')}">${volText(ev.volatility)}</div></div>
                                </div>
                            </div>
                            <div class="terms-section collapsed [margin-top:20px] [padding-top:16px] [border-top:1px_dashed_var(--border-control)] [transition:border-color_0.3s] dark:[border-top-color:rgb(var(--border))] [&.collapsed_.terms-toggle-icon]:[transform:rotate(-90deg)] [&.collapsed_.terms-list]:[display:none] [body&_.collapse-toggle_i]:[transform:rotate(180deg)] [body&_.controls-wrapper]:[max-height:0] [body&_.controls-wrapper]:[opacity:0]" id="termsSection">
                                <div class="terms-header flex items-center justify-between cursor-pointer select-none [padding:6px_10px] [border-radius:8px] [background:var(--surface-brand-40)] [transition:background_0.2s] dark:[background:var(--button-primary)] [&:hover]:[background:var(--surface-brand-40-2)] dark:[&:hover]:[background:var(--button-primary)]" id="termsToggle">
                                    <div class="terms-header-left flex items-center [gap:8px] [font-size:11px] font-extrabold uppercase [letter-spacing:0.6px] [color:rgb(var(--primary))] dark:[color:rgb(var(--primary))] [&_i]:[font-size:12px]"><i class="fas fa-book-open"></i><span>Terms</span></div>
                                    <i class="fas fa-chevron-down terms-toggle-icon [font-size:12px] [color:var(--text-muted-soft)] [transition:transform_0.3s] dark:[color:rgb(var(--primary))]"></i>
                                </div>
                                <div class="terms-list flex flex-col [gap:5px] [margin-top:10px] [animation:fadeInTerms_0.25s_ease]">
                                    <div class="term-row flex [align-items:baseline] [gap:8px] [padding:5px_10px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:6px] [font-size:11.5px] [line-height:1.4] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.term-key]:[font-weight:800] [&_.term-key]:[color:rgb(var(--text-main))] [&_.term-key]:[white-space:nowrap] [&_.term-key]:[min-width:78px] [&_.term-key]:[flex-shrink:0] dark:[&_.term-key]:[color:var(--text-on-primary)] [&_.term-key.term-back]:[color:rgb(var(--primary))] dark:[&_.term-key.term-back]:[color:rgb(var(--primary))] [&_.term-key.term-lay]:[color:var(--text-lay)] dark:[&_.term-key.term-lay]:[color:rgb(var(--text-main))] [&_.term-desc]:[color:rgb(var(--text-muted))] [&_.term-desc]:[font-weight:500] dark:[&_.term-desc]:[color:rgb(var(--text-soft))] max-[480px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[&_.term-key]:[min-width:70px]"><span class="term-key term-back">${(globalThis.bfTranslations?.["Back"] ?? "Back")}</span><span class="term-desc">${(globalThis.bfTranslations?.["Bet in favor of the event"] ?? "Bet in favor of the event")}</span></div>
                                    <div class="term-row flex [align-items:baseline] [gap:8px] [padding:5px_10px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:6px] [font-size:11.5px] [line-height:1.4] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.term-key]:[font-weight:800] [&_.term-key]:[color:rgb(var(--text-main))] [&_.term-key]:[white-space:nowrap] [&_.term-key]:[min-width:78px] [&_.term-key]:[flex-shrink:0] dark:[&_.term-key]:[color:var(--text-on-primary)] [&_.term-key.term-back]:[color:rgb(var(--primary))] dark:[&_.term-key.term-back]:[color:rgb(var(--primary))] [&_.term-key.term-lay]:[color:var(--text-lay)] dark:[&_.term-key.term-lay]:[color:rgb(var(--text-main))] [&_.term-desc]:[color:rgb(var(--text-muted))] [&_.term-desc]:[font-weight:500] dark:[&_.term-desc]:[color:rgb(var(--text-soft))] max-[480px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[&_.term-key]:[min-width:70px]"><span class="term-key term-lay">${(globalThis.bfTranslations?.["Lay"] ?? "Lay")}</span><span class="term-desc">${(globalThis.bfTranslations?.["Bet against the event"] ?? "Bet against the event")}</span></div>
                                    <div class="term-row flex [align-items:baseline] [gap:8px] [padding:5px_10px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:6px] [font-size:11.5px] [line-height:1.4] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.term-key]:[font-weight:800] [&_.term-key]:[color:rgb(var(--text-main))] [&_.term-key]:[white-space:nowrap] [&_.term-key]:[min-width:78px] [&_.term-key]:[flex-shrink:0] dark:[&_.term-key]:[color:var(--text-on-primary)] [&_.term-key.term-back]:[color:rgb(var(--primary))] dark:[&_.term-key.term-back]:[color:rgb(var(--primary))] [&_.term-key.term-lay]:[color:var(--text-lay)] dark:[&_.term-key.term-lay]:[color:rgb(var(--text-main))] [&_.term-desc]:[color:rgb(var(--text-muted))] [&_.term-desc]:[font-weight:500] dark:[&_.term-desc]:[color:rgb(var(--text-soft))] max-[480px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[&_.term-key]:[min-width:70px]"><span class="term-key">${(globalThis.bfTranslations?.["Odds"] ?? "Odds")}</span><span class="term-desc">${(globalThis.bfTranslations?.["Price"] ?? "Price")}</span></div>
                                    <div class="term-row flex [align-items:baseline] [gap:8px] [padding:5px_10px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:6px] [font-size:11.5px] [line-height:1.4] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.term-key]:[font-weight:800] [&_.term-key]:[color:rgb(var(--text-main))] [&_.term-key]:[white-space:nowrap] [&_.term-key]:[min-width:78px] [&_.term-key]:[flex-shrink:0] dark:[&_.term-key]:[color:var(--text-on-primary)] [&_.term-key.term-back]:[color:rgb(var(--primary))] dark:[&_.term-key.term-back]:[color:rgb(var(--primary))] [&_.term-key.term-lay]:[color:var(--text-lay)] dark:[&_.term-key.term-lay]:[color:rgb(var(--text-main))] [&_.term-desc]:[color:rgb(var(--text-muted))] [&_.term-desc]:[font-weight:500] dark:[&_.term-desc]:[color:rgb(var(--text-soft))] max-[480px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[&_.term-key]:[min-width:70px]"><span class="term-key">${(globalThis.bfTranslations?.["Size"] ?? "Size")}</span><span class="term-desc">${(globalThis.bfTranslations?.["Money available at best price"] ?? "Money available at best price")}</span></div>
                                    <div class="term-row flex [align-items:baseline] [gap:8px] [padding:5px_10px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:6px] [font-size:11.5px] [line-height:1.4] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.term-key]:[font-weight:800] [&_.term-key]:[color:rgb(var(--text-main))] [&_.term-key]:[white-space:nowrap] [&_.term-key]:[min-width:78px] [&_.term-key]:[flex-shrink:0] dark:[&_.term-key]:[color:var(--text-on-primary)] [&_.term-key.term-back]:[color:rgb(var(--primary))] dark:[&_.term-key.term-back]:[color:rgb(var(--primary))] [&_.term-key.term-lay]:[color:var(--text-lay)] dark:[&_.term-key.term-lay]:[color:rgb(var(--text-main))] [&_.term-desc]:[color:rgb(var(--text-muted))] [&_.term-desc]:[font-weight:500] dark:[&_.term-desc]:[color:rgb(var(--text-soft))] max-[480px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[&_.term-key]:[min-width:70px]"><span class="term-key">${(globalThis.bfTranslations?.["Balance"] ?? "Balance")}</span><span class="term-desc">${(globalThis.bfTranslations?.["Where the money is now"] ?? "Where the money is now")}</span></div>
                                    <div class="term-row flex [align-items:baseline] [gap:8px] [padding:5px_10px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:6px] [font-size:11.5px] [line-height:1.4] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.term-key]:[font-weight:800] [&_.term-key]:[color:rgb(var(--text-main))] [&_.term-key]:[white-space:nowrap] [&_.term-key]:[min-width:78px] [&_.term-key]:[flex-shrink:0] dark:[&_.term-key]:[color:var(--text-on-primary)] [&_.term-key.term-back]:[color:rgb(var(--primary))] dark:[&_.term-key.term-back]:[color:rgb(var(--primary))] [&_.term-key.term-lay]:[color:var(--text-lay)] dark:[&_.term-key.term-lay]:[color:rgb(var(--text-main))] [&_.term-desc]:[color:rgb(var(--text-muted))] [&_.term-desc]:[font-weight:500] dark:[&_.term-desc]:[color:rgb(var(--text-soft))] max-[480px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[&_.term-key]:[min-width:70px]"><span class="term-key">${(globalThis.bfTranslations?.["Momentum"] ?? "Momentum")}</span><span class="term-desc">${(globalThis.bfTranslations?.["Where the money is moving"] ?? "Where the money is moving")}</span></div>
                                    <div class="term-row flex [align-items:baseline] [gap:8px] [padding:5px_10px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:6px] [font-size:11.5px] [line-height:1.4] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.term-key]:[font-weight:800] [&_.term-key]:[color:rgb(var(--text-main))] [&_.term-key]:[white-space:nowrap] [&_.term-key]:[min-width:78px] [&_.term-key]:[flex-shrink:0] dark:[&_.term-key]:[color:var(--text-on-primary)] [&_.term-key.term-back]:[color:rgb(var(--primary))] dark:[&_.term-key.term-back]:[color:rgb(var(--primary))] [&_.term-key.term-lay]:[color:var(--text-lay)] dark:[&_.term-key.term-lay]:[color:rgb(var(--text-main))] [&_.term-desc]:[color:rgb(var(--text-muted))] [&_.term-desc]:[font-weight:500] dark:[&_.term-desc]:[color:rgb(var(--text-soft))] max-[480px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[&_.term-key]:[min-width:70px]"><span class="term-key">${(globalThis.bfTranslations?.["Δ Delta"] ?? "Δ Delta")}</span><span class="term-desc">${escapeHtml(@json(__("Change in Back interest over 5 minutes, in percentage points")))}</span></div>
                                    <div class="term-row flex [align-items:baseline] [gap:8px] [padding:5px_10px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:6px] [font-size:11.5px] [line-height:1.4] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.term-key]:[font-weight:800] [&_.term-key]:[color:rgb(var(--text-main))] [&_.term-key]:[white-space:nowrap] [&_.term-key]:[min-width:78px] [&_.term-key]:[flex-shrink:0] dark:[&_.term-key]:[color:var(--text-on-primary)] [&_.term-key.term-back]:[color:rgb(var(--primary))] dark:[&_.term-key.term-back]:[color:rgb(var(--primary))] [&_.term-key.term-lay]:[color:var(--text-lay)] dark:[&_.term-key.term-lay]:[color:rgb(var(--text-main))] [&_.term-desc]:[color:rgb(var(--text-muted))] [&_.term-desc]:[font-weight:500] dark:[&_.term-desc]:[color:rgb(var(--text-soft))] max-[480px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[&_.term-key]:[min-width:70px]"><span class="term-key">${(globalThis.bfTranslations?.["Volatility"] ?? "Volatility")}</span><span class="term-desc">${(globalThis.bfTranslations?.["How unpredictable the market is"] ?? "How unpredictable the market is")}</span></div>
                                    <div class="term-row flex [align-items:baseline] [gap:8px] [padding:5px_10px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:6px] [font-size:11.5px] [line-height:1.4] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.term-key]:[font-weight:800] [&_.term-key]:[color:rgb(var(--text-main))] [&_.term-key]:[white-space:nowrap] [&_.term-key]:[min-width:78px] [&_.term-key]:[flex-shrink:0] dark:[&_.term-key]:[color:var(--text-on-primary)] [&_.term-key.term-back]:[color:rgb(var(--primary))] dark:[&_.term-key.term-back]:[color:rgb(var(--primary))] [&_.term-key.term-lay]:[color:var(--text-lay)] dark:[&_.term-key.term-lay]:[color:rgb(var(--text-main))] [&_.term-desc]:[color:rgb(var(--text-muted))] [&_.term-desc]:[font-weight:500] dark:[&_.term-desc]:[color:rgb(var(--text-soft))] max-[480px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[&_.term-key]:[min-width:70px]"><span class="term-key">${(globalThis.bfTranslations?.["Liquidity"] ?? "Liquidity")}</span><span class="term-desc">${(globalThis.bfTranslations?.["Total money in the market"] ?? "Total money in the market")}</span></div>
                                    <div class="term-row flex [align-items:baseline] [gap:8px] [padding:5px_10px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:6px] [font-size:11.5px] [line-height:1.4] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.term-key]:[font-weight:800] [&_.term-key]:[color:rgb(var(--text-main))] [&_.term-key]:[white-space:nowrap] [&_.term-key]:[min-width:78px] [&_.term-key]:[flex-shrink:0] dark:[&_.term-key]:[color:var(--text-on-primary)] [&_.term-key.term-back]:[color:rgb(var(--primary))] dark:[&_.term-key.term-back]:[color:rgb(var(--primary))] [&_.term-key.term-lay]:[color:var(--text-lay)] dark:[&_.term-key.term-lay]:[color:rgb(var(--text-main))] [&_.term-desc]:[color:rgb(var(--text-muted))] [&_.term-desc]:[font-weight:500] dark:[&_.term-desc]:[color:rgb(var(--text-soft))] max-[480px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[&_.term-key]:[min-width:70px]"><span class="term-key">${(globalThis.bfTranslations?.["Runner"] ?? "Runner")}</span><span class="term-desc">${(globalThis.bfTranslations?.["Participant in the market"] ?? "Participant in the market")}</span></div>
                                    <div class="term-row flex [align-items:baseline] [gap:8px] [padding:5px_10px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:6px] [font-size:11.5px] [line-height:1.4] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.term-key]:[font-weight:800] [&_.term-key]:[color:rgb(var(--text-main))] [&_.term-key]:[white-space:nowrap] [&_.term-key]:[min-width:78px] [&_.term-key]:[flex-shrink:0] dark:[&_.term-key]:[color:var(--text-on-primary)] [&_.term-key.term-back]:[color:rgb(var(--primary))] dark:[&_.term-key.term-back]:[color:rgb(var(--primary))] [&_.term-key.term-lay]:[color:var(--text-lay)] dark:[&_.term-key.term-lay]:[color:rgb(var(--text-main))] [&_.term-desc]:[color:rgb(var(--text-muted))] [&_.term-desc]:[font-weight:500] dark:[&_.term-desc]:[color:rgb(var(--text-soft))] max-[480px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[&_.term-key]:[min-width:70px]"><span class="term-key">${(globalThis.bfTranslations?.["Prematch"] ?? "Prematch")}</span><span class="term-desc">${(globalThis.bfTranslations?.["Market before event starts"] ?? "Market before event starts")}</span></div>
                                    <div class="term-row flex [align-items:baseline] [gap:8px] [padding:5px_10px] bg-[rgb(var(--surface))] [border:1px_solid_var(--border-neutral-5)] [border-radius:6px] [font-size:11.5px] [line-height:1.4] [transition:0.3s] dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [&_.term-key]:[font-weight:800] [&_.term-key]:[color:rgb(var(--text-main))] [&_.term-key]:[white-space:nowrap] [&_.term-key]:[min-width:78px] [&_.term-key]:[flex-shrink:0] dark:[&_.term-key]:[color:var(--text-on-primary)] [&_.term-key.term-back]:[color:rgb(var(--primary))] dark:[&_.term-key.term-back]:[color:rgb(var(--primary))] [&_.term-key.term-lay]:[color:var(--text-lay)] dark:[&_.term-key.term-lay]:[color:rgb(var(--text-main))] [&_.term-desc]:[color:rgb(var(--text-muted))] [&_.term-desc]:[font-weight:500] dark:[&_.term-desc]:[color:rgb(var(--text-soft))] max-[480px]:[font-size:11px] max-[480px]:[padding:4px_8px] max-[480px]:[&_.term-key]:[min-width:70px]"><span class="term-key">${(globalThis.bfTranslations?.["Favorite"] ?? "Favorite")}</span><span class="term-desc">${(globalThis.bfTranslations?.["Participant with the lowest Back Odds"] ?? "Participant with the lowest Back Odds")}</span></div>
                                </div>
                            </div>
                        </div>
                    `;

                    if (updating) {
                        const template = document.createElement('template');
                        template.innerHTML = detailHtml;
                        if (detailContent.querySelectorAll('.runner-row').length !== template.content.querySelectorAll('.runner-row').length) {
                            if (globalThis.updateFixtureRows) globalThis.updateFixtureRows(detailContent.querySelector('.runners-list'), template.content.querySelector('.runners-list'));
                            else detailContent.querySelector('.runners-list').replaceChildren(...template.content.querySelector('.runners-list').childNodes);
                        }
                        const fields = detailContent.querySelectorAll('.detail-title, .detail-meta, .market-book-status, .runner-name, .runner-back, .runner-lay, .ind-label, .ind-value, .liq-back, .liq-lay, .liquidity-bar span');
                        template.content.querySelectorAll('.detail-title, .detail-meta, .market-book-status, .runner-name, .runner-back, .runner-lay, .ind-label, .ind-value, .liq-back, .liq-lay, .liquidity-bar span').forEach((next, index) => {
                            const field = fields[index];
                            if (field.innerHTML !== next.innerHTML && (!field.matches('.runner-name') || field.textContent !== next.textContent)) field.innerHTML = next.innerHTML;
                            if (field.className !== next.className) field.className = next.className;
                            if (field.style.cssText !== next.style.cssText) field.style.cssText = next.style.cssText;
                            if (next.hasAttribute('title')) field.setAttribute('title', next.getAttribute('title'));
                        });
                        detailContent.querySelector('.liquidity-bar').setAttribute('aria-label', template.content.querySelector('.liquidity-bar').getAttribute('aria-label'));
                        return;
                    }
                    detailContent.innerHTML = detailHtml;
                    emptyPanel.style.display = 'none';
                    detailContent.style.display = 'block';
                    detailPanel.classList.add('open');
                    terminal.classList.add('with-panel');
                    terminal.classList.remove('panel-expanded');

                    const expandBtn = document.getElementById('expandBtn');
                    if (expandBtn) expandBtn.addEventListener('click', toggleExpand);

                    const termsToggle = document.getElementById('termsToggle');
                    const termsSection = document.getElementById('termsSection');
                    if (termsToggle && termsSection) {
                        termsToggle.addEventListener('click', function() {
                            termsSection.classList.toggle('collapsed');
                        });
                    }

                    if (typeof ym !== 'undefined') {
                        ym(109087271, 'reachGoal', 'market_activity_open_detail', {
                            event: ev.match
                        });
                    }
                }

                function toggleExpand() {
                    const expandBtn = document.getElementById('expandBtn');
                    const isExpanded = terminal.classList.toggle('panel-expanded');
                    if (isExpanded) {
                        terminal.classList.remove('with-panel');
                        if (expandBtn) {
                            expandBtn.innerHTML = '<i class="fas fa-compress"></i>';
                            expandBtn.classList.add('expand-active');
                        }
                        document.body.classList.add('!overflow-hidden');
                        document.documentElement.classList.add('!overflow-hidden');
                    } else {
                        terminal.classList.add('with-panel');
                        if (expandBtn) {
                            expandBtn.innerHTML = '<i class="fas fa-expand"></i>';
                            expandBtn.classList.remove('expand-active');
                        }
                        if (!document.querySelector('dialog[open], #subscriptionModal, [aria-modal="true"]:not(.hidden), .panel-expanded')) {
                            document.body.classList.remove('!overflow-hidden');
                            document.documentElement.classList.remove('!overflow-hidden');
                        }
                    }
                }

                window.closeDetail = function() {
                    detailPanel.classList.remove('open');
                    terminal.classList.remove('with-panel');
                    terminal.classList.remove('panel-expanded');
                    if (!document.querySelector('dialog[open], #subscriptionModal, [aria-modal="true"]:not(.hidden), .panel-expanded')) {
                        document.body.classList.remove('!overflow-hidden');
                        document.documentElement.classList.remove('!overflow-hidden');
                    }
                    detailContent.style.display = 'none';
                    emptyPanel.style.display = 'flex';
                    currentEventId = null;
                    document.querySelectorAll('.events-table tbody tr').forEach(function(r) {
                        r.classList.remove('selected');
                    });
                };

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        if (terminal.classList.contains('panel-expanded')) toggleExpand();
                        else if (detailPanel.classList.contains('open')) closeDetail();
                    }
                });

                // ===== FILTERS =====
                let activeFilter = 'all';
                let activeSport = 'all';

                if (@json($marketActivitySport === 'football' && request()->filled('match')) && events.length) {
                    openDetail((events.find(event => event.market === 'first_half') || events[0]).id);
                }

            })();
        </script>
    @endpush
    @endunless
@endif
@endsection

@includeUnless($marketRowsOnly ?? false, 'users.layout.main')
