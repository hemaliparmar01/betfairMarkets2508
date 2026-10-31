<button type="button" data-timeline-event="{{ $incident['id'] }}"
    data-event-minute="{{ $incident['minute_label'] }}" data-event-player="{{ $incident['player'] }}" data-event-surname="{{ $incident['player_surname'] }}"
    data-event-kind="{{ $incident['kind'] }}" data-event-label="{{ $incident['label'] }}"
    data-event-score="{{ $incident['home_score'] !== null && $incident['away_score'] !== null ? $incident['home_score'].' - '.$incident['away_score'] : '' }}"
    aria-label="{{ $incident['label'].' '.$incident['minute_label'].' '.$incident['player'] }}"
    title="{{ $incident['label'].' '.$incident['minute_label'].' '.$incident['player'] }}"
    @if ($positioned)
        style="left: {{ $incident['left'] }}%{{ $incident['lane'] > 0 ? '; '.($incident['is_home'] ? 'bottom' : 'top').': calc(50% + '.($incident['lane'] * 14).'px)' : '' }}"
    @endif
    @class([
        'z-10 flex h-3.5 w-3.5 shrink-0 cursor-pointer items-center justify-center rounded-sm outline-none hover:ring-2 hover:ring-[var(--border-brand-25)]/60 focus-visible:ring-2 focus-visible:ring-[var(--border-cyan-500)]',
        'absolute -translate-x-1/2' => $positioned,
        'bottom-1/2' => $positioned && $incident['is_home'],
        'top-1/2' => $positioned && ! $incident['is_home'],
        'relative' => ! $positioned,
    ])>
    @if ($incident['kind'] === 'goal')
        <span class="flex h-3 w-3 items-center justify-center text-[11px] leading-none" aria-hidden="true">⚽</span>
    @elseif ($incident['kind'] === 'corner')
        <span class="text-[10px] leading-none" aria-hidden="true">⚑</span>
    @else
        <span class="h-3 w-2 rounded-[1px] shadow-sm {{ $incident['kind'] === 'yellow' ? 'bg-[var(--surface-warning)]' : 'bg-[var(--surface-danger)]' }}" aria-hidden="true"></span>
    @endif
</button>
