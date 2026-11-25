@props(['marketId', 'selectionId'])

<img
    {{ $attributes->class(['h-auto w-full rounded-lg'])->merge(['alt' => __('Market Chart')]) }}
    data-market-chart
    data-chart-key="{{ $marketId }}:{{ $selectionId }}"
    data-chart-url="{{ route('betfair.chart', ['marketId' => $marketId, 'selectionId' => $selectionId]) }}"
    src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='320' height='120'%3E%3Crect width='320' height='120' rx='8' fill='%23f1f5f9'/%3E%3C/svg%3E"
    width="320"
    height="120"
    decoding="async"
>
