<article class="countrywise-card leaguewise-details-card" data-match-details-panel hidden>
    @if($checkAuthAndSubscription['display_odds'])
        @if (!empty($detailMarkets))
            <template data-match-details-config>{!! json_encode([
                'markets' => $detailMarkets,
                'columns' => $detailColumnOptions,
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</template>
            <div data-match-details-render></div>
        @else
            <div class="leaguewise-market-panel-state leaguewise-market-panel-state--spacious">
                {{ __('No detail history available yet.') }}
            </div>
        @endif
    @else
        <div class="text-[0.9rem] mt-2 mb-2 text-[rgb(var(--text-soft))]">
            @if ($checkAuthAndSubscription['is_authenticated'] === false)
                {{ __('Please log in to access match insights and platform features.') }}
            @elseif ($checkAuthAndSubscription['has_active_subscription'] === false)
                {{ __('Your subscription is inactive. Subscribe now to unlock all premium content and betting insights.') }}
            @endif
        </div>
    @endif
</article>
