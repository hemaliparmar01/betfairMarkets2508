{{-- <div
    class="flex flex-wrap items-center justify-between border-b border-[var(--color-border)] pb-3 transition-colors duration-300">
    <div class="flex flex-col items-start">
        <img src="{{ url('logo.png') }}" class="h-auto max-w-full w-[250px] max-[480px]:w-[190px]" alt="" srcset="">
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <button
            class="rounded-[30px] border border-[var(--color-border-control)] bg-transparent px-3 py-[3px] text-base text-[var(--color-text-muted)] transition-colors duration-200 hover:border-[var(--color-primary)] hover:text-[var(--color-text-main)]"
            id="themeToggle" title="Dark/Light mode"><i class="fas fa-moon"></i></button>
        <button
            class="lang-btn active-lang rounded-[30px] border border-[var(--color-border-control)] bg-transparent px-3 py-[3px] text-[13px] font-medium text-[var(--color-text-muted)] transition-colors duration-200 [&.active-lang]:border-[var(--color-primary)] [&.active-lang]:bg-[var(--color-primary)] [&.active-lang]:text-[var(--color-on-primary)] dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] dark:[&.active-lang]:[background:var(--button-primary)] dark:[&.active-lang]:[border-color:rgb(var(--border))] dark:[&.active-lang]:[color:var(--text-on-primary)] [background:none] [border:1px_solid_var(--border-control)] [padding:3px_12px] [border-radius:30px] [font-size:13px] [font-weight:500] [color:var(--text-secondary)] [cursor:pointer] [transition:0.2s] [&.active-lang]:[background:var(--surface-heading)] [&.active-lang]:[color:var(--text-on-primary)] [&.active-lang]:[border-color:var(--border-heading)] [padding:4px_14px] [text-decoration:none]"
            data-lang="en">EN</button>
        <button
            class="lang-btn rounded-[30px] border border-[var(--color-border-control)] bg-transparent px-3 py-[3px] text-[13px] font-medium text-[var(--color-text-muted)] transition-colors duration-200 [&.active-lang]:border-[var(--color-primary)] [&.active-lang]:bg-[var(--color-primary)] [&.active-lang]:text-[var(--color-on-primary)] dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] dark:[&.active-lang]:[background:var(--button-primary)] dark:[&.active-lang]:[border-color:rgb(var(--border))] dark:[&.active-lang]:[color:var(--text-on-primary)] [background:none] [border:1px_solid_var(--border-control)] [padding:3px_12px] [border-radius:30px] [font-size:13px] [font-weight:500] [color:var(--text-secondary)] [cursor:pointer] [transition:0.2s] [&.active-lang]:[background:var(--surface-heading)] [&.active-lang]:[color:var(--text-on-primary)] [&.active-lang]:[border-color:var(--border-heading)] [padding:4px_14px] [text-decoration:none]"
            data-lang="ka">KA</button>
        <span
            class="cursor-default rounded-[60px] border border-[var(--color-border-strong)] bg-transparent px-4 py-1 text-[13px] font-medium text-[var(--color-text-muted)] transition-colors duration-300">{{ __('Login') }}</span>
        <span
            class="cursor-default rounded-[60px] bg-[var(--color-primary)] px-[18px] py-[5px] text-[13px] font-medium text-[var(--color-on-primary)] transition-colors duration-300">{{ __('Start Free Trial') }}</span>
    </div>
</div>

<div class="my-1.5 flex flex-wrap gap-7 text-[15px] font-medium text-[var(--color-text-secondary)]" id="navLinks">
    <span
        class="active-nav cursor-pointer border-b-2 border-transparent py-1 transition-colors duration-200 hover:border-[var(--color-primary-hover)] hover:text-[var(--color-text-heading)] [&.active-nav]:border-[var(--color-primary-accent)] [&.active-nav]:text-[var(--color-text-heading)]">{{ __('Fixtures') }}</span>
    <span
        class="cursor-pointer border-b-2 border-transparent py-1 transition-colors duration-200 hover:border-[var(--color-primary-hover)] hover:text-[var(--color-text-heading)] [&.active-nav]:border-[var(--color-primary-accent)] [&.active-nav]:text-[var(--color-text-heading)]">{{ __('Market Activity') }}</span>
    <span
        class="cursor-pointer border-b-2 border-transparent py-1 transition-colors duration-200 hover:border-[var(--color-primary-hover)] hover:text-[var(--color-text-heading)] [&.active-nav]:border-[var(--color-primary-accent)] [&.active-nav]:text-[var(--color-text-heading)]">{{ __('Notifications') }}</span>
    <span
        class="cursor-pointer border-b-2 border-transparent py-1 transition-colors duration-200 hover:border-[var(--color-primary-hover)] hover:text-[var(--color-text-heading)] [&.active-nav]:border-[var(--color-primary-accent)] [&.active-nav]:text-[var(--color-text-heading)]">{{ __('Subscriptions') }}</span>
    <span
        class="cursor-pointer border-b-2 border-transparent py-1 transition-colors duration-200 hover:border-[var(--color-primary-hover)] hover:text-[var(--color-text-heading)] [&.active-nav]:border-[var(--color-primary-accent)] [&.active-nav]:text-[var(--color-text-heading)]">{{ __('Contact') }}</span>
</div> --}}
{{-- <div class="top-bar dark:[border-bottom-color:rgb(var(--border))] [display:flex] [align-items:center] [justify-content:space-between] [flex-wrap:wrap] [padding-bottom:12px] [border-bottom:1px_solid_var(--border-neutral-5-2)] [transition:border-color_0.3s] [gap:14px] [padding-bottom:14px] max-[480px]:[align-items:center]">
    <div class="logo-area [display:flex] [flex-direction:column] [align-items:flex-start] [gap:0]">
        <span class="logo-bf dark:[color:var(--text-on-primary)] dark:[&_i]:[color:rgb(var(--primary))] [font-weight:700] [font-size:26px] [letter-spacing:-0.5px] [color:rgb(var(--text-main))] [background:transparent] [padding:0] [display:flex] [align-items:center] [gap:8px] [transition:color_0.3s] [&_i]:[font-size:22px] [&_i]:[color:rgb(var(--text-main))] [&_i]:[transition:color_0.3s] [font-size:24px] [&_i]:[font-size:20px] [&_i]:[color:rgb(var(--primary))] max-[768px]:[font-size:20px]"><i class="fas fa-chart-line"></i> BF Markets</span>
        <span class="logo-sub dark:[color:rgb(var(--text-soft))] [font-weight:400] [font-size:13px] [color:rgb(var(--text-muted))] [letter-spacing:0.2px] [margin-top:-2px] [transition:color_0.3s] [font-size:12px] max-[768px]:[font-size:11px]">The Data Terminal for Sports Exchange Markets</span>
    </div>
    <div class="top-right [display:flex] [align-items:center] [gap:16px] [flex-wrap:wrap] [gap:10px] max-[480px]:[width:100%] max-[480px]:[justify-content:flex-start]">
        <button class="theme-toggle dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] [background:none] [border:1px_solid_var(--border-control)] [border-radius:30px] [padding:3px_12px] [font-size:16px] [cursor:pointer] [color:var(--text-secondary)] [transition:0.2s] [padding:4px_14px] [font-size:15px]" id="themeToggle" title="Dark/Light mode"><i class="fas fa-moon"></i></button>
        <button class="lang-btn active-lang dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] dark:[&.active-lang]:[background:var(--button-primary)] dark:[&.active-lang]:[border-color:rgb(var(--border))] dark:[&.active-lang]:[color:var(--text-on-primary)] [background:none] [border:1px_solid_var(--border-control)] [padding:3px_12px] [border-radius:30px] [font-size:13px] [font-weight:500] [color:var(--text-secondary)] [cursor:pointer] [transition:0.2s] [&.active-lang]:[background:var(--surface-heading)] [&.active-lang]:[color:var(--text-on-primary)] [&.active-lang]:[border-color:var(--border-heading)] [padding:4px_14px] [text-decoration:none]" data-lang="en">EN</button>
        <button class="lang-btn dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] dark:[&.active-lang]:[background:var(--button-primary)] dark:[&.active-lang]:[border-color:rgb(var(--border))] dark:[&.active-lang]:[color:var(--text-on-primary)] [background:none] [border:1px_solid_var(--border-control)] [padding:3px_12px] [border-radius:30px] [font-size:13px] [font-weight:500] [color:var(--text-secondary)] [cursor:pointer] [transition:0.2s] [&.active-lang]:[background:var(--surface-heading)] [&.active-lang]:[color:var(--text-on-primary)] [&.active-lang]:[border-color:var(--border-heading)] [padding:4px_14px] [text-decoration:none]" data-lang="ka">KA</button>
        <span class="btn-outline dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] [border:1px_solid_var(--border-brand-15)] [background:transparent] [padding:4px_16px] [border-radius:60px] [font-weight:500] [font-size:13px] [color:var(--text-secondary)] [cursor:default] [transition:0.3s] [padding:5px_16px] [font-size:12.5px] [cursor:pointer] [text-decoration:none] [display:inline-block]">Login</span>
        <span class="btn-primary dark:[background:var(--button-primary)] dark:[color:var(--text-on-primary)] [background:var(--surface-heading)] [border:none] [padding:5px_18px] [border-radius:60px] [font-weight:500] [font-size:13px] [color:var(--text-on-primary)] [cursor:default] [transition:0.3s] [padding:5px_16px] [font-size:12.5px] [cursor:pointer] [text-decoration:none] [display:inline-block]">Start Free Trial</span>
    </div>
</div> --}}
<header class="top-bar flex flex-wrap items-center justify-between gap-3.5 border-b border-[var(--border-neutral-5-2)] pb-3.5 dark:[border-bottom-color:rgb(var(--border))] [display:flex] [align-items:center] [justify-content:space-between] [flex-wrap:wrap] [padding-bottom:12px] [border-bottom:1px_solid_var(--border-neutral-5-2)] [transition:border-color_0.3s] [gap:14px] [padding-bottom:14px] max-[480px]:[align-items:center]">
    <div class="logo-area flex flex-col items-start [display:flex] [flex-direction:column] [align-items:flex-start] [gap:0]">
        <a href="{{ route(request()->routeIs('horseracing.*') ? 'horseracing.home' : (request()->routeIs('greyhounds.*') ? 'greyhounds.home' : (request()->routeIs('tennis.*') ? 'tennis.home' : (request()->routeIs('basketball.*') ? 'basketball.home' : (request()->routeIs('baseball.*') ? 'baseball.home' : (request()->routeIs('americanfootball.*') ? 'americanfootball.home' : (request()->routeIs('icehockey.*') ? 'icehockey.home' : (request()->routeIs('cricket.*') ? 'cricket.home' : 'football.home')))))))) }}" class="flex flex-col items-start">
            <img src="{{ url('logo.png') }}" class="h-auto max-w-full w-[250px] max-[480px]:w-[190px]" alt="" srcset="">
        </a>
    </div>
    <button type="button" id="mobileNavToggle" class="ml-auto inline-flex size-10 items-center justify-center rounded-lg border border-[var(--border-control)] text-[var(--text-secondary)] dark:border-[rgb(var(--border))] dark:text-[rgb(var(--text-soft))] lg:hidden" aria-controls="navLinks" aria-expanded="false" aria-label="{{ __('Open menu') }}">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>
    <div class="top-right flex flex-wrap items-center gap-2.5 [display:flex] [align-items:center] [gap:16px] [flex-wrap:wrap] [gap:10px] max-[480px]:[width:100%] max-[480px]:[justify-content:flex-start]">
        <button type="button" class="theme-toggle cursor-pointer rounded-full border border-[var(--border-control)] px-3.5 py-1 dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] [background:none] [border:1px_solid_var(--border-control)] [border-radius:30px] [padding:3px_12px] [font-size:16px] [cursor:pointer] [color:var(--text-secondary)] [transition:0.2s] [padding:4px_14px] [font-size:15px]" id="themeToggle" title="{{ __('Dark/Light mode') }}"><i class="fas fa-moon"></i></button>
        <form method="POST" action="{{ route('locale.update') }}" data-locale-switch class="contents">
            @csrf
            <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
            @foreach (config('app.supported_locales') as $locale => $label)
                <button type="submit" name="locale" value="{{ $locale }}" class="lang-btn {{ app()->isLocale($locale) ? 'active-lang' : '' }} cursor-pointer rounded-full border border-[var(--border-control)] px-3.5 py-1 text-[13px] dark:[border-color:rgb(var(--border))] dark:[color:rgb(var(--text-soft))] dark:[&.active-lang]:[background:var(--button-primary)] dark:[&.active-lang]:[border-color:rgb(var(--border))] dark:[&.active-lang]:[color:var(--text-on-primary)] [background:none] [border:1px_solid_var(--border-control)] [padding:3px_12px] [border-radius:30px] [font-size:13px] [font-weight:500] [color:var(--text-secondary)] [cursor:pointer] [transition:0.2s] [&.active-lang]:[background:var(--surface-heading)] [&.active-lang]:[color:var(--text-on-primary)] [&.active-lang]:[border-color:var(--border-heading)] [padding:4px_14px] [text-decoration:none]" data-lang="{{ $locale }}" aria-pressed="{{ app()->isLocale($locale) ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </form>
        @auth('customers')
            @php $accountCustomer = auth('customers')->user(); @endphp
            <details id="customerAccountMenu" class="relative">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-[var(--border-brand-15)] px-4 py-2 text-sm font-semibold text-[var(--text-secondary)] dark:border-[rgb(var(--border))] dark:text-[rgb(var(--text-soft))] [&::-webkit-details-marker]:hidden">
                    <span class="relative flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[var(--surface-neutral-0-7)] text-sm font-bold text-[rgb(var(--primary))] dark:bg-[rgb(var(--surface))] dark:text-[rgb(var(--text-soft))]" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr(trim($accountCustomer->name), 0, 1)) }}
                        @if ($accountCustomer->avatar_path)
                            <img src="{{ route('customers.avatar') }}" alt="" class="absolute inset-0 size-full object-cover" onerror="this.remove()" />
                        @endif
                    </span>
                    {{ $accountCustomer->name }}
                    <i class="fas fa-chevron-down text-xs" aria-hidden="true"></i>
                </summary>
                <div class="absolute right-0 top-full z-50 mt-2 w-44 rounded-xl border border-[rgb(var(--border))] bg-[rgb(var(--surface))] p-2 shadow-lg dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))]">
                    <a href="{{ route('customers.profile') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-[var(--text-secondary)] hover:bg-[var(--surface-neutral-0-7)] dark:text-white dark:hover:bg-[rgb(var(--surface))]"><i class="fas fa-user" aria-hidden="true"></i>{{ __('My Profile') }}</a>
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-[var(--text-red-600)] hover:bg-[var(--surface-red-50)] dark:text-[var(--text-red-300)] dark:hover:bg-[rgb(var(--surface))]"><i class="fas fa-right-from-bracket" aria-hidden="true"></i>{{ __('Logout') }}</button>
                    </form>
                </div>
            </details>
        @else
            <a href="{{ route('login') }}" class="rounded-full border border-[var(--border-brand-15)] px-4 py-1 text-sm font-medium text-[var(--text-secondary)] dark:border-[rgb(var(--border))] dark:text-[rgb(var(--text-soft))]">{{ __('Login') }}</a>
            <a href="{{ route('register') }}" class="rounded-full bg-[var(--surface-heading)] px-4 py-1 text-sm font-medium text-white dark:bg-[var(--button-primary)]">{{ __('Start Free Trial') }}</a>
        @endauth
    </div>
