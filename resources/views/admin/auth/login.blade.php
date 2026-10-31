<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Admin Login') }}</title>
    @vite(['resources/admin/css/app.css', 'resources/admin/js/app.js'])
    <script>window.bfTranslations = {{ Illuminate\Support\Js::from($uiTranslations) }};</script>
</head>
<body class="admin-theme min-h-screen bg-[var(--surface-slate-100)] text-[var(--text-slate-900)] antialiased">
    <script>
        try {
            if (localStorage.getItem('bf-markets-theme') === 'dark') {
                document.body.classList.add('dark-mode');
                document.documentElement.style.colorScheme = 'dark';
            }
        } catch {}
    </script>
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-md">
            <div class="rounded-2xl border border-[var(--border-slate-200)] bg-[rgb(var(--surface))] shadow-sm">
                <div class="px-6 pt-6 pb-5 sm:px-8 sm:pt-8">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--surface-slate-900)] text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 4v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V7l7-4z" />
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-lg font-semibold tracking-tight text-[var(--text-slate-900)]">{{ __('Admin Panel') }}</h1>
                            <p class="text-sm text-[var(--text-slate-500)]">{{ __('Sign in to continue') }}</p>
                        </div>
                    </div>

                    @if (session('status'))
                        <div class="mb-4 rounded-xl border border-[var(--border-green-200)] bg-[var(--surface-green-50)] px-3 py-2 text-sm text-[var(--text-green-700)]">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.login') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-[var(--text-slate-700)]">
                                {{ __('Email') }}
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                placeholder="{{ __('Enter your email') }}"
                                class="h-11 w-full rounded-xl border border-[var(--border-slate-300)] bg-[rgb(var(--surface))] px-3 text-sm text-[var(--text-slate-900)] outline-none transition focus:border-[var(--border-slate-300)] focus:ring-2 focus:ring-[var(--border-slate-200)]"
                            >
                            @error('email')
                                <p class="mt-1.5 text-sm text-[var(--text-red-500)]">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="password" class="block text-sm font-medium text-[var(--text-slate-700)]">
                                    {{ __('Password') }}
                                </label>

                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-sm text-[var(--text-slate-500)] hover:text-[var(--text-slate-700)]">
                                        {{ __('Forgot?') }}
                                    </a>
                                @endif
                            </div>

                            <div class="relative">
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    required
                                    placeholder="{{ __('Enter your password') }}"
                                    class="h-11 w-full rounded-xl border border-[var(--border-slate-300)] bg-[rgb(var(--surface))] px-3 pr-10 text-sm text-[var(--text-slate-900)] outline-none transition focus:border-[var(--border-slate-300)] focus:ring-2 focus:ring-[var(--border-slate-200)]"
                                >

                                <!-- Eye Icon -->
                                <button
                                    type="button"
                                    onclick="togglePassword()"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-[var(--text-slate-500)] hover:text-[var(--text-slate-700)]"
                                >
                                    <!-- Eye Open -->
                                    <svg id="eye-open" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M2.25 12s3.75-7.5 9.75-7.5S21.75 12 21.75 12 18 19.5 12 19.5 2.25 12 2.25 12z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M12 15a3 3 0 100-6 3 3 0 000 6z" />
                                    </svg>

                                    <!-- Eye Slash -->
                                    <svg id="eye-close" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M3 3l18 18M10.5 10.5a3 3 0 004.243 4.243M9.88 5.09A9.77 9.77 0 0112 4.5c6 0 9.75 7.5 9.75 7.5a13.05 13.05 0 01-2.44 3.32M6.53 6.53A13.1 13.1 0 002.25 12s3.75 7.5 9.75 7.5c1.61 0 3.07-.33 4.38-.91" />
                                    </svg>
                                </button>
                            </div>

                            @error('password')
                                <p class="mt-1.5 text-sm text-[var(--text-red-500)]">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <label class="inline-flex items-center gap-2 text-sm text-[var(--text-slate-700)]">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    class="h-4 w-4 rounded border-[var(--border-slate-300)] text-[var(--text-slate-900)] focus:ring-[var(--border-slate-200)]"
                                >
                                <span>{{ __('Remember me') }}</span>
                            </label>
                        </div>

                        <button
                            type="submit"
                            class="h-11 w-full rounded-xl bg-[var(--surface-slate-900)] text-sm font-medium text-white transition hover:bg-[var(--surface-slate-800)] focus:outline-none focus:ring-2 focus:ring-[var(--border-slate-200)]"
                        >
                            {{ __('Sign In') }}
                        </button>
                    </form>
                </div>

                <div class="rounded-b-2xl border-t border-[var(--border-slate-200)] bg-[var(--surface-slate-50)] px-6 py-3 sm:px-8">
                    <p class="text-center text-xs text-[var(--text-slate-500)]">
                        {{ __('Authorized access only') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const eyeOpen = document.getElementById('eye-open');
            const eyeClose = document.getElementById('eye-close');

            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClose.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClose.classList.add('hidden');
            }
    }
</script>
</body>
</html>
