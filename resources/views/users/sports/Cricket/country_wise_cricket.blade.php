@extends('users.layouts.app',['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('cricket'),'moneywayUrl' => route('cricket.moneyway.page')])

@section('title', 'BetGuru | Cricket Country Fixtures')
@section('meta_description', 'BetGuru cricket country fixtures overview.')

@push('css')
    @include('users.layouts.partials.fixtures.theme-styles')
@endpush

@section('content')
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

        $countrySlugs = collect($countries ?? [])
            ->pluck('key')
            ->filter()
            ->map(fn ($key) => strtolower((string) $key))
            ->values()
            ->all();

        $todayDate = now(get_current_user_timezone())->format('Y-m-d');
    @endphp

    <div data-match-cards-page data-sport="cricket" data-refresh-url='{{ route('cricket.home.match-cards.partial', ['tab' => 'country_wise_page', 'country_id' => $country_id, 'date' => $todayDate]) }}' class="betwatch-home">
        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => 'cricket'])

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

                        @include('users.sports.Cricket.partials.country', ['countries' => $countries])
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

                            <h1 class="countrywise-title">{{ $country['name'] ?? 'Cricket Country' }}</h1>
                        </div>

                        <div class="countrywise-main-tabs">
                            @foreach ($countryTabs as $tab)
                                <button type="button" class="countrywise-main-tab"
                                    @if ($tab['key'] === 'moneyway')
                                        data-navigation-url="{{ route('cricket.country.moneyway.page', ['country' => $country_id]) }}"
                                    @else
                                        data-navigation-url="{{ route('cricket.country.page', ['country' => $country_id]) }}"
                                    @endif
                                >
                                    {{ $tab['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </article>

                    <article class="countrywise-card countrywise-filter-bar">
                        <div class="countrywise-filter-pills">
                            @foreach ($marketTabs as $tab)
                                <button type="button" class="countrywise-pill"
                                    data-match-tab="{{ $tab['key'] }}">
                                    {{ $tab['label'] }}
                                </button>
                            @endforeach
                        </div>

                        @include('users.layouts.partials.fixtures.cricket-toolbar-day-head', [
                            'toolbarRoute' => route('cricket.home.match-cards.partial', [
                                'tab' => 'country_wise_page',
                                'country_id' => $country_id,
                            ]),
                        ])
                    </article>


                    <div data-matchcards-container class="betwatch-center-column" data-cricket-matchcards-container></div>
                    <div data-matchcards-sentinel class="betwatch-sentinel" data-cricket-matchcards-sentinel></div>

                    @include('users.layouts.partials.fixtures.matchCard.card-loader')
                </div>
            </div>
        </section>
    </div>
    @include('popup')
@endsection


