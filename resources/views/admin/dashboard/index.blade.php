@extends('admin.layouts.main', ['active' => 'dashboard'])

@section('content')
    <h1 class="mb-6 text-3xl font-bold">{{ __('Dashboard') }}</h1>
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (['totalUsers' => 'Customers', 'onlineUsers' => 'Online Customers', 'restrictedUsers' => 'Restricted Customers', 'expiringSoonUsers' => 'Expiring Within 7 Days', 'activeSubscriptions' => 'Active Subscriptions', 'activeTrials' => 'Active Trials', 'subscriptionPlans' => 'Subscription Plans', 'activePlans' => 'Active Plans'] as $key => $label)
            <div class="rounded-2xl border border-[var(--border-slate-200)] bg-[rgb(var(--surface))] p-5">
                <p class="text-sm text-[var(--text-slate-500)]">{{ __($label) }}</p>
                <p class="mt-2 text-3xl font-semibold">{{ $stats[$key] }}</p>
            </div>
        @endforeach
    </div>
    <section class="overflow-x-auto rounded-2xl border border-[var(--border-slate-200)] bg-[rgb(var(--surface))] p-5">
        <h2 class="mb-4 text-xl font-semibold">{{ __('Recent Customer Logins') }}</h2>
        <table class="w-full text-left text-sm">
            <thead><tr><th class="p-3">{{ __('Customer') }}</th><th class="p-3">{{ __('IP Address') }}</th><th class="p-3">{{ __('Device') }}</th><th class="p-3">{{ __('Logged In') }}</th><th class="p-3">{{ __('Logged Out') }}</th></tr></thead>
            <tbody>
                @forelse ($recentLogins as $login)
                    <tr class="border-t border-[var(--border-slate-100)]">
                        <td class="p-3"><a class="text-[var(--text-blue-700)] underline" href="{{ route('admin.users.edit', $login->customer_id) }}">{{ $login->customer?->email ?? 'Deleted customer' }}</a></td>
                        <td class="p-3">{{ $login->ip_address ?? '—' }}</td>
                        <td class="p-3">{{ $login->device_type }}</td>
                        <td class="p-3">{{ $login->logged_in_at?->format('d M Y H:i') }}</td>
                        <td class="p-3">{{ $login->logged_out_at?->format('d M Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-3 text-[var(--text-slate-500)]">{{ __('No customer logins yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <section id="branding-settings" class="mt-8 rounded-2xl border border-[var(--border-slate-200)] bg-[rgb(var(--surface))] p-5">
        <h2 class="mb-4 text-xl font-semibold">{{ __('Logo and Favicon Settings') }}</h2>
        @if (session('success'))
            <p role="status" class="mb-4 text-[var(--text-green-700)]">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <ul role="alert" class="mb-4 text-[var(--text-red-700)]">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif
        <form method="POST" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data">
            @csrf
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach (config('app.branding_assets') as $key => $label)
                    <div class="rounded-xl border border-[var(--border-slate-200)] p-4">
                        <label for="{{ $key }}" class="mb-3 block text-sm font-semibold">{{ __($label) }}</label>
                        <img src="{{ $branding['assets'][$key] }}" alt="{{ __($label) }}" class="mb-3 h-16 max-w-full object-contain" onerror="this.onerror=null;this.src=@js($branding['defaults'][explode('_', $key)[0]])">
                        <input type="file" id="{{ $key }}" name="{{ $key }}" accept="image/png,image/jpeg,image/gif,image/webp" class="block w-full text-sm">
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-sm text-[var(--text-slate-500)]">{{ __('PNG, JPEG, GIF or WebP. Maximum 2 MB and 4096 × 4096 pixels per image. Leave a field empty to keep its current image.') }}</p>
            <button type="submit" class="mt-4 rounded-xl bg-[var(--surface-slate-900)] px-5 py-3 text-sm font-semibold text-white">{{ __('Save Changes') }}</button>
        </form>
    </section>
@endsection
