<aside class="w-48 shrink-0 overflow-y-auto border-r border-[var(--border-slate-200)] bg-[rgb(var(--surface))] p-3 sm:w-60">
    <nav class="space-y-2" aria-label="{{ __('Admin navigation') }}">
        @foreach (['admin.dashboard' => 'Dashboard', 'admin.users' => 'Customers', 'admin.subscriptions.index' => 'Subscriptions', 'admin.contact_messages.index' => 'Contact Messages'] as $routeName => $label)
            @if (Route::has($routeName))
                <a href="{{ route($routeName) }}" class="block rounded-xl px-3 py-3 text-sm font-semibold {{ request()->routeIs($routeName, $routeName.'.*') ? 'bg-[var(--surface-slate-900)] text-white' : 'text-[var(--text-slate-700)] hover:bg-[var(--surface-slate-100)]' }}">{{ __($label) }}</a>
            @endif
        @endforeach
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="w-full rounded-xl px-3 py-3 text-left text-sm font-semibold text-[var(--text-red-700)] hover:bg-[var(--surface-red-50)]">{{ __('Logout') }}</button>
        </form>
    </nav>
</aside>
