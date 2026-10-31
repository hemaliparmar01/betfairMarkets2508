@extends('users.layout.main')

@section('title', __('Fixtures · BF Markets'))

@section('content')

    <!-- ===== PAGE HEADER ===== -->
    <div class="tos-page-header [margin:28px_0_8px]">
        <h1 class="tos-page-title [font-size:32px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.5px] flex items-center [gap:12px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[font-size:28px] max-[768px]:[font-size:24px]"><i class="fas fa-file-contract"></i> {{ __('Terms of Service') }}</h1>
        <p class="tos-page-subtitle [font-size:14px] [color:rgb(var(--text-muted))] [margin-top:6px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('BF Markets — The Data Terminal for Sports Exchange Markets') }}</p>
        <span class="tos-last-updated inline-block [font-size:12px] [color:var(--text-brand-35)] italic [margin-top:6px] [padding:4px_12px] [background:var(--surface-control)] [border-radius:20px] [transition:0.3s] dark:[background:rgb(var(--surface-soft))] dark:[color:rgb(var(--text-soft))]">{{ __('Last Updated: May 3, 2026') }}</span>
    </div>

    <!-- ===== SECTION 1 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">1</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-info-circle"></i> {{ __('Introduction') }}</span>
        </div>
        <p>{{ __('Welcome to BF Markets ("Service", "we", "us", or "our"). BF Markets is a sports exchange data analytics platform that provides real-time odds, liquidity monitoring, market depth visualization, and other analytical tools and data.') }}</p>
        <div class="tos-info-card warning [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:14px_18px] [margin:12px_0_16px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [background:linear-gradient(135deg,_var(--surface-neutral-0-5),_var(--surface-warning-0))]! [border-left-color:var(--border-warning)]! dark:[background:linear-gradient(135deg,_var(--status-warning-surface),_var(--status-warning-surface))]! dark:[border-left-color:var(--status-warning-border)]! [&_.label]:[color:var(--text-warning-30)]! [&_p]:[margin-bottom:4px]! [&_p]:[font-size:13px]! [&_.label]:[font-size:10px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:4px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('⚠️ Important') }}</div>
            <p>{{ __('We are not a bookmaker, do not accept bets, and do not provide gambling services.') }}</p>
        </div>
        <div class="tos-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:14px_18px] [margin:12px_0_16px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:4px]! [&_p]:[font-size:13px]! [&_.label]:[font-size:10px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:4px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('🔹 Owner and Operator') }}</div>
            <p>{{ __('The BF Markets platform is created, owned, and operated by') }} <strong>Digital Analytics Group LLC</strong>.</p>
            <p><a href="https://digitalanalyticsgroup.com" target="_blank">https://digitalanalyticsgroup.com</a></p>
        </div>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 2 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">2</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-check-circle"></i> {{ __('Acceptance of Terms') }}</span>
        </div>
        <p>{{ __('By accessing or using the Service, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service. If you do not agree with any part of these Terms, please do not use the Service.') }}</p>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 3 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">3</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-user-check"></i> {{ __('Age Restriction') }}</span>
        </div>
        <p>{{ __('The Service is intended solely for individuals who are at least') }} <strong>{{ __('18 years of age') }}</strong>.</p>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 4 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">4</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-list-alt"></i> {{ __('Description of the Service') }}</span>
        </div>
        <p>{{ __('The platform provides users with real-time odds, liquidity data, market depth visualizations, charts, and analytical tools. All information provided through the Service is for') }} <strong>{{ __('informational purposes only') }}</strong>.</p>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 5 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">5</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-exclamation-triangle"></i> {{ __('No Financial Advice') }}</span>
        </div>
        <p>{{ __('Our Service does not constitute financial, investment, trading, or betting advice or recommendations. Any decisions made by users are entirely their own responsibility.') }}</p>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 6 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">6</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-shield-alt"></i> {{ __('Risk Acknowledgement') }}</span>
        </div>
        <p>{{ __('Sports exchange trading and betting involve significant financial risks and may result in financial losses. Your use of the Service is entirely') }} <strong>{{ __('at your own risk and responsibility') }}</strong>.</p>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 7 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">7</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-user-cog"></i> {{ __('User Obligations') }}</span>
        </div>
        <p>{{ __('Users agree to:') }}</p>
        <ul class="tos-guide-list">
            <li>{{ __('Use the Service only for lawful purposes;') }}</li>
            <li>{{ __('Not share their account with third parties;') }}</li>
            <li>{{ __('Not copy, scrape, distribute, or otherwise use the platform\'s data in any unauthorized or unlawful manner.') }}</li>
        </ul>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 8 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">8</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-credit-card"></i> {{ __('Subscriptions and Payments') }}</span>
        </div>
        <p>{{ __('New users receive a') }} <strong>{{ __('7-day free trial period') }}</strong> {{ __('upon registration. During the trial period, no payment is required and no charges will be automatically deducted from the user\'s payment card.') }}</p>
        <p>{{ __('After the trial period expires, users must manually select one of the available paid subscription plans in order to continue using the Service:') }}</p>
        <p>
            <span class="plan-badge">{{ __('1 Week') }}</span>
            <span class="plan-badge">{{ __('1 Month') }}</span>
            <span class="plan-badge">{{ __('1 Quarter (3 Months)') }}</span>
        </p>

        <div class="tos-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:14px_18px] [margin:12px_0_16px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:4px]! [&_p]:[font-size:13px]! [&_.label]:[font-size:10px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:4px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('💡 Auto Renewal') }}</div>
            <p>{{ __('When purchasing their first paid subscription, users may choose whether to enable the automatic renewal feature. Automatic renewal will only be activated if the user expressly provides consent by selecting the appropriate checkbox ("Auto Renewal" or "Subscription").') }}</p>
            <p>{{ __('If the user does not enable automatic renewal, the subscription will not renew automatically and the user must manually purchase a new subscription if they wish to continue using the Service after the current subscription period expires.') }}</p>
            <p>{{ __('If the user enables automatic renewal, the subscription fee will be automatically charged to the designated payment method at the beginning of each subsequent billing period until the user cancels the automatic renewal feature.') }}</p>
        </div>

        <div class="tos-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:14px_18px] [margin:12px_0_16px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:4px]! [&_p]:[font-size:13px]! [&_.label]:[font-size:10px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:4px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('💳 Payments') }}</div>
            <p>{{ __('All payments are') }} <strong>{{ __('final and non-refundable') }}</strong>{{ __(', except where otherwise required by applicable law.') }}</p>
        </div>

        <div class="tos-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:14px_18px] [margin:12px_0_16px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:4px]! [&_p]:[font-size:13px]! [&_.label]:[font-size:10px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:4px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('📧 Automatic Renewal Notifications') }}</div>
            <p>{{ __('Users will receive an email notification before the automatic payment date:') }}</p>
            <ul class="tos-guide-list">
                <li><strong>{{ __('3 days in advance') }}</strong> {{ __('for monthly and quarterly plans;') }}</li>
                <li><strong>{{ __('24 hours in advance') }}</strong> {{ __('for weekly plans.') }}</li>
            </ul>
        </div>

        <div class="tos-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:14px_18px] [margin:12px_0_16px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:4px]! [&_p]:[font-size:13px]! [&_.label]:[font-size:10px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:4px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('💰 Price Changes') }}</div>
            <p>{{ __('We reserve the right to change subscription fees. Users will be notified of any price changes at least') }} <strong>{{ __('30 calendar days in advance') }}</strong>{{ __('. Any new pricing will take effect from the next billing cycle.') }}</p>
        </div>

        <div class="tos-info-card warning [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:14px_18px] [margin:12px_0_16px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [background:linear-gradient(135deg,_var(--surface-neutral-0-5),_var(--surface-warning-0))]! [border-left-color:var(--border-warning)]! dark:[background:linear-gradient(135deg,_var(--status-warning-surface),_var(--status-warning-surface))]! dark:[border-left-color:var(--status-warning-border)]! [&_.label]:[color:var(--text-warning-30)]! [&_p]:[margin-bottom:4px]! [&_p]:[font-size:13px]! [&_.label]:[font-size:10px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:4px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('💳 Payment Methods and User Responsibility') }}</div>
            <p>{{ __('Subscription payments may only be made using a payment card registered in the') }} <strong>{{ __('user\'s own name') }}</strong>{{ __('. The platform performs automatic verification of the cardholder\'s first and last name; therefore, payments made using a card issued in another person\'s name may be technically restricted.') }}</p>
        </div>

        <div class="tos-info-card danger [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:14px_18px] [margin:12px_0_16px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:4px]! [&_p]:[font-size:13px]! [&_.label]:[font-size:10px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:4px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('⚠️ Unauthorized Transactions') }}</div>
            <p>{{ __('The use of another person\'s (third party\'s) bank card is strictly prohibited. If an unauthorized transaction is processed due to a technical issue, BF Markets reserves the right to immediately suspend the user\'s account, cancel the transaction, and initiate additional verification procedures.') }}</p>
            <p>{{ __('By using the Service, you confirm that the payment card used belongs to you and that you accept full responsibility for any unauthorized activity.') }}</p>
            <p>{{ __('If an automatic payment cannot be processed due to technical reasons, including banking restrictions, expired cards, insufficient funds, or other technical issues, the user must manually make the payment if they wish to continue using the Service.') }}</p>
        </div>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 9 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">9</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-check-double"></i> {{ __('Data Accuracy') }}</span>
        </div>
        <p>{{ __('We do not guarantee that the information and data provided through the platform will always be 100% accurate, complete, uninterrupted, or error-free.') }}</p>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 10 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">10</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-handshake"></i> {{ __('Third-Party Services') }}</span>
        </div>
        <p>{{ __('The Service may rely on third-party services, including payment providers and data suppliers. We are not responsible for the performance, availability, or reliability of such third-party services.') }}</p>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 11 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">11</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-copyright"></i> {{ __('Intellectual Property') }}</span>
        </div>
        <p>{{ __('All content, data, designs, software, and other intellectual property displayed on the platform belong to BF Markets or are used under appropriate licenses or permissions.') }}</p>
        <p>{{ __('Any copying, distribution, transmission, modification, or commercial use of any materials without our prior written consent is strictly prohibited.') }}</p>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 12 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">12</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-balance-scale"></i> {{ __('Limitation of Liability') }}</span>
        </div>
        <p>{{ __('BF Markets shall not be liable for:') }}</p>
        <ul class="tos-guide-list">
            <li>{{ __('Financial losses;') }}</li>
            <li>{{ __('Trading or betting outcomes;') }}</li>
            <li>{{ __('Data errors, delays, or interruptions;') }}</li>
            <li>{{ __('Temporary unavailability of the Service;') }}</li>
            <li>{{ __('Any decisions made by users based on information available on the platform.') }}</li>
        </ul>
        <div class="tos-info-card danger [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:14px_18px] [margin:12px_0_16px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:4px]! [&_p]:[font-size:13px]! [&_.label]:[font-size:10px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:4px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('⚠️ Payment Provider Disclaimer') }}</div>
            <p>{{ __('We are not responsible for technical failures or interruptions of payment providers, including but not limited to TBC, PayPal, BOG, Flitt, or any other independent payment service providers.') }}</p>
        </div>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 13 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">13</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-toggle-on"></i> {{ __('Service Availability') }}</span>
        </div>
        <p>{{ __('We reserve the right to temporarily suspend, modify, update, or discontinue the Service at any time without prior notice.') }}</p>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 14 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">14</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-user-slash"></i> {{ __('Account Suspension and Termination') }}</span>
        </div>
        <p>{{ __('We may temporarily suspend, restrict, or permanently terminate a user\'s account if there is reasonable suspicion that these Terms of Service or applicable laws have been violated.') }}</p>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 15 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">15</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-gavel"></i> {{ __('Governing Law') }}</span>
        </div>
        <p>{{ __('These Terms of Service shall be governed by and construed in accordance with the laws of') }} <strong>{{ __('Georgia') }}</strong>.</p>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 16 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">16</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-sync-alt"></i> {{ __('Amendments') }}</span>
        </div>
        <p>{{ __('We reserve the right to amend these Terms of Service at any time. The updated version will be published on this page and, unless otherwise specified, shall become effective immediately upon publication.') }}</p>
    </div>

    <div class="tos-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 17 ===== -->
    <div class="tos-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="tos-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="tos-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">17</span>
            <span class="tos-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-envelope"></i> {{ __('Contact Information') }}</span>
        </div>
        <p><i class="fas fa-envelope [color:rgb(var(--primary))]! [margin-right:8px]!"></i> <a href="mailto:office@bfmarketsanalytics.com">{{ __('office') }}@bfmarketsanalytics{{ __('.com') }}</a></p>
    </div>

@endsection
