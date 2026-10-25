@once
    <style>
        .betwatch-team-with-cards {
            display: inline-flex;
            align-items: center;
            gap: 0.24rem;
            min-width: 0;
        }

        .betwatch-team-with-cards--home {
            justify-content: flex-end;
        }

        .betwatch-team-with-cards--away {
            justify-content: flex-start;
        }

        .betwatch-card-stack {
            display: inline-flex;
            align-items: center;
            gap: 0.12rem;
            flex: 0 0 auto;
        }

        .betwatch-card-indicator {
            width: 0.5rem;
            height: 0.72rem;
            border-radius: 0.08rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.48rem;
            line-height: 1;
            font-weight: 800;
            color: #7a5200;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
        }

        .betwatch-card-indicator--yellow {
            background: #f6c43d;
        }

        .betwatch-card-indicator--red {
            background: #e03131;
            color: #ffffff;
        }

        @keyframes betwatchGoalPulse {

            0%,
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(224, 49, 49, 0.35);
            }

            50% {
                transform: scale(1.06);
                box-shadow: 0 0 0 0.28rem rgba(224, 49, 49, 0);
            }
        }

        .betwatch-goal-indicator {
            align-items: center;
            gap: 0.18rem;
            flex: 0 0 auto;
            border-radius: 999px;
            background: rgba(224, 49, 49, 0.12);
            padding: 0.1rem 0.34rem;
            color: #c92a2a;
            font-size: 0.62rem;
            font-weight: 900;
            line-height: 1.2;
            text-transform: uppercase;
        }

        @keyframes betwatchGoalLifetime {
            from {
                opacity: 1;
                visibility: visible;
            }

            to {
                opacity: 0;
                visibility: hidden;
                width: 0;
                max-width: 0;
                padding: 0;
                margin: 0;
                gap: 0;
                overflow: hidden;
            }
        }

        .betwatch-goal-indicator {
            animation: betwatchGoalPulse 1s ease-in-out infinite, betwatchGoalLifetime 25s step-end forwards;
        }

        .betwatch-goal-indicator::before {
            content: "";
            width: 0.38rem;
            height: 0.38rem;
            border-radius: 999px;
            background: #e03131;
        }

        .betwatch-goal-indicator.is-active {
            display: inline-flex;
        }

        .betwatch-shock-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            min-width: 0;
            max-width: 18rem;
            padding: 0.24rem 0.5rem;
            border: 1px solid rgba(224, 49, 49, 0.18);
            border-radius: 0.65rem;
            background: rgba(224, 49, 49, 0.1);
            color: #c92a2a;
            font-size: 0.68rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .betwatch-shock-badge__label {
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .betwatch-shock-badge__market {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .betwatch-shock-badge__runner {
            display: block;
            margin-top: 0.04rem;
            overflow: hidden;
            color: #7f1d1d;
            font-size: 0.62rem;
            font-weight: 700;
            text-overflow: ellipsis;
        }

        .betwatch-shock-badge__movement {
            display: inline-flex;
            align-items: center;
            gap: 0.18rem;
            flex: 0 0 auto;
        }

        .betwatch-shock-badge__drop {
            flex: 0 0 auto;
            color: #991b1b;
        }

        .betwatch-trend-tooltip {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .betwatch-trend-tooltip__bubble {
            position: absolute;
            left: 50%;
            bottom: calc(100% + 12px);
            z-index: 9999;
            display: inline-flex;
            align-items: center;
            gap: 0.22rem;
            min-width: max-content;
            padding: 10px;
            color: #ffffff;
            background: #343b48;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: translateX(-50%) translateY(5px);
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
        }

        .betwatch-trend-tooltip__bubble::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 100%;
            width: 0;
            height: 0;
            border-left: 9px solid transparent;
            border-right: 9px solid transparent;
            border-top: 9px solid #343b48;
            transform: translateX(-50%);
        }

        .betwatch-trend-tooltip__arrow {
            font-size: 1.2em;
            line-height: 1;
            opacity: 0.8;
        }

        .betwatch-trend-tooltip:hover .betwatch-trend-tooltip__bubble,
        .betwatch-trend-tooltip:focus-visible .betwatch-trend-tooltip__bubble {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }
    </style>
@endonce

@if ($isMarketShockDetector = request()->input('type') === 'market_shock_detector')
    <style>
        .market-shock-detector {
            width: 100%;
            padding: 18px;
            border: 1px solid #e7e7eb;
            border-radius: 12px;
            background: #ffffff;
            color: #171721;
        }

        .market-shock-detector__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .market-shock-detector__heading {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .market-shock-detector__icon {
            display: inline-flex;
            width: 38px;
            height: 38px;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border-radius: 50%;
            background: #6f31bc;
            color: #ffffff;
        }

        .market-shock-detector__icon svg {
            width: 21px;
            height: 21px;
            fill: currentColor;
        }

        .market-shock-detector__title-row {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .market-shock-detector__title {
            margin: 0;
            color: #171721;
            font-size: 16px;
            font-weight: 800;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .market-shock-detector__info {
            display: inline-flex;
            width: 15px;
            height: 15px;
            align-items: center;
            justify-content: center;
            border: 1px solid #78808c;
            border-radius: 50%;
            color: #78808c;
            font-size: 9px;
            font-weight: 800;
        }

        .market-shock-detector__subtitle {
            margin: 3px 0 0;
            color: #687080;
            font-size: 11px;
            line-height: 1.35;
        }

        .market-shock-detector__actions {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .market-shock-detector__action {
            display: inline-flex;
            min-height: 34px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 14px;
            border: 1px solid #dedee5;
            border-radius: 6px;
            background: #ffffff;
            color: #1e1e27;
            font-size: 11px;
            font-weight: 700;
        }

        .market-shock-detector__grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .market-shock-card {
            min-width: 0;
            padding: 12px;
            border: 1px solid #e3e3e9;
            border-radius: 9px;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(27, 31, 44, 0.05);
        }

        .market-shock-card__badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 10px;
            padding: 4px 9px;
            border-radius: 4px;
            background: #ef3340;
            color: #ffffff;
            font-size: 9px;
            font-weight: 800;
            line-height: 1;
            text-transform: uppercase;
        }

        .market-shock-card__match {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #171721;
            font-size: 12px;
            font-weight: 800;
        }

        .market-shock-card__sport-icon {
            display: inline-flex;
            width: 15px;
            height: 15px;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .market-shock-card__league {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 6px;
            color: #292934;
            font-size: 10px;
            font-weight: 600;
        }

        .market-shock-card__flag {
            width: 15px;
            height: 11px;
            object-fit: cover;
            border-radius: 1px;
        }

        .market-shock-card__meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            padding-bottom: 9px;
            border-bottom: 1px solid #ededf1;
            color: #555d6b;
            font-size: 9px;
        }

        .market-shock-card__detected {
            color: #ef3340;
            font-weight: 700;
        }

        .market-shock-card__countdown {
            display: inline-flex;
            width: 100%;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            color: #555d6b;
            font-weight: 700;
        }

        .market-shock-card__countdown-value,
        .market-shock-detector__footer-countdown {
            color: #ef3340;
            font-variant-numeric: tabular-nums;
            font-weight: 900;
        }

        .market-shock-card__details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 10px;
        }

        .market-shock-card__detail-label {
            display: block;
            margin-bottom: 4px;
            color: #636a78;
            font-size: 9px;
        }

        .market-shock-card__detail-value {
            display: block;
            color: #23232c;
            font-size: 10px;
            font-weight: 700;
        }

        .market-shock-card__selection {
            color: #6f31bc;
        }

        .market-shock-card__odds {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }

        .market-shock-card__odd {
            display: flex;
            min-height: 34px;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e2e8;
            border-radius: 5px;
            background: #ffffff;
            color: #20202a;
            font-size: 14px;
            font-weight: 800;
        }

        .market-shock-card__odd--current {
            color: #20a44b;
        }

        .market-shock-card__arrow {
            color: #737986;
            font-size: 16px;
        }

        .market-shock-card__drop {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            margin-top: 10px;
        }

        .market-shock-card__percentage {
            flex: 0 0 auto;
            color: #ef3340;
            font-size: 14px;
            font-weight: 900;
        }

        .market-shock-card__threshold {
            color: #31333c;
            font-size: 8px;
            font-weight: 600;
            line-height: 1.35;
        }

        .market-shock-card__chart {
            display: flex;
            width: 100%;
            min-height: 32px;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin-top: 13px;
            border: 1px solid #8b63bc;
            border-radius: 5px;
            background: #ffffff;
            color: #6f31bc;
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
        }

        .market-shock-card__chart svg {
            width: 15px;
            height: 15px;
        }

        .market-shock-detector__empty {
            grid-column: 1 / -1;
            padding: 40px 20px;
            color: #666d78;
            text-align: center;
        }

        .market-shock-detector__footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 14px;
            color: #5e6470;
            font-size: 10px;
        }

        @media (max-width: 1200px) {
            .market-shock-detector__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 680px) {
            .market-shock-detector__header {
                flex-direction: column;
            }

            .market-shock-detector__grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    @php
        $marketShockCards = $isMarketShockDetector
            ? collect($resolvedMatchGroups)
                ->flatMap(function ($matchGroup) {
                    return collect($matchGroup['matches'] ?? [])->map(
                        fn($match) => ['group' => $matchGroup, 'match' => $match],
                    );
                })
                ->filter(fn($item) => !empty($item['match']['market_shock_alert']))
                ->sortByDesc(fn($item) => (int) data_get($item, 'match.market_shock_alert.detected_at_ts', 0))
                ->values()
            : collect();

        $marketShockNowTs = now('UTC')->timestamp;
        $marketShockNextExpiryTs = $marketShockCards
            ->map(fn($item) => (int) data_get($item, 'match.market_shock_alert.expires_at_ts', 0))
            ->filter(fn($expiresAtTs) => $expiresAtTs > $marketShockNowTs)
            ->min();
        $marketShockNextRemaining = max(0, (int) $marketShockNextExpiryTs - $marketShockNowTs);
        $marketShockNextRemainingText = sprintf(
            '%02d:%02d',
            intdiv($marketShockNextRemaining, 60),
            $marketShockNextRemaining % 60
        );
    @endphp
@endif
@if ($isMarketShockDetector)
    <section class="market-shock-detector">
        <header class="market-shock-detector__header">
            <div class="market-shock-detector__heading">
                <span class="market-shock-detector__icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M13.2 1 4.8 13h6L9.9 23 19.2 10h-6.1L13.2 1Z" />
                    </svg>
                </span>

                <div>
                    <div class="market-shock-detector__title-row">
                        <h2 class="market-shock-detector__title">
                            Market Shock Detector
                        </h2>

                        <span class="market-shock-detector__info">i</span>
                    </div>

                    <p class="market-shock-detector__subtitle">
                        Detects sudden, significant market shocks in real-time
                    </p>
                </div>
            </div>
        </header>

        <div class="market-shock-detector__grid">
            @forelse ($marketShockCards as $shockCard)
                @php
                    $matchGroup = $shockCard['group'];
                    $match = $shockCard['match'];
                    $alert = $match['market_shock_alert'];

                    $oldOdd = is_numeric($alert['initial_odd'] ?? null) ? (float) $alert['initial_odd'] : null;

                    $currentOdd = is_numeric($alert['current_odd'] ?? null) ? (float) $alert['current_odd'] : null;

                    $dropPercentage = -abs((float) ($alert['drop_percentage'] ?? 0));
                    $threshold = abs((float) ($alert['threshold'] ?? 0));

                    $kickoff = !empty($alert['kickoff'])
                        ? \Carbon\Carbon::parse($alert['kickoff'])->setTimezone(get_current_user_timezone())
                        : null;

                    $kickoffText = $kickoff
                        ? ($kickoff->isToday()
                            ? 'Today ' . $kickoff->format('H:i')
                            : $kickoff->format('d M H:i'))
                        : '-';

                    $detectedAt = !empty($alert['detected_at_ts'])
                        ? \Carbon\Carbon::createFromTimestampUTC((int) $alert['detected_at_ts'])
                        : null;

                    $detectedAgo = $detectedAt ? $detectedAt->shortAbsoluteDiffForHumans() . ' ago' : '-';

                    $expiresAtTs = (int) ($alert['expires_at_ts']
                        ?? ((int) ($alert['detected_at_ts'] ?? 0) + 1200));
                    $remainingSeconds = max(0, $expiresAtTs - $marketShockNowTs);
                    $remainingText = sprintf(
                        '%02d:%02d',
                        intdiv($remainingSeconds, 60),
                        $remainingSeconds % 60
                    );

                    $flagCode = strtolower((string) ($matchGroup['country_code'] ?? ''));
                @endphp

                <article class="market-shock-card" data-football-score-match="{{ $match['id'] ?? '' }}"
                    data-football-realtime-row data-football-match-id="{{ $match['id'] ?? '' }}"
                    data-market-shock-expires-at="{{ $expiresAtTs }}"
                    data-football-realtime-key="shock:{{ $match['id'] ?? '' }}:{{ $alert['market_id'] ?? '' }}:{{ $alert['runner_id'] ?? '' }}">

                    <div class="market-shock-card__badge">
                        ✹ Shock Detected
                    </div>

                    <div class="market-shock-card__match">
                        <span class="market-shock-card__sport-icon">⚽</span>
                        <span>
                            {{ $match['team_1'] ?? ($alert['team_1'] ?? '-') }}
                            vs
                            {{ $match['team_2'] ?? ($alert['team_2'] ?? '-') }}
                        </span>
                    </div>

                    <div class="market-shock-card__league">
                        @if ($flagCode && $flagCode !== 'international')
                            <img class="market-shock-card__flag" src="https://flagcdn.com/w40/{{ $flagCode }}.png"
                                alt="">
                        @endif

                        <span>
                            {{ $alert['league'] ?? ($matchGroup['league_name'] ?? '-') }}
                        </span>
                    </div>

                    <div class="market-shock-card__meta">
                        <span>◷ {{ $kickoffText }}</span>

                        <span class="market-shock-card__detected">
                            Detected: {{ $detectedAgo }}
                        </span>

                        <span class="market-shock-card__countdown">
                            Expires in
                            <strong class="market-shock-card__countdown-value" data-market-shock-countdown>
                                {{ $remainingText }}
                            </strong>
                        </span>
                    </div>

                    <div class="market-shock-card__details">
                        <div>
                            <span class="market-shock-card__detail-label">
                                Market
                            </span>

                            <span class="market-shock-card__detail-value">
                                {{ $alert['market_name'] ?? '-' }}
                            </span>
                        </div>

                        <div>
                            <span class="market-shock-card__detail-label">
                                Selection
                            </span>

                            <span class="market-shock-card__detail-value market-shock-card__selection">
                                {{ $alert['selection_name'] ?? '-' }}
                            </span>
                        </div>
                    </div>

                    <div class="market-shock-card__odds">
                        <span class="market-shock-card__odd">
                            {{ $oldOdd !== null ? $oldOdd : '-' }}
                        </span>

                        <span class="market-shock-card__arrow">→</span>

                        <span class="market-shock-card__odd market-shock-card__odd--current">
                            {{ $currentOdd !== null ? $currentOdd : '-' }}
                        </span>
                    </div>

                    <div class="market-shock-card__drop">
                        <span class="market-shock-card__percentage">
                            ↓ {{ number_format($dropPercentage, 1) }}%
                        </span>

                        <span class="market-shock-card__threshold">
                            (drop exceeded {{ number_format($threshold, 0) }}%
                            threshold within 3 min)
                        </span>
                    </div>

                    <a href="#" class="market-shock-card__chart js-open-chart-modal"
                        data-market-name='@json($alert['market_name'] ?? 'Market')' data-match-id="{{ $alert['market_id'] ?? '' }}"
                        data-runner-ids='@json([(int) ($alert['runner_id'] ?? 0)])'
                        data-runner-labels='@json([$alert['selection_name'] ?? 'Selection'])'>

                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M3 16.5h14" />
                            <path d="M5 14V9" />
                            <path d="M9 14V5" />
                            <path d="M13 14V7" />
                            <path d="M17 14V3" />
                        </svg>

                        Chart
                    </a>
                </article>
            @empty
                <div class="market-shock-detector__empty">
                    No active Market Shock alerts.
                </div>
            @endforelse
        </div>

        <footer class="market-shock-detector__footer" data-market-shock-footer
            @if ($marketShockCards->isEmpty()) hidden @endif>
            <span>ⓘ</span>
            <span>
                Next alert expires in
                <strong class="market-shock-detector__footer-countdown" data-market-shock-next-countdown>
                    {{ $marketShockNextRemainingText }}
                </strong>
            </span>
        </footer>
    </section>
@elseif($isPrematchDrops = request()->input('type') === 'prematch_drops')
    {{-- @if ($isPrematchDrops)
        <style>
            .prematch-drops {
                width: 100%;
                overflow: hidden;
                border: 1px solid #e3e3e8;
                border-radius: 10px;
                background: #ffffff;
                color: #191921;
            }

            .prematch-drops__header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                padding: 16px 18px;
                border-bottom: 1px solid #e7e7ec;
            }

            .prematch-drops__heading {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .prematch-drops__icon {
                display: inline-flex;
                width: 35px;
                height: 35px;
                align-items: center;
                justify-content: center;
                color: #7339be;
            }

            .prematch-drops__icon svg {
                width: 30px;
                height: 30px;
            }

            .prematch-drops__title-row {
                display: flex;
                align-items: center;
                gap: 7px;
            }

            .prematch-drops__title {
                margin: 0;
                color: #181820;
                font-size: 16px;
                font-weight: 900;
                line-height: 1.2;
                text-transform: uppercase;
            }

            .prematch-drops__info {
                display: inline-flex;
                width: 15px;
                height: 15px;
                align-items: center;
                justify-content: center;
                border: 1px solid #858b96;
                border-radius: 50%;
                color: #727986;
                font-size: 9px;
                font-weight: 800;
            }

            .prematch-drops__subtitle {
                margin: 3px 0 0;
                color: #687080;
                font-size: 10px;
                line-height: 1.35;
            }

            .prematch-drops__actions {
                display: flex;
                align-items: center;
                gap: 9px;
            }

            .prematch-drops__action {
                display: inline-flex;
                min-height: 33px;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 0 14px;
                border: 1px solid #dedee5;
                border-radius: 6px;
                background: #ffffff;
                color: #202029;
                font-size: 10px;
                font-weight: 700;
            }

            .prematch-drops__table-scroll {
                width: 100%;
                overflow-x: auto;
                overflow-y: hidden;
            }

            .prematch-drops__table {
                min-width: 1050px;
            }

            .prematch-drops__table-head,
            .prematch-drops__row {
                display: grid;
                grid-template-columns:
                    105px
                    minmax(245px, 1.7fr)
                    minmax(125px, 0.85fr)
                    minmax(145px, 1fr)
                    minmax(250px, 1.6fr)
                    80px
                    90px
                    55px;
                align-items: center;
            }

            .prematch-drops__table-head {
                min-height: 32px;
                padding: 0 16px;
                border-bottom: 1px solid #e7e7ec;
                color: #636a76;
                font-size: 8px;
                font-weight: 800;
                text-transform: uppercase;
            }

            .prematch-drops__row {
                min-height: 62px;
                padding: 7px 16px;
                border-bottom: 1px solid #e7e7ec;
            }

            .prematch-drops__row:last-child {
                border-bottom: 0;
            }

            .prematch-drops__status {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 10px;
                font-weight: 800;
            }

            .prematch-drops__status::before {
                content: "";
                width: 11px;
                height: 11px;
                flex: 0 0 auto;
                border-radius: 50%;
            }

            .prematch-drops__status--new {
                color: #1da43c;
            }

            .prematch-drops__status--new::before {
                background: #20ad3d;
            }

            .prematch-drops__status--active {
                color: #dfaa00;
            }

            .prematch-drops__status--active::before {
                background: #ffc20a;
            }

            .prematch-drops__status--weak {
                color: #e62932;
            }

            .prematch-drops__status--weak::before {
                background: #ed2732;
            }

            .prematch-drops__match {
                display: grid;
                grid-template-columns: 22px minmax(0, 1fr);
                align-items: center;
                gap: 8px;
                min-width: 0;
            }

            .prematch-drops__sport-icon {
                font-size: 16px;
            }

            .prematch-drops__teams {
                display: block;
                overflow: hidden;
                color: #202029;
                font-size: 10px;
                font-weight: 800;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .prematch-drops__match-meta {
                display: flex;
                align-items: center;
                gap: 7px;
                margin-top: 4px;
                color: #4f5664;
                font-size: 9px;
            }

            .prematch-drops__flag {
                width: 14px;
                height: 10px;
                border-radius: 1px;
                object-fit: cover;
            }

            .prematch-drops__kickoff {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                margin-left: 6px;
            }

            .prematch-drops__market,
            .prematch-drops__selection {
                overflow: hidden;
                padding-right: 10px;
                font-size: 10px;
                font-weight: 700;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .prematch-drops__selection {
                color: #7339be;
            }

            .prematch-drops__odds {
                display: grid;
                grid-template-columns: 47px 22px 47px 22px 47px;
                align-items: center;
                gap: 4px;
            }

            .prematch-drops__odd {
                display: inline-flex;
                min-height: 29px;
                align-items: center;
                justify-content: center;
                border: 1px solid #dedee5;
                border-radius: 5px;
                background: #ffffff;
                color: #25252e;
                font-size: 11px;
                font-weight: 800;
            }

            .prematch-drops__odd--current {
                color: #20a34a;
            }

            .prematch-drops__arrow {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                font-weight: 900;
            }

            .prematch-drops__arrow--down {
                color: #ef3340;
            }

            .prematch-drops__arrow--up {
                color: #20a34a;
            }

            .prematch-drops__arrow--same {
                color: #828895;
            }

            .prematch-drops__percentage {
                color: #ef3340;
                font-size: 12px;
                font-weight: 900;
            }

            .prematch-drops__started {
                color: #343740;
                font-size: 10px;
                font-weight: 700;
            }

            .prematch-drops__chart {
                display: inline-flex;
                width: 37px;
                height: 29px;
                align-items: center;
                justify-content: center;
                border: 1px solid #9b72c8;
                border-radius: 5px;
                background: #ffffff;
                color: #7339be;
            }

            .prematch-drops__chart svg {
                width: 16px;
                height: 16px;
            }

            .prematch-drops__empty {
                padding: 42px 20px;
                color: #666d78;
                text-align: center;
            }

            .prematch-drops__legend {
                display: flex;
                align-items: center;
                gap: 26px;
                padding: 10px 24px;
                border-top: 1px solid #e7e7ec;
                color: #3f4550;
                font-size: 9px;
            }

            .prematch-drops__legend-item {
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }

            .prematch-drops__legend-dot {
                width: 9px;
                height: 9px;
                border-radius: 50%;
            }

            .prematch-drops__legend-dot--new {
                background: #20ad3d;
            }

            .prematch-drops__legend-dot--active {
                background: #ffc20a;
            }

            .prematch-drops__legend-dot--weak {
                background: #ed2732;
            }

            @media (max-width: 680px) {
                .prematch-drops__header {
                    flex-direction: column;
                }

                .prematch-drops__legend {
                    align-items: flex-start;
                    flex-direction: column;
                    gap: 7px;
                }
            }
        </style>

        @php
            $statusPriority = [
                'NEW' => 0,
                'ACTIVE' => 1,
                'WEAK' => 2,
            ];

            $prematchDropCards = collect($resolvedMatchGroups)
                ->flatMap(function ($matchGroup) {
                    return collect($matchGroup['matches'] ?? [])->map(fn ($match) => [
                        'group' => $matchGroup,
                        'match' => $match,
                    ]);
                })
                ->filter(fn ($item) => ! empty($item['match']['prematch_drop_hit']))
                ->sort(function ($first, $second) use ($statusPriority) {
                    $firstSignal = $first['match']['prematch_drop_hit'];
                    $secondSignal = $second['match']['prematch_drop_hit'];

                    $firstPriority = $statusPriority[
                        $firstSignal['prematch_drop_status'] ?? 'WEAK'
                    ] ?? 3;

                    $secondPriority = $statusPriority[
                        $secondSignal['prematch_drop_status'] ?? 'WEAK'
                    ] ?? 3;

                    return ($firstPriority <=> $secondPriority)
                        ?: (
                            (int) ($secondSignal['detected_at_ts'] ?? 0)
                            <=>
                            (int) ($firstSignal['detected_at_ts'] ?? 0)
                        );
                })
                ->values();
        @endphp
    @endif
    @php
        $statusPriority = [
            'NEW' => 0,
            'ACTIVE' => 1,
            'WEAK' => 2,
        ];

        $prematchDropCards = collect($resolvedMatchGroups)
            ->flatMap(function ($matchGroup) {
                return collect($matchGroup['matches'] ?? [])->map(fn ($match) => [
                    'group' => $matchGroup,
                    'match' => $match,
                ]);
            })->filter(fn ($item) => ! empty($item['match']['prematch_drop_hit']))->sort(function ($first, $second) use ($statusPriority) {
                $firstSignal = $first['match']['prematch_drop_hit'];
                $secondSignal = $second['match']['prematch_drop_hit'];

                $firstPriority = $statusPriority[$firstSignal['prematch_drop_status'] ?? 'WEAK'] ?? 3;
                $secondPriority = $statusPriority[$secondSignal['prematch_drop_status'] ?? 'WEAK'] ?? 3;
                return ($firstPriority <=> $secondPriority)?: ((int) ($secondSignal['detected_at_ts'] ?? 0) <=> (int) ($firstSignal['detected_at_ts'] ?? 0));
            })->values();
    @endphp
    <section class="prematch-drops">
        <header class="prematch-drops__header">
            <div class="prematch-drops__heading">
                <span class="prematch-drops__icon">
                    <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 7l8 8 5-5 11 11"/>
                        <path d="M21 21h7v-7"/>
                    </svg>
                </span>

                <div>
                    <div class="prematch-drops__title-row">
                        <h2 class="prematch-drops__title">
                            Prematch Drops
                        </h2>

                        <span class="prematch-drops__info">i</span>
                    </div>

                    <p class="prematch-drops__subtitle">
                        Track ongoing price drops in real-time before the match starts
                    </p>
                </div>
            </div>
        </header>

        <div class="prematch-drops__table-scroll">
            <div class="prematch-drops__table">
                <div class="prematch-drops__table-head">
                    <span>Status</span>
                    <span>Match / League</span>
                    <span>Market</span>
                    <span>Selection</span>
                    <span>Odds Movement (Baseline → Current)</span>
                    <span>Drop %</span>
                    <span>Drop Started</span>
                    <span>Chart</span>
                </div>

                @forelse ($prematchDropCards as $prematchDropCard)
                    @php
                        $matchGroup = $prematchDropCard['group'];
                        $match = $prematchDropCard['match'];
                        $signal = $match['prematch_drop_hit'];

                        $dropHit = $signal['prematch_drop_hits'][0] ?? $signal;

                        $status = strtoupper(
                            (string) ($signal['prematch_drop_status'] ?? 'WEAK')
                        );

                        $statusClass = match ($status) {
                            'NEW' => 'new',
                            'ACTIVE' => 'active',
                            default => 'weak',
                        };

                        $baselineOdd = is_numeric($dropHit['baseline_odd'] ?? null)
                            ? (float) $dropHit['baseline_odd']
                            : null;

                        $previousOdd = is_numeric($dropHit['previous_odd'] ?? null)
                            ? (float) $dropHit['previous_odd']
                            : $baselineOdd;

                        $currentOdd = is_numeric($dropHit['current_odd'] ?? null)
                            ? (float) $dropHit['current_odd']
                            : null;

                        $firstDirection = match (true) {
                            $baselineOdd === null || $previousOdd === null => 'same',
                            $previousOdd < $baselineOdd => 'down',
                            $previousOdd > $baselineOdd => 'up',
                            default => 'same',
                        };

                        $secondDirection = match (true) {
                            $previousOdd === null || $currentOdd === null => 'same',
                            $currentOdd < $previousOdd => 'down',
                            $currentOdd > $previousOdd => 'up',
                            default => 'same',
                        };

                        $dropPercentage = -abs((float) (
                            $dropHit['drop_percentage']
                            ?? $signal['prematch_drop_max_drop']
                            ?? 0
                        ));

                        $kickoff = ! empty($signal['kickoff'])
                            ? \Carbon\Carbon::parse($signal['kickoff'])
                                ->setTimezone(get_current_user_timezone())
                            : null;

                        $detectedAt = ! empty($signal['detected_at_ts'])
                            ? \Carbon\Carbon::createFromTimestampUTC(
                                (int) $signal['detected_at_ts']
                            )
                            : null;

                        $dropStartedAgo = $detectedAt
                            ? $detectedAt->shortAbsoluteDiffForHumans() . ' ago'
                            : '-';

                        $flagCode = strtolower(
                            (string) ($matchGroup['country_code'] ?? '')
                        );
                    @endphp

                    <article class="prematch-drops__row"
                        data-football-score-match="{{ $match['id'] ?? '' }}"
                        data-football-realtime-row
                        data-football-match-id="{{ $match['id'] ?? '' }}"
                        data-football-realtime-key="drop:{{ $match['id'] ?? '' }}:{{ $dropHit['market_id'] ?? $signal['market_id'] ?? '' }}:{{ $dropHit['runner_id'] ?? $signal['runner_id'] ?? '' }}">

                        <span class="prematch-drops__status prematch-drops__status--{{ $statusClass }}">
                            {{ $status }}
                        </span>

                        <div class="prematch-drops__match">
                            <span class="prematch-drops__sport-icon">⚽</span>

                            <div>
                                <span class="prematch-drops__teams">
                                    {{ $match['team_1'] ?? $signal['team_1'] ?? '-' }}
                                    vs
                                    {{ $match['team_2'] ?? $signal['team_2'] ?? '-' }}
                                </span>

                                <div class="prematch-drops__match-meta">
                                    @if ($flagCode && $flagCode !== 'international')
                                        <img class="prematch-drops__flag"
                                            src="https://flagcdn.com/w40/{{ $flagCode }}.png"
                                            alt="">
                                    @endif

                                    <span>
                                        {{ $signal['league']
                                            ?? $matchGroup['league_name']
                                            ?? '-' }}
                                    </span>

                                    <span class="prematch-drops__kickoff">
                                        ◷ {{ $kickoff?->format('H:i') ?? '-' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <span class="prematch-drops__market">
                            {{ $dropHit['market_name'] ?? '-' }}
                        </span>

                        <span class="prematch-drops__selection">
                            {{ $dropHit['runner_name']
                                ?? $dropHit['selection_name']
                                ?? '-' }}
                        </span>

                        <div class="prematch-drops__odds">
                            <span class="prematch-drops__odd">
                                {{ $baselineOdd ?? '-' }}
                            </span>

                            <span class="prematch-drops__arrow prematch-drops__arrow--{{ $firstDirection }}">
                                @if ($firstDirection === 'down')
                                    ↓
                                @elseif ($firstDirection === 'up')
                                    ↑
                                @else
                                    →
                                @endif
                            </span>

                            <span class="prematch-drops__odd">
                                {{ $previousOdd ?? '-' }}
                            </span>

                            <span class="prematch-drops__arrow prematch-drops__arrow--{{ $secondDirection }}">
                                @if ($secondDirection === 'down')
                                    ↓
                                @elseif ($secondDirection === 'up')
                                    ↑
                                @else
                                    →
                                @endif
                            </span>

                            <span class="prematch-drops__odd prematch-drops__odd--current">
                                {{ $currentOdd ?? '-' }}
                            </span>
                        </div>

                        <span class="prematch-drops__percentage">
                            {{ number_format($dropPercentage, 1) }}%
                        </span>

                        <span class="prematch-drops__started">
                            {{ $dropStartedAgo }}
                        </span>

                        <a href="#"
                            class="prematch-drops__chart js-open-chart-modal"
                            data-market-name='@json($dropHit['market_name'] ?? 'Market')'
                            data-match-id="{{ $signal['market_id'] ?? '' }}"
                            data-runner-ids='@json([(int) ($signal['runner_id'] ?? 0)])'
                            data-runner-labels='@json([
                                $dropHit['runner_name']
                                    ?? $dropHit['selection_name']
                                    ?? 'Selection'
                            ])'>

                            <svg viewBox="0 0 20 20" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <path d="M3 16.5h14"/>
                                <path d="M5 14V10"/>
                                <path d="M9 14V7"/>
                                <path d="M13 14V4"/>
                                <path d="M17 14V8"/>
                            </svg>
                        </a>
                    </article>
                @empty
                    <div class="prematch-drops__empty">
                        No active Prematch Drops.
                    </div>
                @endforelse
            </div>
        </div>

        <footer class="prematch-drops__legend">
            <span class="prematch-drops__legend-item">
                <span class="prematch-drops__legend-dot prematch-drops__legend-dot--new"></span>
                <strong>NEW</strong> – Drop just detected
            </span>

            <span class="prematch-drops__legend-item">
                <span class="prematch-drops__legend-dot prematch-drops__legend-dot--active"></span>
                <strong>ACTIVE</strong> – Drop is continuing
            </span>

            <span class="prematch-drops__legend-item">
                <span class="prematch-drops__legend-dot prematch-drops__legend-dot--weak"></span>
                <strong>WEAK</strong> – Drop is weakening or reversing
            </span>
        </footer>
    </section> --}}
    @if ($isPrematchDrops)
        <style>
            .prematch-drops {
                width: 100%;
                overflow: hidden;
                border: 1px solid #e3e3e8;
                border-radius: 10px;
                background: #ffffff;
                color: #191921;
            }

            .prematch-drops,
            .prematch-drops * {
                box-sizing: border-box;
            }

            .prematch-drops__header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                padding: 16px 18px;
                border-bottom: 1px solid #e7e7ec;
            }

            .prematch-drops__heading {
                display: flex;
                align-items: center;
                gap: 10px;
                min-width: 0;
            }

            .prematch-drops__icon {
                display: inline-flex;
                width: 35px;
                height: 35px;
                flex: 0 0 auto;
                align-items: center;
                justify-content: center;
                color: #7339be;
            }

            .prematch-drops__icon svg {
                width: 30px;
                height: 30px;
            }

            .prematch-drops__title-row {
                display: flex;
                align-items: center;
                gap: 7px;
            }

            .prematch-drops__title {
                margin: 0;
                color: #181820;
                font-size: 16px;
                font-weight: 900;
                line-height: 1.2;
                text-transform: uppercase;
            }

            .prematch-drops__info {
                display: inline-flex;
                width: 15px;
                height: 15px;
                flex: 0 0 auto;
                align-items: center;
                justify-content: center;
                border: 1px solid #858b96;
                border-radius: 50%;
                color: #727986;
                font-size: 9px;
                font-weight: 800;
            }

            .prematch-drops__subtitle {
                margin: 3px 0 0;
                color: #687080;
                font-size: 10px;
                line-height: 1.35;
            }

            .prematch-drops__table-scroll {
                width: 100%;
                overflow: hidden;
            }

            .prematch-drops__table {
                width: 100%;
                min-width: 0;
            }

            .prematch-drops__row {
                display: flex;
                width: 100%;
                min-width: 0;
                flex-direction: column;
                gap: 12px;
                padding: 13px 16px;
                border-bottom: 3px solid #e7e7ec;
            }

            .prematch-drops__row:last-child {
                border-bottom: 0;
            }

            .prematch-drops__layer {
                display: grid;
                width: 100%;
                min-width: 0;
                align-items: center;
                gap: 14px;
            }

            .prematch-drops__layer--top {
                grid-template-columns: 90px minmax(0, 1fr) auto;
            }

            .prematch-drops__layer--bottom {
                grid-template-columns: minmax(220px, 1fr) auto 42px;
                padding-top: 10px;
                border-top: 1px solid #eeeef2;
            }

            .prematch-drops__status {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 10px;
                font-weight: 800;
                white-space: nowrap;
            }

            .prematch-drops__status::before {
                content: "";
                width: 11px;
                height: 11px;
                flex: 0 0 auto;
                border-radius: 50%;
            }

            .prematch-drops__status--new {
                color: #1da43c;
            }

            .prematch-drops__status--new::before {
                background: #20ad3d;
            }

            .prematch-drops__status--active {
                color: #dfaa00;
            }

            .prematch-drops__status--active::before {
                background: #ffc20a;
            }

            .prematch-drops__status--weak {
                color: #e62932;
            }

            .prematch-drops__status--weak::before {
                background: #ed2732;
            }

            .prematch-drops__match {
                display: grid;
                min-width: 0;
                grid-template-columns: 22px minmax(0, 1fr);
                align-items: center;
                gap: 8px;
            }

            .prematch-drops__match>div {
                min-width: 0;
            }

            .prematch-drops__sport-icon {
                font-size: 16px;
            }

            .prematch-drops__teams {
                display: block;
                min-width: 0;
                overflow: hidden;
                color: #202029;
                font-size: 10px;
                font-weight: 800;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .prematch-drops__match-meta {
                display: flex;
                min-width: 0;
                align-items: center;
                flex-wrap: wrap;
                gap: 5px 7px;
                margin-top: 4px;
                color: #4f5664;
                font-size: 9px;
            }

            .prematch-drops__flag {
                width: 14px;
                height: 10px;
                flex: 0 0 auto;
                border-radius: 1px;
                object-fit: cover;
            }

            .prematch-drops__kickoff {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                margin-left: 6px;
                white-space: nowrap;
            }

            .prematch-drops__drop-summary {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 22px;
            }

            .prematch-drops__metric {
                display: flex;
                flex-direction: column;
                gap: 3px;
                white-space: nowrap;
            }

            .prematch-drops__context {
                display: grid;
                min-width: 0;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }

            .prematch-drops__field {
                display: flex;
                min-width: 0;
                flex-direction: column;
                gap: 3px;
            }

            .prematch-drops__field-label {
                color: #777e8b;
                font-size: 8px;
                font-weight: 800;
                text-transform: uppercase;
            }

            .prematch-drops__market,
            .prematch-drops__selection {
                display: block;
                min-width: 0;
                overflow: hidden;
                padding: 0;
                font-size: 10px;
                font-weight: 700;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .prematch-drops__selection {
                color: #7339be;
            }

            .prematch-drops__movement {
                display: flex;
                min-width: 0;
                align-items: center;
                gap: 12px;
            }

            .prematch-drops__odds {
                display: grid;
                flex: 0 0 auto;
                grid-template-columns: 47px 22px 47px 22px 47px;
                align-items: center;
                gap: 4px;
            }

            .prematch-drops__odd {
                display: inline-flex;
                min-height: 29px;
                align-items: center;
                justify-content: center;
                border: 1px solid #dedee5;
                border-radius: 5px;
                background: #ffffff;
                color: #25252e;
                font-size: 11px;
                font-weight: 800;
            }

            .prematch-drops__odd--current {
                color: #20a34a;
            }

            .prematch-drops__arrow {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                font-weight: 900;
                line-height: 1;
                will-change: transform, opacity;
            }

            .prematch-drops__arrow--down {
                color: #ef3340;
                animation: prematch-drops-arrow-down 1.35s ease-in-out infinite;
            }

            .prematch-drops__arrow--up {
                color: #20a34a;
                animation: prematch-drops-arrow-up 1.35s ease-in-out infinite;
            }

            .prematch-drops__arrow--same {
                color: #828895;
            }

            @keyframes prematch-drops-arrow-down {
                0% {
                    opacity: 0;
                    transform: translate3d(0, -3px, 0);
                }

                30%,
                70% {
                    opacity: 1;
                }

                100% {
                    opacity: 0;
                    transform: translate3d(0, 3px, 0);
                }
            }

            @keyframes prematch-drops-arrow-up {
                0% {
                    opacity: 0;
                    transform: translate3d(0, 3px, 0);
                }

                30%,
                70% {
                    opacity: 1;
                }

                100% {
                    opacity: 0;
                    transform: translate3d(0, -3px, 0);
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .prematch-drops__arrow--down,
                .prematch-drops__arrow--up {
                    animation: none;
                }
            }

            .prematch-drops__percentage {
                color: #ef3340;
                font-size: 12px;
                font-weight: 900;
            }

            .prematch-drops__started {
                color: #343740;
                font-size: 10px;
                font-weight: 700;
            }

            .prematch-drops__chart {
                display: inline-flex;
                width: 37px;
                height: 29px;
                align-items: center;
                justify-content: center;
                border: 1px solid #9b72c8;
                border-radius: 5px;
                background: #ffffff;
                color: #7339be;
            }

            .prematch-drops__chart svg {
                width: 16px;
                height: 16px;
            }

            .prematch-drops__empty {
                padding: 42px 20px;
                color: #666d78;
                text-align: center;
            }

            .prematch-drops__legend {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px 26px;
                padding: 10px 24px;
                border-top: 1px solid #e7e7ec;
                color: #3f4550;
                font-size: 9px;
            }

            .prematch-drops__legend-item {
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }

            .prematch-drops__legend-dot {
                width: 9px;
                height: 9px;
                flex: 0 0 auto;
                border-radius: 50%;
            }

            .prematch-drops__legend-dot--new {
                background: #20ad3d;
            }

            .prematch-drops__legend-dot--active {
                background: #ffc20a;
            }

            .prematch-drops__legend-dot--weak {
                background: #ed2732;
            }

            @media (max-width: 850px) {
                .prematch-drops__layer--top {
                    grid-template-columns: 80px minmax(0, 1fr);
                }

                .prematch-drops__drop-summary {
                    grid-column: 1 / -1;
                    justify-content: flex-start;
                    padding-left: 94px;
                }

                .prematch-drops__layer--bottom {
                    grid-template-columns: minmax(0, 1fr) 42px;
                }

                .prematch-drops__context {
                    grid-column: 1 / -1;
                }
            }

            @media (max-width: 560px) {
                .prematch-drops__header {
                    padding: 14px 12px;
                }

                .prematch-drops__row {
                    padding: 12px;
                }

                .prematch-drops__layer--top,
                .prematch-drops__layer--bottom {
                    grid-template-columns: minmax(0, 1fr);
                }

                .prematch-drops__drop-summary {
                    grid-column: auto;
                    justify-content: flex-start;
                    padding-left: 0;
                }

                .prematch-drops__context {
                    grid-column: auto;
                    grid-template-columns: minmax(0, 1fr);
                    gap: 9px;
                }

                .prematch-drops__movement {
                    align-items: flex-start;
                    flex-direction: column;
                }

                .prematch-drops__chart {
                    justify-self: start;
                }

                .prematch-drops__legend {
                    align-items: flex-start;
                    flex-direction: column;
                    gap: 7px;
                    padding: 10px 12px;
                }
            }
        </style>
        @php
            $statusPriority = [
                'NEW' => 0,
                'ACTIVE' => 1,
                'WEAK' => 2,
            ];

            $prematchDropCards = collect($resolvedMatchGroups)->flatMap(function ($matchGroup) {
                return collect($matchGroup['matches'] ?? [])->map(
                    fn ($match) => [
                        'group' => $matchGroup,
                        'match' => $match,
                    ]
                );})->filter(fn ($item) => ! empty($item['match']['prematch_drop_hit']))->sort(function ($first, $second) use ($statusPriority) {
                $firstSignal = $first['match']['prematch_drop_hit'];
                $secondSignal = $second['match']['prematch_drop_hit'];
                $firstPriority = $statusPriority[$firstSignal['prematch_drop_status'] ?? 'WEAK'] ?? 3;
                $secondPriority = $statusPriority[$secondSignal['prematch_drop_status'] ?? 'WEAK'] ?? 3;
                return ($firstPriority <=> $secondPriority) ?: ((int) ($secondSignal['detected_at_ts'] ?? 0) <=> (int) ($firstSignal['detected_at_ts'] ?? 0));
            })->values();
        @endphp
         <section class="prematch-drops">
            <header class="prematch-drops__header">
                <div class="prematch-drops__heading">
                    <span class="prematch-drops__icon">
                        <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 7l8 8 5-5 11 11"/>
                            <path d="M21 21h7v-7"/>
                        </svg>
                    </span>
                    <div>
                        <div class="prematch-drops__title-row">
                            <h2 class="prematch-drops__title">Prematch Drops</h2>
                            <span class="prematch-drops__info">i</span>
                        </div>
                        <p class="prematch-drops__subtitle">Track ongoing price drops in real-time before the match starts</p>
                    </div>
                </div>
            </header>

            <div class="prematch-drops__table-scroll">
                <div class="prematch-drops__table">
                    @forelse ($prematchDropCards as $prematchDropCard)
                        @php
                            $matchGroup = $prematchDropCard['group'];
                            $match = $prematchDropCard['match'];
                            $signal = $match['prematch_drop_hit'];
                            $dropHit = $signal['prematch_drop_hits'][0] ?? $signal;
                            $status = strtoupper((string) ($signal['prematch_drop_status'] ?? 'WEAK'));
                            $statusClass = match ($status) {
                                'NEW' => 'new',
                                'ACTIVE' => 'active',
                                default => 'weak',
                            };
                            $baselineOdd = is_numeric($dropHit['baseline_odd'] ?? null) ? (float) $dropHit['baseline_odd'] : null;
                            $previousOdd = is_numeric($dropHit['previous_odd'] ?? null) ? (float) $dropHit['previous_odd'] : $baselineOdd;
                            $currentOdd = is_numeric($dropHit['current_odd'] ?? null) ? (float) $dropHit['current_odd'] : null;
                            $firstDirection = match (true) {$baselineOdd === null || $previousOdd === null => 'same',
                                $previousOdd < $baselineOdd => 'down',
                                $previousOdd > $baselineOdd => 'up',
                                default => 'same',
                            };

                            $secondDirection = match (true) {
                                $previousOdd === null || $currentOdd === null => 'same',
                                $currentOdd < $previousOdd => 'down',
                                $currentOdd > $previousOdd => 'up',
                                default => 'same',
                            };

                            $dropPercentage = -abs((float) ( $dropHit['drop_percentage'] ?? $signal['prematch_drop_max_drop'] ?? 0));
                            $kickoff = !empty($signal['kickoff']) ? \Carbon\Carbon::parse($signal['kickoff'])->setTimezone(get_current_user_timezone()): null;
                            $detectedAt = ! empty($signal['detected_at_ts']) ? \Carbon\Carbon::createFromTimestampUTC((int) $signal['detected_at_ts']): null;
                            $dropStartedAgo = $detectedAt ? $detectedAt->shortAbsoluteDiffForHumans() . ' ago' : '-';
                            $flagCode = strtolower((string) ($matchGroup['country_code'] ?? ''));
                        @endphp

                        <article class="prematch-drops__row"  data-football-score-match="{{ $match['id'] ?? '' }}"  data-football-realtime-row  data-football-match-id="{{ $match['id'] ?? '' }}"  data-football-realtime-key="drop:{{ $match['id'] ?? '' }}:{{ $dropHit['market_id'] ?? $signal['market_id'] ?? '' }}:{{ $dropHit['runner_id'] ?? $signal['runner_id'] ?? '' }}">

                            <div class="prematch-drops__layer prematch-drops__layer--top">
                                <span class="prematch-drops__status prematch-drops__status--{{ $statusClass }}">{{ $status }}</span>
                                <div class="prematch-drops__match">
                                    <span class="prematch-drops__sport-icon">⚽</span>
                                    <div>
                                        <span class="prematch-drops__teams">{{ $match['team_1'] ?? $signal['team_1'] ?? '-' }} vs {{ $match['team_2'] ?? $signal['team_2'] ?? '-' }}</span>
                                        <div class="prematch-drops__match-meta">
                                            @if ($flagCode && $flagCode !== 'international')
                                                <img class="prematch-drops__flag" src="https://flagcdn.com/w40/{{ $flagCode }}.png" alt="">
                                            @endif
                                            <span>{{ $signal['league'] ?? $matchGroup['league_name'] ?? '-' }}</span>
                                            <span class="prematch-drops__kickoff">◷ {{ $kickoff?->format('H:i') ?? '-' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="prematch-drops__drop-summary">
                                    <span class="prematch-drops__metric">
                                        <span class="prematch-drops__field-label">Drop</span>
                                        <span class="prematch-drops__percentage">
                                            {{ number_format($dropPercentage, 1) }}%
                                        </span>
                                    </span>

                                    <span class="prematch-drops__metric">
                                        <span class="prematch-drops__field-label">Started </span>
                                        <span class="prematch-drops__started"> {{ $dropStartedAgo }}</span>
                                    </span>
                                </div>
                            </div>

                            <div class="prematch-drops__layer prematch-drops__layer--bottom">
                                <div class="prematch-drops__context">
                                    <span class="prematch-drops__field">
                                        <span class="prematch-drops__field-label">Market</span>
                                        <span class="prematch-drops__market">{{ $dropHit['market_name'] ?? '-' }}</span>
                                    </span>

                                    <span class="prematch-drops__field">
                                        <span class="prematch-drops__field-label">Selection</span>
                                        <span class="prematch-drops__selection">{{ $dropHit['runner_name'] ?? $dropHit['selection_name'] ?? '-' }}</span>
                                    </span>
                                </div>

                                <div class="prematch-drops__movement">
                                    <span class="prematch-drops__field-label">Odds Movement</span>
                                    <div class="prematch-drops__odds">
                                        <span class="prematch-drops__odd">{{ $baselineOdd ?? '-' }}</span>
                                        <span class="prematch-drops__arrow prematch-drops__arrow--{{ $firstDirection }}">
                                            @if ($firstDirection === 'down')
                                                ↓
                                            @elseif ($firstDirection === 'up')
                                                ↑
                                            @else
                                                →
                                            @endif
                                        </span>
                                        <span class="prematch-drops__odd">{{ $previousOdd ?? '-' }}</span>
                                        <span class="prematch-drops__arrow prematch-drops__arrow--{{ $secondDirection }}">
                                            @if ($secondDirection === 'down')
                                                ↓
                                            @elseif ($secondDirection === 'up')
                                                ↑
                                            @else
                                                →
                                            @endif
                                        </span>

                                        <span class="prematch-drops__odd prematch-drops__odd--current">
                                            {{ $currentOdd ?? '-' }}
                                        </span>
                                    </div>
                                </div>

                                <a href="#" class="prematch-drops__chart js-open-chart-modal" data-market-name='@json($dropHit['market_name'] ?? 'Market')' data-match-id="{{ $signal['market_id'] ?? '' }}" data-runner-ids='@json([(int) ($signal['runner_id'] ?? 0)])' data-runner-labels='@json([ $dropHit['runner_name'] ?? $dropHit['selection_name'] ?? 'Selection' ])'>
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <path d="M3 16.5h14"/><path d="M5 14V10"/><path d="M9 14V7"/><path d="M13 14V4"/><path d="M17 14V8"/>
                                    </svg>
                                </a>
                            </div>
                        </article>
                    @empty
                        <div class="prematch-drops__empty">
                            No active Prematch Drops.
                        </div>
                    @endforelse
                </div>
            </div>

            <footer class="prematch-drops__legend">
                <span class="prematch-drops__legend-item">
                    <span class="prematch-drops__legend-dot prematch-drops__legend-dot--new"></span>
                    <strong>NEW</strong>
                    – Drop just detected
                </span>

                <span class="prematch-drops__legend-item">
                    <span class="prematch-drops__legend-dot prematch-drops__legend-dot--active"></span>
                    <strong>ACTIVE</strong>
                    – Drop is continuing
                </span>

                <span class="prematch-drops__legend-item">
                    <span class="prematch-drops__legend-dot prematch-drops__legend-dot--weak"></span>
                    <strong>WEAK</strong>
                    – Drop is weakening or reversing
                </span>
            </footer>
        </section>
    @endif
@else
    @if (count($resolvedMatchGroups) === 0)
        <article class="betwatch-match-card" data-football-card>
            <div class="betwatch-match-row betwatch-match-row--empty">
                <span
                    class="betwatch-teams__label betwatch-empty-label betwatch-empty-label--small font-semibold">{{ __('No football matches available.') }}</span>
            </div>
        </article>
    @endif
    @foreach ($resolvedMatchGroups as $matchGroup)
        <article class="betwatch-match-card" data-match-card-shell
            data-football-realtime-group="{{ $matchGroup['matches'][0]['league_id'] ?? '' }}">
            <div class="betwatch-match-head">
                <svg class="betwatch-star-icon {{ $matchGroup['is_fav'] == 1 ? 'is-favorite' : '' }}"
                    data-favorite-trigger data-favorite-sport="football" data-favorite-type="league"
                    data-favorite-league-id="{{ $matchGroup['matches'][0]['league_id'] ?? '' }}"
                    data-favorite-label="{{ $matchGroup['league_name'] ?? 'Unknown league' }}" viewBox="0 0 20 20"
                    fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                    aria-label="Toggle league favorite">
                    <path
                        d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                </svg>

                <div class="betwatch-match-league">
                    @php $flagCode = strtolower($matchGroup['country_code'] ?? ''); @endphp
                    @if ($flagCode && $flagCode !== 'international')
                        <img class="betguru-flag-img" src="https://flagcdn.com/w40/{{ $flagCode }}.png"
                            alt="{{ $matchGroup['country_name'] ?? 'Country' }} flag">
                    @endif
                    <a href="{{ route('football.country.page', ['country' => $matchGroup['country_code']]) }}"><span>{{ $matchGroup['country_name'] }}</span>
                        : </a>
                    <a
                        href="{{ route('football.league.page', ['league_id' => $matchGroup['matches'][0]['league_id'] ?? 1]) }}"><span>{{ $matchGroup['league_name'] }}</span></a>
                </div>
                <span class="betwatch-odds-head">1</span>
                <span class="betwatch-odds-head">X</span>
                <span class="betwatch-odds-head">2</span>
            </div>

            @foreach ($matchGroup['matches'] ?? ($matchGroup['rows'] ?? []) as $matchIndex => $match)
                @php
                    $parts = $match['parts'] ?? [];
                    $matchUrl = route('football.match.data', ['match_id' => (int) $match['id']]);
                    $hasExtraMarkets = !empty($match['extra_markets'] ?? []);
                    $isLiveMatch = $match['result_status'] == 'Live' ? true : false;
                    $matchKey = $match['id'] . '-' . $matchIndex;
                    $matchLabel = count($parts) === 3 ? trim(($parts[0] ?? 'Home') . ' - ' . ($parts[2] ?? 'Away')) : $parts[0] ?? 'Unknown Match';
                    $homeYellowCards = max((int) ($match['yellow_v1'] ?? ($match['yellow_team_1'] ?? 0)), 0);
                    $awayYellowCards = max((int) ($match['yellow_v2'] ?? ($match['yellow_team_2'] ?? 0)), 0);
                    $homeRedCards = max((int) ($match['red_v1'] ?? ($match['red_team_1'] ?? 0)), 0);
                    $awayRedCards = max((int) ($match['red_v2'] ?? ($match['red_team_2'] ?? 0)), 0);
                    // $homeGoalValue = is_numeric($match['goal_team_1'] ?? null) ? (int) $match['goal_team_1'] : null;
                    // $awayGoalValue = is_numeric($match['goal_team_2'] ?? null) ? (int) $match['goal_team_2'] : null;
                    // $homeGoalEventAt = is_numeric($match['home_goal_event_at'] ?? null) ? (int) $match['home_goal_event_at'] : null;
                    // $awayGoalEventAt = is_numeric($match['away_goal_event_at'] ?? null) ? (int) $match['away_goal_event_at'] : null;

                    // if (($homeGoalValue === null || $awayGoalValue === null) && isset($parts[1])) {
                    //     $scoreText = (string) $parts[1];

                    //     if (preg_match('/(\d+)\s*[-:]\s*(\d+)/', $scoreText, $scoreParts)) {
                    //         $homeGoalValue ??= (int) $scoreParts[1];
                    //         $awayGoalValue ??= (int) $scoreParts[2];
                    //     }
                    // }

                    // $shockHit = $match['shock_hits'][0] ?? null;
                    // $prematchDropHit = $match['prematch_drop_hits'][0] ?? null;
                    // $movementHit = $shockHit ?? $prematchDropHit;
                    // $movementMarket = $movementHit['market_name'] ?? 'Market';
                    // $movementRunner = $movementHit['runner_name'] ?? ($movementHit['selection_name'] ?? null);
                    // $movementStartOdd = $movementHit['initial_odd'] ?? ($movementHit['baseline_odd'] ?? '-');
                    // $movementCurrentOdd = $movementHit['current_odd'] ?? '-';
                    // $movementDrop = $movementHit['drop_percentage'] ?? null;
                    $statusText = (string) ($match['status'] ?? '');
                    // $isLiveMatch = ($match['is_live'] ?? false) === true || ($match['is_live'] ?? '') === 'live' || $statusText === 'Inplay' || ($match['type'] ?? '') === 'live';
                    // $isHalfTime = (bool) ($match['is_ht'] ?? false);
                    $isFinished = (bool) ($match['finished'] ?? false);
                    $homeGoalEventAt = (int) ($match['home_goal_event_at'] ?? 0);
                    $awayGoalEventAt = (int) ($match['away_goal_event_at'] ?? 0);

                    $homeGoalRemaining = max(0, 25 - (time() - $homeGoalEventAt));
                    $awayGoalRemaining = max(0, 25 - (time() - $awayGoalEventAt));
                    // $apiMinute = is_numeric($match['live_time'] ?? null) ? (int) $match['live_time'] : null;
                    // $apiExtraTime = is_numeric($match['extra_time'] ?? null) ? (int) $match['extra_time'] : null;
                    // $displayStatusText = $isHalfTime ? 'HT' : ($isFinished ? 'Finished' : $statusText);
                    // $hasLiveMinuteMark = $isLiveMatch && preg_match("/^\d+(?:\+\d+)?'$/", $displayStatusText);
                @endphp

                <div class="betwatch-match-row-shell {{ $hasExtraMarkets ? 'betwatch-match-row-shell--expandable' : '' }}"
                    data-football-realtime-row data-football-match-id="{{ $match['id'] ?? '' }}"
                    data-football-league-id="{{ $match['league_id'] ?? '' }}"
                    data-football-realtime-key="fixture:{{ $match['id'] ?? '' }}">
                    <div class="betwatch-match-row" data-football-score-match="{{ $match['id'] ?? '' }}"
                        @if ($hasExtraMarkets) data-collapse-trigger data-collapse-target="{{ $matchKey }}" aria-expanded="false" tabindex="0" role="button" @endif>
                        <svg class="betwatch-star-icon {{ $match['is_fav'] == 1 ? 'is-favorite' : '' }}"
                            data-favorite-trigger data-favorite-sport="football" data-favorite-type="match"
                            data-favorite-league-id="{{ $match['league_id'] ?? '' }}"
                            data-favorite-match-id="{{ $match['id'] ?? '' }}"
                            data-favorite-label="{{ $matchLabel }}" viewBox="0 0 20 20" fill="none"
                            stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                            aria-label="Toggle match favorite">
                            <path
                                d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                        </svg>

                        {{-- <span> --}}
                        @if ($isLiveMatch)
                            @if ($statusText !== 'Live' && $statusText !== 'HT' && $statusText !== 'Finished' && $statusText !== 'Inplay')
                                <span class="betwatch-status !text-[rgb(var(--danger))]">{{ trim($statusText) }}<span class="betwatch-live-minute-mark">'</span>
                                </span>
                            @else
                                <span class="betwatch-status !text-[rgb(var(--danger))]">{{ trim($statusText) }}</span>
                            @endif
                        @else
                            <span class="text-[0.73rem]">{{ $statusText }}</span>
                        @endif
                        {{-- </span> --}}

                        <span class="betwatch-teams">
                            @if (count($parts) === 3)
                                <span class="betwatch-team-with-cards betwatch-team-with-cards--home">
                                    @if ($match['result_status'] == 'Live' && $homeGoalRemaining > 0)
                                        <span class="betwatch-goal-indicator" data-football-goal-indicator="home" style="--betwatch-goal-lifetime: {{ $homeGoalRemaining }}s">
                                            GOAL
                                        </span>
                                    @endif
                                    <span class="betwatch-card-stack" aria-label="{{ $parts[0] }} cards">
                                        @for ($redCardIndex = 0; $redCardIndex < $homeRedCards; $redCardIndex++)
                                            <span class="betwatch-card-indicator betwatch-card-indicator--red"
                                                aria-hidden="true"></span>
                                        @endfor
                                        @if ($homeYellowCards > 0)
                                            <span class="betwatch-card-indicator betwatch-card-indicator--yellow"
                                                aria-hidden="true">
                                                {{ $homeYellowCards > 1 ? $homeYellowCards : '' }}
                                            </span>
                                        @endif
                                    </span>
                                    <a href="{{ $matchUrl }}"
                                        class="betwatch-teams__label">{{ $parts[0] }}</a>
                                </span>
                                <span
                                    class="betwatch-score {{ $isFinished ? 'betwatch-score--finished' : '' }}">{{ $parts[1] }}</span>
                                <span class="betwatch-team-with-cards betwatch-team-with-cards--away">
                                    <a href="{{ $matchUrl }}"
                                        class="betwatch-teams__label">{{ $parts[2] }}</a>
                                    <span class="betwatch-card-stack" aria-label="{{ $parts[2] }} cards">
                                        @if ($awayYellowCards > 0)
                                            <span class="betwatch-card-indicator betwatch-card-indicator--yellow"
                                                aria-hidden="true">
                                                {{ $awayYellowCards > 1 ? $awayYellowCards : '' }}
                                            </span>
                                        @endif
                                        @for ($redCardIndex = 0; $redCardIndex < $awayRedCards; $redCardIndex++)
                                            <span class="betwatch-card-indicator betwatch-card-indicator--red"
                                                aria-hidden="true"></span>
                                        @endfor
                                    </span>
                                    @if (
                                        $match['result_status'] == 'Live' && $awayGoalRemaining > 0)
                                        <span class="betwatch-goal-indicator" data-football-goal-indicator="away" style="--betwatch-goal-lifetime: {{ $awayGoalRemaining }}s">
                                            GOAL
                                        </span>
                                    @endif
                                </span>
                            @elseif (count($parts) > 0)
                                <a href="{{ $matchUrl }}" class="betwatch-teams__label">{{ $parts[0] }}</a>
                            @else
                                <span class="betwatch-teams__label">Unknown Match</span>
                            @endif
                        </span>

                        @if ($checkAuthAndSubscription['display_odds'])
                            @forelse ($match['odds'] ?? [] as $oddIndex => $odd)
                                @php
                                    if ($oddIndex == 0) {
                                        $firstOdd = $match['first_home_teams_odds'];
                                    } elseif ($oddIndex == 1) {
                                        $firstOdd = $match['first_draw_teams_odds'];
                                    } elseif ($oddIndex == 2) {
                                        $firstOdd = $match['first_away_teams_odds'];
                                    }

                                    $oddDirection = $odd['direction'] ?? ($odd['trend'] ?? 'neutral');
                                    $oddDirection = $oddDirection === 'same' ? 'neutral' : $oddDirection;
                                    $oddValue = $odd['odd'] ?? ($odd['value'] ?? '-');
                                    $oddDirection = is_numeric($oddValue) ? $oddDirection : 'neutral';
                                @endphp
                                <span class="betwatch-odd betwatch-odd--{{ $oddDirection }}">
                                    <x-football-odd-hover :first-odd="$firstOdd" :current-odd="$oddValue" :direction="$oddDirection">
                                        @if ($oddDirection == 'up')
                                            <span class="betwatch-trend">&uarr;</span>
                                        @elseif ($oddDirection == 'down')
                                            <span class="betwatch-trend">&darr;</span>
                                        @endif
                                        <span>{{ $oddValue }}</span>
                                    </x-football-odd-hover>
                                </span>
                            @empty
                                @for ($i = 0; $i < 3; $i++)
                                    <span class="betwatch-odd betwatch-odd--neutral">
                                        <span>-</span>
                                    </span>
                                @endfor
                            @endforelse
                        @else
                            @for ($i = 0; $i < 3; $i++)
                                <span class="betwatch-odd betwatch-odd--neutral">
                                    @include('lock_icon')
                                </span>
                            @endfor
                        @endif
                    </div>

                    @if ($hasExtraMarkets && $checkAuthAndSubscription['display_odds'])
                        <div class="betwatch-market-collapse" data-collapse-panel="{{ $matchKey }}" hidden>
                            @foreach ($match['extra_markets'] as $marketKey => $market)
                                @php
                                    $marketRunners = array_values((array) ($market['runners'] ?? []));
                                    $isThreeWayMarket = count($marketRunners) === 3;
                                    $isMatchOddsMarket = ($market['market_name'] ?? null) === 'Match Odds';
                                @endphp

                                <div
                                    class="betwatch-market-collapse__row {{ $isThreeWayMarket ? 'betwatch-market-collapse__row--three-way' : 'betwatch-market-collapse__row--two-way' }}">
                                    <svg class="betwatch-star-icon  {{ isset($market['is_fav']) && $market['is_fav'] == 1 ? 'is-favorite' : '' }} betwatch-market-collapse__star"
                                        data-favorite-trigger data-favorite-sport="football"
                                        data-favorite-type="market"
                                        data-favorite-league-id="{{ $match['league_id'] ?? '' }}"
                                        data-favorite-match-id="{{ $match['id'] ?? '' }}"
                                        data-favorite-market-id="{{ $market['api_market_id'] }}"
                                        data-favorite-label="{{ $matchLabel }}" viewBox="0 0 20 20" fill="none"
                                        stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                                        aria-label="Toggle match favorite">
                                        <path
                                            d="m10 2.8 2.16 4.38 4.84.7-3.5 3.42.82 4.82L10 13.84l-4.32 2.28.82-4.82L3 7.88l4.84-.7L10 2.8Z" />
                                    </svg>

                                    <a href="#" class="js-open-chart-modal"
                                        data-market-name='@json($market['market_name'] ?? 'Market')'
                                        data-match-id="{{ $match['id'] ?? '' }}"
                                        data-runner-ids='@json(collect($marketRunners)->pluck('runner_id')->values())'
                                        data-runner-labels='@json(collect($marketRunners)->map(function ($runner, $index) use ($isThreeWayMarket) {
                                                    if ($isThreeWayMarket) {
                                                        return $index === 0 ? '1' : ($index === 1 ? 'X' : '2');
                                                    }
                                                    return $runner['label'] ?? ($runner['runner_name'] ?? 'Option');
                                                })->values())'>
                                        <svg class="betwatch-market-collapse__chart-icon" viewBox="0 0 20 20"
                                            fill="none" stroke="currentColor" stroke-width="1.6"
                                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M2.8 15.5h14.4" />
                                            <path d="M5 15.2V10.6" />
                                            <path d="M9 15.2V7.8" />
                                            <path d="M13 15.2V5.5" />
                                            <path d="M17 15.2V8.8" />
                                            <path d="m4.1 6.9 2.8-2.3 2.6 1.5 4.1-3.2 2.1 1.1" />
                                        </svg>
                                    </a>

                                    <div class="betwatch-market-collapse__name">
                                        <span>{{ $market['market_name'] ?? 'Market' }}</span>
                                    </div>

                                    @foreach ($marketRunners as $selectionKey => $selection)
                                        @php
                                            $selectionDirection =
                                                $selection['odd_direction'] == 'same'
                                                    ? 'neutral'
                                                    : $selection['odd_direction'] ?? 'neutral';
                                            $selectionOdd = $selection['odd'] ?? null;
                                            $selectionDirection = is_numeric($selectionOdd)
                                                ? $selectionDirection
                                                : 'neutral';

                                            if ($isThreeWayMarket) {
                                                $displayLabel =
                                                    $selectionKey === 0 ? '1' : ($selectionKey === 1 ? 'X' : '2');
                                            } else {
                                                $displayLabel = $selection['label'] ?? 'Option:';
                                            }

                                            $selectionOdd = $selection['odd'] ?? null;
                                            $selectionFirstOdd =
                                                $selection['first_odd'] ??
                                                ($isMatchOddsMarket ? $selection['previous_odd'] ?? null : null);

                                            if (is_numeric($selectionOdd) && is_numeric($selectionFirstOdd)) {
                                                $selectionDirection = match (true) {
                                                    (float) $selectionOdd < (float) $selectionFirstOdd => 'down',
                                                    (float) $selectionOdd > (float) $selectionFirstOdd => 'up',
                                                    default => 'neutral',
                                                };
                                            }
                                        @endphp

                                        <div class="betwatch-market-collapse__selection">
                                            <div class="betwatch-market-collapse__selection-grid">
                                                <span
                                                    class="betwatch-market-collapse__label">{{ $displayLabel }}:&nbsp;&nbsp;</span>

                                                <div class="betwatch-market-collapse__values">
                                                    <span class="betwatch-market-collapse__volume">
                                                        {{ '€' . $selection['market_liquidity'] ?? '€0' }}
                                                    </span>

                                                    <span
                                                        class="betwatch-odd betwatch-odd--{{ $selectionDirection }}">
                                                        <x-football-odd-hover :first-odd="$selectionFirstOdd" :current-odd="$selectionOdd"
                                                            :direction="$selectionDirection">
                                                            @if ($selectionDirection === 'up')
                                                                <span class="betwatch-trend">&uarr;</span>
                                                            @elseif ($selectionDirection === 'down')
                                                                <span class="betwatch-trend">&darr;</span>
                                                            @endif

                                                            <span>{{ $selectionOdd ?? '-' }}</span>
                                                        </x-football-odd-hover>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </article>
    @endforeach
@endif
