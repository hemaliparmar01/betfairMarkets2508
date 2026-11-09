@php
    $periods = $event['statistics_periods'] ?? [];
    if ($periods === [] && ($event['live_stats'] ?? []) !== []) {
        foreach ($event['live_stats'] as $key => $values) {
            $percent = ($event['live_stats_meta'][$key]['percent'] ?? false) ? '%' : '';
            $periods['ALL']['Match statistics'][$key] = [
                'label' => $event['live_stats_meta'][$key]['label'] ?? \Illuminate\Support\Str::headline($key),
                'home' => isset($values['home']) ? $values['home'].$percent : null,
                'away' => isset($values['away']) ? $values['away'].$percent : null,
            ];
        }
    }

    $sections = [
        'Match Overview' => [
            'Ball possession' => ['possession', 'ball_possession'],
            'Distance covered' => ['distance_covered', 'total_distance'],
            'Big chances' => ['big_chance_created', 'big_chances'],
            'Total shots' => ['total_shots'],
            'Goalkeeper saves' => ['goalkeeper_saves', 'total_saves', 'saves'],
            'Number of sprints' => ['number_of_sprints', 'sprints'],
            'Corner kicks' => ['corners'],
            'Fouls' => ['fouls'],
            'Passes' => ['total_passes'],
            'Tackles' => ['tackles', 'total_tackles'],
            'Free kicks' => ['free_kicks'],
            'Yellow cards' => ['yellow_cards'],
            'Average rating' => ['average_rating'],
        ],
        'Shots' => [
            'Total shots' => ['total_shots'],
            'Shots on target' => ['shots_on_target'],
            'Expected goals (xG)' => ['expected_goals', 'expected_goals_x_g', 'xg'],
            'Expected goals on target' => ['expected_goals_on_target', 'expected_goals_on_target_xgot', 'xgot'],
            'Shots off target' => ['shots_off_target'],
            'Blocked shots' => ['blocked_shots'],
            'Shots inside box' => ['shots_inside_box', 'total_shots_inside_box'],
            'Shots outside box' => ['shots_outside_box', 'total_shots_outside_box'],
        ],
        'Attack' => [
            'Big chances scored' => ['big_chances_scored', 'big_chance_scored'],
            'Big chances missed' => ['big_chances_missed', 'big_chance_missed'],
            'Through balls' => ['through_balls'],
            'Touches in penalty area' => ['touches_in_penalty_area', 'touches_in_opposition_box'],
            'Fouled in final third' => ['fouled_in_final_third'],
            'Offsides' => ['offsides'],
        ],
        'Passes' => [
            'Accurate passes' => ['accurate_passes'],
            'Throw-ins' => ['throw_ins'],
            'Final third entries' => ['final_third_entries'],
            'Final third phase' => ['final_third_phase'],
            'Long balls' => ['long_balls', 'accurate_long_balls'],
            'Crosses' => ['crosses', 'accurate_cross'],
        ],
        'Duels & Defending' => [
            'Duels' => ['duels', 'duel_won_percent'],
            'Dispossessed' => ['dispossessed'],
            'Ground duels' => ['ground_duels', 'ground_duels_percentage'],
            'Aerial duels' => ['aerial_duels', 'aerial_duels_percentage'],
            'Dribbles' => ['dribbles', 'dribbles_percentage'],
            'Tackles won' => ['tackles_won', 'won_tackle_percent'],
            'Total tackles' => ['tackles', 'total_tackles'],
            'Interceptions' => ['interceptions'],
            'Recoveries' => ['recoveries', 'ball_recovery'],
            'Clearances' => ['clearances', 'total_clearance'],
        ],
        'Goalkeeping' => [
            'Total saves' => ['total_saves', 'goalkeeper_saves', 'saves'],
            'Goals prevented' => ['goals_prevented'],
            'Big saves' => ['big_saves'],
            'High claims' => ['high_claims', 'good_high_claim'],
            'Punches' => ['punches', 'punches_made'],
            'Goal kicks' => ['goal_kicks'],
        ],
    ];
    $tabs = [
        'ALL' => ['Full Match', 'group-has-[input[value="ALL"]:checked]:block'],
        '1ST' => ['First Half', 'group-has-[input[value="1ST"]:checked]:block'],
        '2ND' => ['Second Half', 'group-has-[input[value="2ND"]:checked]:block'],
    ];
    $number = static function ($value): ?float {
        if (preg_match('/(-?\d+(?:\.\d+)?)\s*%/', (string) $value, $matches)) {
            return (float) $matches[1];
        }

        return is_numeric($value) || preg_match('/^-?\d+(?:\.\d+)?\s*km$/i', trim((string) $value)) ? (float) $value : null;
    };
@endphp

