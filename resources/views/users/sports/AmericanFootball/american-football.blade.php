@extends('users.layouts.app',['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('american-football'),'moneywayUrl' => route('american-football.moneyway.page')])

@section('title', 'BF Markets | American Football Fixtures')
@section('meta_description', 'BF Markets american football fixtures overview.')

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
        ];

        $countrySlugs = collect($countries ?? [])
            ->pluck('key')
            ->filter()
            ->map(fn ($key) => strtolower((string) $key))
            ->values()
            ->all();
    @endphp

    <div data-match-cards-page data-sport="american-football" data-refresh-url='{{ route('american-football.home.match-cards.partial') }}' class="betwatch-home">
        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => 'american-football'])

        <section class="betwatch-main-shell">
            <div class="betwatch-main-grid">
                <article class="betwatch-leagues-card">
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

                    @include('users.sports.AmericanFootball.partials.country', ['countries' => $countries])
                </article>

                <section class="betwatch-center-column">
                    <article class="betwatch-toolbar">
                        @include('users.layouts.partials.fixtures.american-football-toolbar-day-head')

                        <div class="betwatch-toolbar-tabs">
                            @foreach ($matchTabs as $tab)
                                <button type="button" class="betwatch-toolbar-pill betwatch-ui-pill" data-match-tab="{{ strtolower($tab['key']) }}">
                                    {{ $tab['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </article>

                    <div data-matchcards-container class="betwatch-center-column" data-american-football-matchcards-container></div>
                    <div data-matchcards-sentinel class="betwatch-sentinel" data-american-football-matchcards-sentinel></div>

                    @include('users.layouts.partials.fixtures.matchCard.card-loader')

                </section>
            </div>
        </section>
    </div>
    @include('popup')
@endsection


