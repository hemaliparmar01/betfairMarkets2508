{{-- <div class="border-b border-[var(--border-neutral-5-2)] !p-[12px] nav-links flex flex-wrap gap-6 text-[15px] font-medium  [gap:28px]  [font-size:15px] [color:var(--text-brand-60)]  dark:[&_span]:[color:rgb(var(--text-soft))] dark:[&_span]:[cursor:pointer] dark:[&_span.active-nav]:[color:var(--text-on-primary)] dark:[&_span.active-nav]:[border-bottom-color:rgb(var(--border))] dark:[&_span:hover]:[color:var(--text-on-primary)] [&_span]:[cursor:pointer] [&_span]:[padding:4px_0] [&_span]:[border-bottom:2px_solid_transparent] [&_span]:[transition:0.2s] [&_span.active-nav]:[border-bottom-color:var(--border-primary)] [&_span.active-nav]:[color:rgb(var(--text-main))] [&_span:hover]:[color:rgb(var(--text-main))] [&_span:hover]:[border-bottom-color:var(--border-muted-soft)]" id="navLinks">
    <a href="{{ route('football.home') }}" class="active-nav">Fixtures</a>
    <a href="{{ route('football.market-activity') }}">Market Activity</a>
    <a href="#">Notifications</a>
    <a href="#">Subscriptions</a>
    <a href="#">Contact</a>
</div> --}}
{{-- <div id="navLinks" class="flex flex-wrap !p-[12px] items-center gap-3 border-b border-[var(--border-neutral-5-2)] p-3 text-[15px] font-medium">
    <a href="{{ route('football.home') }}" @class(['group flex min-w-[190px] items-center gap-3 rounded-lg border bg-[rgb(var(--surface))] !p-[8px] transition duration-200','border-[var(--border-neutral-5-5)] hover:border-[var(--border-brand-15-2)] hover:shadow-sm','border-l-[3px] !border-l-[var(--border-brand-15-2)] shadow-sm' => request()->routeIs('football.home')])>
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

    <a href="{{ route('football.market-activity') }}" @class(['group flex min-w-[190px] items-center gap-3 rounded-lg border bg-[rgb(var(--surface))] !p-[8px]  transition duration-200','border-[var(--border-neutral-5-5)] hover:border-[var(--border-warning-10)] hover:shadow-sm','border-l-[3px] !border-l-[var(--border-warning-10)] shadow-sm' => request()->routeIs('football.market-activity'),])>
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

    <span class="mx-2 hidden h-10 w-px bg-[var(--surface-neutral-5-5)] md:block"></span>

    <a href="#" class="rounded-md !p-[8px] text-[var(--text-brand-60-2)] transition hover:bg-[var(--surface-neutral-0-13)] hover:text-[var(--text-brand-15)]">{{ __('Notifications') }}</a>
    <a href="#" class="rounded-md !p-[8px] text-[var(--text-brand-60-2)] transition hover:bg-[var(--surface-neutral-0-13)] hover:text-[var(--text-brand-15)]">{{ __('Subscriptions') }}</a>
    <a href="#" class="rounded-md !p-[8px] text-[var(--text-brand-60-2)] transition hover:bg-[var(--surface-neutral-0-13)] hover:text-[var(--text-brand-15)]">{{ __('Contact') }}</a>
</div> --}}
