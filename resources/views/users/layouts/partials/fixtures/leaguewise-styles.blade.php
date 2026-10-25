<style>
    [hidden] {
        display: none !important;
    }

    .leaguewise-content {
        min-width: 0;
        display: grid;
        gap: 1.15rem;
    }

    .leaguewise-page .countrywise-main {
        padding-top: 1rem;
    }

    .leaguewise-summary-card {
        padding: 1rem 1.15rem 0;
    }

    .leaguewise-page .leaguewise-summary-card {
        padding: 1rem 1.35rem 0;
    }

    .leaguewise-summary-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .leaguewise-page .leaguewise-summary-top {
        padding-bottom: 0.8rem;
        border-bottom: 1px solid var(--betwatch-page-border);
    }

    .leaguewise-breadcrumbs {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.45rem;
        min-width: 0;
        font-size: 0.82rem;
        line-height: 1.3;
        color: var(--betwatch-page-text);
    }

    .leaguewise-breadcrumb-country {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        min-width: 0;
    }

    .leaguewise-breadcrumb-country .betwatch-flag {
        width: 1.3rem;
        height: 0.9rem;
        flex-basis: 1.3rem;
    }

    .leaguewise-breadcrumb-sep {
        color: var(--betwatch-page-text-muted);
    }

    .leaguewise-summary-favorite {
        flex: 0 0 auto;
    }

    .leaguewise-page .leaguewise-summary-favorite,
    .leaguewise-page .leaguewise-team .betwatch-star-icon {
        width: 1.32rem;
        height: 1.32rem;
        flex: 0 0 1.32rem;
    }

    .leaguewise-scoreboard {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 1rem;
        align-items: center;
        padding: 1.55rem 0 1.2rem;
        text-align: center;
    }

    .leaguewise-page .leaguewise-scoreboard {
        padding: 1.5rem 0 1.35rem;
    }

    .leaguewise-team {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.85rem;
        min-width: 0;
    }

    .leaguewise-team-name {
        font-size: clamp(1rem, 2.2vw, 1.55rem);
        line-height: 1.1;
        font-weight: 400;
        color: var(--betwatch-page-text);
    }

    .leaguewise-scorebox {
        display: grid;
        gap: 0.35rem;
        justify-items: center;
        align-content: center;
    }

    .leaguewise-score {
        font-size: clamp(2rem, 3.6vw, 2.8rem);
        line-height: 1;
        font-weight: 400;
        letter-spacing: -0.05em;
        color: var(--betwatch-page-danger);
    }

    .leaguewise-minute {
        font-size: 0.85rem;
        line-height: 1;
        color: var(--betwatch-page-danger);
    }

    .leaguewise-tabs {
        margin-top: 0;
    }

    .leaguewise-market-board {
        overflow: hidden;
    }

    .leaguewise-market-group + .leaguewise-market-group {
        border-top: 1px solid var(--betwatch-page-border);
    }

    .leaguewise-market-header {
        display: grid;
        width: 100%;
        grid-template-columns: 1.4rem minmax(0, 1fr) 2rem;
        gap: 0.9rem;
        align-items: center;
        padding: 1rem 1.15rem;
        border: 0;
        background: var(--betwatch-page-surface);
        color: var(--betwatch-page-text);
        cursor: pointer;
    }

    .leaguewise-page .leaguewise-market-header {
        padding: 1.1rem 1.35rem;
    }

    .leaguewise-market-title {
        font-size: 0.86rem;
        font-weight: 600;
        text-align: center;
        color: var(--betwatch-page-text);
    }

    .leaguewise-market-summary {
        grid-column: 2 / 4;
        display: grid;
        grid-template-columns: repeat(var(--option-count), minmax(0, 1fr));
        gap: 0.8rem;
        padding-top: 0.05rem;
    }

    .leaguewise-market-summary--1 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    .leaguewise-market-summary--2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .leaguewise-market-summary--3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .leaguewise-market-summary--4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .leaguewise-market-summary-scroller {
        grid-column: 2 / 4;
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        padding-top: 0.05rem;
        scrollbar-width: thin;
    }

    .leaguewise-market-summary--scrollable {
        grid-column: auto;
        width: max-content;
        min-width: max(100%, var(--summary-min-width, 100%));
        padding-top: 0;
    }

    .leaguewise-market-summary-scroller::-webkit-scrollbar {
        height: 8px;
    }

    .leaguewise-market-summary-scroller::-webkit-scrollbar-track {
        background: var(--betwatch-page-scroll-track);
        border-radius: 999px;
    }

    .leaguewise-market-summary-scroller::-webkit-scrollbar-thumb {
        background: var(--betwatch-page-scroll-thumb);
        border-radius: 999px;
    }

    .leaguewise-market-summary-option {
        text-align: center;
    }

    .leaguewise-market-summary-money {
        font-size: 0.82rem;
        line-height: 1.15;
        color: var(--betwatch-page-text);
    }

    .leaguewise-market-summary-meta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.3rem;
        margin-top: 0.18rem;
        font-size: 0.66rem;
        color: var(--betwatch-page-text-soft);
    }

    .leaguewise-market-summary-price {
        margin-top: 0.22rem;
        font-size: 0.81rem;
        color: var(--betwatch-page-text);
    }

    .leaguewise-market-panel {
        border-top: 1px solid var(--betwatch-page-border);
        padding: 0.9rem 1.15rem 1rem;
    }

    .leaguewise-page .leaguewise-market-panel {
        padding: 1rem 1.35rem 1.15rem;
    }

    .leaguewise-market-graphs {
        display: grid;
        grid-template-columns: repeat(var(--option-count), minmax(0, 1fr));
        gap: 1rem;
    }

    .leaguewise-market-graph-card {
        text-align: center;
    }

    .leaguewise-market-options {
        display: grid;
        grid-template-columns: repeat(var(--option-count), minmax(0, 1fr));
        gap: 0.85rem;
    }

    .leaguewise-market-option {
        text-align: center;
    }

    .leaguewise-market-label {
        font-size: 0.84rem;
        line-height: 1.2;
        color: var(--betwatch-page-text);
    }

    .leaguewise-market-graph-shell {
        display: grid;
        grid-template-columns: 1.65rem minmax(0, 1fr) 1.9rem;
        gap: 0.25rem;
        align-items: stretch;
        margin-top: 0.45rem;
    }

    .leaguewise-market-axis {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 10.8rem;
        font-size: 0.56rem;
        line-height: 1;
        user-select: none;
    }

    .leaguewise-market-axis--left {
        color: var(--betwatch-page-danger);
        text-align: right;
    }

    .leaguewise-market-axis--right {
        color: var(--betwatch-page-text-soft);
        text-align: left;
    }

    .leaguewise-market-plot {
        position: relative;
        min-height: 10.8rem;
        border: 1px solid var(--betwatch-page-border);
        background:
            repeating-linear-gradient(to bottom, var(--betwatch-page-surface) 0 1.72rem, var(--betwatch-page-surface-soft) 1.72rem 1.78rem),
            linear-gradient(to right, transparent 0 calc(100% - 1px), var(--betwatch-page-surface-soft) calc(100% - 1px) 100%);
        overflow: hidden;
    }

    .leaguewise-market-chart {
        display: block;
        width: 100%;
        height: 100%;
    }

    .leaguewise-market-header--static {
        grid-template-columns: 1fr;
        cursor: default;
    }

    .leaguewise-market-panel-scroller {
        overflow-x: auto;
        overflow-y: hidden;
        padding-bottom: 0.35rem;
    }

    .leaguewise-market-panel-track {
        display: flex;
        gap: 1.1rem;
        min-width: max-content;
    }

    .leaguewise-market-panel-track--compact {
        justify-content: center;
    }

    .leaguewise-chart-card {
        flex: 0 0 255px;
        width: 255px;
        display: grid;
        grid-template-rows: auto auto auto;
        gap: 0.45rem;
        text-align: center;
    }

    .leaguewise-chart-card__title {
        margin: 0;
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--betwatch-page-text);
    }

    .leaguewise-chart-card__frame {
        display: flex;
        min-height: 188px;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--betwatch-page-border);
        border-radius: 0.25rem;
        background: var(--betwatch-page-surface);
        padding: 0.2rem;
    }

    .leaguewise-chart-card__image {
        display: block;
        width: 100%;
        height: auto;
        object-fit: contain;
    }

    .leaguewise-chart-card__meta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        font-size: 0.82rem;
        line-height: 1.1;
        color: var(--betwatch-page-text);
    }

    .leaguewise-market-panel-state--spacious {
        padding: 3rem 1.5rem;
    }

    .leaguewise-market-chart-line {
        fill: none;
        stroke: var(--betwatch-page-danger);
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .leaguewise-market-chart-bar {
        stroke: var(--betwatch-page-border-strong);
        stroke-width: 2;
        stroke-linecap: round;
        opacity: 0.95;
    }

    .leaguewise-market-graph-foot {
        margin-top: 0.45rem;
        font-size: 0.82rem;
        line-height: 1.2;
        color: var(--betwatch-page-text);
    }

    .leaguewise-market-money {
        margin-top: 0.5rem;
        font-size: 0.82rem;
        line-height: 1.15;
        color: var(--betwatch-page-text);
    }

    .leaguewise-market-meta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        margin-top: 0.35rem;
        font-size: 0.67rem;
        color: var(--betwatch-page-text-soft);
    }

    .leaguewise-market-price {
        margin-top: 0.35rem;
        font-size: 0.81rem;
        color: var(--betwatch-page-text);
    }

    .leaguewise-market-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        justify-self: end;
        width: 2rem;
        height: 2rem;
        border: 0;
        padding: 0;
        background: transparent;
        color: var(--betwatch-page-text);
        cursor: pointer;
    }

    .leaguewise-market-toggle svg {
        width: 1rem;
        height: 1rem;
        stroke: currentColor;
        transition: transform 0.2s ease;
    }

    .leaguewise-market-toggle.is-open svg {
        transform: rotate(180deg);
    }

    .leaguewise-details-card {
        padding: 1rem 1.2rem 1.25rem;
        display: grid;
        gap: 1rem;
        width: 100%;
        max-width: 100%;
        overflow: auto;
    }

    .leaguewise-page .leaguewise-details-card {
        padding: 1rem 1.4rem 1.35rem;
    }

    .leaguewise-details-topbar {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        min-width: 0;
    }

    .leaguewise-details-tabs {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        overflow-x: auto;
        overflow-y: hidden;
        flex: 1 1 auto;
        min-width: 0;
        padding-bottom: 0.2rem;
        scroll-behavior: smooth;
        scrollbar-width: thin;
    }

    .leaguewise-details-tabs::-webkit-scrollbar {
        height: 6px;
    }

    .leaguewise-details-tabs::-webkit-scrollbar-track {
        background: var(--betwatch-page-scroll-track);
        border-radius: 999px;
    }

    .leaguewise-details-tabs::-webkit-scrollbar-thumb {
        background: var(--betwatch-page-scroll-thumb);
        border-radius: 999px;
    }

    .leaguewise-details-pill {
        flex: 0 0 auto;
        height: 2.7rem;
        padding: 0 1.1rem;
        border: 1px solid var(--betwatch-page-border);
        border-radius: 999px;
        background: var(--betwatch-page-surface);
        color: var(--betwatch-page-text);
        font-size: 0.92rem;
        font-weight: 500;
        white-space: nowrap;
    }

    .leaguewise-details-pill.is-active {
        border-color: var(--betwatch-page-active-bg);
        background: var(--betwatch-page-active-bg);
        color: var(--betwatch-page-active-text);
    }

    .leaguewise-details-carousel-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.7rem;
        height: 2.7rem;
        border: 1px solid var(--betwatch-page-border);
        border-radius: 999px;
        background: var(--betwatch-page-surface);
        color: var(--betwatch-page-text);
        box-shadow: var(--betwatch-page-shadow-sm);
        flex: 0 0 auto;
    }

    .leaguewise-details-carousel-btn svg {
        width: 1rem;
        height: 1rem;
        stroke: currentColor;
    }

    .leaguewise-details-divider {
        height: 1px;
        background: var(--betwatch-page-border);
    }

    .leaguewise-details-toolbar {
        display: flex;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 0.85rem;
    }

    .leaguewise-details-toolbar-item {
        position: relative;
    }

    .leaguewise-details-dropdown {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        border: 0;
        padding: 0;
        background: transparent;
        color: var(--betwatch-page-text);
        font-size: 0.92rem;
        font-weight: 500;
        cursor: pointer;
    }

    .leaguewise-details-dropdown svg {
        width: 1rem;
        height: 1rem;
        stroke: currentColor;
    }

    .leaguewise-details-dropdown__badge {
        min-width: 1.55rem;
        height: 1.55rem;
        padding: 0 0.35rem;
        border-radius: 0.38rem;
        background: var(--betwatch-page-surface-soft);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--betwatch-page-text-soft);
    }

    .leaguewise-details-menu {
        position: absolute;
        top: calc(100% + 0.7rem);
        right: 0;
        z-index: 8;
        min-width: 12.5rem;
        display: grid;
        gap: 0.2rem;
        padding: 0.95rem 1rem;
        border: 1px solid var(--betwatch-page-border);
        border-radius: 0.8rem;
        background: var(--betwatch-page-surface);
        box-shadow: var(--betwatch-page-shadow-md);
    }

    .leaguewise-details-menu__option {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        font-size: 0.92rem;
        line-height: 1.2;
        color: var(--betwatch-page-text);
        cursor: pointer;
        user-select: none;
    }

    .leaguewise-details-menu__option + .leaguewise-details-menu__option {
        margin-top: 0.12rem;
    }

    .leaguewise-details-menu__option input {
        width: 1.05rem;
        height: 1.05rem;
        accent-color: var(--betwatch-page-primary);
        flex: 0 0 1.05rem;
    }

    .leaguewise-detail-grid-shell {
        /* overflow-x: auto;
        overflow-y: hidden; */
        padding-bottom: 0.35rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .leaguewise-detail-grid-shell::-webkit-scrollbar {
        height: 8px;
    }

    .leaguewise-detail-grid-shell::-webkit-scrollbar-track {
        background: var(--betwatch-page-scroll-track);
        border-radius: 999px;
    }

    .leaguewise-detail-grid-shell::-webkit-scrollbar-thumb {
        background: var(--betwatch-page-scroll-thumb);
        border-radius: 999px;
    }

    .leaguewise-detail-grid {
        min-width: 58rem;
        display: grid;
        grid-template-columns: 5.2rem minmax(0, 1fr);
        gap: 1.45rem;
        align-items: start;
    }

    .leaguewise-detail-time {
        padding-top: 8.15rem;
    }

    .leaguewise-detail-time__head {
        min-height: 2rem;
        display: flex;
        align-items: center;
        font-size: 0.88rem;
        font-weight: 700;
        color: var(--betwatch-page-text);
    }

    .leaguewise-detail-time__rows,
    .leaguewise-detail-outcome__body {
        display: grid;
        gap: 0.15rem;
    }

    .leaguewise-detail-outcomes {
        display: grid;
        grid-template-columns: repeat(3, minmax(13.8rem, 1fr));
        gap: 1.15rem;
    }

    .leaguewise-detail-outcome {
        display: grid;
        gap: 0.5rem;
    }

    .leaguewise-detail-outcome__head {
        display: grid;
        gap: 0.55rem;
    }

    .leaguewise-detail-outcome__title {
        min-height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--betwatch-page-text);
    }

    .leaguewise-detail-outcome__subhead {
        min-height: 3rem;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        align-items: center;
        gap: 0.55rem;
        padding: 0.95rem 0.85rem;
        border-radius: 0.65rem;
        background: var(--betwatch-page-surface-soft);
        font-size: 0.84rem;
        font-weight: 700;
        color: var(--betwatch-page-text);
        text-align: center;
    }

    .leaguewise-detail-row {
        min-height: 1.6rem;
        display: grid;
        align-items: center;
        font-size: 0.84rem;
        color: var(--betwatch-page-text);
    }

    .leaguewise-detail-row--values {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.55rem;
        text-align: center;
    }

    .leaguewise-detail-row--values .is-up {
        color: var(--betwatch-page-success);
    }

    .leaguewise-detail-row--values .is-down {
        color: var(--betwatch-page-danger);
    }

    .leaguewise-detail-row--values .leaguewise-detail-cell--odd-color {
        color: var(--betwatch-page-primary);
        font-weight: 600;
    }

    .leaguewise-detail-columns--1 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    .leaguewise-detail-columns--2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .leaguewise-detail-columns--3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .leaguewise-detail-columns--4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .leaguewise-detail-columns--5 {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .leaguewise-detail-outcomes--1-1 {
        grid-template-columns: repeat(1, minmax(7.3rem, 1fr));
    }

    .leaguewise-detail-outcomes--1-2 {
        grid-template-columns: repeat(2, minmax(7.3rem, 1fr));
    }

    .leaguewise-detail-outcomes--1-3 {
        grid-template-columns: repeat(3, minmax(7.3rem, 1fr));
    }

    .leaguewise-detail-outcomes--2-1 {
        grid-template-columns: repeat(1, minmax(12.95rem, 1fr));
    }

    .leaguewise-detail-outcomes--2-2 {
        grid-template-columns: repeat(2, minmax(12.95rem, 1fr));
    }

    .leaguewise-detail-outcomes--2-3 {
        grid-template-columns: repeat(3, minmax(12.95rem, 1fr));
    }

    .leaguewise-detail-outcomes--3-1 {
        grid-template-columns: repeat(1, minmax(18.6rem, 1fr));
    }

    .leaguewise-detail-outcomes--3-2 {
        grid-template-columns: repeat(2, minmax(18.6rem, 1fr));
    }

    .leaguewise-detail-outcomes--3-3 {
        grid-template-columns: repeat(3, minmax(18.6rem, 1fr));
    }

    .leaguewise-detail-outcomes--4-1 {
        grid-template-columns: repeat(1, minmax(24.25rem, 1fr));
    }

    .leaguewise-detail-outcomes--4-2 {
        grid-template-columns: repeat(2, minmax(24.25rem, 1fr));
    }

    .leaguewise-detail-outcomes--4-3 {
        grid-template-columns: repeat(3, minmax(24.25rem, 1fr));
    }

    .leaguewise-detail-outcomes--5-1 {
        grid-template-columns: repeat(1, minmax(29.9rem, 1fr));
    }

    .leaguewise-detail-outcomes--5-2 {
        grid-template-columns: repeat(2, minmax(29.9rem, 1fr));
    }

    .leaguewise-detail-outcomes--5-3 {
        grid-template-columns: repeat(3, minmax(29.9rem, 1fr));
    }

    .leaguewise-detail-grid--1-1,
    .leaguewise-detail-grid--1-2,
    .leaguewise-detail-grid--1-3,
    .leaguewise-detail-grid--2-1,
    .leaguewise-detail-grid--2-2,
    .leaguewise-detail-grid--3-1,
    .leaguewise-detail-grid--4-1,
    .leaguewise-detail-grid--5-1 {
        min-width: 46rem;
    }

    .leaguewise-detail-grid--2-3 {
        min-width: 46.45rem;
    }

    .leaguewise-detail-grid--3-2 {
        min-width: 46rem;
    }

    .leaguewise-detail-grid--3-3 {
        min-width: 63.4rem;
    }

    .leaguewise-detail-grid--4-2 {
        min-width: 56.1rem;
    }

    .leaguewise-detail-grid--4-3 {
        min-width: 80.35rem;
    }

    .leaguewise-detail-grid--5-2 {
        min-width: 67.4rem;
    }

    .leaguewise-detail-grid--5-3 {
        min-width: 97.3rem;
    }

    @media (min-width: 768px) {
        .leaguewise-scoreboard {
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            text-align: initial;
        }

        .leaguewise-team--home {
            justify-content: flex-end;
            text-align: right;
        }

        .leaguewise-team--away {
            justify-content: flex-start;
            text-align: left;
        }

        .leaguewise-market-header {
            grid-template-columns: 1.4rem minmax(10rem, 1fr) minmax(0, 28rem) 2rem;
            min-height: 4rem;
        }

        .leaguewise-market-summary {
            grid-column: auto;
        }
    }

    @media (max-width: 767.98px) {
        .leaguewise-summary-card {
            padding-inline: 1rem;
        }

        .leaguewise-page .leaguewise-summary-card {
            padding-inline: 1rem;
        }

        .leaguewise-page .leaguewise-market-header,
        .leaguewise-page .leaguewise-market-panel {
            padding-inline: 1rem;
        }

        .leaguewise-breadcrumbs {
            font-size: 0.78rem;
        }

        .leaguewise-details-card,
        .leaguewise-page .leaguewise-details-card {
            padding-inline: 1rem;
        }

        .leaguewise-details-toolbar {
            justify-content: flex-start;
        }

        .leaguewise-details-menu {
            left: 0;
            right: auto;
        }

        .leaguewise-detail-grid {
            min-width: 48rem;
        }

        .leaguewise-market-summary,
        .leaguewise-market-options,
        .leaguewise-market-graphs {
            grid-template-columns: 1fr !important;
        }
    }
</style>
