@extends('users.layouts.app',['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('home.en'),'moneywayUrl' => route('football.moneyway.page')])

@section('title', 'BF Markets | Country Fixtures')
@section('meta_description', 'BF Markets country fixtures overview.')

@push('css')
    @include('users.layouts.partials.fixtures.theme-styles')
@endpush

@section('content')

    @php
        $countryTabs = [
            ['key' => 'fixtures','label' => 'Fixtures'],
            ['key' => 'moneyway','label' => 'Moneyway']
        ];

        $marketTabs = [
            ['key' => 'live','label' => __('Live')],
            ['key' => 'Upcoming','label' => __('Upcoming')],
            ['key' => 'Finished','label' => __('Finished')],
            ['key' => 'Favorites','label' => __('Favorites')]
        ];

        $countrySlugs = collect($countries ?? [])->pluck('key')->filter()->map(fn ($key) => strtolower((string) $key))->values()->all();
    @endphp
    {{-- <div data-match-cards-page data-sport="football" data-refresh-url='{{ route('football.home.match-cards.partial', ['tab' => 'country_wise_page', 'country_id' => $country_id,'type' => 'fixtures']) }}'
        @football-date-changed.window="
            selectedDate = $event.detail?.date || null;
            const datedRefreshUrl = new URL(refreshUrl, window.location.origin);
            if (selectedDate) {
                datedRefreshUrl.searchParams.set('date', selectedDate);
            } else {
                datedRefreshUrl.searchParams.delete('date');
            }
            refreshUrl = datedRefreshUrl.toString();
        "
        class="betwatch-home"> --}}
        <div data-match-cards-page data-sport="football" data-football-realtime-view="fixtures"
            data-refresh-url="{{ route('football.home.match-cards.partial', [
                'country_id' => $country_id,
            ]) }}"
            data-request-method="POST" data-page-size="6" class="betwatch-home">
        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => 'football'])

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

                        {{-- @include('users.layouts.partials.fixtures.countryLists.shared', ['countries' => $countries]) --}}

                        @include('users.sports.Football.Layouts.country', ['countries' => $countries])
                    </article>
                </aside>

                <div class="countrywise-content">
                    <article class="countrywise-card countrywise-hero">
                        <div class="countrywise-hero-top">
                            <span class="countrywise-flag-badge" aria-hidden="true">
                                @php $flagCode = strtolower($country['flag'] ?? ''); @endphp
                                @if($flagCode && $flagCode !== 'international')
                                    <img class="betguru-flag-img" src="https://flagcdn.com/w40/{{ $flagCode }}.png" alt="{{ $matchGroup['name'] ?? 'Country' }} flag">
                                @endif
                            </span>
                            <h1 class="countrywise-title">{{ $country['name'] ?? 'Testing country' }}</h1>
                        </div>

                        {{-- data-navigation-url="{{ route('football.country.page', ['country' => $country_id]) }}"> --}}
                        <div class="countrywise-main-tabs">
                            @foreach ($countryTabs ?? [] as $tab)
                                <button type="button"
                                {{-- @if ($tab['key'] == 'moneyway')
                                    data-navigation-url="{{ route('football.country.page', ['country' => $country_id]) }}/moneyway"
                                @else
                                    data-navigation-url="{{ route('football.country.page', ['country' => $country_id]) }}"
                                @endif> --}}
                                @if ($tab['key'] == 'moneyway')
                                    data-navigation-url="{{ route('football.country.moneyway.page', ['country' => $country_id]) }}"
                                @else
                                    data-navigation-url="{{ route('football.country.page', ['country' => $country_id]) }}"
                                @endif>
                                    {{ $tab['label'] }}
                                </button>
                            @endforeach
                            {{-- @foreach ($countryTabs ?? [] as $tab)
                                <button></button>
                                <button type="button" class="countrywise-main-tab" > {{ $tab['label'] }}</button>
                            @endforeach --}}
                        </div>
                    </article>

                    <article class="countrywise-card countrywise-filter-bar">
                        <div class="countrywise-filter-pills">
                            @foreach ($marketTabs ?? [] as $tab)
                                <button type="button" class="countrywise-pill betwatch-toolbar-pill" data-match-tab="{{ strtolower($tab['key']) }}">
                                    {{ $tab['label'] }}
                                </button>
                            @endforeach
                        </div>

                        @include('users.layouts.partials.fixtures.football-toolbar-day-head', [
                            'toolbarRoute' => route('football.home.match-cards.partial', [
                                'tab' => 'country_wise_page',
                                'country_id' => $country_id,
                                'type' => 'fixtures',
                            ]),
                        ])
                    </article>


                    {{-- <div data-matchcards-container class="betwatch-center-column" data-football-matchcards-container></div>
                    <div data-matchcards-sentinel class="betwatch-sentinel" data-football-matchcards-sentinel></div> --}}

                    <div data-matchcards-container class="betwatch-center-column football-match-cards"
                        data-football-matchcards-container></div>
                    <div data-matchcards-sentinel class="betwatch-sentinel"
                        data-football-matchcards-sentinel></div>
                    @include('users.layouts.partials.fixtures.matchCard.card-loader')

                    {{-- @foreach ($matchGroups as $group)
                        @include('users.layouts.partials.fixtures.matchCard.football', ['group' => $group])
                    @endforeach --}}
                </div>

                {{-- @include('users.layouts.partials.fixtures.support-card') --}}
            </div>
        </section>
    </div>
    @include('popup')
@endsection

@push('js')
    @vite('resources/js/frontend/sports/football.js')
@endpush
