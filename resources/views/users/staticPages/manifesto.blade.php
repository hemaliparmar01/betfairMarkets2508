@extends('users.layout.main')

@section('title', __('Fixtures · BF Markets'))

@section('content')

    <!-- ===== PAGE HEADER ===== -->
    <div class="manifesto-page-header [margin:28px_0_8px]">
        <h1 class="manifesto-page-title [font-size:32px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.5px] flex items-center [gap:12px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:var(--text-warning)] [&_i]:[font-size:28px] max-[768px]:[font-size:24px]"><i class="fas fa-fire"></i> {{ __('BF Markets Manifesto') }}</h1>
        <p class="manifesto-page-subtitle [font-size:14px] [color:rgb(var(--text-muted))] [margin-top:6px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('BF Markets — The Data Terminal for Sports Exchange Markets') }}</p>
    </div>

    <!-- ===== SECTION 1 ===== -->
    <div class="manifesto-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="manifesto-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="manifesto-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">1</span>
            <span class="manifesto-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-eye"></i> {{ __('Vision') }}</span>
        </div>
        <p><strong>BF Markets</strong> {{ __('is not a gambling platform. It is a') }} <strong>{{ __('financial terminal for sports exchanges') }}</strong>{{ __(', built on') }} <strong>{{ __('Quant-Dashboard') }}</strong> {{ __('principles.') }}</p>
        <p>{{ __('Our philosophy is rooted in') }} <strong>{{ __('Bloomberg-style thinking') }}</strong>:</p>
        <div class="manifesto-quote-block [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white [border-radius:16px] [padding:28px_32px] [margin:24px_0] [font-size:19px] font-semibold italic text-center [letter-spacing:-0.3px] relative overflow-hidden [box-shadow:0_8px_24px_var(--shadow-brand-75)] dark:[background:linear-gradient(135deg,_var(--button-primary),_var(--button-primary))] max-[768px]:[font-size:16px] max-[768px]:[padding:22px_24px] [&_i]:[margin-right:10px]! [&_i]:[opacity:0.7]! [&_i]:[font-size:18px]! [&::before]:[position:absolute] [&::before]:[top:-20px] [&::before]:[left:20px] [&::before]:[font-size:100px] [&::before]:[opacity:0.12] [&::before]:[line-height:1]">
            <i class="fas fa-quote-left"></i>
            {{ __('Data must be clean, signals must be clear, and reactions must be instantaneous.') }}
        </div>
    </div>

    <div class="manifesto-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 2 ===== -->
    <div class="manifesto-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="manifesto-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="manifesto-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">2</span>
            <span class="manifesto-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-filter"></i> {{ __('Data Hygiene') }}</span>
        </div>
        <p>{{ __('Like a Bloomberg Terminal, our highest priority is') }} <strong>{{ __('informational purity') }}</strong>.</p>
        <p>{{ __('We filter out market noise.') }}</p>
        <div class="highlight-box [background:var(--surface-row)] [border:2px_solid_var(--border-primary)] [border-radius:16px] [padding:22px_26px] [margin:20px_0] [transition:0.3s] dark:[background:rgb(var(--surface-soft))] dark:[border-color:rgb(var(--border))] [&_p]:[font-size:16px] [&_p]:[font-weight:500] [&_p]:[margin:0] [&_p]:[color:rgb(var(--text-main))] dark:[&_p]:[color:var(--text-on-primary)] [&_.title]:[font-size:11px] [&_.title]:[font-weight:700] [&_.title]:[text-transform:uppercase] [&_.title]:[color:rgb(var(--primary))] [&_.title]:[letter-spacing:0.6px] [&_.title]:[margin-bottom:10px] dark:[&_.title]:[color:rgb(var(--primary))]">
            <div class="title">{{ __('Core Principle') }}</div>
            <p>{{ __('A BF Markets user does not waste time on insignificant') }} <strong>{{ __('0.01 fluctuations') }}</strong>.</p>
        </div>
        <p>{{ __('Our') }} <strong>{{ __('Smart Threshold') }}</strong> {{ __('technology ensures that only movements with') }} <strong>{{ __('meaningful market impact') }}</strong> {{ __('appear on your screen.') }}</p>
    </div>

    <div class="manifesto-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 3 ===== -->
    <div class="manifesto-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="manifesto-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="manifesto-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">3</span>
            <span class="manifesto-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-vector-square"></i> {{ __('Vector-Based Intelligence') }}</span>
        </div>
        <p>{{ __('We') }} <strong>{{ __('reject static history') }}</strong>.</p>
        <p>{{ __('Traditional platforms display differences relative to the opening odds, often creating a') }} <strong>{{ __('distorted view of the market') }}</strong>.</p>
        <p>{{ __('BF Markets utilizes') }} <strong>{{ __('Dynamic Velocity Tracking') }}</strong>.</p>
        <div class="manifesto-quote-block [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white [border-radius:16px] [padding:28px_32px] [margin:24px_0] [font-size:19px] font-semibold italic text-center [letter-spacing:-0.3px] relative overflow-hidden [box-shadow:0_8px_24px_var(--shadow-brand-75)] dark:[background:linear-gradient(135deg,_var(--button-primary),_var(--button-primary))] max-[768px]:[font-size:16px] max-[768px]:[padding:22px_24px] [&_i]:[margin-right:10px]! [&_i]:[opacity:0.7]! [&_i]:[font-size:18px]! [&::before]:[position:absolute] [&::before]:[top:-20px] [&::before]:[left:20px] [&::before]:[font-size:100px] [&::before]:[opacity:0.12] [&::before]:[line-height:1]">
            <i class="fas fa-arrow-right"></i>
            {{ __('We show you not where the price has been, but where it is moving right now.') }}
        </div>
        <p>{{ __('Our arrows represent') }} <strong>{{ __('live market momentum') }}</strong> {{ __('rather than historical inertia.') }}</p>
    </div>

    <div class="manifesto-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 4 ===== -->
    <div class="manifesto-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="manifesto-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="manifesto-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">4</span>
            <span class="manifesto-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-balance-scale"></i> {{ __('Neutrality as an Asset') }}</span>
        </div>
        <p>{{ __('In a financial terminal,') }} <strong>{{ __('silence is information') }}</strong>.</p>
        <div class="manifesto-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('💡 Principle of Neutrality') }}</div>
            <p>{{ __('If no arrow is displayed in the BF Markets terminal, it means the') }} <strong>{{ __('market is in equilibrium') }}</strong>.</p>
        </div>
        <p>{{ __('We do not create the illusion of movement where none exists.') }}</p>
        <p>{{ __('This disciplined and neutral approach protects traders from') }} <strong>{{ __('impulsive decisions') }}</strong> {{ __('and allows them to act only when the market provides a') }} <strong>{{ __('genuine signal') }}</strong>.</p>
    </div>

    <div class="manifesto-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 5 ===== -->
    <div class="manifesto-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="manifesto-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="manifesto-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">5</span>
            <span class="manifesto-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-layer-group"></i> {{ __('Quant-Level Architecture') }}</span>
        </div>
        <p>{{ __('Our system is built upon') }} <strong>{{ __('two fundamental layers') }}</strong>:</p>

        <div class="manifesto-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('⚡ Execution Layer (Terminal UI)') }}</div>
            <p>{{ __('Ultra-fast, snapshot-based visualization designed for') }} <strong>{{ __('immediate decision-making') }}</strong>.</p>
        </div>

        <div class="manifesto-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:16px_20px] [margin:14px_0_18px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:6px]! [&_p]:[font-size:14px]! [&_.label]:[font-size:11px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:6px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('🔬 Analytical Layer (Market Shock & Drops)') }}</div>
            <p>{{ __('Deep, cumulative algorithms that analyze') }} <strong>{{ __('market anomalies and structural changes') }}</strong> {{ __('over time.') }}</p>
        </div>

        <p>{{ __('This architecture is designed to deliver exceptional') }} <strong>{{ __('stability, precision, and performance') }}</strong> {{ __('under heavy workloads.') }}</p>
    </div>

    <div class="manifesto-divider h-px [background:var(--surface-subtle)] [margin:28px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 6 ===== -->
    <div class="manifesto-section [margin:32px_0] [&_p]:[font-size:15px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:10px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:15px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:10px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)]">
        <div class="manifesto-section-header flex items-center [gap:12px] [margin-bottom:18px] [padding-bottom:12px] [border-bottom:2px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="manifesto-section-number inline-flex items-center justify-center [width:36px] [height:36px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:15px] shrink-0">6</span>
            <span class="manifesto-section-title [font-size:22px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:20px] max-[768px]:[font-size:18px]"><i class="fas fa-bullseye"></i> {{ __('Signal over Information') }}</span>
        </div>
        <p>{{ __('Information is available to') }} <strong>{{ __('everyone') }}</strong>.</p>
        <p>{{ __('Signals are for') }} <strong>{{ __('those who know how to see them') }}</strong>.</p>
        <p>{{ __('The mission of BF Markets is to') }} <strong>{{ __('extract opportunity from chaos') }}</strong>.</p>

        <div class="manifesto-quote-block [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white [border-radius:16px] [padding:28px_32px] [margin:24px_0] [font-size:19px] font-semibold italic text-center [letter-spacing:-0.3px] relative overflow-hidden [box-shadow:0_8px_24px_var(--shadow-brand-75)] dark:[background:linear-gradient(135deg,_var(--button-primary),_var(--button-primary))] max-[768px]:[font-size:16px] max-[768px]:[padding:22px_24px] [&_i]:[margin-right:10px]! [&_i]:[opacity:0.7]! [&_i]:[font-size:18px]! [&::before]:[position:absolute] [&::before]:[top:-20px] [&::before]:[left:20px] [&::before]:[font-size:100px] [&::before]:[opacity:0.12] [&::before]:[line-height:1]">
            <i class="fas fa-quote-left"></i>
            {{ __('We don\'t provide data points; we provide market direction.') }}
        </div>
    </div>

@endsection
