@extends('users.layout.main')

@section('title', __('Pinnacle Odds Drops Guide · BF Markets'))

@section('content')

    <!-- ===== PAGE HEADER ===== -->
    <div class="page-header [display:flex] [align-items:center] [justify-content:space-between] [flex-wrap:wrap] [gap:12px] [margin:18px_0_14px] [margin:28px_0_8px]">
        <h1 class="page-title [font-size:24px] [font-weight:800] [color:rgb(var(--text-main))] [display:flex] [align-items:center] [gap:10px] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-danger-15)] [&_i]:[font-size:22px] max-[768px]:[font-size:18px] max-[768px]:[&_i]:[font-size:18px] max-[480px]:[font-size:16px] [font-size:32px] [font-weight:700] [letter-spacing:-0.5px] [gap:12px] [&_i]:[font-size:28px] max-[768px]:[font-size:24px] max-[480px]:[font-size:20px] max-[480px]:[gap:8px] max-[480px]:[&_i]:[font-size:20px] max-[768px]:[font-size:22px] max-[768px]:[gap:10px] max-[768px]:[&_i]:[font-size:22px] max-[480px]:[font-size:19px] max-[1024px]:[font-size:28px] max-[480px]:[font-size:21px]"><i class="fas fa-arrow-trend-down"></i> {{ __('Pinnacle Odds Drops Guide') }}</h1>
        <p class="page-subtitle [font-size:14px] [color:rgb(var(--text-muted))] [margin-top:6px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))] max-[768px]:[font-size:13px] max-[768px]:[font-size:14px]">{{ __('BF Markets — The Data Terminal for Sports Exchange Markets') }}</p>
    </div>

    <!-- ===== SECTION 1 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">1</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-danger-15)] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-info-circle"></i> {{ __('What are Pinnacle Odds Drops?') }}</span>
        </div>
        <p><strong>{{ __('Pinnacle Odds Drops') }}</strong> {{ __('is a real-time monitoring dashboard that shows Pinnacle odds drops and compares them with Betfair Exchange market prices.') }}</p>
        <div class="market-activity-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('In simple terms') }}</div>
            <p>{{ __('Pinnacle Odds Drops shows') }} <strong>{{ __('which match had an odds drop') }}</strong>, <strong>{{ __('how big the drop was') }}</strong>, <strong>{{ __('what Pinnacle and Betfair prices are right now') }}</strong>{{ __(', and') }} <strong>{{ __('whether there\'s an Edge (value)') }}</strong>.</p>
        </div>
        <p>{{ __('Unlike a traditional betting page, Pinnacle Odds Drops is not designed for placing bets. It is a') }} <strong>{{ __('monitoring tool') }}</strong> {{ __('— built for traders to quickly spot where the price moved.') }}</p>
        <div class="market-activity-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('Why is Pinnacle special?') }}</div>
            <p>{{ __('Pinnacle is considered one of the world\'s sharpest and most accurate bookmakers. Changes in its odds directly reflect the real market situation and value.') }}</p>
        </div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 2 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">2</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-danger-15)] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-bullseye"></i> {{ __('Purpose') }}</span>
        </div>
        <p>{{ __('The page answers four key questions instantly:') }}</p>
        <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
            <thead>
                <tr>
                    <th>{{ __('Question') }}</th>
                    <th>{{ __('Indicator') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ __('Which match had an odds drop?') }}</td>
                    <td class="value-col">{{ __('Drop %') }}</td>
                </tr>
                <tr>
                    <td>{{ __('How big was the drop?') }}</td>
                    <td class="value-col">NVP</td>
                </tr>
                <tr>
                    <td>{{ __('What are Pinnacle and Betfair prices?') }}</td>
                    <td class="value-col">{{ __('Edge %') }}</td>
                </tr>
                <tr>
                    <td>{{ __('Is there an Edge (value)?') }}</td>
                    <td class="value-col">{{ __('Edge %') }}</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 3 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">3</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-danger-15)] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-users"></i> {{ __('Who Uses Pinnacle Odds Drops?') }}</span>
        </div>
        <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
            <thead>
                <tr>
                    <th>{{ __('User Type') }}</th>
                    <th>{{ __('Use Case') }}</th>
                    <th>{{ __('How They Use It') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>{{ __('Traders') }}</strong></td>
                    <td>{{ __('Identify odds drops') }}</td>
                    <td>{{ __('Monitor Drop % and Edge %') }}</td>
                </tr>
                <tr>
                    <td><strong>{{ __('Arbitrageurs') }}</strong></td>
                    <td>{{ __('Find price discrepancies') }}</td>
                    <td>{{ __('Compare Pinnacle and Betfair prices') }}</td>
                </tr>
                <tr>
                    <td><strong>{{ __('Value Bettors') }}</strong></td>
                    <td>{{ __('Find value opportunities') }}</td>
                    <td>{{ __('Look for positive Edge %') }}</td>
                </tr>
                <tr>
                    <td><strong>{{ __('Data Analysts') }}</strong></td>
                    <td>{{ __('Study market behavior') }}</td>
                    <td>{{ __('Analyze drop patterns') }}</td>
                </tr>
                <tr>
                    <td><strong>{{ __('Casual Bettors') }}</strong></td>
                    <td>{{ __('Identify interesting matches') }}</td>
                    <td>{{ __('See where the money is moving') }}</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 4 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">4</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-danger-15)] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-sliders-h"></i> {{ __('Key Features') }}</span>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.1 Drop % — Odds Drop Percentage') }}</div>
            <p><strong>{{ __('Definition:') }}</strong> {{ __('Drop % shows how much the Pinnacle odds dropped.') }}</p>
            <div class="market-activity-example-block my-3 max-w-full overflow-x-auto rounded-xl border border-[var(--border-neutral-5-4)] bg-[var(--surface-neutral-0-10)] p-4 font-mono text-[13px] leading-relaxed whitespace-pre-line text-[var(--text-secondary-strong)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-[rgb(var(--text-soft))]">{{ __('Example: Pinnacle') }} <span class="hl-red font-semibold text-[var(--text-danger-15)] dark:text-[rgb(var(--danger))]">2.20</span> → <span class="hl-green font-semibold text-[var(--text-success-35)] dark:text-[rgb(var(--success))]">1.95</span> = <span class="hl-red font-semibold text-[var(--text-danger-15)] dark:text-[rgb(var(--danger))]">-11.4%</span></div>
            <p>{{ __('This means Pinnacle reduced the odds from 2.20 to 1.95 — an 11.4% drop.') }}</p>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.2 NVP (No-Vig Price) — Fair Price') }}</div>
            <p><strong>{{ __('Definition:') }}</strong> {{ __('NVP is the fair price — the odds without the bookmaker\'s margin (vig).') }}</p>
            <div class="market-activity-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
                <div class="label">{{ __('Example') }}</div>
                <p>{{ __('NVP = 2.02 → This is the fair price without the bookmaker\'s margin.') }}</p>
            </div>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.3 Pinnacle Edge — Pinnacle Value') }}</div>
            <p><strong>{{ __('Definition:') }}</strong> {{ __('Pinnacle Edge shows Pinnacle\'s price deviation from the fair price.') }}</p>
            <div class="market-activity-example-block my-3 max-w-full overflow-x-auto rounded-xl border border-[var(--border-neutral-5-4)] bg-[var(--surface-neutral-0-10)] p-4 font-mono text-[13px] leading-relaxed whitespace-pre-line text-[var(--text-secondary-strong)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-[rgb(var(--text-soft))]">{{ __('Example:') }} <span class="hl-blue font-semibold text-[rgb(var(--primary))] dark:text-[rgb(var(--primary))]">{{ __('Pinnacle Edge') }}</span> = (1.95 / 2.02 - 1) × 100 = <span class="hl-red font-semibold text-[var(--text-danger-15)] dark:text-[rgb(var(--danger))]">-3.47%</span></div>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.4 Betfair Edge — Betfair Value') }}</div>
            <p><strong>{{ __('Definition:') }}</strong> {{ __('Betfair Edge shows Betfair\'s Back price deviation from the fair price.') }}</p>
            <div class="market-activity-example-block my-3 max-w-full overflow-x-auto rounded-xl border border-[var(--border-neutral-5-4)] bg-[var(--surface-neutral-0-10)] p-4 font-mono text-[13px] leading-relaxed whitespace-pre-line text-[var(--text-secondary-strong)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-[rgb(var(--text-soft))]">{{ __('Example:') }} <span class="hl-green font-semibold text-[var(--text-success-35)] dark:text-[rgb(var(--success))]">{{ __('Betfair Edge') }}</span> = (2.14 / 2.02 - 1) × 100 = <span class="hl-green font-semibold text-[var(--text-success-35)] dark:text-[rgb(var(--success))]">+5.94%</span></div>
            <div class="market-activity-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
                <div class="label">{{ __('Interpretation') }}</div>
                <p><span class="hl-green font-semibold text-[var(--text-success-35)] dark:text-[rgb(var(--success))]">> +3%</span> {{ __('→ Green (value)') }}</p>
                <p><span class="hl-orange font-semibold text-[var(--text-warning-25)] dark:text-[rgb(var(--warning))]">0% - +3%</span> {{ __('→ Yellow (borderline)') }}</p>
                <p><span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">< 0%</span> {{ __('→ Grey (no value)') }}</p>
            </div>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.5 Live vs Prematch Modes') }}</div>
            <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
                <thead>
                    <tr>
                        <th>{{ __('Mode') }}</th>
                        <th>{{ __('Description') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="value-col">{{ __('Prematch') }}</td>
                        <td>{{ __('Before the match starts') }}</td>
                    </tr>
                    <tr>
                        <td class="value-col">{{ __('Live') }}</td>
                        <td>{{ __('During the match') }}</td>
                    </tr>
                </tbody>
            </table></div>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.6 Sport Filters') }}</div>
            <p>{{ __('The page supports the following sports:') }}</p>
            <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
                <li>{{ __('⚽ Soccer') }}</li>
                <li>{{ __('🎾 Tennis') }}</li>
                <li>{{ __('🏀 Basketball') }}</li>
                <li>{{ __('🏒 Ice Hockey') }}</li>
                <li>{{ __('⚾ Baseball') }}</li>
                <li>{{ __('🏏 Cricket') }}</li>
                <li>{{ __('🎮 Esports') }}</li>
            </ul>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.7 Simple vs Advanced View') }}</div>
            <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
                <thead>
                    <tr>
                        <th>{{ __('View') }}</th>
                        <th>{{ __('Description') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="value-col">{{ __('Simple') }}</td>
                        <td>{{ __('Compact view — one row per alert') }}</td>
                    </tr>
                    <tr>
                        <td class="value-col">{{ __('Advanced') }}</td>
                        <td>{{ __('Detailed view — more info (NVP, Pinnacle Edge, Betfair sizes)') }}</td>
                    </tr>
                </tbody>
            </table></div>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.8 Group by Match') }}</div>
            <p>{{ __('If a match has multiple alerts (e.g. 4 different totals), they are grouped into one card:') }}</p>
            <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
                <li><strong>{{ __('Header:') }}</strong> {{ __('Match + sport + league + alert count') }}</li>
                <li><strong>{{ __('Click header:') }}</strong> {{ __('Collapse / Expand') }}</li>
                <li><strong>{{ __('Inside:') }}</strong> {{ __('Individual alerts') }}</li>
            </ul>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.9 Detail Overlay') }}</div>
            <p>{{ __('Clicking any alert opens a detailed popup:') }}</p>
            <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
                <thead>
                    <tr>
                        <th>{{ __('Section') }}</th>
                        <th>{{ __('Description') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="value-col">{{ __('Top Block') }}</td>
                        <td>{{ __('Drop, Pinnacle, NVP, Pinnacle Edge, Betfair Back/Lay, Sizes, Betfair Edge, Time') }}</td>
                    </tr>
                    <tr>
                        <td class="value-col">{{ __('Full Market') }}</td>
                        <td>{{ __('Table (Outcome, Pinnacle, NVP, Betfair, Edge)') }}</td>
                    </tr>
                    <tr>
                        <td class="value-col">{{ __('Recent Alerts') }}</td>
                        <td>{{ __('Last 5 alerts for the same match') }}</td>
                    </tr>
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 5 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">5</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-danger-15)] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-desktop"></i> {{ __('How to Read the Terminal') }}</span>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('5.1 Column Headers') }}</div>
        <div class="market-activity-example-block my-3 max-w-full overflow-x-auto rounded-xl border border-[var(--border-neutral-5-4)] bg-[var(--surface-neutral-0-10)] p-4 font-mono text-[13px] leading-relaxed whitespace-pre-line text-[var(--text-secondary-strong)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-[rgb(var(--text-soft))]"><span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">|</span> {{ __('DROP') }} <span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">|</span> {{ __('MATCH · MARKET') }} <span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">|</span> {{ __('PINNACLE ODDS') }} <span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">|</span> NVP <span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">|</span> {{ __('BETFAIR') }} <span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">|</span> {{ __('EDGE %') }} <span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">|</span></div>
        <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
            <thead>
                <tr>
                    <th>{{ __('Column') }}</th>
                    <th>{{ __('Description') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="value-col">{{ __('DROP') }}</td>
                    <td>{{ __('Drop percentage (red)') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('MATCH') }}</td>
                    <td>{{ __('Match + market') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('PINNACLE ODDS') }}</td>
                    <td>{{ __('Old → new odds') }}</td>
                </tr>
                <tr>
                    <td class="value-col">NVP</td>
                    <td>{{ __('No-Vig Price (fair price)') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('BETFAIR') }}</td>
                    <td>{{ __('Betfair odds') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('EDGE %') }}</td>
                    <td>{{ __('Edge percentage') }}</td>
                </tr>
            </tbody>
        </table></div>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('5.2 Alert Row Example') }}</div>
        <div class="market-activity-example-block my-3 max-w-full overflow-x-auto rounded-xl border border-[var(--border-neutral-5-4)] bg-[var(--surface-neutral-0-10)] p-4 font-mono text-[13px] leading-relaxed whitespace-pre-line text-[var(--text-secondary-strong)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-[rgb(var(--text-soft))]">
            <span class="hl-red font-semibold text-[var(--text-danger-15)] dark:text-[rgb(var(--danger))]">-11.4%</span> <span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">|</span> {{ __('Inter v Napoli · Moneyline') }} <span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">|</span> <span class="hl-blue font-semibold text-[rgb(var(--primary))] dark:text-[rgb(var(--primary))]">2.20 → 1.95</span> <span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">|</span> <span class="hl-blue font-semibold text-[rgb(var(--primary))] dark:text-[rgb(var(--primary))]">2.02</span> <span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">|</span> <span class="hl-green font-semibold text-[var(--text-success-35)] dark:text-[rgb(var(--success))]">2.14</span> <span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">|</span> <span class="hl-green font-semibold text-[var(--text-success-35)] dark:text-[rgb(var(--success))]">+5.9%</span>
        </div>
        </div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 6 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">6</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-danger-15)] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-book"></i> {{ __('Glossary of Terms') }}</span>
        </div>
        <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
            <thead>
                <tr>
                    <th>{{ __('Term') }}</th>
                    <th>{{ __('Definition') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="value-col">{{ __('Drop') }}</td>
                    <td>{{ __('Odds movement to a lower price') }}</td>
                </tr>
                <tr>
                    <td class="value-col">NVP</td>
                    <td>{{ __('No-Vig Price — fair price without margin') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Edge') }}</td>
                    <td>{{ __('Value percentage vs fair price') }}</td>
                </tr>
                <tr>
                    <td class="value-col">Pinnacle</td>
                    <td>{{ __('Bookmaker known for sharp odds') }}</td>
                </tr>
                <tr>
                    <td class="value-col">Betfair</td>
                    <td>{{ __('Exchange market for Back/Lay prices') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Sharp Money') }}</td>
                    <td>{{ __('Money from professional bettors') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('CLV') }}</td>
                    <td>{{ __('Closing Line Value — value vs closing line') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Back') }}</td>
                    <td>{{ __('Betting FOR a selection') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Lay') }}</td>
                    <td>{{ __('Betting AGAINST a selection') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Live') }}</td>
                    <td>{{ __('During the match') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Prematch') }}</td>
                    <td>{{ __('Before the match starts') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Alert') }}</td>
                    <td>{{ __('Notification of a market movement') }}</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 7 — NEW: How to Use This Strategy Effectively? ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">7</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-danger-15)] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-lightbulb"></i> {{ __('How to Use This Strategy Effectively?') }}</span>
        </div>
        <p>{{ __('Monitoring Pinnacle odds drops is') }} <strong>{{ __('one of the most powerful tools') }}</strong> {{ __('in sports betting. Here are four main reasons why this is critically important:') }}</p>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('7.1 Following "Sharp Money"') }}</div>
            <p>{{ __('Pinnacle stands out for its very high limits and the fact that it does not block professional players. When odds drop sharply, it almost always means that professional syndicates or large bettors have placed significant money on a specific outcome. Knowing this information allows you to follow the market experts\' trend.') }}</p>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('7.2 Reflection of Insider Information') }}</div>
            <p>{{ __('Sharp odds drops often happen before the news becomes public. This could be:') }}</p>
            <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
                <li>{{ __('Sudden injury or disqualification of a key player') }}</li>
                <li>{{ __('Internal team conflict or a coaching change') }}</li>
                <li>{{ __('Sharp change in motivation (e.g., if a competitor lost points)') }}</li>
            </ul>
            <p>{{ __('Pinnacle\'s algorithms and users react instantly, giving you an advantage.') }}</p>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('7.3 Finding Value Bets') }}</div>
            <p>{{ __('This is the most practical benefit:') }}</p>
            <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
                <li>{{ __('When the Pinnacle odds drop (e.g., from 2.10 to 1.80), other, softer bookmakers are often slow to react') }}</li>
                <li>{{ __('You can find a local or less popular bookmaker where the old, higher odds (2.10) are still available') }}</li>
                <li>{{ __('This way, you place a bet at odds higher than their real probability, which means guaranteed profit in the long run (CLV — Closing Line Value)') }}</li>
            </ul>
            <div class="market-activity-example-block my-3 max-w-full overflow-x-auto rounded-xl border border-[var(--border-neutral-5-4)] bg-[var(--surface-neutral-0-10)] p-4 font-mono text-[13px] leading-relaxed whitespace-pre-line text-[var(--text-secondary-strong)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-[rgb(var(--text-soft))]">
                {{ __('Example: Pinnacle') }} <span class="hl-red font-semibold text-[var(--text-danger-15)] dark:text-[rgb(var(--danger))]">2.10 → 1.80</span>
                {{ __('Soft Bookmaker still') }} <span class="hl-green font-semibold text-[var(--text-success-35)] dark:text-[rgb(var(--success))]">2.10</span>
                → <span class="hl-green font-semibold text-[var(--text-success-35)] dark:text-[rgb(var(--success))]">{{ __('Value Bet') }}</span> {{ __('(value exists)') }}
            </div>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('7.4 Match-Fixing Indicator') }}</div>
            <p>{{ __('In lower leagues or less popular sports,') }} <strong>{{ __('anomalous and sharp odds drops') }}</strong> {{ __('often indicate suspicious or fixed matches. If odds drop without any logical reason, it is a signal that something unsportsmanlike is happening in the market.') }}</p>
            <div class="market-activity-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
                <div class="label">{{ __('How BF Markets Helps') }}</div>
                <p>{{ __('BF Markets\'') }} <strong>{{ __('Pinnacle Odds Drops') }}</strong> {{ __('page monitors exactly this strategy. It automatically records Pinnacle odds drops, analyzes Betfair Exchange prices, and displays') }} <strong>{{ __('Edge %') }}</strong> {{ __('— allowing you to:') }}</p>
                <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
                    <li>{{ __('Quickly see where "sharp money" is moving') }}</li>
                    <li>{{ __('Discover Value Bets by Edge %') }}</li>
                    <li>{{ __('Receive signals about anomalous drops') }}</li>
                    <li>{{ __('Get automated alerts on Telegram') }}</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 8 — Practical Examples ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">8</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-danger-15)] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-flask"></i> {{ __('Practical Examples') }}</span>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('Example 1: Sharp Drop (Football)') }}</div>
            <div class="market-activity-example-block my-3 max-w-full overflow-x-auto rounded-xl border border-[var(--border-neutral-5-4)] bg-[var(--surface-neutral-0-10)] p-4 font-mono text-[13px] leading-relaxed whitespace-pre-line text-[var(--text-secondary-strong)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-[rgb(var(--text-soft))]">
                {{ __('Inter vs Napoli — Moneyline Pinnacle:') }} <span class="hl-red font-semibold text-[var(--text-danger-15)] dark:text-[rgb(var(--danger))]">2.20 → 1.95</span> (<span class="hl-red font-semibold text-[var(--text-danger-15)] dark:text-[rgb(var(--danger))]">-11.4%</span>{{ __(') NVP:') }} <span class="hl-blue font-semibold text-[rgb(var(--primary))] dark:text-[rgb(var(--primary))]">2.02</span>
                {{ __('Betfair Back:') }} <span class="hl-green font-semibold text-[var(--text-success-35)] dark:text-[rgb(var(--success))]">2.14</span>
                {{ __('Betfair Edge:') }} <span class="hl-green font-semibold text-[var(--text-success-35)] dark:text-[rgb(var(--success))]">+5.9%</span>
            </div>
            <p><strong>{{ __('Interpretation:') }}</strong></p>
            <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
                <li><strong>{{ __('Drop -11.4%') }}</strong> {{ __('→ Pinnacle sharply reduced the odds — sharp money has entered') }}</li>
                <li><strong>{{ __('Betfair Edge +5.9%') }}</strong> {{ __('→ Betfair price is above fair price — value exists') }}</li>
                <li><strong>{{ __('NVP 2.02') }}</strong> {{ __('→ Fair price') }}</li>
            </ul>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('Example 2: Prematch Drop (Basketball)') }}</div>
            <div class="market-activity-example-block my-3 max-w-full overflow-x-auto rounded-xl border border-[var(--border-neutral-5-4)] bg-[var(--surface-neutral-0-10)] p-4 font-mono text-[13px] leading-relaxed whitespace-pre-line text-[var(--text-secondary-strong)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-[rgb(var(--text-soft))]">
                {{ __('Sunshine Coast Phoenix vs Cairns Dolphins — Moneyline Pinnacle:') }} <span class="hl-red font-semibold text-[var(--text-danger-15)] dark:text-[rgb(var(--danger))]">2.86 → 2.70</span> (<span class="hl-red font-semibold text-[var(--text-danger-15)] dark:text-[rgb(var(--danger))]">-5.6%</span>{{ __(') NVP:') }} <span class="hl-blue font-semibold text-[rgb(var(--primary))] dark:text-[rgb(var(--primary))]">3.04</span>
                {{ __('Betfair:') }} <span class="hl-muted text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">{{ __('not matched') }}</span>
            </div>
            <p><strong>{{ __('Interpretation:') }}</strong></p>
            <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
                <li><strong>{{ __('Drop -5.6%') }}</strong> {{ __('→ Small drop detected') }}</li>
                <li><strong>{{ __('Betfair not matched') }}</strong> {{ __('→ Betfair price unavailable') }}</li>
                <li><strong>{{ __('NVP 3.04') }}</strong> {{ __('→ Fair price') }}</li>
            </ul>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('Example 3: Value Bet (Soft Bookmaker vs Pinnacle)') }}</div>
            <div class="market-activity-example-block my-3 max-w-full overflow-x-auto rounded-xl border border-[var(--border-neutral-5-4)] bg-[var(--surface-neutral-0-10)] p-4 font-mono text-[13px] leading-relaxed whitespace-pre-line text-[var(--text-secondary-strong)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))] dark:text-[rgb(var(--text-soft))]">
                {{ __('Pinnacle:') }} <span class="hl-red font-semibold text-[var(--text-danger-15)] dark:text-[rgb(var(--danger))]">2.10 → 1.80</span> {{ __('(-14.3%) Soft Bookmaker: still') }} <span class="hl-green font-semibold text-[var(--text-success-35)] dark:text-[rgb(var(--success))]">2.10</span>

                NVP: <span class="hl-blue font-semibold text-[rgb(var(--primary))] dark:text-[rgb(var(--primary))]">1.85</span>
                {{ __('Soft Bookmaker Edge:') }} <span class="hl-green font-semibold text-[var(--text-success-35)] dark:text-[rgb(var(--success))]">+13.5%</span>
            </div>
            <p><strong>{{ __('Interpretation:') }}</strong></p>
            <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
                <li><strong>{{ __('Pinnacle reduced the odds') }}</strong> {{ __('→ The market is changing direction') }}</li>
                <li><strong>{{ __('Soft Bookmaker still has old odds') }}</strong> {{ __('→ Opportunity') }}</li>
                <li><strong>{{ __('+13.5% Edge') }}</strong> {{ __('→ Value exists — CLV') }}</li>
            </ul>
        </div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 9 — Performance ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">9</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-danger-15)] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-cogs"></i> {{ __('Performance & Architecture') }}</span>
        </div>
        <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
            <li><strong>{{ __('Updates:') }}</strong> {{ __('Real-time, event-driven') }}</li>
            <li><strong>{{ __('Live and Prematch:') }}</strong> {{ __('Both modes run in parallel') }}</li>
            <li><strong>{{ __('Latency:') }}</strong> {{ __('Kept low for fast decision-making') }}</li>
            <li><strong>{{ __('History:') }}</strong> {{ __('Short rolling window per event') }}</li>
        </ul>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 10 — Summary ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">10</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-danger-15)] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-check-circle"></i> {{ __('Summary') }}</span>
        </div>
        <p><strong>{{ __('Pinnacle Odds Drops is a professional-grade market monitoring tool.') }}</strong></p>
        <p>{{ __('It is designed for:') }}</p>
        <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
            <li><strong>{{ __('Traders') }}</strong> {{ __('who need real-time market intelligence') }}</li>
            <li><strong>{{ __('Arbitrageurs') }}</strong> {{ __('who scan for price discrepancies') }}</li>
            <li><strong>{{ __('Analysts') }}</strong> {{ __('who study market behavior') }}</li>
            <li><strong>{{ __('Value Bettors') }}</strong> {{ __('who are looking for value') }}</li>
        </ul>
        <div class="market-activity-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('Core Principle') }}</div>
            <p><strong>{{ __('It is not a betting page — it is a data terminal.') }}</strong></p>
        </div>
    </div>

@endsection
