<article class="betwatch-moneyway-filter-card">
    <div class="betwatch-moneyway-filter-head">
        <h2 class="betwatch-moneyway-filter-title">{{ __('Filters') }}</h2>
        <button type="button" class="betwatch-moneyway-filter-reset" data-moneyway-reset>{{ __('Reset') }}</button>
    </div>

    @foreach ([
        'volume' => ['label' => __('Volume'), 'min' => 0, 'max' => 850000, 'step' => 1000, 'mode' => 'numeric'],
        'percent' => ['label' => __('Percent of Volume'), 'min' => 0, 'max' => 100, 'step' => 1, 'mode' => 'numeric'],
        'odd' => ['label' => __('Odd'), 'min' => 1.01, 'max' => 1000, 'step' => 0.01, 'mode' => 'decimal'],
    ] as $group => $range)
        <div class="betwatch-moneyway-filter-group">
            <label class="betwatch-moneyway-filter-label">{{ $range['label'] }}</label>
            <div class="betwatch-moneyway-range-shell">
                <span class="betwatch-moneyway-range-track"></span>
                <span class="betwatch-moneyway-range-fill" data-moneyway-fill="{{ $group }}"></span>
                @foreach (['min', 'max'] as $bound)
                    <input type="range"
                        class="betwatch-moneyway-range betwatch-moneyway-range--{{ $bound }}"
                        min="{{ $range['min'] }}" max="{{ $range['max'] }}" step="{{ $range['step'] }}"
                        data-moneyway-range data-group="{{ $group }}" data-bound="{{ $bound }}">
                @endforeach
            </div>
            <div class="betwatch-moneyway-filter-row">
                @foreach (['min', 'max'] as $bound)
                    <input type="text" inputmode="{{ $range['mode'] }}"
                        class="betwatch-moneyway-filter-input"
                        data-moneyway-value data-group="{{ $group }}" data-bound="{{ $bound }}">
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="betwatch-moneyway-switch">
        <button type="button" class="betwatch-moneyway-switch-button is-active"
            data-moneyway-filter-toggle aria-pressed="true"></button>
        <span data-moneyway-filter-label
            data-on-label="{{ __('Filter on') }}"
            data-off-label="{{ __('Filter off') }}">{{ __('Filter on') }}</span>
    </div>
</article>
