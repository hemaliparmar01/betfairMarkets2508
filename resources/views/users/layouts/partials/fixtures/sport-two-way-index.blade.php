@php
    $matchTabs = [
        ['key' => 'live', 'label' => __('Live')],
        ['key' => 'upcoming', 'label' => __('Upcoming')],
        ['key' => 'finished', 'label' => __('Finished')],
        ['key' => 'favorites', 'label' => __('Favorites')],
        ['key' => 'time', 'label' => __('By time')],
    ];

    $checkedLeagueDefaults = collect($countries)
        ->pluck('key')
        ->mapWithKeys(fn ($key) => [$key => true])
        ->all();
@endphp

<div data-simple-tabs data-sport="{{ $activeSport }}" class="betwatch-home">
    @include('users.layouts.partials.fixtures.sports-strip', ['activeSport' => $activeSport])

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

                @include('users.layouts.partials.fixtures.countryLists.shared', ['countries' => $countries])

            </article>

                <section class="betwatch-center-column">
                    <article class="betwatch-toolbar">
                        @include('users.layouts.partials.fixtures.toolbar-day-head')

                    <div class="betwatch-toolbar-tabs">
                        @foreach ($matchTabs as $tab)
                            <button type="button" class="betwatch-toolbar-pill betwatch-ui-pill {{ $tab['key'] === 'live' ? 'is-active' : '' }}"
                                data-simple-tab data-tab-group="market">
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>
                </article>

                @foreach ($matchGroups as $group)
                    @include('users.layouts.partials.fixtures.matchCard.two-way-generic', [
                        'group' => $group,
                        'routeName' => $routeName,
                    ])
                @endforeach
            </section>
        </div>
    </section>
</div>

