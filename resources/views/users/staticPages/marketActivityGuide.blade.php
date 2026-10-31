@extends('users.layout.main')

@section('title', __('Fixtures · BF Markets'))

@section('content')

    <!-- ===== PAGE HEADER ===== -->
    <div class="page-header [display:flex] [align-items:center] [justify-content:space-between] [flex-wrap:wrap] [gap:12px] [margin:18px_0_14px] [margin:28px_0_8px]">
        <h1 class="page-title [font-size:24px] [font-weight:800] [color:rgb(var(--text-main))] [display:flex] [align-items:center] [gap:10px] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[font-size:22px] max-[768px]:[font-size:18px] max-[768px]:[&_i]:[font-size:18px] max-[480px]:[font-size:16px] [font-size:32px] [font-weight:700] [letter-spacing:-0.5px] [gap:12px] [&_i]:[font-size:28px] max-[768px]:[font-size:24px] max-[480px]:[font-size:20px] max-[480px]:[gap:8px] max-[480px]:[&_i]:[font-size:20px] max-[768px]:[font-size:22px] max-[768px]:[gap:10px] max-[768px]:[&_i]:[font-size:22px] max-[480px]:[font-size:19px] max-[1024px]:[font-size:28px] max-[480px]:[font-size:21px]"><i class="fas fa-book-open"></i> {{ __('Market Activity Guide') }}</h1>
        <p class="page-subtitle [font-size:14px] [color:rgb(var(--text-muted))] [margin-top:6px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))] max-[768px]:[font-size:13px] max-[768px]:[font-size:14px]">{{ __('BF Markets — The Data Terminal for Sports Exchange Markets') }}</p>
    </div>

    <!-- ===== SECTION 1 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">1</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-info-circle"></i> {{ __('What is Market Activity?') }}</span>
        </div>
        <p><strong>{{ __('Market Activity') }}</strong> {{ __('is a real-time monitoring dashboard for Betfair Exchange markets across multiple sports. It displays live odds, liquidity, and market movement in a single, high-density view — designed for fast decision-making.') }}</p>
        <div class="market-activity-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('In simple terms') }}</div>
            <p>{{ __('Market Activity shows') }} <strong>{{ __('where the money is') }}</strong>, <strong>{{ __('where it\'s moving') }}</strong>{{ __(', and') }}
                <strong>{{ __('how fast') }}</strong> {{ __('— across all supported live and upcoming events, according to the respective sport and market type.') }}</p>
        </div>
        <p>{{ __('Unlike a traditional betting page, Market Activity is not designed for placing bets. It is a') }} <strong>{{ __('data terminal') }}</strong> {{ __('— built for scanning, filtering, and understanding market behavior at a glance.') }}</p>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 2 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">2</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-bullseye"></i> {{ __('Purpose') }}</span>
        </div>
        <p>{{ __('The page answers four key questions instantly:') }}</p>
        <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
            <thead>
                <tr>
                    <th>{{ __('Question') }}</th>
                    <th>{{ __('Indicator') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ __('Where is the money right now?') }}</td>
                    <td class="value-col">{{ __('Balance') }}</td>
                </tr>
                <tr>
                    <td>{{ __('Where is the money moving toward?') }}</td>
                    <td class="value-col">{{ __('Momentum') }}</td>
                </tr>
                <tr>
                    <td>{{ __('How fast is Back Size changing?') }}</td>
                    <td class="value-col">{{ __('Δ (Delta)') }}</td>
                </tr>
                <tr>
                    <td>{{ __('How unpredictable is the market?') }}</td>
                    <td class="value-col">{{ __('Volatility') }}</td>
                </tr>
            </tbody>
        </table></div>
        <p>{{ __('These four indicators are calculated in real-time from Betfair Exchange odds and liquidity data, updated via') }}
            <strong>{{ __('real-time, low-latency updates') }}</strong>.</p>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 3 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">3</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-users"></i> {{ __('Who Uses Market Activity?') }}</span>
        </div>
        <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
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
                    <td>{{ __('Identify market trends and enter/exit positions') }}</td>
                    <td>{{ __('Monitor Momentum and Δ to catch price movements early') }}</td>
                </tr>
                <tr>
                    <td><strong>{{ __('Arbitrageurs') }}</strong></td>
                    <td>{{ __('Find price discrepancies across markets') }}</td>
                    <td>{{ __('Compare Back/Lay odds and liquidity across events') }}</td>
                </tr>
                <tr>
                    <td><strong>{{ __('Swing Traders') }}</strong></td>
                    <td>{{ __('Capture medium-term moves') }}</td>
                    <td>{{ __('Use rolling history (1s / 3s / 5s / 10s) to confirm sustained trends') }}</td>
                </tr>
                <tr>
                    <td><strong>{{ __('Scalpers') }}</strong></td>
                    <td>{{ __('Exploit micro-movements') }}</td>
                    <td>{{ __('Real-time updates + Δ provide fast price action') }}</td>
                </tr>
                <tr>
                    <td><strong>{{ __('Horse Racing Traders') }}</strong></td>
                    <td>{{ __('Pre-race monitoring and analysis') }}</td>
                    <td>{{ __('Monitor') }} <strong>{{ __('prematch') }}</strong> {{ __('markets 5–10 minutes before the off; use Momentum, Δ, and the FAV badge to assess market direction and intensity') }}</td>
                </tr>
                <tr>
                    <td><strong>{{ __('Data Analysts') }}</strong></td>
                    <td>{{ __('Study market behavior and liquidity') }}</td>
                    <td>{{ __('Volatility + Liquidity distribution provide a clear picture of market depth') }}</td>
                </tr>
                <tr>
                    <td><strong>{{ __('Casual Bettors') }}</strong></td>
                    <td>{{ __('Identify interesting market opportunities') }}</td>
                    <td>{{ __('Balance and Momentum highlight markets with notable liquidity or movement patterns') }}</td>
                </tr>
                <tr>
                    <td><strong>{{ __('Platform Admins') }}</strong></td>
                    <td>{{ __('Monitor system health') }}</td>
                    <td>{{ __('Real-time update frequency and event count show if the feed is healthy') }}</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 4 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">4</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-sliders-h"></i> {{ __('Key Features') }}</span>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.1 Real-Time Odds & Liquidity') }}</div>
            <p>{{ __('Every event displays:') }}</p>
            <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
                <thead>
                    <tr>
                        <th>{{ __('Field') }}</th>
                        <th>{{ __('Description') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="back-col">{{ __('Back Odds') }}</td>
                        <td>{{ __('Best available price to bet FOR a selection (Blue)') }}</td>
                    </tr>
                    <tr>
                        <td class="back-col">{{ __('Back Size') }}</td>
                        <td>{{ __('Amount of money available at the best Back price (Blue)') }}</td>
                    </tr>
                    <tr>
                        <td class="lay-col">{{ __('Lay Odds') }}</td>
                        <td>{{ __('Best available price to bet AGAINST a selection (Pink)') }}</td>
                    </tr>
                    <tr>
                        <td class="lay-col">{{ __('Lay Size') }}</td>
                        <td>{{ __('Amount of money available at the best Lay price (Pink)') }}</td>
                    </tr>
                </tbody>
            </table></div>
            <p>{{ __('All values are updated in real-time from the Betfair Exchange') }} <strong>{{ __('data stream') }}</strong> {{ __('with') }}
                <strong>{{ __('low-latency updates') }}</strong>.</p>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.2 Indicators') }}</div>
        <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
            <thead>
                <tr>
                    <th>{{ __('Indicator') }}</th>
                    <th>{{ __('Full Name') }}</th>
                    <th>{{ __('What It Shows') }}</th>
                    <th>{{ __('How It\'s Calculated') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="value-col">{{ __('Balance') }}</td>
                    <td>{{ __('Liquidity Balance') }}</td>
                    <td>{{ __('Where the money is right now') }}</td>
                    <td>{{ __('Back Size vs Lay Size ratio') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Momentum') }}</td>
                    <td>{{ __('Market Momentum') }}</td>
                    <td>{{ __('Where the money is moving toward') }}</td>
                    <td><strong>{{ __('Composite indicator') }}</strong> {{ __('— evaluates odds movement, liquidity change, and market balance. Final score is determined by BF Markets\' internal calibration.') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Δ (Delta)') }}</td>
                    <td>{{ __('Price Change') }}</td>
                    <td>{{ __('How fast Back Size is changing') }}</td>
                    <td>{{ __('% change in Back Size over a short rolling window') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Volatility') }}</td>
                    <td>{{ __('Market Volatility') }}</td>
                    <td>{{ __('How unpredictable the market is') }}</td>
                    <td>{{ __('Standard deviation of log returns over a rolling window') }}</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
        <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.3 Balance — Where the Money Is') }}</div>
    <p><strong>{{ __('Definition:') }}</strong> {{ __('Balance compares the total Back liquidity against the total Lay liquidity for a given runner.') }}</p>
    <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
        <thead>
            <tr>
                <th>{{ __('Value') }}</th>
                <th>{{ __('Meaning') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="back-col">{{ __('Back Heavy ↑') }}</td>
                <td>{{ __('Greater concentration of liquidity on the Back side') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Balanced →') }}</td>
                <td>{{ __('Liquidity is roughly evenly distributed on both sides') }}</td>
            </tr>
            <tr>
                <td class="lay-col">{{ __('Lay Heavy ↓') }}</td>
                <td>{{ __('Greater concentration of liquidity on the Lay side') }}</td>
            </tr>
        </tbody>
    </table></div>
    <p><strong>{{ __('Example:') }}</strong></p>
    <div class="market-activity-example-block">{{ __('Bayern Munich: Back Size 23.2K | Lay Size 4.2K →') }} <span class="hl-blue">{{ __('Back Heavy') }}</span> {{ __('(more money on Back)') }}</div>
    <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
        <li><strong>{{ __('Back Heavy') }}</strong> {{ __('→ Liquidity is more concentrated on the Back side') }}</li>
        <li><strong>{{ __('Lay Heavy') }}</strong> {{ __('→ Liquidity is more concentrated on the Lay side') }}</li>
        <li><strong>{{ __('Balanced') }}</strong> {{ __('→ Liquidity is roughly evenly distributed') }}</li>
    </ul>
    </div>

    <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
        <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.4 Momentum — Where the Money Is Moving') }}</div>
    <p><strong>{{ __('Definition:') }}</strong> {{ __('Momentum measures the direction and strength of market movement.') }}</p>
    <div class="market-activity-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
        <div class="label">{{ __('Conceptual Logic') }}</div>
        <p>{{ __('Momentum is a') }} <strong>{{ __('composite indicator') }}</strong> {{ __('that evaluates odds movement, liquidity change, and market balance. The final score is determined by') }} <strong>{{ __('BF Markets\' internal calibration') }}</strong>.</p>
    </div>
    <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
        <thead>
            <tr>
                <th>{{ __('Value') }}</th>
                <th>{{ __('Meaning') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="back-col">{{ __('Strong Back ↑↑') }}</td>
                <td>{{ __('Strong movement toward the Back side') }}</td>
            </tr>
            <tr>
                <td class="back-col">{{ __('Back ↑') }}</td>
                <td>{{ __('Moderate movement toward the Back side') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Neutral →') }}</td>
                <td>{{ __('No significant movement') }}</td>
            </tr>
            <tr>
                <td class="lay-col">{{ __('Lay ↓') }}</td>
                <td>{{ __('Moderate movement toward the Lay side') }}</td>
            </tr>
            <tr>
                <td class="lay-col">{{ __('Strong Lay ↓↓') }}</td>
                <td>{{ __('Strong movement toward the Lay side') }}</td>
            </tr>
        </tbody>
    </table></div>
    <div class="market-activity-example-block">{{ __('Momentum:') }} <span class="hl-blue">{{ __('Back ↑') }}</span>
        {{ __('→ Data shows a stronger concentration toward the Back side') }}</div>
    </div>

    <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
        <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.5 Δ (Delta) — How Fast Back Size Is Changing') }}</div>
    <p><strong>{{ __('Definition:') }}</strong> {{ __('Delta shows the') }} <strong>{{ __('percentage change in Back Size') }}</strong> {{ __('over a short rolling window.') }}</p>
    <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
        <thead>
            <tr>
                <th>{{ __('Value') }}</th>
                <th>{{ __('Meaning') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="back-col">+18% ↑</td>
                <td>{{ __('Back Size increased by 18%') }}</td>
            </tr>
            <tr>
                <td class="lay-col">-24% ↓</td>
                <td>{{ __('Back Size decreased by 24%') }}</td>
            </tr>
            <tr>
                <td class="value-col">0% →</td>
                <td>{{ __('No significant change') }}</td>
            </tr>
        </tbody>
    </table></div>
    <div class="market-activity-example-block">{{ __('Δ 5s:') }} <span class="hl-green">+50%</span>
        {{ __('→ Back Size increased by 50% in the last 5 seconds') }}</div>
    <div class="market-activity-info-card warning [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
        <div class="label">{{ __('Note') }}</div>
        <p>{{ __('Delta measures only Back Size. Lay Size changes are visible separately in the Lay Size column.') }}</p>
    </div>
    </div>

    <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
        <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.6 Volatility — How Unpredictable the Market Is') }}</div>
    <p><strong>{{ __('Definition:') }}</strong> {{ __('Volatility measures how much the odds are fluctuating over a rolling window.') }}</p>
    <p><strong>{{ __('Method:') }}</strong> {{ __('Standard deviation of log returns of Back Odds.') }}</p>
    <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
        <thead>
            <tr>
                <th>{{ __('Value') }}</th>
                <th>{{ __('Icon') }}</th>
                <th>{{ __('Meaning') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="value-col">{{ __('Low') }}</td>
                <td>🟢</td>
                <td>{{ __('Relatively stable movement of odds') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Medium') }}</td>
                <td>🟡</td>
                <td>{{ __('Moderate fluctuation of odds') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('High') }}</td>
                <td>🔴</td>
                <td>{{ __('Fast / significant fluctuation of odds') }}</td>
            </tr>
        </tbody>
    </table></div>
    <div class="market-activity-example-block">{{ __('Volatility:') }} <span class="hl-red">{{ __('High 🔴') }}</span>
        {{ __('→ 75th minute, score 3-2 → Any goal will dramatically change the odds') }}</div>
    </div>

    <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
        <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.7 Horse Racing — Favorite Identification (Prematch Only)') }}</div>
    <p>{{ __('For Horse Racing events, the system automatically identifies the') }} <strong>{{ __('Favorite') }}</strong> {{ __('— the runner with the') }}
        <strong>{{ __('lowest Back Odds') }}</strong>.</p>
    <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
        <thead>
            <tr>
                <th>{{ __('Element') }}</th>
                <th>{{ __('Description') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="value-col">{{ __('⭐ FAV Badge') }}</td>
                <td>{{ __('Displayed next to the favorite runner') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Favorite Odds') }}</td>
                <td>{{ __('Lowest Back Odds among all runners') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Favorite Momentum') }}</td>
                <td>{{ __('Shows the direction and intensity of market movement relative to the favorite\'s odds') }}</td>
            </tr>
        </tbody>
    </table></div>
    <div class="market-activity-example-block">{{ __('Thunder Bolt: Back 2.58') }} <span class="hl-orange">{{ __('⭐ FAV') }}</span>
        {{ __('Storm Chaser 1: Back 2.73 Storm Chaser 2: Back 3.35 → Thunder Bolt is the favorite (lowest Back Odds)') }}</div>
    </div>

    <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
        <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.8 Horse Racing — Pre-Race Monitoring Window (Prematch Only)') }}</div>
    <div class="market-activity-info-card warning [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
        <div class="label">{{ __('Important') }}</div>
        <p>{{ __('Horse Racing in Market Activity is available') }} <strong>{{ __('only in Prematch') }}</strong>{{ __('. Live races are') }}
            <strong>{{ __('not') }}</strong> {{ __('shown in this terminal.') }}</p>
    </div>
    <p><strong>{{ __('Why this matters:') }}</strong></p>
    <p>{{ __('Professional horse racing traders typically monitor the') }} <strong>{{ __('prematch') }}</strong> {{ __('market') }} <strong>{{ __('5–10 minutes before the race starts') }}</strong>{{ __('. During this window:') }}</p>
    <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
        <li>{{ __('Market activity increases significantly') }}</li>
        <li>{{ __('This causes rapid') }} <strong>{{ __('odds movement') }}</strong> {{ __('and significant') }} <strong>{{ __('money flow') }}</strong></li>
        <li>{{ __('The direction of market movement is reflected in changes across') }} <strong>{{ __('odds, liquidity, and other indicators') }}</strong></li>
        <li>{{ __('Traders need to assess market direction') }} <strong>{{ __('within a very short time window') }}</strong></li>
    </ul>
    <div class="market-activity-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
        <div class="label">{{ __('How BF Markets supports this') }}</div>
        <p><strong>{{ __('One of the core values of BF Markets Market Activity is the fast and clear representation of market data') }}</strong> {{ __('— specifically for traders who need to determine where the money is moving within a very short time window (5–10 minutes before the race).') }}
    </div>
    <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
        <thead>
            <tr>
                <th>{{ __('Feature') }}</th>
                <th>{{ __('Benefit for Pre-Race Monitoring') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="value-col">{{ __('Real-time, low-latency updates') }}</td>
                <td>{{ __('See price movements as they happen in the prematch market') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Momentum') }}</td>
                <td>{{ __('Shows the direction and intensity of market movement') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Δ (Delta)') }}</td>
                <td>{{ __('Shows how fast Back Size is changing') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('FAV Badge') }}</td>
                <td>{{ __('Quickly identifies the favorite') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Ranking') }}</td>
                <td>{{ __('Races starting in ≤5 minutes appear at the top') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Volatility') }}</td>
                <td>{{ __('Shows the level of price fluctuation') }}</td>
            </tr>
        </tbody>
    </table></div>
    </div>

    <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
        <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.9 Ranking & Sorting') }}</div>
    <p>{{ __('Events are automatically ranked and sorted by:') }}</p>
    <p><strong>{{ __('1. Status Priority:') }}</strong></p>
    <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
        <li><strong>{{ __('LIVE') }}</strong> {{ __('(highest) — applies to all sports except Horse Racing') }}</li>
        <li><strong>{{ __('UPCOMING ≤ 5 min') }}</strong></li>
        <li><strong>{{ __('UPCOMING ≤ 10 min') }}</strong></li>
        <li><strong>{{ __('UPCOMING > 10 min') }}</strong></li>
    </ul>
    <p><strong>{{ __('2. Tie-breakers (within same status group):') }}</strong></p>
    <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
        <li>{{ __('Momentum strength') }}</li>
        <li>{{ __('Liquidity (Back Size + Lay Size)') }}</li>
        <li>{{ __('Time') }}</li>
    </ul>
    <div class="market-activity-info-card warning [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
        <div class="label">{{ __('Note') }}</div>
        <p>{{ __('Horse Racing is') }} <strong>{{ __('Prematch only') }}</strong> {{ __('— LIVE priority does not apply to Horse Racing events.') }}</p>
    </div>
    </div>

    <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
        <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.10 Filters') }}</div>
    <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
        <thead>
            <tr>
                <th>{{ __('Filter') }}</th>
                <th>{{ __('Description') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="value-col">{{ __('Live') }}</td>
                <td>{{ __('Show only in-play events') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Upcoming') }}</td>
                <td>{{ __('Show only pre-match events') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Favorites') }}</td>
                <td>{{ __('Show only events you have starred') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('HT 0-0') }}</td>
                <td>{{ __('Half-time 0-0') }}</td>
            </tr>
            <tr>
                <td class="value-col">0-0 @ 70'</td>
                <td>{{ __('0-0 at 70 minutes') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Fav Losing') }}</td>
                <td>{{ __('Favorite is currently losing') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Prematch Drops') }}</td>
                <td>{{ __('Significant odds drop before kick-off') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Market Shock') }}</td>
                <td>{{ __('Sudden, large market movement') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Fav Not Winning') }}</td>
                <td>{{ __('Favorite is not winning') }}</td>
            </tr>
        </tbody>
    </table></div>
    <p class="[font-size:12px]! [color:var(--text-brand-35)]!"><em>{{ __('For detailed filter descriptions, see the Filter Guide.') }}</em></p>
    </div>

    <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
        <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('4.11 Sport Selector') }}</div>
    <p>{{ __('Market Activity supports the following sports (all available on Betfair Exchange):') }}</p>
    <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
        <li>{{ __('Football (Soccer)') }}</li>
        <li>{{ __('Tennis') }}</li>
        <li>{{ __('Horse Racing (') }}<strong>{{ __('Prematch only') }}</strong>)</li>
        <li>{{ __('Greyhounds') }}</li>
        <li>{{ __('Basketball') }}</li>
        <li>{{ __('Cricket') }}</li>
        <li>{{ __('Ice Hockey') }}</li>
        <li>{{ __('American Football') }}</li>
        <li>{{ __('Baseball') }}</li>
    </ul>
    </div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 5 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">5</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-desktop"></i> {{ __('How to Read the Terminal') }}</span>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('5.1 Left Column — Event List') }}</div>
        <div class="market-activity-example-block"><span class="hl-muted">|</span> {{ __('TIME') }} <span class="hl-muted">|</span>
            {{ __('COMP') }} <span class="hl-muted">|</span> {{ __('MATCH') }} <span class="hl-muted">|</span> <span class="hl-blue">{{ __('BACK') }}</span>
            <span class="hl-muted">|</span> <span class="hl-blue">{{ __('SIZE') }}</span> <span class="hl-muted">|</span> <span
                class="hl-pink">{{ __('LAY') }}</span> <span class="hl-muted">|</span> <span class="hl-pink">{{ __('SIZE') }}</span> <span
                class="hl-muted">|</span> {{ __('BAL') }} <span class="hl-muted">|</span> {{ __('MOM') }} <span class="hl-muted">|</span> Δ <span
                class="hl-muted">|</span> {{ __('VOL') }} <span class="hl-muted">|</span> {{ __('ACTION') }} <span class="hl-muted">|</span>
        </div>
        <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
            <thead>
                <tr>
                    <th>{{ __('Column') }}</th>
                    <th>{{ __('Description') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="value-col">{{ __('TIME') }}</td>
                    <td>{{ __('Match status (LIVE / UP) and minute. For Horse Racing: time until race starts') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('COMP') }}</td>
                    <td>{{ __('Tournament / league name') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('MATCH') }}</td>
                    <td>{{ __('Home team vs Away team. For Horse Racing: race name') }}</td>
                </tr>
                <tr>
                    <td class="back-col">{{ __('BACK') }}</td>
                    <td>{{ __('Best Back Odds (Blue)') }}</td>
                </tr>
                <tr>
                    <td class="back-col">{{ __('SIZE') }}</td>
                    <td>{{ __('Back Size (Blue)') }}</td>
                </tr>
                <tr>
                    <td class="lay-col">{{ __('LAY') }}</td>
                    <td>{{ __('Best Lay Odds (Pink)') }}</td>
                </tr>
                <tr>
                    <td class="lay-col">{{ __('SIZE') }}</td>
                    <td>{{ __('Lay Size (Pink)') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('BAL') }}</td>
                    <td>{{ __('Balance indicator') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('MOM') }}</td>
                    <td>{{ __('Momentum indicator') }}</td>
                </tr>
                <tr>
                    <td class="value-col">Δ</td>
                    <td>{{ __('Delta (Back Size change)') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('VOL') }}</td>
                    <td>{{ __('Volatility indicator') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('ACTION') }}</td>
                    <td>{{ __('"Open →" to view details') }}</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
        <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('5.2 Right Panel — Detail View') }}</div>
    <p>{{ __('When you click "Open →" on any event, the right panel shows:') }}</p>
    <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
        <thead>
            <tr>
                <th>{{ __('Section') }}</th>
                <th>{{ __('Description') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="value-col">{{ __('Header') }}</td>
                <td>{{ __('Event title, status, time / time until start, and, where applicable, score') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Runners Table') }}</td>
                <td>{{ __('All runners with Back/Lay Odds and Sizes') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Liquidity Distribution') }}</td>
                <td>{{ __('Back vs Lay liquidity bar') }}</td>
            </tr>
            <tr>
                <td class="value-col">{{ __('Indicators') }}</td>
                <td>{{ __('Balance, Momentum, Volatility') }}</td>
            </tr>
        </tbody>
    </table></div>
    </div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 6 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">6</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-book"></i> {{ __('Glossary of Terms') }}</span>
        </div>
        <div class="w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-xl" role="region" aria-label="{{ __('Scrollable guide table') }}" tabindex="0"><table class="market-activity-guide-table min-w-[480px] w-full [border-collapse:collapse] [margin:12px_0_16px] [font-size:13px] [border-radius:12px] overflow-hidden [box-shadow:0_2px_8px_var(--shadow-neutral-100)] [&_thead_th]:[background:var(--surface-control)]! [&_thead_th]:[color:var(--text-brand-60)]! [&_thead_th]:[font-weight:600]! [&_thead_th]:[font-size:11px]! [&_thead_th]:[text-transform:uppercase]! [&_thead_th]:[letter-spacing:0.3px]! [&_thead_th]:[padding:10px_14px]! [&_thead_th]:[text-align:left]! [&_thead_th]:[border-bottom:2px_solid_var(--border-neutral-5-2)]! [&_thead_th]:[transition:0.3s]! dark:[&_thead_th]:[background:rgb(var(--surface-soft))]! dark:[&_thead_th]:[color:rgb(var(--text-soft))]! dark:[&_thead_th]:[border-bottom-color:rgb(var(--border))]! [&_tbody_td]:[padding:9px_14px]! [&_tbody_td]:[border-bottom:1px_solid_var(--border-neutral-5-3)]! [&_tbody_td]:[color:var(--text-secondary-strong)]! [&_tbody_td]:[transition:0.3s]! [&_tbody_td]:[vertical-align:top]! dark:[&_tbody_td]:[border-bottom-color:rgb(var(--border))]! dark:[&_tbody_td]:[color:rgb(var(--text-soft))]! [&_.value-col]:[font-weight:600]! [&_.value-col]:[color:rgb(var(--text-main))]! dark:[&_.value-col]:[color:var(--text-on-primary)]! [&_.back-col]:[color:rgb(var(--primary))]! [&_.back-col]:[font-weight:600]! dark:[&_.back-col]:[color:rgb(var(--primary))]! [&_.lay-col]:[color:var(--text-lay)]! [&_.lay-col]:[font-weight:600]! dark:[&_.lay-col]:[color:var(--text-accent)]! [&_tbody_tr:hover_td]:[background:var(--surface-row)] dark:[&_tbody_tr:hover_td]:[background:rgb(var(--surface-soft))]">
            <thead>
                <tr>
                    <th>{{ __('Term') }}</th>
                    <th>{{ __('Definition') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="back-col">{{ __('Back') }}</td>
                    <td>{{ __('Betting FOR a selection to win (Blue)') }}</td>
                </tr>
                <tr>
                    <td class="lay-col">{{ __('Lay') }}</td>
                    <td>{{ __('Betting AGAINST a selection to win (Pink)') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Odds') }}</td>
                    <td>{{ __('Decimal price — shows the total return per unit staked') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Size') }}</td>
                    <td>{{ __('Amount of money available at the best price') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Liquidity') }}</td>
                    <td>{{ __('Total money available in the market') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Runner') }}</td>
                    <td>{{ __('A participant in a market (team, player, horse)') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Market') }}</td>
                    <td>{{ __('A specific betting market (Match Odds, Over/Under, etc.)') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Event') }}</td>
                    <td>{{ __('A single match/race containing multiple markets') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Balance') }}</td>
                    <td>{{ __('Where the money is right now') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Momentum') }}</td>
                    <td>{{ __('Where the money is moving toward') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Δ (Delta)') }}</td>
                    <td>{{ __('How fast Back Size is changing') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Volatility') }}</td>
                    <td>{{ __('How unpredictable the market is') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Favorite') }}</td>
                    <td>{{ __('Runner with the lowest Back Odds (Horse Racing)') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('FAV Badge') }}</td>
                    <td>{{ __('⭐ indicator for the favorite runner') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Real-time feed') }}</td>
                    <td>{{ __('Continuous live data stream from Betfair Exchange') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Latency') }}</td>
                    <td>{{ __('Delay between data publish and display — kept low') }}</td>
                </tr>
                <tr>
                    <td class="value-col">{{ __('Prematch') }}</td>
                    <td>{{ __('Markets available before the event starts') }}</td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 7 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">7</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-flask"></i> {{ __('Practical Examples') }}</span>
        </div>

        <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
            <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('Example 1: Back Heavy Market (Football)') }}</div>
        <div class="market-activity-example-block">{{ __('Bayern Munich vs Dortmund — 75\' (3-2)') }}

            <span class="hl-blue">{{ __('Bayern Munich') }}</span>{{ __(': Back') }} <span class="hl-blue">2.39</span> {{ __('| Back Size') }} <span
                class="hl-blue">23.2K</span> {{ __('| Lay') }} <span class="hl-pink">2.05</span> {{ __('| Lay Size') }} <span
                class="hl-pink">4.2K</span>
            <span class="hl-blue">{{ __('Dortmund') }}</span>{{ __(': Back') }} <span class="hl-blue">3.31</span> {{ __('| Back Size') }} <span
                class="hl-blue">3.7K</span> {{ __('| Lay') }} <span class="hl-pink">4.23</span> {{ __('| Lay Size') }} <span
                class="hl-pink">10.9K</span>
            <span class="hl-blue">{{ __('The Draw') }}</span>{{ __(': Back') }} <span class="hl-blue">5.51</span> {{ __('| Back Size') }} <span
                class="hl-blue">12.5K</span> {{ __('| Lay') }} <span class="hl-pink">5.34</span> {{ __('| Lay Size') }} <span
                class="hl-pink">9.0K</span>

            <span class="hl-blue">{{ __('Balance: Back Heavy') }}</span>
            <span class="hl-muted">{{ __('Momentum: Neutral') }}</span>
            <span class="hl-red">{{ __('Volatility: High') }}</span>
        </div>
        <p><strong>{{ __('Interpretation:') }}</strong></p>
        <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
            <li><strong>{{ __('Back Heavy') }}</strong> {{ __('→ 22.7K on Back vs 5.4K on Lay — data shows stronger concentration toward Bayern\'s Back side') }}</li>
            <li><strong>{{ __('Momentum Neutral') }}</strong> {{ __('→ No significant movement') }}</li>
            <li><strong>{{ __('Volatility High') }}</strong> {{ __('→ 75th minute, 3-2 — any goal changes everything') }}</li>
        </ul>
    </div>

    <div class="market-activity-subsection [margin-bottom:24px] [padding-left:8px]">
        <div class="market-activity-subsection-title [font-size:16px] font-semibold [color:var(--text-brand-60)] [margin-bottom:10px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('Example 2: Horse Racing Favorite (Prematch)') }}</div>
    <div class="market-activity-example-block">{{ __('Horse Racing — Upcoming Race starts in 4 min') }}

        <span class="hl-blue">{{ __('Thunder Bolt') }}</span>{{ __(': Back') }} <span class="hl-blue">2.58</span> {{ __('| Size') }} <span
            class="hl-blue">10.3K</span> {{ __('| Lay') }} <span class="hl-pink">2.47</span> {{ __('| Size') }} <span
            class="hl-pink">10.6K</span> <span class="hl-orange">{{ __('⭐ FAV') }}</span>
        <span class="hl-blue">{{ __('Storm Chaser 1') }}</span>{{ __(': Back') }} <span class="hl-blue">2.73</span> {{ __('| Size') }} <span
            class="hl-blue">8.7K</span> {{ __('| Lay') }} <span class="hl-pink">2.93</span> {{ __('| Size') }} <span class="hl-pink">5.2K</span>
        <span class="hl-blue">{{ __('Storm Chaser 2') }}</span>{{ __(': Back') }} <span class="hl-blue">3.35</span> {{ __('| Size') }} <span
            class="hl-blue">2.3K</span> {{ __('| Lay') }} <span class="hl-pink">3.11</span> {{ __('| Size') }} <span class="hl-pink">6.1K</span>

        <span class="hl-muted">{{ __('Balance: Balanced →') }}</span>
        <span class="hl-muted">{{ __('Momentum: Neutral →') }}</span>
        <span class="hl-orange">{{ __('Volatility: Medium 🟡') }}</span>
    </div>
    <p><strong>{{ __('Interpretation:') }}</strong></p>
    <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
        <li><strong>{{ __('Thunder Bolt is FAV') }}</strong> {{ __('→ Lowest Back Odds (2.58)') }}</li>
        <li><strong>{{ __('Balanced') }}</strong> {{ __('→ Money is roughly equal on both sides') }}</li>
        <li><strong>{{ __('Neutral') }}</strong> {{ __('→ No significant movement') }}</li>
        <li><strong>{{ __('Medium Volatility') }}</strong> {{ __('→ Moderate fluctuation') }}</li>
        <li><strong>{{ __('Race starts in 4 min') }}</strong> {{ __('→ This race appears at the top due to the ranking rule (≤5 min)') }}</li>
    </ul>
    </div>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 8 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">8</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-cogs"></i> {{ __('Performance & Architecture') }}</span>
        </div>
        <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
            <li><strong>{{ __('Updates:') }}</strong> {{ __('The core live feed is') }} <strong>{{ __('event-driven') }}</strong> {{ __('— pushed per change, not polled. Auxiliary data may be refreshed through other methods.') }}</li>
            <li><strong>{{ __('History:') }}</strong> {{ __('Short rolling window per event (1s / 3s / 5s / 10s)') }}</li>
            <li><strong>{{ __('Rendering:') }}</strong> {{ __('Only changed rows are updated (selective DOM updates)') }}</li>
            <li><strong>{{ __('Ranking:') }}</strong> {{ __('Debounced — positions change only when significant movement occurs') }}</li>
            <li><strong>{{ __('Memory:') }}</strong> {{ __('Compact rolling history per event') }}</li>
        </ul>
    </div>

    <div class="market-activity-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 9 ===== -->
    <div class="market-activity-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="market-activity-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="market-activity-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">9</span>
            <span class="market-activity-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-check-circle"></i> {{ __('Summary') }}</span>
        </div>
        <p><strong>{{ __('Market Activity is a professional-grade sports exchange monitoring terminal.') }}</strong></p>
        <p>{{ __('It is designed for:') }}</p>
        <ul class="guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px] [&_li]:[position:relative] [&_li]:[font-size:13px] [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
            <li><strong>{{ __('Traders') }}</strong> {{ __('who need real-time market intelligence') }}</li>
            <li><strong>{{ __('Arbitrageurs') }}</strong> {{ __('who scan for discrepancies') }}</li>
            <li><strong>{{ __('Analysts') }}</strong> {{ __('who study market behavior') }}</li>
            <li><strong>{{ __('Bettors') }}</strong> {{ __('who want to observe where significant money flow is occurring') }}</li>
        </ul>
        <div class="market-activity-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('Core Principle') }}</div>
            <p><strong>{{ __('It is not a betting page — it is a data terminal.') }}</strong></p>
        </div>
    </div>

@endsection
