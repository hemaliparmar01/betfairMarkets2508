@extends('users.layout.main')

@section('title', __('Filter Guide').' · BF Markets')

@section('content')
    <!-- ===== PAGE HEADER ===== -->
    <div class="filter-guide-page-header [margin:28px_0_8px]">
        <h1 class="filter-guide-page-title [font-size:32px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.5px] flex items-center [gap:12px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-warning)] [&_i]:[font-size:28px] max-[768px]:[font-size:24px]"><i class="fas fa-filter"></i> {{ __('Filter Guide') }}</h1>
        <p class="filter-guide-page-subtitle [font-size:14px] [color:rgb(var(--text-muted))] [margin-top:6px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('BF Markets — The Data Terminal for Sports Exchange Markets') }}</p>
    </div>

    <!-- ===== 1. LIVE ===== -->
    <div class="filter-guide-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="filter-guide-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="filter-guide-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">1</span>
            <span class="filter-guide-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-circle [color:var(--text-live)]!"></i> {{ __('Live') }}</span>
        </div>
        <p><strong>{{ __('What it is:') }}</strong> {{ __('In-play match filter.') }}</p>
        <p><strong>{{ __('Usage:') }}</strong> {{ __('Instantly displays all matches that are currently live. Ideal for users trading live market dynamics, in-play goals, and changing odds.') }}</p>
    </div>

    <div class="filter-guide-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== 2. UPCOMING ===== -->
    <div class="filter-guide-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="filter-guide-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="filter-guide-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">2</span>
            <span class="filter-guide-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-clock"></i> {{ __('Upcoming') }}</span>
        </div>
        <p><strong>{{ __('What it is:') }}</strong> {{ __('Upcoming match filter.') }}</p>
        <p><strong>{{ __('Usage:') }}</strong> {{ __('Displays matches scheduled to start later. Helps with pre-match planning, analysis, and organizing your daily watchlist.') }}</p>
    </div>

    <div class="filter-guide-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== 3. FINISHED ===== -->
    <div class="filter-guide-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="filter-guide-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="filter-guide-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">3</span>
            <span class="filter-guide-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-archive"></i> {{ __('Finished') }}</span>
        </div>
        <p><strong>{{ __('What it is:') }}</strong> {{ __('Archive of completed matches.') }}</p>
        <p><strong>{{ __('Usage:') }}</strong> {{ __('Shows completed matches and their final results. Useful for reviewing outcomes and analyzing your trading strategies.') }}</p>
    </div>

    <div class="filter-guide-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== 4. FAVORITES ===== -->
    <div class="filter-guide-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="filter-guide-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="filter-guide-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">4</span>
            <span class="filter-guide-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-star [color:var(--text-warning)]!"></i> {{ __('Favorites') }}</span>
        </div>
        <p><strong>{{ __('What it is:') }}</strong> {{ __('Your personal watchlist.') }}</p>
        <p><strong>{{ __('Usage:') }}</strong> {{ __('Displays all matches marked with a star (⭐), allowing you to monitor only the games that matter to you.') }}</p>
    </div>

    <div class="filter-guide-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== 5. BY TIME ===== -->
    <div class="filter-guide-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="filter-guide-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="filter-guide-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">5</span>
            <span class="filter-guide-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-sort-amount-down"></i> {{ __('By Time') }}</span>
        </div>
        <p><strong>{{ __('What it is:') }}</strong> {{ __('Chronological sorting tool.') }}</p>
        <p><strong>{{ __('Usage:') }}</strong> {{ __('Sorts matches by kickoff time, from earliest to latest, making it easier to organize your trading schedule.') }}</p>
    </div>

    <div class="filter-guide-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== 6. HT 0-0 ===== -->
    <div class="filter-guide-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="filter-guide-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="filter-guide-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">6</span>
            <span class="filter-guide-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-futbol"></i> {{ __('HT 0-0 (Half Time 0-0)') }}</span>
        </div>
        <p><strong>{{ __('What it is:') }}</strong> {{ __('Live filter for matches that are goalless at half-time.') }}</p>
        <p><strong>{{ __('Usage:') }}</strong> {{ __('Identifies live matches where the first half has ended 0-0.') }}</p>
        <div class="filter-guide-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('📊 Strategy') }}</div>
            <p>{{ __('Many traders use this filter to explore second-half goal markets (Over 0.5, Over 1.5, Over 2.5, etc.), as odds for goals often increase significantly after a goalless first half.') }}</p>
        </div>
    </div>

    <div class="filter-guide-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== 7. 0-0 @ 70' ===== -->
    <div class="filter-guide-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="filter-guide-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="filter-guide-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">7</span>
            <span class="filter-guide-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-hourglass-half"></i> 0-0 @ 70'</span>
        </div>
        <p><strong>{{ __('What it is:') }}</strong> {{ __('Live filter for matches that remain goalless at or after the 70th minute.') }}</p>
        <p><strong>{{ __('Usage:') }}</strong> {{ __('Displays matches that are still 0-0.') }}</p>
        <div class="filter-guide-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('📊 Strategy') }}</div>
            <p>{{ __('Widely used to identify potential "Late Goal" opportunities. At this stage of the match, the odds for at least one goal are often significantly higher.') }}</p>
        </div>
    </div>

    <div class="filter-guide-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== 8. FAV LOSING ===== -->
    <div class="filter-guide-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="filter-guide-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="filter-guide-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">8</span>
            <span class="filter-guide-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-arrow-down [color:var(--text-danger)]!"></i> {{ __('Fav Losing (Favorite Losing)') }}</span>
        </div>
        <p><strong>{{ __('What it is:') }}</strong> {{ __('Live tracker for matches where the pre-match favorite is currently losing.') }}</p>
        <p><strong>{{ __('Usage:') }}</strong> {{ __('Displays matches in which a heavily favored team is behind during live play.') }}</p>
        <div class="filter-guide-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('📊 Strategy') }}</div>
            <p>{{ __('Some traders monitor these situations for potential opportunities involving the favorite not to lose (1X/X2) or a possible comeback scenario, as live odds may differ substantially from pre-match prices.') }}</p>
        </div>
    </div>

    <div class="filter-guide-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== 9. PREMATCH DROPS ===== -->
    <div class="filter-guide-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="filter-guide-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="filter-guide-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">9</span>
            <span class="filter-guide-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-arrow-trend-down"></i> {{ __('Prematch Drops') }}</span>
        </div>
        <p><strong>{{ __('What it is:') }}</strong> {{ __('Early-warning radar for initial pre-match odds movements.') }}</p>
        <p><strong>{{ __('Usage:') }}</strong> {{ __('Displays matches that have not started yet but have already shown early price movement.') }}</p>
        <div class="filter-guide-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('📊 Strategy') }}</div>
            <p>{{ __('The system identifies the earliest odds changes that may develop into broader market trends. This allows users to detect emerging market momentum at an early stage.') }}</p>
        </div>
    </div>

    <div class="filter-guide-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== 10. MARKET SHOCK DETECTOR ===== -->
    <div class="filter-guide-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="filter-guide-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="filter-guide-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">10</span>
            <span class="filter-guide-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-bolt [color:var(--text-warning)]!"></i> {{ __('Market Shock Detector') }}</span>
        </div>
        <p><strong>{{ __('What it is:') }}</strong> {{ __('A lightning-fast detector for sudden market shocks and sharp price movements.') }}</p>
        <p><strong>{{ __('How it works:') }}</strong> {{ __('Unlike Prematch Drops, this filter operates exclusively on pre-match markets and detects aggressive price movements occurring within approximately the last 1 to 3 minutes. It scans Football (four core markets), Tennis, and Cricket (Match Odds).') }}</p>
        <div class="filter-guide-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('📊 Strategy') }}</div>
            <p>{{ __('The filter is designed to identify moments when the global exchange market reacts rapidly to significant events, such as injuries, lineup announcements, or other material information. It helps users observe market changes before slower-moving markets fully adjust.') }}</p>
        </div>
    </div>

    <div class="filter-guide-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== 11. FAV NOT WINNING ===== -->
    <div class="filter-guide-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="filter-guide-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="filter-guide-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">11</span>
            <span class="filter-guide-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-hand-paper [color:rgb(var(--primary))]!"></i> {{ __('Fav Not Winning') }}</span>
        </div>
        <p><strong>{{ __('What is Fav Not Winning?') }}</strong></p>
        <p>{{ __('This is a') }} <strong>{{ __('live filter') }}</strong> {{ __('that shows matches where the pre-match favorite is') }} <strong>{{ __('not yet winning') }}</strong> {{ __('— meaning the favorite is either drawing or losing.') }}</p>

        <p><strong>{{ __('How is it different from Fav Losing?') }}</strong></p>
        <ul class="filter-guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:10px_0_16px] [&_li]:[padding:6px_0_6px_24px]! [&_li]:[position:relative]! [&_li]:[font-size:14px]! [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
            <li><strong>{{ __('Fav Losing') }}</strong> {{ __('— shows only matches where the favorite is') }} <strong>{{ __('losing') }}</strong>.</li>
            <li><strong>{{ __('Fav Not Winning') }}</strong> {{ __('— shows both matches where the favorite is losing and matches where the favorite\'s game is') }} <strong>{{ __('still a draw') }}</strong>.</li>
        </ul>

        <p><strong>{{ __('Examples:') }}</strong></p>
        <div class="filter-guide-example-block [background:var(--surface-muted)] [border:1px_solid_var(--border-neutral-5)] [border-radius:12px] [padding:14px_18px] [font-family:'SF_Mono',_'Fira_Code',_'Consolas',_monospace] [font-size:13px] [color:var(--text-brand-60)] [margin:10px_0_14px] whitespace-pre-wrap [line-height:1.8] [transition:0.3s] dark:[background:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] [&_.hl-green]:[color:var(--text-success)]! [&_.hl-green]:[font-weight:600]! dark:[&_.hl-green]:[color:rgb(var(--success))]! [&_.hl-red]:[color:var(--text-danger)]! [&_.hl-red]:[font-weight:600]! dark:[&_.hl-red]:[color:rgb(var(--danger))]! [&_.hl-blue]:[color:rgb(var(--primary))]! [&_.hl-blue]:[font-weight:600]! dark:[&_.hl-blue]:[color:rgb(var(--primary))]! [&_.hl-muted]:[color:var(--text-brand-35)]!">
            <span class="hl-red">0–1</span> {{ __('— the favorite is losing →') }} <span class="hl-green">{{ __('shown') }}</span>
            <span class="hl-red">1–2</span> {{ __('— the favorite is losing →') }} <span class="hl-green">{{ __('shown') }}</span>
            <span class="hl-blue">0–0</span> {{ __('— the match involving the favorite is still a draw →') }} <span class="hl-green">{{ __('shown') }}</span>
            <span class="hl-blue">1–1</span> {{ __('— the match involving the favorite is still a draw →') }} <span class="hl-green">{{ __('shown') }}</span>
            <span class="hl-red">1–0</span> {{ __('— if the home team is the favorite and is winning →') }} <span class="hl-red">{{ __('not shown') }}</span>
        </div>

        <p><strong>{{ __('How does the filter work?') }}</strong></p>
        <p>{{ __('The filter starts working') }} <strong>{{ __('from the 29th minute') }}</strong> {{ __('and monitors matches where the pre-match favorite\'s odds are within the defined range. If the favorite is drawing or losing, the match appears in the filter.') }}</p>
        <p>{{ __('Once the favorite takes the lead and is winning, the match is') }} <strong>{{ __('automatically removed') }}</strong> {{ __('from the filter.') }}</p>

        <div class="filter-guide-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('📊 Why can this filter be useful?') }}</div>
            <p>{{ __('Fav Not Winning brings together, in one view, matches where the favorite is not yet following the expected winning scenario — whether the match is a draw or the favorite is losing. This makes it easier to monitor the favorite\'s current position and how the match develops.') }}</p>
        </div>
    </div>

@endsection
