@once
    <style>
        @keyframes betwatchSpin {
            to {
                transform: rotate(360deg);
            }
        }

        [data-sport="football"] .betwatch-matchcards-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 3.5rem;
            padding: 0.75rem 0 1.25rem;
            background: transparent;
        }

        [data-sport="football"] .betwatch-matchcards-loading[hidden] {
            display: none !important;
        }

        [data-sport="football"] .betwatch-matchcards-loading .betwatch-spinner {
            width: 1.5rem;
            height: 1.5rem;
            border-width: 2px;
            border-color: rgb(var(--text-muted) / 0.2);
            border-top-color: rgb(var(--primary));
        }
    </style>
@endonce

<div class="betwatch-matchcards-loading betwatch-loading-center"
    data-matchcards-loading
    role="status"
    aria-live="polite"
    aria-label="{{ __('Loading data') }}"
    hidden>
    <span class="betwatch-spinner" aria-hidden="true"></span>
</div>
