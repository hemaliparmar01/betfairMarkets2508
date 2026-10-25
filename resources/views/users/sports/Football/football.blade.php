@extends('users.layouts.app',['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('home.en'),'moneywayUrl' => route('football.moneyway.page')])

@section('title', 'BF Markets | Fixtures')
@section('meta_description', 'BF Markets fixtures overview.')

@push('css')
    @include('users.layouts.partials.fixtures.theme-styles')
@endpush


@section('content')
    @php
        $matchTabs = [
            ['key' => 'live', 'label' => __('Live')],
            ['key' => 'upcoming', 'label' => __('Upcoming')],
            ['key' => 'finished', 'label' => __('Finished')],
            ['key' => 'favorites', 'label' => __('Favorites')],
            ['key' => 'by_time', 'label' => __('By time')],
            ['key' => 'ht_0_0', 'label' => 'HT 0-0'],
            ['key' => '0_0@70', 'label' => "0-0 @ 70'"],
            ['key' => 'fav_losing', 'label' => 'Fav Losing'],
            ['key' => 'prematch_drops', 'label' => 'Prematch Drops'],
            ['key' => 'market_shock_detector', 'label' => 'Market Shock Detector'],
        ];
    @endphp

    @php
        $countrySlugs = collect($countries ?? [])
            ->pluck('key')
            ->filter()
            ->map(fn($key) => strtolower((string) $key))
            ->values()
            ->all();
    @endphp

    <div data-match-cards-page data-sport="football" data-football-realtime-view="fixtures" data-refresh-url="{{ route('football.home.match-cards.partial') }}" data-request-method="POST" data-page-size="6" class="betwatch-home">
    {{-- <div data-match-cards-page data-sport="football" data-refresh-url='{{ route('football.home.match-cards.partial') }}' class="betwatch-home"> --}}
        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => 'football'])
        <section class="betwatch-main-shell">
            <div class="betwatch-main-grid">
                <article class="betwach-countries betwatch-leagues-card">
                    <h2 class="betwatch-leagues-title">{{ __('All leagues') }}</h2>

                    <div class="betwatch-filter-box">
                        <svg class="betwatch-filter-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                            stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3.5 5.5h13" />
                            <path d="M6.5 10h7" />
                            <path d="M8.75 14.5h2.5" />
                        </svg>

                        <input data-country-search type="text" class="betwatch-filter-input" placeholder="{{ __('Filter') }}">
                    </div>

                    {{-- @include('users.layouts.partials.fixtures.countryLists.shared', [
                        'countries' => $countries,
                    ]) --}}
                    @include('users.sports.Football.Layouts.country', ['countries' => $countries])

                </article>

                <section class="betwatch-center-column">
                    <article class="betwatch-toolbar">
                        @include('users.layouts.partials.fixtures.football-toolbar-day-head')

                        <div class="betwatch-toolbar-tabs">
                            @foreach ($matchTabs as $tab)
                                <button type="button" class="betwatch-toolbar-pill betwatch-ui-pill" data-match-tab="{{ $tab['key'] }}">
                                    {{ $tab['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </article>

                    <div data-matchcards-container class="betwatch-center-column football-match-cards"
                        data-football-matchcards-container></div>
                    {{-- @include('users.layouts.partials.fixtures.matchCard.football', ['matchGroups' => $resolvedMatchGroups]) --}}
                    <div data-matchcards-sentinel class="betwatch-sentinel" data-football-matchcards-sentinel></div>
                    @include('users.layouts.partials.fixtures.matchCard.card-loader')

                </section>
            </div>
        </section>
    </div>
    @include('popup')
@endsection

@push('js')
    @vite('resources/js/frontend/sports/football.js')
@endpush
