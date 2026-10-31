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
@endsection
