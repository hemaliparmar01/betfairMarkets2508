@extends('users.layouts.app',['activeHeaderUrl' => 'fixtures', 'fixtuesUrl' => route('home.en'),'moneywayUrl' => route('football.moneyway.page')])

@section('title', 'BF Markets | Fixtures')
@section('meta_description', 'BF Markets fixtures overview.')

@push('css')
    @include('users.layouts.partials.fixtures.theme-styles')
@endpush

@section('content')
    <div class="betwatch-home">
        <div data-match-cards-page data-sport="football" data-refresh-url='{{ route('football.home.match-cards.partial', ['tab' => 'team_wise_page', 'team_id' => $team_id]) }}'
            data-request-method="POST" data-page-size="6" class="betwatch-home">

        @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => 'football'])
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

                        @include('users.sports.Football.Layouts.country', ['countries' => $countries])

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

                            <h1 class="countrywise-title">{{ $country['name'] ?? 'Testing country' }}</h1>
                        </div>

                        <div class="countrywise-main-tabs">
                            @foreach ($countryTabs ?? [] as $tab)
                                <button type="button" class="countrywise-main-tab">
                                    {{ $tab['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </article>

                    <article class="countrywise-card countrywise-filter-bar">
                        <div class="countrywise-filter-pills">
                            @foreach ($marketTabs ?? [] as $tab)
                                <button type="button" class="countrywise-pill" data-match-tab="{{ strtolower($tab['key']) }}">
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


                    <div data-matchcards-container class="betwatch-center-column" data-football-matchcards-container></div>
                    <div data-matchcards-sentinel class="betwatch-sentinel" data-football-matchcards-sentinel></div>

                    @include('users.layouts.partials.fixtures.matchCard.card-loader')

                    {{-- @foreach ($matchGroups as $group)
                        @include('users.layouts.partials.fixtures.matchCard.football', ['group' => $group])
                    @endforeach --}}
                </div>
            </div>
        </section>
    </div>
    @include('popup')
@endsection
