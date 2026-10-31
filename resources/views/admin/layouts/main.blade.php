<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __($title ?? 'Admin Panel') }}</title>

    @stack('css')

    @vite(['resources/admin/css/app.css', 'resources/admin/js/app.js'])
    <script>window.bfTranslations = {{ Illuminate\Support\Js::from($uiTranslations) }};</script>
</head>
<body class="admin-theme h-screen overflow-hidden bg-[var(--surface-slate-100)] text-[var(--text-slate-900)] antialiased">
    <script>
        try {
            if (localStorage.getItem('bf-markets-theme') === 'dark') {
                document.body.classList.add('dark-mode');
                document.documentElement.style.colorScheme = 'dark';
            }
        } catch {}
    </script>
    <div class="flex h-full flex-col">

        <!-- Navbar -->
        @include('admin.layouts.navbar')

        <div class="flex flex-1 overflow-hidden">
            <!-- Sidebar -->
            @include('admin.layouts.sidebar')

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <div class="mx-auto max-w-7xl">
                    {{ $slot ?? '' }}

                    @yield('content')
                </div>
            </main>
        </div>

        <!-- Footer -->
        @include('admin.layouts.footer')
    </div>
    @stack('scripts')
</body>
</html>
