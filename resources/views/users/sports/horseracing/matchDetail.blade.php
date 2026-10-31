@php $raceSport = $raceSport ?? 'horseracing'; $raceTitle = $raceTitle ?? 'Horse Racing'; @endphp
@extends('users.layout.main')
@section('title', $match['team1'].' · '.$match['team2'].' · BF Markets')
@section('content')
    @include('users.layout.sports')
    <section id="horseRaceDetail" data-horse-detail data-race-channel="sports.{{ $raceSport }}.live" data-race-sport="{{ $raceSport }}" data-market-chart-url="{{ env('BETFAIR_API_KEY') }}" data-horse-url="{{ route($raceSport.'.matches.show', $match['id']) }}" data-horse-payload="{{ json_encode($detail) }}" class="min-w-0 pt-4 text-[var(--text-brand-70)] dark:text-[var(--text-on-primary)]">
        <a href="{{ route($raceSport.'.home') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-[rgb(var(--text-muted))] hover:text-[rgb(var(--primary))]">← {{ __('Back to '.$raceTitle) }}</a>
        <div class="mb-4 rounded-lg border border-[var(--border-gray-200)] bg-[rgb(var(--surface))] p-4 shadow-sm dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3"><span class="text-2xl">{{ $raceSport === 'greyhounds' ? '🐕' : '🏇' }}</span><div><h1 class="text-xl font-bold" data-horse-course>{{ $match['team1'] }}</h1><p class="text-sm text-[rgb(var(--text-muted))]" data-horse-description>{{ $match['team2'] }}</p><p class="text-xs text-[rgb(var(--text-muted))]">{{ $match['country'] ?? '' }}</p></div></div>
                <div class="flex items-center gap-4"><div class="text-right"><div class="text-2xl font-bold">{{ $match['minutes'] }}</div><div id="countdown-timer" class="text-xs font-medium text-[rgb(var(--text-muted))]"></div></div><span data-horse-status class="rounded-full bg-[var(--surface-blue-100)] px-3 py-1 text-xs font-bold text-[var(--text-blue-700)]">{{ $match['status'] === 'Prematch' ? __('Upcoming') : $match['status'] }}</span></div>
            </div>
        </div>
        <div class="mb-3 flex flex-wrap gap-2" data-horse-markets>@foreach ($detail['markets'] as $market)<button type="button" data-horse-market="{{ $market['type'] }}" class="rounded-full border border-[var(--border-control)] px-3 py-1 text-xs font-semibold [&.active]:bg-[var(--surface-heading)] [&.active]:text-white">{{ $market['type'] }}</button>@endforeach</div>
        <p class="mb-3 text-xs text-[rgb(var(--text-muted))]">{{ __('Pressure shows odds direction and available exchange liquidity, not traded volume.') }}</p>
        <p data-horse-error class="hidden mb-3 text-sm text-[var(--text-red-500)]"></p>
        <div class="overflow-hidden rounded-lg border border-[var(--border-gray-200)] bg-[rgb(var(--surface))] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]">
            <div class="runner-header"><div>{{ __('Runner') }}</div><div class="text-center">{{ __('Favourite') }}</div><div class="text-center">{{ __('Back') }}</div><div class="text-center">{{ __('Lay') }}</div><div class="text-center">{{ __('Available Liquidity Pressure') }}</div></div>
            <div id="runners-container" aria-live="polite">@if (($detail['race']['available'] ?? true) === false)<p class="p-8 text-center">{{ __('Race details and odds become available five minutes before the start.') }}</p>@elseif (! $checkAuthAndSubscription['display_odds'])<p class="flex items-center justify-center gap-3 p-8">@include('lock_icon') {{ __('Log in with an active subscription to view race odds.') }}</p>@endif</div>
        </div>
            <div class="mt-6 p-4 bg-[var(--surface-gray-50)] rounded-lg border border-[var(--border-gray-200)] dark:bg-[rgb(var(--bg))] dark:border-[rgb(var(--border))]">
                <h3 class="text-xs font-bold text-[var(--text-gray-700)] dark:text-[var(--text-on-primary)] uppercase tracking-wider mb-3">{{ __('Visual Indicators Legend') }}</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 text-xs text-[var(--text-gray-600)] dark:text-[rgb(var(--text-soft))]">
                    <div class="flex items-center gap-2">
                        <span class="legend-arrow-down text-base">↓</span>
                        <span>{{ __('Odds dropped (shortening)') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="legend-arrow-up text-base">↑</span>
                        <span>{{ __('Odds rose (drifting)') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="legend-arrow-down text-base">↓↓</span>
                        <span>{{ __('Large movement (5%+)') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span style="color: var(--text-warning-5); font-size: 14px;">⚡</span>
                        <span>{{ __('Sharp movement (8%+)') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] line-through text-[var(--text-gray-400)]">2.50</span>
                        <span>→</span>
                        <span class="font-bold">2.45</span>
                        <span>{{ __('Old → New') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full bg-[var(--surface-neutral-5)] text-[var(--text-success)] text-[10px] font-bold">-2.0%</span>
                        <span>{{ __('Change %') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg width="40" height="16" viewBox="0 0 40 16" fill="none">
                            <path d="M2 12 L10 8 L18 10 L26 4 L34 6 L38 2" stroke="var(--text-success)" stroke-width="2" stroke-linecap="round" fill="none"/>
                        </svg>
                        <span>{{ __('Recent trend (last 30 seconds)') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-sm bg-[var(--surface-success)]"></span>
                        <span>{{ __('Back pressure') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-sm bg-[var(--surface-danger)]"></span>
                        <span>{{ __('Lay pressure') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-[var(--surface-red-500)]"></span>
                        <span>{{ __('1st Favourite') }}</span>
                    </div>
                </div>
            </div>

    </section>
@endsection
@push('head')
<style>
#horseRaceDetail {
.odd-box.flash-size { animation: size-flash 0.65s ease-out; }
@keyframes size-flash { 50% { box-shadow: inset 0 0 0 3px var(--shadow-warning); } }
.runner-header {
            display: grid;
            grid-template-columns: 2fr 1.2fr 1.4fr 1.4fr 1.6fr;
            gap: 12px;
            padding: 10px 16px;
            background-color: var(--surface-neutral-0-17);
            border-bottom: 1px solid var(--border-neutral-5-9);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-brand-45-2);
        }
        .runner-row {
            display: grid;
            grid-template-columns: 2fr 1.2fr 1.4fr 1.4fr 1.6fr;
            gap: 12px;
            padding: 10px 16px;
            align-items: center;
            border-bottom: 1px solid var(--border-neutral-0);
            transition: background 0.15s ease;
        }
        .runner-row:last-child { border-bottom: none; }
        .runner-row:hover { background-color: var(--surface-neutral-0-18); }

        
        .odd-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            line-height: 1.2;
            transition: all 0.3s ease;
            min-height: 52px;
            position: relative;
            overflow: hidden;
        }
        .back-box { background-color: var(--surface-brand-0); border: 1px solid var(--border-brand-10); color: rgb(var(--text-main)); }
        .lay-box { background-color: var(--surface-danger-0); border: 1px solid var(--border-accent-10); color: rgb(var(--text-main)); }

        
        .odd-value {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-size: 15px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        
        .change-arrow {
            font-size: 12px;
            font-weight: 900;
            transition: transform 0.2s ease;
        }
        .change-arrow.up { color: var(--text-danger); }
        .change-arrow.down { color: var(--text-success); }
        .change-arrow.double {
            animation: arrow-pulse 0.6s ease-in-out;
        }

        
        .odds-history {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-size: 10.5px;
            font-weight: 600;
            color: var(--text-brand-30-2);
            margin-top: 2px;
            font-variant-numeric: tabular-nums;
        }
        .odds-history .old-value {
            text-decoration: line-through;
            opacity: 0.7;
        }
        .odds-history .new-value {
            color: var(--text-brand-60-3);
            font-weight: 700;
        }

        
        .change-pct {
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 99px;
            margin-top: 2px;
        }
        .change-pct.up {
            background: var(--surface-neutral-0);
            color: var(--text-danger);
        }
        .change-pct.down {
            background: var(--surface-neutral-5);
            color: var(--text-success);
        }

        
        @keyframes flash-down {
            0% { background-color: var(--surface-success-5); border-color: var(--border-success); }
            100% { background-color: var(--surface-hover); border-color: var(--border-brand-10); }
        }
        @keyframes flash-up {
            0% { background-color: var(--surface-danger-0-2); border-color: var(--border-danger); }
            100% { background-color: var(--surface-hover); border-color: var(--border-brand-10); }
        }
        .flash-down { animation: flash-down 1s ease-out; }
        .flash-up { animation: flash-up 1s ease-out; }

        
        @keyframes arrow-pulse {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(3px); }
        }

        
        .sharp-change {
            position: absolute;
            top: 2px;
            right: 4px;
            font-size: 10px;
            color: var(--text-warning-5);
            animation: bolt-flash 0.8s ease-in-out 3;
        }
        @keyframes bolt-flash {
            0%, 100% { opacity: 0; }
            50% { opacity: 1; }
        }

        
        .sparkline-container {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 20px;
            width: 100%;
            margin-top: 4px;
            position: relative;
        }
        .sparkline {
            width: 100%;
            height: 20px;
            display: block;
        }
        .sparkline-path {
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .sparkline-path.up { stroke: var(--text-danger); }
        .sparkline-path.down { stroke: var(--text-success); }
        .sparkline-path.flat { stroke: var(--text-brand-30-2); }

        
        .fav-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
        }
        .fav-1 {
            background: linear-gradient(135deg, var(--surface-danger-5), var(--surface-danger-15));
            color: var(--text-on-primary);
            box-shadow: 0 2px 8px var(--shadow-danger-15);
        }
        .fav-2 {
            background: linear-gradient(135deg, var(--surface-warning-0-3), var(--surface-warning-5));
            color: var(--text-warning-55);
            box-shadow: 0 2px 8px var(--shadow-warning-5);
        }
        .fav-3 {
            background: linear-gradient(135deg, var(--surface-brand-5), var(--surface-brand-10));
            color: var(--text-on-primary);
            box-shadow: 0 2px 8px var(--shadow-brand-10);
        }
        .fav-none {
            background-color: var(--surface-neutral-5-6);
            color: var(--text-neutral-30);
            border: 1px dashed var(--border-neutral-15);
        }

        @keyframes badge-pop {
            0% { transform: scale(1); }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }
        .badge-changed {
            animation: badge-pop 0.5s ease-out;
        }

        
        .pressure-container {
            display: flex;
            flex-direction: column;
            gap: 4px;
            width: 100%;
        }
        .pressure-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .pressure-bar-bg {
            width: 100%;
            height: 6px;
            background-color: var(--surface-neutral-10);
            border-radius: 99px;
            overflow: hidden;
        }
        .pressure-bar-fill {
            height: 100%;
            border-radius: 99px;
            transition: width 0.5s ease;
        }
        .pressure-bar-fill.back { background: linear-gradient(90deg, var(--surface-success), var(--surface-success-15)); }
        .pressure-bar-fill.lay { background: linear-gradient(90deg, var(--surface-danger), var(--surface-danger-5-2)); }
        .pressure-bar-fill.neutral { background: var(--surface-neutral-30); }

        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--text-brand-45-2);
        }
        .legend-arrow-up { color: var(--text-danger); font-weight: 900; }
        .legend-arrow-down { color: var(--text-success); font-weight: 900; }

        
        @media (max-width: 768px) {
            .runner-header { display: none; }
            .runner-row {
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 12px;
                border-bottom: 1px solid var(--border-neutral-5-9);
                background-color: var(--surface-neutral-0-19);
                border-radius: 8px;
                margin-bottom: 8px;
                box-shadow: 0 1px 3px var(--shadow-neutral-100-2);
            }
            .runner-row:hover { background-color: var(--surface-neutral-0-19); }
            .runner-name-mobile {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                font-weight: 700;
                color: rgb(var(--text-main));
                font-size: 15px;
                margin-bottom: 4px;
            }
            .runner-odds-mobile {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .odd-box { min-height: 48px; font-size: 14px; }
            .odd-box::before {
                content: attr(data-label);
                font-size: 9px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: var(--text-brand-45-2);
                margin-bottom: 2px;
            }
            .pressure-container { grid-column: auto; margin-top: 4px; }
            .pressure-label { font-size: 10px; }
        }
        @media (max-width: 480px) {
            .runner-row { padding: 10px; }
            .runner-name-mobile { font-size: 13px; }
            .odd-box { font-size: 13px; min-height: 44px; padding: 4px 6px; }
            .odds-history { font-size: 9.5px; }
            .change-pct { font-size: 9px; }
        }
    
}
.dark-mode #horseRaceDetail { .runner-header,.runner-row:hover {background:var(--surface-brand-75);color:var(--text-neutral-5)} .runner-row {background:var(--surface-brand-80);border-color:var(--border-brand-70)} .back-box {background:var(--surface-brand-65);color:var(--text-neutral-5);border-color:var(--border-brand-60)} .lay-box {background:var(--surface-accent-70);color:var(--text-neutral-5);border-color:var(--border-accent-60)} .odds-history .new-value {color:var(--text-neutral-5)} .runner-name-mobile,.runner-row {color:inherit} }
#horseRaceDetail .runner-row > * {min-width:0}
#horseRaceDetail .runner-name {overflow-wrap:anywhere}
#horseRaceDetail .sparkline {max-width:100px}
#horseRaceDetail .sparkline-path.flat {stroke-dasharray:2 2}
</style>
@endpush
@push('js')
    @vite('resources/users/js/sports/horseracing.js')
@endpush

@push('head')<style>@media(max-width:768px){#horseRaceDetail .horse-desktop{display:none}#horseRaceDetail .runner-name-mobile{display:flex}}@media(min-width:769px){#horseRaceDetail .runner-name-mobile{display:none}}</style>@endpush
