@php
    $countryTabs = [
        ['key' => 'fixtures', 'label' => 'Fixtures'],
        ['key' => 'moneyway', 'label' => 'Moneyway'],
    ];

    $marketTabs = [
        ['key' => 'live', 'label' => __('Live')],
        ['key' => 'upcoming', 'label' => __('Upcoming')],
        ['key' => 'finished', 'label' => __('Finished')],
        ['key' => 'favorites', 'label' => __('Favorites')],
    ];

    $country = $countryMap[$selectedCountry] ?? [
        'name' => ucwords(str_replace('-', ' ', $selectedCountry)),
        'flag' => null,
        'league' => ucwords(str_replace('-', ' ', $selectedCountry)) . ' : Fixtures',
    ];

    $matchGroups = $matchGroupsByCountry[$selectedCountry] ?? reset($matchGroupsByCountry);

    $checkedLeagueDefaults = collect($countries)
        ->pluck('key')
        ->mapWithKeys(fn ($key) => [$key => true])
        ->all();
@endphp

<div data-simple-tabs data-sport="{{ $activeSport }}" class="countrywise-page">
    @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => $activeSport])

    <section class="betwatch-main-shell">
        <div class="betwatch-main-grid">
            <aside class="countrywise-sidebar">
                <article class="betwatch-leagues-card">
                    <h2 class="betwatch-leagues-title">{{ __('All leagues') }}</h2>

                    <div class="betwatch-filter-box">
                        <svg class="betwatch-filter-icon" viewBox="0 0 20 20" fill="none"
                            stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
                            stroke-linejoin="round" aria-hidden="true">
                            <path d="M3.5 5.5h13" />
                            <path d="M6.5 10h7" />
                            <path d="M8.75 14.5h2.5" />
                        </svg>

                        <input data-country-search type="text" class="betwatch-filter-input" placeholder="{{ __('Filter') }}">
                    </div>

                    @include('users.layouts.partials.fixtures.countryLists.shared', ['countries' => $countries])

                </article>
            </aside>

            <div class="countrywise-content">
                <article class="countrywise-card countrywise-hero">
                    <div class="countrywise-hero-top">
                        @php $flagCode = strtolower($country['flag'] ?? ''); @endphp
                            @if($flagCode && $flagCode !== 'international')
                                <span class="countrywise-flag-badge" aria-hidden="true">
                                    <img class="betguru-flag-img" src="https://flagcdn.com/w40/{{ $flagCode }}.png" alt="{{ $country['name'] ?? 'Country' }} flag">
                                </span>
                            @endif
                        <h1 class="countrywise-title">{{ $country['name'] }}</h1>
                    </div>

                    <div class="countrywise-main-tabs">
                        @foreach ($countryTabs as $tab)
                            <button type="button" class="countrywise-main-tab {{ $tab['key'] === 'fixtures' ? 'is-active' : '' }}"
                                data-simple-tab data-tab-group="main">
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>
                </article>

                <article class="countrywise-card countrywise-filter-bar">
                    <div class="countrywise-filter-pills">
                        @foreach ($marketTabs as $tab)
                            <button type="button" class="countrywise-pill {{ $tab['key'] === 'live' ? 'is-active' : '' }}"
                                data-simple-tab data-tab-group="market">
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>

                    <button type="button" class="countrywise-date-button">
                        <svg viewBox="0 0 20 20" fill="none" stroke-width="1.9" stroke-linecap="round"
                            stroke-linejoin="round" aria-hidden="true">
                            <path d="m12.5 4.5-5 5 5 5" />
                        </svg>
                        <span class="countrywise-date-button__grow">
                            <svg viewBox="0 0 20 20" fill="none" stroke-width="1.7" stroke-linecap="round"
                                stroke-linejoin="round" aria-hidden="true">
                                <rect x="3.5" y="4.5" width="13" height="12" rx="2" />
                                <path d="M6.5 2.8v3.4M13.5 2.8v3.4M3.5 8.2h13" />
                            </svg>
                            <span>Select date</span>
                        </span>
                        <svg viewBox="0 0 20 20" fill="none" stroke-width="1.9" stroke-linecap="round"
                            stroke-linejoin="round" aria-hidden="true">
                            <path d="m7.5 4.5 5 5-5 5" />
                        </svg>
                    </button>
                </article>

                @foreach ($matchGroups as $group)
                    @include('users.layouts.partials.fixtures.matchCard.two-way-generic', [
                        'group' => $group,
                        'routeName' => $routeName,
                    ])
                @endforeach
            </div>
        </div>
    </section>
</div>

