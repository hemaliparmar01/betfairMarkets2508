@php
    $icon = match ($filter) {
        'all' => 'fas fa-layer-group',
        'live' => 'fas fa-circle text-[var(--text-danger)] text-[8px]',
        'upcoming' => 'fas fa-clock',
        'finished' => 'fas fa-flag-checkered',
        'favorites' => 'fas fa-star text-[var(--text-warning)]',
        'by_time' => 'fas fa-sort-amount-down',
        'fav_losing' => 'fas fa-arrow-down text-[var(--text-danger)]',
        'prematch_drops' => 'fas fa-arrow-trend-down',
        'market_shock' => 'fas fa-bolt text-[var(--text-warning)]',
        'fav_not_winning' => 'fas fa-hand-paper',
        'halftime' => 'fas fa-pause',
        'quarter_break' => 'fas fa-hourglass-half',
        'period_break' => 'fas fa-hourglass-half',
        'innings_break' => 'fas fa-hourglass-half',
        'extra_innings' => 'fas fa-baseball',
        default => null,
    };
@endphp
@if ($icon !== null)
    <i class="{{ $icon }}" aria-hidden="true"></i>
@endif
