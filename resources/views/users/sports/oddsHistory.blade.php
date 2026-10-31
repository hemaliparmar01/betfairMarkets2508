@php $sequence = oddsMovementSequence($history ?? []); @endphp
@if ($sequence !== '')
    <div data-odds-history class="mt-2 min-w-0 text-left text-xs">
        <span class="font-semibold text-[var(--text-slate-500)] dark:text-[var(--text-slate-400)]">{{ __('Odds history') }}</span>
        <p class="mt-1 max-h-24 overflow-y-auto break-words font-mono leading-5 text-[var(--text-brand-50-2)] dark:text-[rgb(var(--primary))]">{{ $sequence }}</p>
        @if ($incomplete ?? false)
            <p class="mt-1 text-[10px] text-[var(--text-slate-500)] dark:text-[var(--text-slate-400)]">{{ __('Earlier movements may be missing from the previously limited history.') }}</p>
        @endif
    </div>
@endif
