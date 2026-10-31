@php $checkAuthAndSubscription = $checkAuthAndSubscription ?? checkAuthAndSubscription(); @endphp
<div id="tennisFilters" class="my-4 space-y-2 border-b border-[var(--border-subtle)] pb-4 dark:border-[rgb(var(--border))]">
    @foreach ([['all' => 'All', 'live' => 'Live', 'upcoming' => 'Upcoming', 'finished' => 'Finished', 'favorites' => 'Favorites', 'by_time' => 'By Time'], ['fav_losing' => 'Fav Losing', 'fav_not_winning' => 'Fav Not Winning', 'prematch_drops' => 'Prematch Drops', 'market_shock' => 'Market Shock']] as $filters)
        <div class="flex flex-wrap gap-2">
            @foreach ($filters as $value => $label)
                <button type="button" data-filter="{{ $value }}" @disabled(! $checkAuthAndSubscription['display_odds'] && in_array($value, ['ht00', '00_70', 'fav_losing', 'prematch_drops', 'market_shock', 'fav_not_winning'], true)) @class(['filter-pill cursor-pointer disabled:cursor-not-allowed disabled:opacity-40 rounded-full border border-[var(--border-control)] bg-[var(--surface-control)] px-3.5 py-2 text-xs font-semibold text-[var(--text-secondary-strong)] transition hover:border-[var(--border-primary)] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface-soft))] dark:text-[rgb(var(--text-soft))] [&.active]:border-[var(--border-primary)] [&.active]:bg-[var(--surface-heading)] [&.active]:text-white', 'active' => $value === request('type', 'all')])>{{ __($label) }}</button>
            @endforeach
        </div>
    @endforeach
</div>
