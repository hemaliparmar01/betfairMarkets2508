<aside class="betwatch-main-sidebar-spacer">
    <article class="betwatch-support-card" data-support-card>
        <h2 class="betwatch-support-title">Support Betwatch</h2>

        <p class="betwatch-support-copy">
            Maintaining this website is not free for me.
            <br>
            If you like my job or it's useful to you don't hesitate to donate!
        </p>

        <div class="betwatch-support-progress">
            <div class="betwatch-support-track" aria-hidden="true">
                <div class="betwatch-support-fill"></div>
            </div>
            <div class="betwatch-support-count">30&#8364; donated this month</div>
        </div>

        <div class="betwatch-support-amounts">
            @foreach ([5, 10, 20] as $amount)
                <button type="button" class="betwatch-support-pill betwatch-ui-pill"
                    data-support-amount="{{ $amount }}">
                    <span>{{ $amount }}&#8364;</span>
                </button>
            @endforeach
        </div>

        <button type="button" class="betwatch-support-custom">
            <span>Custom amount:</span>
            <span>&#8364;</span>
        </button>

        <button type="button" class="betwatch-support-check" data-support-monthly aria-pressed="false">
            <span class="betwatch-support-check-box" data-support-monthly-on hidden></span>
            <span class="betwatch-support-empty-check" data-support-monthly-off></span>
            <span>Make this a monthly donation</span>
        </button>

        <button type="button" class="betwatch-support-donate betwatch-ui-pill">
            <svg viewBox="0 0 20 20" aria-hidden="true">
                <path
                    d="M10 17.2 3.55 10.7A4.37 4.37 0 0 1 9.73 4.5L10 4.77l.27-.27a4.37 4.37 0 0 1 6.18 6.2L10 17.2Z" />
            </svg>
            <span>Donate</span>
        </button>
    </article>
</aside>