<div class="group flex min-h-0 flex-col" data-statistics-periods>
    <div class="flex shrink-0 border-b border-[#d1dbe6] bg-[#f0f4f8] dark:bg-[rgb(var(--bg))]" role="radiogroup" aria-label="{{ __('Statistics period') }}">
        @foreach ($tabs as $period => [$title, $visibility])
            <label class="flex-1 cursor-pointer border-b-[3px] border-transparent px-2 py-3 text-center text-xs font-bold uppercase tracking-wide text-[#5a7d99] has-checked:border-current has-checked:bg-white has-checked:text-[#0b2a40] has-focus-visible:outline dark:has-checked:bg-[rgb(var(--surface))] dark:has-checked:text-[rgb(var(--primary))]">
                <input type="radio" name="live-stats-period-{{ $event['sportsApiPro_match_id'] }}" value="{{ $period }}" class="sr-only" @checked($period === 'ALL')>
                {{ __($title) }}
            </label>
        @endforeach
    </div>

    @foreach ($tabs as $period => [$title, $visibility])
        @php
            $items = collect($periods[$period] ?? [])->collapse()->all();
            if ($period === 'ALL') {
                foreach (['corners', 'yellow_cards'] as $stat) {
                    if (isset($event['live_stats'][$stat])) {
                        $items[$stat] = $event['live_stats'][$stat];
                    }
                }
            }
            foreach ($items as $item) {
                $labelKey = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::camel(preg_replace('/[^a-z0-9 ]/i', '', $item['label'] ?? '')));
                if ($labelKey !== '') {
                    $items[$labelKey] ??= $item;
                }
            }
            $xg = $items['expected_goals'] ?? $items['expected_goals_x_g'] ?? $items['xg'] ?? [];
            $periodSections = $period === 'ALL' ? $sections : [
                'Match Overview' => array_diff_key($sections['Match Overview'], array_flip(['Distance covered', 'Number of sprints', 'Average rating'])),
                'Shots' => array_slice($sections['Shots'], 0, 5),
            ];
        @endphp
        <div class="hidden max-h-[75vh] min-h-0 space-y-4 overflow-y-auto overscroll-contain bg-[#f7f9fc] p-5 dark:bg-[rgb(var(--bg))] {{ $visibility }}" data-statistics-period="{{ $period }}">
            @if ($items === [])
                <p class="py-6 text-center text-sm text-[var(--text-slate-500)] dark:text-[var(--text-slate-400)]">{{ __('Statistics for this period are not available yet.') }}</p>
            @else
                @foreach ($periodSections as $section => $rows)
                    <section class="rounded-lg border border-[#e3ecf5] bg-white p-4 shadow-sm dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]">
                        <h4 class="mb-3 border-b border-[#edf2f7] pb-2 text-[11px] font-bold uppercase tracking-wide text-[#5a7d99] dark:border-[rgb(var(--border))] dark:text-[var(--text-slate-400)]">{{ __($section) }} ({{ __($title) }})</h4>
                        @if ($section === 'Match Overview')
                            <div class="mb-4 flex items-center justify-center gap-2 rounded-lg border border-[#c6d8ea] bg-[#f0f7ff] p-4 text-center sm:gap-5 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))]">
                                <span class="text-[22px] font-extrabold tabular-nums text-[#0b2a40] sm:text-[28px] dark:text-[rgb(var(--primary))]">{{ $xg['home'] ?? '—' }}</span>
                                <span class="rounded-full bg-[#e8f2fc] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-[#1a6b9c] sm:text-xs">{{ __('Expected goals (xG)') }}</span>
                                <span class="text-[22px] font-extrabold tabular-nums text-[#0b2a40] sm:text-[28px] dark:text-[rgb(var(--primary))]">{{ $xg['away'] ?? '—' }}</span>
                            </div>
                        @endif
                        @foreach ($rows as $label => $keys)
                            @php
                                $item = null;
                                foreach ($keys as $key) {
                                    if (isset($items[$key])) {
                                        $item = $items[$key];
                                        break;
                                    }
                                }
                                $home = $item['home'] ?? null;
                                $away = $item['away'] ?? null;
                                $homeValue = $number($home);
                                $awayValue = $number($away);
                                $comparable = $homeValue !== null && $awayValue !== null;
                                $possession = $label === 'Ball possession';
                            @endphp
                            <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,3fr)_minmax(0,1fr)] items-center gap-2 py-1.5 text-xs min-[601px]:text-[13px]" data-statistic="{{ $keys[0] }}">
                                <span @class(['break-words text-left tabular-nums', 'font-extrabold text-[#0b2a40] dark:text-[rgb(var(--primary))]' => $comparable && $homeValue > $awayValue, 'font-medium text-[#8aaccc] dark:text-[var(--text-slate-400)]' => ! $comparable || $homeValue <= $awayValue])>{{ $possession && is_numeric($home) ? $home.'%' : ($home ?? '—') }}</span>
                                <span class="text-center font-medium text-[#5a7d99] dark:text-[var(--text-slate-300)]">{{ __($label) }}</span>
                                <span @class(['break-words text-right tabular-nums', 'font-extrabold text-[#0b2a40] dark:text-[rgb(var(--primary))]' => $comparable && $awayValue > $homeValue, 'font-medium text-[#8aaccc] dark:text-[var(--text-slate-400)]' => ! $comparable || $awayValue <= $homeValue])>{{ $possession && is_numeric($away) ? $away.'%' : ($away ?? '—') }}</span>
                            </div>
                        @endforeach
                    </section>
                @endforeach
            @endif
        </div>
    @endforeach
</div>
