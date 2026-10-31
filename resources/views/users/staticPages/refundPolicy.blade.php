@extends('users.layout.main')

@section('title', __('Refund Policy').' · BF Markets')

@section('content')

    <!-- ===== PAGE HEADER ===== -->
    <div class="refund-policy-page-header [margin:28px_0_8px]">
        <h1 class="refund-policy-page-title [font-size:32px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.5px] flex items-center [gap:12px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[font-size:28px] max-[768px]:[font-size:24px]"><i class="fas fa-undo-alt"></i> {{ __('Refund Policy') }}</h1>
        <p class="refund-policy-page-subtitle [font-size:14px] [color:rgb(var(--text-muted))] [margin-top:6px] [transition:color_0.3s] dark:[color:rgb(var(--text-soft))]">{{ __('BF Markets — The Data Terminal for Sports Exchange Markets') }}</p>
        <span class="refund-policy-last-updated inline-block [font-size:12px] [color:var(--text-brand-35)] italic [margin-top:6px] [padding:4px_12px] [background:var(--surface-control)] [border-radius:20px] [transition:0.3s] dark:[background:rgb(var(--surface-soft))] dark:[color:rgb(var(--text-soft))]">{{ __('Last Updated: May 8, 2026') }}</span>
    </div>

    <!-- ===== SECTION 1 ===== -->
    <div class="refund-policy-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="refund-policy-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="refund-policy-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">1</span>
            <span class="refund-policy-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-info-circle"></i> {{ __('General Refund Policy') }}</span>
        </div>
        <p>{{ __('At') }} <strong>{{ __('BetfairMarkets.com') }}</strong>{{ __(', operated by') }} <strong>Digital Analytics Group LLC</strong>{{ __(', all sales are') }} <strong>{{ __('final') }}</strong>.</p>
        <div class="refund-policy-info-card warning [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:14px_18px] [margin:12px_0_16px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [background:linear-gradient(135deg,_var(--surface-neutral-0-5),_var(--surface-warning-0))]! [border-left-color:var(--border-warning)]! dark:[background:linear-gradient(135deg,_var(--status-warning-surface),_var(--status-warning-surface))]! dark:[border-left-color:var(--status-warning-border)]! [&_.label]:[color:var(--text-warning-30)]! [&_p]:[margin-bottom:4px]! [&_p]:[font-size:13px]! [&_.label]:[font-size:10px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:4px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('⚠️ Important') }}</div>
            <p>{{ __('Due to the digital nature of our services and the immediate access provided to our proprietary data analytics tools, we do') }} <strong>{{ __('not offer refunds or credits') }}</strong> {{ __('once a paid subscription has been activated, except where required by applicable law.') }}</p>
        </div>
    </div>

    <div class="refund-policy-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 2 ===== -->
    <div class="refund-policy-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="refund-policy-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="refund-policy-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">2</span>
            <span class="refund-policy-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-gift"></i> {{ __('7-Day Free Trial') }}</span>
        </div>
        <p>{{ __('To ensure transparency and customer satisfaction, we offer a') }} <strong>{{ __('7-day free trial') }}</strong> {{ __('to all new users.') }}</p>
        <div class="refund-policy-info-card success [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:14px_18px] [margin:12px_0_16px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [background:linear-gradient(135deg,_var(--surface-neutral-5-3),_var(--surface-neutral-5-4))]! [border-left-color:var(--border-success)]! dark:[background:linear-gradient(135deg,_var(--status-success-surface),_var(--status-success-surface))]! dark:[border-left-color:var(--status-success-border)]! [&_.label]:[color:var(--text-success)]! [&_p]:[margin-bottom:4px]! [&_p]:[font-size:13px]! [&_.label]:[font-size:10px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:4px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('✓ Trial Period') }}</div>
            <p>{{ __('The trial period allows you to') }} <strong>{{ __('fully evaluate') }}</strong> {{ __('our data feeds, analytical tools, and platform features.') }}</p>
        </div>
        <p>{{ __('By purchasing a paid subscription after the 7-day trial period, you acknowledge that you have') }} <strong>{{ __('evaluated the Service and determined that it meets your requirements') }}</strong>{{ __('. Accordingly, subscription fees are non-refundable except where required by applicable law.') }}</p>
    </div>

    <div class="refund-policy-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 3 ===== -->
    <div class="refund-policy-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="refund-policy-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="refund-policy-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">3</span>
            <span class="refund-policy-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-list-check"></i> {{ __('Trial Period Details') }}</span>
        </div>
        <ul class="refund-policy-guide-list [&_li::before]:content-['▸'] list-none p-0 [margin:8px_0_14px] [&_li]:[padding:5px_0_5px_22px]! [&_li]:[position:relative]! [&_li]:[font-size:13px]! [&_li::before]:[position:absolute] [&_li::before]:[left:4px] [&_li::before]:[color:rgb(var(--primary))] [&_li::before]:[font-weight:700] dark:[&_li::before]:[color:rgb(var(--primary))]">
            <li>{{ __('The 7-day free trial') }} <strong>{{ __('does not require a payment method to be stored in advance') }}</strong>;</li>
            <li>{{ __('Upon expiration of the trial period, the user must') }} <strong>{{ __('make a manual payment') }}</strong> {{ __('to continue accessing the Service;') }}</li>
            <li>{{ __('The recurring payment (Auto-Renewal) feature is activated') }} <strong>{{ __('only if the user explicitly consents') }}</strong> {{ __('to it and enables the feature during the first paid transaction;') }}</li>
            <li>{{ __('Tokenized payment information is stored') }} <strong>{{ __('only by secure payment providers') }}</strong> {{ __('and not by BF Markets.') }}</li>
        </ul>
    </div>

    <div class="refund-policy-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 4 ===== -->
    <div class="refund-policy-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="refund-policy-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="refund-policy-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">4</span>
            <span class="refund-policy-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-times-circle"></i> {{ __('Cancellation') }}</span>
        </div>
        <p>{{ __('You may') }} <strong>{{ __('cancel your auto-renewal subscription at any time') }}</strong> {{ __('through your account settings to prevent future billing.') }}</p>
        <div class="refund-policy-info-card [background:linear-gradient(135deg,_var(--surface-panel),_var(--surface-hover))] [border-left:4px_solid_var(--border-primary)] [border-radius:0_12px_12px_0] [padding:14px_18px] [margin:12px_0_16px] [transition:0.3s] dark:[background:linear-gradient(135deg,_rgb(var(--surface-soft)),_rgb(var(--surface-soft)))] dark:[border-left-color:rgb(var(--border))] [&_p]:[margin-bottom:4px]! [&_p]:[font-size:13px]! [&_.label]:[font-size:10px] [&_.label]:[font-weight:700] [&_.label]:[text-transform:uppercase] [&_.label]:[color:rgb(var(--primary))] [&_.label]:[letter-spacing:0.5px] [&_.label]:[margin-bottom:4px] dark:[&_.label]:[color:rgb(var(--primary))]">
            <div class="label">{{ __('💡 Access After Cancellation') }}</div>
            <p>{{ __('Upon cancellation, you will') }} <strong>{{ __('retain access to the Service') }}</strong> {{ __('until the end of your current paid billing period.') }}</p>
        </div>
    </div>

    <div class="refund-policy-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 5 ===== -->
    <div class="refund-policy-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="refund-policy-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="refund-policy-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">5</span>
            <span class="refund-policy-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-gavel"></i> {{ __('Statutory Rights') }}</span>
        </div>
        <p>{{ __('Nothing in this Refund Policy') }} <strong>{{ __('limits or affects your statutory rights') }}</strong> {{ __('under applicable law.') }}</p>
    </div>

    <div class="refund-policy-divider h-px [background:var(--surface-subtle)] [margin:24px_0] [transition:background_0.3s] dark:[background:rgb(var(--surface))]"></div>

    <!-- ===== SECTION 6 ===== -->
    <div class="refund-policy-section [margin:28px_0] [&_p]:[font-size:14px] [&_p]:[color:var(--text-secondary-strong)] [&_p]:[margin-bottom:8px] [&_p]:[transition:color_0.3s] [&_li]:[font-size:14px] [&_li]:[color:var(--text-secondary-strong)] [&_li]:[margin-bottom:8px] [&_li]:[transition:color_0.3s] dark:[&_p]:[color:rgb(var(--text-soft))] dark:[&_li]:[color:rgb(var(--text-soft))] [&_strong]:[color:rgb(var(--text-main))] [&_strong]:[font-weight:600] [&_strong]:[transition:color_0.3s] dark:[&_strong]:[color:var(--text-on-primary)] [&_a]:[color:rgb(var(--primary))] [&_a]:[text-decoration:none] [&_a]:[font-weight:600] [&_a]:[transition:0.2s] dark:[&_a]:[color:rgb(var(--primary))] [&_a:hover]:[text-decoration:underline]!">
        <div class="refund-policy-section-header flex items-center [gap:12px] [margin-bottom:16px] [padding-bottom:10px] [border-bottom:1px_solid_var(--border-subtle)] [transition:border-color_0.3s] dark:[border-bottom-color:rgb(var(--border))]">
            <span class="refund-policy-section-number inline-flex items-center justify-center [width:34px] [height:34px] [border-radius:10px] [background:linear-gradient(135deg,_var(--surface-heading),_var(--surface-primary))] text-white font-bold [font-size:14px] shrink-0">6</span>
            <span class="refund-policy-section-title [font-size:20px] font-bold [color:rgb(var(--text-main))] [letter-spacing:-0.3px] [transition:color_0.3s] dark:[color:var(--text-on-primary)] [&_i]:[color:rgb(var(--primary))] [&_i]:[margin-right:8px] [&_i]:[font-size:18px] max-[768px]:[font-size:17px]"><i class="fas fa-envelope"></i> {{ __('Contact Us') }}</span>
        </div>
        <p><i class="fas fa-envelope [color:rgb(var(--primary))]! [margin-right:8px]!"></i> <a href="mailto:office@bfmarketsanalytics.com">{{ __('office') }}@bfmarketsanalytics{{ __('.com') }}</a></p>
    </div>

@endsection
