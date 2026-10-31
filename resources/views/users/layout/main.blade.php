<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('BF Markets · Data Terminal'))</title>
    <meta name="author" content="Digital Analytics Group LLC">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Georgian:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" type="image/png" href="{{ url('favicon.png') }}">
    @vite(['resources/users/css/app.css', 'resources/users/js/app.js'])
    @stack('head')
    <script>window.bfTranslations = {{ Illuminate\Support\Js::from($uiTranslations) }};</script>
</head>
<body class="min-w-0 max-[480px]:!px-2 max-[480px]:!pt-2 @if (request()->routeIs('cricket.*', 'icehockey.*', 'americanfootball.*', 'baseball.*', 'basketball.*', 'tennis.*', 'greyhounds.*', 'horseracing.*')) [&_a]:cursor-pointer [&_button:enabled]:cursor-pointer [&_select]:cursor-pointer [&_summary]:cursor-pointer @endif">
    <script>
        try {
            if (localStorage.getItem('bf-markets-theme') === 'dark') {
                document.body.classList.add('dark-mode');
                document.documentElement.style.colorScheme = 'dark';
            }
        } catch {}
    </script>
    <div class="app mx-auto w-full rounded-3xl bg-[rgb(var(--surface))] px-6 pb-6 pt-[18px] shadow-[0_16px_48px_var(--shadow-brand-85-2)] max-[800px]:p-3.5 dark:[background:rgb(var(--surface))] dark:[border-color:rgb(var(--border))] [max-width:1480px] [margin:0_auto] [background:rgb(var(--surface))] [border-radius:32px] [box-shadow:0_16px_48px_var(--shadow-brand-85-3)] [padding:20px_28px_32px] [transition:background_0.3s] max-[800px]:[padding:14px] [width:100%] [max-width:100%] [border-radius:24px] [padding:18px_24px_24px] max-[768px]:[padding:14px_14px_20px] max-[768px]:[border-radius:16px]">
        @unless (request()->routeIs('login', 'register', 'customers.verification*', 'login.mobile.*', 'customers.password.*'))
            @include('users.layout.header')
        @endunless
        <main class="min-w-0 max-w-full [overflow-wrap:anywhere] [&_img]:max-w-full [&_img]:h-auto [&_input]:max-w-full [&_textarea]:max-w-full">@yield('content')</main>

        @include('users.layout.footer')
        <button type="button" class="fixed bottom-5 right-5 z-[9999] flex size-11 items-center justify-center rounded-full bg-[var(--surface-heading)] text-2xl text-white" id="scroll-to-top" aria-label="{{ __('Scroll to top') }}">↑</button>
    </div>
    <script>
        document.getElementById('scroll-to-top')?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    </script>
    @stack('js')
    @auth('customers')
        <div id="alert-popup-container" data-customer-id="{{ auth('customers')->id() }}" data-popup-enabled="{{ auth('customers')->user()->popup_enabled ?? true ? '1' : '0' }}" data-preferences-url="{{ route('notifications.update') }}" class="pointer-events-none fixed right-4 top-4 z-[11000] flex w-[min(420px,calc(100%-2rem))] flex-col gap-3" aria-live="polite"></div>
    @endauth
</body>
</html>