</header>
<div id="navLinks" class="hidden flex-wrap !p-[12px] items-center gap-3 border-b border-[var(--border-neutral-5-2)] p-3 text-[15px] font-medium lg:flex max-[480px]:!px-0 [&>a:nth-child(-n+3)]:max-[480px]:min-w-0 [&>a:nth-child(-n+3)]:max-[480px]:w-full [&>a]:max-[480px]:break-words max-[480px]:gap-2">
    <a href="{{ route(request()->routeIs('horseracing.*') ? 'horseracing.home' : (request()->routeIs('greyhounds.*') ? 'greyhounds.home' : (request()->routeIs('tennis.*') ? 'tennis.home' : (request()->routeIs('basketball.*') ? 'basketball.home' : (request()->routeIs('baseball.*') ? 'baseball.home' : (request()->routeIs('americanfootball.*') ? 'americanfootball.home' : (request()->routeIs('icehockey.*') ? 'icehockey.home' : (request()->routeIs('cricket.*') ? 'cricket.home' : 'football.home')))))))) }}" @class(['group flex min-w-[190px] items-center gap-3 rounded-lg border bg-[rgb(var(--surface))] !p-[8px] transition duration-200','border-[var(--border-neutral-5-5)] hover:border-[var(--border-brand-15-2)] hover:shadow-sm','border-l-[3px] !border-l-[var(--border-brand-15-2)] shadow-sm' => request()->routeIs('football.home', 'cricket.home', 'icehockey.home', 'americanfootball.home', 'baseball.home', 'basketball.home', 'tennis.home', 'greyhounds.home', 'horseracing.home')])>
        <span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-[var(--surface-neutral-0-11)] text-[var(--text-brand-15)]">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v3M16 2v3M3 9h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 13h2M11 13h2M15 13h2M7 17h2M11 17h2M15 17h2"/>
            </svg>
        </span>

        <span class="flex flex-col leading-tight">
            <span class="font-semibold text-[var(--text-brand-70)]">{{ __('Fixtures') }}</span>
            <span class="mt-1 text-xs font-normal text-[var(--text-brand-40)]">
                {{ __('Matches') }} &amp; {{ __('schedules') }}
            </span>
        </span>
    </a>

    <a href="{{ route(request()->routeIs('horseracing.*') ? 'horseracing.market-activity' : (request()->routeIs('greyhounds.*') ? 'greyhounds.market-activity' : (request()->routeIs('tennis.*') ? 'tennis.market-activity' : (request()->routeIs('basketball.*') ? 'basketball.market-activity' : (request()->routeIs('baseball.*') ? 'baseball.market-activity' : (request()->routeIs('americanfootball.*') ? 'americanfootball.market-activity' : (request()->routeIs('icehockey.*') ? 'icehockey.market-activity' : (request()->routeIs('cricket.*') ? 'cricket.market-activity' : 'football.market-activity')))))))) }}" @class(['group flex min-w-[190px] items-center gap-3 rounded-lg border bg-[rgb(var(--surface))] !p-[8px]  transition duration-200','border-[var(--border-neutral-5-5)] hover:border-[var(--border-warning-10)] hover:shadow-sm','border-l-[3px] !border-l-[var(--border-warning-10)] shadow-sm' => request()->routeIs('football.market-activity', 'cricket.market-activity', 'icehockey.market-activity', 'americanfootball.market-activity', 'baseball.market-activity', 'basketball.market-activity', 'tennis.market-activity', 'greyhounds.market-activity', 'horseracing.market-activity')])>
        <span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-[var(--surface-warning-0-2)] text-[var(--text-warning-10)]">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2 12h4l2.5-7 4 14 3-10 2 3H22"/>
            </svg>
        </span>

        <span class="flex flex-col leading-tight">
            <span class="font-semibold text-[var(--text-brand-70)]">
                {{ __('Market Activity') }}
            </span>
            <span class="mt-1 text-xs font-normal text-[var(--text-brand-40)]">
                {{ __('Live market signals') }}
            </span>
        </span>
    </a>

    <a href="{{ route('football.pinnacle-odds') }}" @class(['group flex min-w-[190px] items-center gap-3 rounded-lg border bg-[rgb(var(--surface))] !p-[8px] transition duration-200','border-[var(--border-neutral-5-5)] hover:border-[var(--border-danger-15)] hover:shadow-sm','border-l-[3px] !border-l-[var(--border-danger-15)] shadow-sm' => request()->routeIs('football.pinnacle-odds')])>
        <span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-[var(--surface-neutral-0-12)] text-[var(--text-danger-15)]">
            <i class="fas fa-arrow-trend-down" aria-hidden="true"></i>
        </span>
        <span class="flex flex-col leading-tight">
            <span class="font-semibold text-[var(--text-brand-70)]">{{ __('Pinnacle Odds') }}</span>
            <span class="mt-1 text-xs font-normal text-[var(--text-brand-40)]">{{ __('Realtime odds drops') }}</span>
        </span>
    </a>

    <span class="mx-2 hidden h-10 w-px bg-[var(--surface-neutral-5-5)] md:block"></span>

    <a href="{{ route('notifications') }}" class="rounded-md !p-[8px] text-[var(--text-brand-60-2)] transition hover:bg-[var(--surface-neutral-0-13)] hover:text-[var(--text-brand-15)]">{{ __('Notifications') }}</a>
    <a href="{{ route('subscriptions') }}" class="rounded-md !p-[8px] text-[var(--text-brand-60-2)] transition hover:bg-[var(--surface-neutral-0-13)] hover:text-[var(--text-brand-15)]">{{ __('Subscriptions') }}</a>
    <a href="{{ route('contact-us') }}" class="rounded-md !p-[8px] text-[var(--text-brand-60-2)] transition hover:bg-[var(--surface-neutral-0-13)] hover:text-[var(--text-brand-15)]">{{ __('Contact') }}</a>
</div>
<script>
    (() => {
        const accountMenu = document.getElementById('customerAccountMenu');
        document.addEventListener('click', (event) => {
            if (accountMenu?.open && !accountMenu.contains(event.target)) accountMenu.open = false;
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && accountMenu?.open) {
                accountMenu.open = false;
                accountMenu.querySelector('summary').focus();
            }
        });
        const toggle = document.getElementById('mobileNavToggle');
        const nav = document.getElementById('navLinks');
        if (!toggle || !nav) return;

        const setOpen = (open) => {
            nav.classList.toggle('hidden', !open);
            nav.classList.toggle('flex', open);
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? (globalThis.bfTranslations?.["Close menu"] ?? "Close menu") : (globalThis.bfTranslations?.["Open menu"] ?? "Open menu"));
            toggle.querySelector('i').className = open ? 'fas fa-times' : 'fas fa-bars';
        };

        toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
        nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                toggle.focus();
            }
        });
    })();
</script>
@include('popup', ['premiumGate' => true])
