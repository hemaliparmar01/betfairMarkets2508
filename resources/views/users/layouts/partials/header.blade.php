<header data-site-header class="sticky top-0 z-50 !bg-[rgb(var(--bg-soft))]">
    @php
        $customer = auth('customer')->user();
        $currentLocale = current_site_locale();
        $remainingDays = (int) ($customer->remaining_days ?? 7);
        $siteSetting = $sharedSiteSettings ?? null;
    @endphp

    <div class="border-b transition-all duration-300 betguru-header-shell betguru-header-shell--top" data-header-shell>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            {{-- Desktop Header --}}
            <div class="hidden xl:block">

                {{-- Top Row --}}
                <div class="flex items-center justify-between gap-8">

                    {{-- Logo --}}
                    <a href="{{ route('home.en') }}" class="flex min-w-0 items-center gap-3">
                        {{-- <div class="flex w-[230px] flex-shrink-0 items-center justify-center overflow-hidden rounded-2xl text-lg font-bold text-[rgb(var(--surface))]">
                            @if (current_site_locale() == 'ka')
                                @php $translateLogo = data_get(json_decode($siteSetting->logo_translations),current_site_locale()); @endphp
                                <img src="{{ asset('storage/' . $translateLogo) }}" alt="{{ $siteSetting->site_name ?? 'BetGuru' }}" class="h-full w-full object-contain">
                            @else
                                <img src="{{ asset('storage/' . $siteSetting->logo) }}" alt="{{ $siteSetting->site_name ?? 'BetGuru' }}" class="h-full w-full object-contain">
                            @endif
                        </div> --}}

                        @php
                            $currentLocale = current_site_locale();
                            $logoTranslations = json_decode($siteSetting->logo_translations ?? '{}',true);
                            $darkLogoTranslations = json_decode($siteSetting->dark_logo_translations ?? $siteSetting->dark_logo_translation ?? '{}', true);

                            $lightLogo = $currentLocale === 'ka' ? data_get($logoTranslations, $currentLocale) : $siteSetting->logo;
                            $darkLogo = $currentLocale === 'ka' ? data_get($darkLogoTranslations, $currentLocale) : $siteSetting->dark_logo;
                            $lightLogoUrl = !empty($lightLogo) ? asset('storage/' . $lightLogo) : null;
                            $darkLogoUrl = !empty($darkLogo)? asset('storage/' . $darkLogo): $lightLogoUrl;
                        @endphp

                        <div class="flex w-[230px] flex-shrink-0 items-center justify-center overflow-hidden rounded-2xl text-lg font-bold text-[rgb(var(--surface))]">
                            @if ($lightLogoUrl)
                                <img src="{{ $lightLogoUrl }}" data-theme-logo data-light-src="{{ $lightLogoUrl }}" data-dark-src="{{ $darkLogoUrl }}" alt="{{ $siteSetting->site_name ?? 'BetGuru' }}" class="h-full w-full object-contain">
                            @else
                                BG
                            @endif
                        </div>

                        {{-- <div class="min-w-0 leading-tight">
                            @if ($currentLocale == 'en')
                                <p class="truncate text-lg font-bold theme-text-main">
                                    {{ $siteSetting->site_name ?? 'BetGuru' }}
                                </p>
                                <p class="truncate text-xs theme-text-muted">
                                    {{ $siteSetting->tagline ?? 'Full Monitoring of Betfair Markets' }}
                                </p>
                            @else
                                <p class="truncate text-lg font-bold theme-text-main">
                                    {{ data_get(json_decode($siteSetting->site_name_translations), $currentLocale) }}
                                </p>
                                <p class="truncate text-xs theme-text-muted">
                                    {{ data_get(json_decode($siteSetting->tagline_translations), $currentLocale) }}
                                </p>
                            @endif
                        </div> --}}
                    </a>

                    {{-- Right Actions --}}
                    <div class="flex shrink-0 items-center justify-end gap-3">
                        <div class="inline-flex items-center rounded-2xl border px-1 py-1 theme-panel-subtle">
                            <a href="{{ locale_switch_url('en') }}"
                                class="rounded-xl px-3 py-2 text-xs font-semibold transition {{ $currentLocale === 'en' ? 'betguru-toggle-tab-active' : 'theme-text-muted' }}">
                                EN
                            </a>
                            <a href="{{ locale_switch_url('ka') }}"
                                class="rounded-xl px-3 py-2 text-xs font-semibold transition {{ $currentLocale === 'ka' ? 'betguru-toggle-tab-active' : 'theme-text-muted' }}">
                                KA
                            </a>
                        </div>

                        <button type="button" data-theme-toggle
                            class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl border transition theme-panel-subtle"
                            title="{{ __('Switch to dark mode') }}">
                            <span data-theme-icon>🌙</span>
                        </button>

                        @if (! $customer)
                            <a href="{{ route('customer.login') }}"
                                class="inline-flex items-center whitespace-nowrap rounded-2xl px-5 py-3 text-sm font-semibold transition theme-panel-subtle">
                                {{ __('Login') }}
                            </a>

                            <a href="{{ route('register') }}"
                                class="inline-flex items-center whitespace-nowrap rounded-2xl px-5 py-3 text-sm font-semibold text-[rgb(var(--surface))] transition hover:translate-y-[-1px] betguru-gradient-cta">
                                {{ __('Start Free Trial') }}
                            </a>
                        @else
                            <div class="relative flex items-center gap-3" data-account-menu-root>
                                @php
                                    $firstName = $customer->name ?? ($customer->first_name ?? '');
                                    $lastName = $customer->surname ?? ($customer->last_name ?? '');
                                    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));

                                    if (! $initials) {
                                        $initials = strtoupper(substr($customer->username ?? 'U', 0, 2));
                                    }
                                @endphp

                                <button type="button" data-account-menu-toggle aria-expanded="false"
                                    class="inline-flex items-center gap-3 rounded-2xl px-3 py-2 transition theme-panel-surface">
                                    @if ($customer->avatar_path)
                                        <img src="{{ asset('storage/' . $customer->avatar_path) }}"
                                            alt="{{ $customer->username }}"
                                            class="h-10 w-10 rounded-full object-cover">
                                    @else
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold text-[rgb(var(--surface))] betguru-gradient-fill">
                                            {{ $initials }}
                                        </div>
                                    @endif

                                    <span class="hidden max-w-[120px] truncate text-sm font-semibold xl:inline">
                                        {{ $customer->username }}
                                    </span>

                                    <svg class="h-4 w-4 theme-text-muted" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7" />
                                    </svg>
                                </button>

                                <div class="absolute right-0 top-full mt-3 w-72 overflow-hidden rounded-3xl betguru-dropdown-shell" data-account-menu hidden>
                                    <div class="p-4 betguru-border-bottom">
                                        <p class="text-sm font-bold theme-text-main">
                                            {{ $customer->username }}
                                        </p>
                                        <p class="mt-1 truncate text-xs theme-text-muted">
                                            {{ $customer->email }}
                                        </p>
                                    </div>

                                    <div class="p-2">
                                        <a href="{{ route('customer.profile') }}" class="user-menu-link">
                                            {{ __('Profile Settings') }}
                                        </a>

                                        <a href="{{ route('customer.profile.favorites') }}" class="user-menu-link">
                                            {{ __('Favorite Matches') }}
                                        </a>

                                        <a href="{{ route('customer.profile.subscriptions') }}" class="user-menu-link">
                                            {{ __('Subscriptions') }}
                                        </a>
                                    </div>

                                    <form method="POST" action="{{ route('customer.logout') }}" class="p-2 betguru-border-top">
                                        @csrf
                                        <button type="submit" class="user-menu-link-danger w-full text-left">
                                            {{ __('Logout') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Divider --}}
                <div class="my-2 border-t theme-border"></div>

                {{-- Center Navigation Row --}}
                <nav class="flex items-center justify-start gap-2 overflow-x-auto whitespace-nowrap pb-1">
                    <a href="{{ $fixtuesUrl }}"
                        class="rounded-2xl px-5 py-3 text-sm font-semibold transition hover:opacity-80 {{ trim($activeHeaderUrl) == 'fixtures' ? 'betguru-toggle-tab-active' : 'theme-text-main' }}">
                        {{ __('Fixtures') }}
                    </a>

                    <a href="{{ $moneywayUrl }}"
                        class="rounded-2xl px-5 py-3 text-sm font-semibold transition hover:opacity-80 {{ trim($activeHeaderUrl) == 'moneyway' ? 'betguru-toggle-tab-active' : 'theme-text-main' }}">
                        {{ __('Moneyway') }}
                    </a>

                    <a href="#" class="rounded-2xl px-5 py-3 text-sm font-semibold transition theme-text-main hover:opacity-80">
                        {{ __('Notifications') }}
                    </a>

                    <a href="{{ route('checkout.show') }}"
                        class="rounded-2xl px-5 py-3 text-sm font-semibold transition hover:opacity-80 {{ trim($activeHeaderUrl) == 'checkout' ? 'betguru-toggle-tab-active' : 'theme-text-main' }}">
                        {{ __('Subscriptions') }}
                    </a>

                    <a href="{{ route('contactus') }}" class="rounded-2xl px-5 py-3 text-sm font-semibold transition theme-text-main hover:opacity-80">
                        {{ __('Contact') }}
                    </a>
                </nav>
            </div>

            {{-- Mobile Header --}}
            <div class="flex items-center justify-between gap-3 xl:hidden">
                <a href="{{ route('home.en') }}" class="flex min-w-0 flex-1 items-center gap-3">
                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center overflow-hidden rounded-2xl text-lg font-bold text-[rgb(var(--surface))]">
                        @if (! empty($siteSetting?->logo))
                            <img src="{{ asset('storage/' . $siteSetting->logo) }}"
                                alt="{{ $siteSetting->site_name ?? 'BetGuru' }}"
                                class="h-full w-full object-contain">
                        @else
                            BG
                        @endif
                    </div>

                    <div class="min-w-0 leading-tight">
                        @if ($currentLocale == 'en')
                            <p class="truncate text-sm font-bold theme-text-main">
                                {{ $siteSetting->site_name ?? 'BetGuru' }}
                            </p>
                            <p class="truncate text-xs theme-text-muted">
                                {{ $siteSetting->tagline ?? 'Full Monitoring of Betfair Markets' }}
                            </p>
                        @else
                            <p class="truncate text-sm font-bold theme-text-main">
                                {{ data_get(json_decode($siteSetting->site_name_translations), $currentLocale) }}
                            </p>
                            <p class="truncate text-xs theme-text-muted">
                                {{ data_get(json_decode($siteSetting->tagline_translations), $currentLocale) }}
                            </p>
                        @endif
                    </div>
                </a>

                <button type="button" data-mobile-menu-toggle
                    class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl border theme-panel-subtle"
                    aria-expanded="false"
                    aria-label="{{ __('Toggle navigation menu') }}">
                    <svg data-mobile-open-icon xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>

                    <svg data-mobile-close-icon xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" hidden>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile Menu --}}
    <div class="mx-auto mt-3 max-w-7xl overflow-hidden rounded-[28px] border xl:hidden betguru-mobile-menu-shell" data-mobile-menu hidden>
        <div class="space-y-2 p-4">
            <a href="{{ $fixtuesUrl }}" data-close-mobile-menu
                class="betguru-mobile-nav-link {{ trim($activeHeaderUrl) == 'fixtures' ? 'is-active' : '' }}">
                {{ __('Fixtures') }}
            </a>

            <a href="{{ $moneywayUrl }}" data-close-mobile-menu
                class="betguru-mobile-nav-link {{ trim($activeHeaderUrl) == 'moneyway' ? 'is-active' : '' }}">
                {{ __('Moneyway') }}
            </a>

            <a href="#" data-close-mobile-menu class="betguru-mobile-nav-link">
                {{ __('Notifications') }}
            </a>

            <a href="#" data-close-mobile-menu class="betguru-mobile-nav-link">
                {{ __('Historical Data') }}
            </a>

            <a href="{{ route('checkout.show') }}" data-close-mobile-menu
                class="betguru-mobile-nav-link {{ trim($activeHeaderUrl) == 'checkout' ? 'is-active' : '' }}">
                {{ __('Subscriptions') }}
            </a>

            <a href="#" data-close-mobile-menu class="betguru-mobile-nav-link">
                {{ __('Contact') }}
            </a>

            <div class="grid grid-cols-2 gap-2 pt-2">
                <a href="{{ locale_switch_url('en') }}" data-close-mobile-menu
                    class="inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-semibold theme-panel-subtle {{ $currentLocale === 'en' ? 'betguru-toggle-tab-active' : '' }}">
                    EN
                </a>

                <a href="{{ locale_switch_url('ka') }}" data-close-mobile-menu
                    class="inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-semibold theme-panel-subtle {{ $currentLocale === 'ka' ? 'betguru-toggle-tab-active' : '' }}">
                    KA
                </a>
            </div>

            <div class="grid grid-cols-1 gap-3 pt-3">
                <button type="button" data-theme-toggle
                    class="inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-semibold theme-panel-subtle">
                    <span data-theme-label data-light-label="{{ __('Switch to Light Mode') }}" data-dark-label="{{ __('Switch to Dark Mode') }}">{{ __('Switch to Dark Mode') }}</span>
                </button>

                @if (! $customer)
                    <a href="{{ route('customer.login') }}" data-close-mobile-menu
                        class="inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-semibold theme-panel-subtle">
                        {{ __('Login') }}
                    </a>

                    <a href="{{ route('register') }}" data-close-mobile-menu
                        class="inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-semibold text-[rgb(var(--surface))] betguru-gradient-cta">
                        {{ __('Start Free Trial') }}
                    </a>
                @else
                    <div class="rounded-2xl p-4 theme-panel-soft">
                        <div class="flex items-center gap-3">
                            @if ($customer->avatar_path)
                                <img src="{{ asset('storage/' . $customer->avatar_path) }}"
                                    alt="{{ $customer->username }}"
                                    class="h-11 w-11 rounded-full object-cover">
                            @else
                                <div class="flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold text-[rgb(var(--surface))] betguru-gradient-fill">
                                    {{ $initials ?? strtoupper(substr($customer->username ?? 'U', 0, 2)) }}
                                </div>
                            @endif

                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold theme-text-main">
                                    {{ $customer->username }}
                                </p>
                                <p class="truncate text-xs theme-text-muted">
                                    {{ $customer->email }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 text-xs font-semibold theme-text-muted">
                            {{ $customer->subscription_plan ?? __('Trial') }}
                            {{ __(':days days left', ['days' => $remainingDays]) }}
                        </div>
                    </div>

                    <a href="{{ route('customer.profile') }}" data-close-mobile-menu
                        class="betguru-mobile-nav-link">
                        {{ __('Profile Settings') }}
                    </a>

                    <a href="{{ route('customer.profile.favorites') }}" data-close-mobile-menu
                        class="betguru-mobile-nav-link">
                        {{ __('Favorite Matches') }}
                    </a>

                    <a href="{{ route('customer.profile.subscriptions') }}" data-close-mobile-menu
                        class="betguru-mobile-nav-link">
                        {{ __('Subscriptions') }}
                    </a>

                    <form method="POST" action="{{ route('customer.logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left user-menu-link-danger">
                            {{ __('Logout') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</header>
