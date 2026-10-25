@php
    $toolbarSport = $toolbarSport ?? 'football';
@endphp

<div class="betwatch-toolbar-head-shell"
    data-date-toolbar
    data-sport="{{ $toolbarSport }}"
    data-request-payload="{{ e(json_encode($requestPayload ?? [])) }}"
    data-today-label="{{ __('Today') }}">
    <div class="betwatch-toolbar-head">
        <button type="button" class="betwatch-toolbar-nav betwatch-ui-circle" data-date-previous aria-label="{{ __('Previous day') }}">
            <svg viewBox="0 0 20 20" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m12.5 4.5-5 5 5 5" />
            </svg>
        </button>

        <button type="button" class="betwatch-toolbar-day" data-date-toggle>
            <span data-date-label>{{ __('Today') }}</span>
            <svg viewBox="0 0 20 20" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m5.5 7.5 4.5 4.5 4.5-4.5" />
            </svg>
        </button>

        <button type="button" class="betwatch-toolbar-nav betwatch-ui-circle" data-date-next aria-label="{{ __('Next day') }}">
            <svg viewBox="0 0 20 20" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m7.5 4.5 5 5-5 5" />
            </svg>
        </button>
    </div>

    <div class="betwatch-toolbar-calendar-shell" data-date-calendar hidden>
        <div class="betwatch-toolbar-calendar">
            <div class="betwatch-toolbar-calendar-head">
                <h3 class="betwatch-toolbar-calendar-title" data-month-label></h3>
                <div class="betwatch-toolbar-calendar-controls">
                    <button type="button" class="betwatch-toolbar-calendar-nav betwatch-ui-circle" data-month-previous aria-label="{{ __('Previous month') }}">
                        <svg viewBox="0 0 20 20" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12.5 4.5-5 5 5 5" /></svg>
                    </button>
                    <button type="button" class="betwatch-toolbar-calendar-nav betwatch-ui-circle" data-month-next aria-label="{{ __('Next month') }}">
                        <svg viewBox="0 0 20 20" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7.5 4.5 5 5-5 5" /></svg>
                    </button>
                </div>
            </div>

            <div class="betwatch-toolbar-weekdays">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                    <span>{{ $weekday }}</span>
                @endforeach
            </div>
            <div class="betwatch-toolbar-calendar-grid" data-calendar-grid></div>
        </div>
    </div>
</div>
