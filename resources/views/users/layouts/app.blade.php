<!DOCTYPE html>
<html class="scroll-smooth" lang="{{ current_site_locale() }}" class="h-full">

<head>
    @php
        $pageTitle = trim($__env->yieldContent('title'));
        $metaDescription = trim($__env->yieldContent('meta_description'));
        $resolvedTitle = __($pageTitle !== '' ? $pageTitle : 'BF Markets Analytics | Sports Exchange Data Terminal');
        $resolvedDescription = __($metaDescription !== '' ? $metaDescription : 'BF Markets Analytics is a real-time sports exchange data terminal for tracking odds, liquidity, and market movements. Built for professional traders.');
        $resolvedSiteSettings = $sharedSiteSettings ?? null;
        $resolvedSiteName = $resolvedSiteSettings->site_name ?? 'BetGuru';
        $resolvedFavicon = $resolvedSiteSettings->favicon ?? null;
    @endphp

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $resolvedTitle }}</title>
    <meta name="description" content="{{ $resolvedDescription }}">
    <meta name="keywords" content="sports exchange analytics, betting exchange data terminal, odds trading platform, liquidity tracking, real-time market monitoring">
    <meta name="author" content="Digital Analytics Group LLC">

    <link rel="canonical" href="{{ url()->current() }}">

    <link rel="alternate" hreflang="en" href="{{ locale_switch_url('en') }}">
    <link rel="alternate" hreflang="ka" href="{{ locale_switch_url('ka') }}">
    <link rel="alternate" hreflang="x-default" href="{{ locale_switch_url('en') }}">

    <meta property="og:title" content="{{ $resolvedTitle }}">
    <meta property="og:description" content="{{ $resolvedDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ url('/og-image.jpg') }}">
    <meta property="og:site_name" content="{{ $resolvedSiteName }}">
    <meta name="yandex-verification" content="5cbf522a1f034893" />

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $resolvedTitle }}">
    <meta name="twitter:description" content="{{ $resolvedDescription }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Georgian:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    @if ($resolvedFavicon)
        <link rel="icon" type="image/png" href="{{ asset('storage/' . $resolvedFavicon) }}">
    @else
        <link rel="icon" type="image/svg+xml"
            href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%23FFB300'/%3E%3Ctext x='50' y='72' font-size='48' text-anchor='middle' fill='%230A0A0A' font-weight='bold' font-family='Arial, sans-serif'%3EBF%3C/text%3E%3C/svg%3E">
    @endif

    @yield('head')

    @vite(['resources/css/users/app.css', 'resources/js/app.js'])
    
    @stack('css')
    @include('shared.theme-runtime-css')
</head>

<body class="h-full"
    data-route="{{ Route::currentRouteName() }}"
    data-customer-authenticated="{{ auth('customer')->check() ? '1' : '0' }}"
    data-favorites-toggle-url="{{ route('customer.favorites.toggle') }}"
    data-favorites-state-url="{{ route('customer.favorites.states') }}"
    data-login-url="{{ route('customer.login') }}">
    @include('users.layouts.partials.header')
    @yield('content')

    @php
        $legalPrefix = current_site_locale() === 'ka' ? '/ka' : '';
        $footerSiteName = $resolvedSiteName ?? 'BetGuru';
    @endphp

    @if (!request()->routeIs(['customer.login', 'verification', 'register']))
    @include('users.layouts.partials.footer')
    @include('users.layouts.partials.fixtures.chart-modal')
    @endif

    <button type="button" class="fixed right-[20px] bottom-[20px] w-[45px] h-[45px] border-0 rounded-full betguru-gradient-fill text-[rgb(var(--surface))] text-[24px] cursor-pointer z-[9999]" id="scroll-to-top">↑</button>

    <noscript>
        <div><img src="https://mc.yandex.ru/watch/109087271" class="absolute -left-[9999px]" alt="" />
        </div>
    </noscript>
    <script>
        document.querySelector('#scroll-to-top')?.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth',
            });
        });
    </script>
    @stack('js')
</body>

</html>
