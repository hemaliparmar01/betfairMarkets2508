<div class="betwatch-toolbar-tabs betwatch-toolbar-tabs">
    @foreach ($moneywayTabs as $tab)
        <button type="button" class="betwatch-toolbar-pill betwatch-ui-pill"
            data-match-tab="{{ $tab['key'] }}">
            {{ $tab['label'] }}
        </button>
    @endforeach

    <div class="betwatch-moneyway-market-menu" data-moneyway-market-root>
        <button type="button" class="betwatch-toolbar-pill betwatch-ui-pill"
            data-moneyway-market-toggle aria-expanded="false">
            {{ __('Markets') }}
        </button>

        <div class="betwatch-moneyway-market-dropdown" data-moneyway-market-menu hidden>
            @foreach ($moneywayMarketOptions as $option)
                <label class="betwatch-moneyway-market-option">
                    <input type="checkbox" value="{{ $option }}" data-moneyway-market>
                    <span>{{ $option }}</span>
                </label>
            @endforeach
        </div>
    </div>
</div>
