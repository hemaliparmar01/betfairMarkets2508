@php
    $countryItems = collect($countries ?? [])->values();
@endphp

<div data-country-filter>
    <div class="betwatch-country-list">
        <label class="betwatch-country-item">
            <input type="checkbox" class="betwatch-country-check" data-country-checkbox data-country-all="1" checked>
            <span class="betwatch-country-name">{{ __('All') }}</span>
        </label>

        @foreach ($countryItems as $country)
            @php
                $countryKey = strtolower((string) ($country['key'] ?? $country['slug'] ?? ''));
                $countryFlag = strtolower((string) ($country['flag'] ?? $countryKey));
                $countryName = (string) ($country['name'] ?? '');
            @endphp
            <label class="betwatch-country-item" data-country-item data-country-name="{{ strtolower($countryName) }}">
                <input type="checkbox" class="betwatch-country-check" data-country-checkbox value="{{ $countryKey }}" checked>
                @if ($countryFlag && $countryFlag !== 'international')
                    <img class="betguru-flag-img" src="https://flagcdn.com/w40/{{ $countryFlag }}.png" alt="{{ $countryName }} flag">
                @endif
                <a class="betwatch-country-name" href="{{ route('football.country.page', ['country' => $countryKey]) }}">{{ $countryName }}</a>
            </label>
        @endforeach
    </div>

    <div class="text-center mt-3">
        <button type="button" class="betwatch-show-more betwatch-ui-pill"
            data-country-show-more
            data-more-label="{{ __('Show more') }}"
            data-less-label="{{ __('Show less') }}">
            <span data-country-show-more-label>{{ __('Show more') }}</span>
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="betwatch-country-toggle-icon" data-country-show-more-icon>
                <path d="m5.5 7.5 4.5 4.5 4.5-4.5" />
            </svg>
        </button>
    </div>
</div>
