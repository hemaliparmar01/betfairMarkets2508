@extends('users.layouts.app',['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('american-football'),'moneywayUrl' => route('american-football.moneyway.page')])

@section('title', 'BF Markets | American Football Team Fixtures')
@section('meta_description', 'BetGuru american football team fixtures overview.')

@push('css')
    @include('users.layouts.partials.fixtures.theme-styles')
@endpush

@section('content')
    <div data-match-cards-page data-sport="american-football" data-refresh-url='{{ route('american-football.home.match-cards.partial', ['tab' => 'team_wise_page', 'team_id' => $team_id]) }}' class="betwatch-home">
        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => 'american-football'])

        <section class="betwatch-main-shell">
            <div class="betwatch-main-grid">
                <aside class="countrywise-sidebar">
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
                </aside>

                <div class="countrywise-content">
                    <article class="countrywise-card countrywise-hero">
                        <div class="countrywise-hero-top">
                            <h1 class="countrywise-title">American Football Team Fixtures</h1>
                        </div>
                    </article>

                    <article class="countrywise-card countrywise-filter-bar">
                        <div class="countrywise-filter-pills">
                            <button type="button" class="countrywise-pill is-active" data-match-tab="{{ strtolower($tab['key']) }}">{{ __('Live & Upcoming') }}</button>
                        </div>
                    </article>

                    <div data-matchcards-container class="betwatch-center-column" data-american-football-matchcards-container></div>
                    <div data-matchcards-sentinel class="betwatch-sentinel" data-american-football-matchcards-sentinel></div>

                    @include('users.layouts.partials.fixtures.matchCard.card-loader')
                </div>
            </div>
        </section>
    </div>
    @include('popup')
@endsection


