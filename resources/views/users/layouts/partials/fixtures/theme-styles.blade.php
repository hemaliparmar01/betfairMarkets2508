<style>
    [hidden] {
        display: none !important;
    }

    .betwatch-home,
    .countrywise-page {
        min-height: calc(100vh - 7rem);
        background: transparent;
        font-size: 14px;
        color: var(--betwatch-page-text);
    }

    .betwatch-strip {
        width: 100%;
        border-bottom: 1px solid var(--betwatch-page-border);
        background: var(--betwatch-page-surface);
    }

    .betwatch-strip-shell {
        width: var(--betwatch-shell-width);
        margin-inline: auto;
        padding-inline: 0;
        position: relative;
    }

    .betwatch-strip-scroll {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    .betwatch-strip-scroll::-webkit-scrollbar {
        display: none;
    }

    .betwatch-strip-track {
        box-sizing: border-box;
        display: grid;
        width: 100%;
        min-width: 960px;
        grid-template-columns:
            minmax(0.9rem, 1fr) 5.9rem 5.9rem 6rem 5.9rem 6.9rem 5.8rem 5.8rem 5.9rem 5.2rem minmax(0.9rem, 1fr);
    }

    .betwatch-strip-spacer,
    .betwatch-sport-button {
        min-height: 3.55rem;
        border-right: 1px solid var(--betwatch-page-border);
    }

    .betwatch-strip-spacer {
        background: var(--betwatch-page-surface);
    }

    .betwatch-strip-spacer--end {
        border-right: 0;
    }

    .betwatch-sport-dropdown {
        position: relative;
        min-height: 3.55rem;
        border-right: 1px solid var(--betwatch-page-border);
        background: var(--betwatch-page-surface);
    }

    .betwatch-sport-dropdown .betwatch-sport-button {
        width: 100%;
        min-height: 3.55rem;
        border-right: 0;
    }

    .betwatch-sport-button {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.28rem 0.48rem;
        color: var(--betwatch-page-text);
        background: var(--betwatch-page-surface);
        transition: background-color 0.2s ease, box-shadow 0.2s ease;
    }

    .betwatch-sport-button:hover {
        background: var(--betwatch-page-hover-tint);
        text-decoration: none !important;
    }

    .betwatch-sport-button.is-active {
        background: var(--betwatch-page-active-tint);
        box-shadow: inset 0 -2px 0 var(--betwatch-page-text);
    }

    .betwatch-sport-stack {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.2rem;
    }

    .betwatch-sport-icon {
        display: flex;
        width: 1.15rem;
        height: 1.15rem;
        align-items: center;
        justify-content: center;
        color: var(--betwatch-page-text-soft);
    }

    .betwatch-sport-icon svg {
        width: 1.15rem;
        height: 1.15rem;
    }

    .betwatch-sport-icon--horse-racing,
    .betwatch-sport-icon svg.betwatch-horse-racing-icon {
        width: 2.2rem;
        height: 2.2rem;
    }

    .betwatch-sport-label,
    .betwatch-sport-row {
        font-size: 0.84rem;
        line-height: 1;
        font-weight: 400;
        color: var(--betwatch-page-text);
        white-space: nowrap;
    }

    .betwatch-sport-row {
        display: inline-flex;
        align-items: center;
        gap: 0.22rem;
    }

    .betwatch-sport-row svg {
        width: 0.8rem;
        height: 0.8rem;
    }

    .betwatch-sport-dropdown-menu {
        width: 11.25rem;
        padding: 0.4rem 0;
        border: 1px solid var(--betwatch-page-border);
        border-radius: 0.9rem;
        background: var(--betwatch-page-surface);
        box-shadow: var(--betwatch-page-shadow-sm);
    }

    .betwatch-strip-dropdown-shell {
        position: absolute;
        top: calc(100% + 0.35rem);
        right: 0;
        z-index: 40;
    }

    .betwatch-sport-dropdown-link {
        display: flex;
        align-items: center;
        min-height: 2.35rem;
        padding: 0.45rem 0.9rem;
        font-size: 0.82rem;
        color: var(--betwatch-page-text);
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    .betwatch-sport-dropdown-link:hover,
    .betwatch-sport-dropdown-link.is-active {
        color: var(--betwatch-page-text);
    }

    .betwatch-sport-dropdown-link:hover {
        background: var(--betwatch-page-hover-tint);
    }

    .betwatch-sport-dropdown-link.is-active {
        background: var(--betwatch-page-active-tint);
    }

    .betwatch-main-shell,
    .countrywise-main {
        width: var(--betwatch-shell-width);
        margin-inline: auto;
        max-width: none;
        padding: 1.35rem 0 2rem;
    }

    .betwatch-main-grid,
    .countrywise-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        align-items: start;
        gap: 1.15rem;
    }

    .countrywise-sidebar,
    .countrywise-content,
    .betwatch-main-sidebar-spacer {
        min-width: 0;
    }

    .betwatch-center-column {
        min-width: 0;
        display: grid;
        gap: 0.72rem;
    }

    .countrywise-content {
        display: grid;
        gap: 0.85rem;
    }

    .countrywise-card,
    .betwatch-leagues-card,
    .betwatch-support-card,
    .betwatch-toolbar,
    .betwatch-match-card {
        border: 1px solid var(--betwatch-page-border);
        border-radius: 1.15rem;
        background: var(--betwatch-page-surface);
    }

    .betwatch-leagues-card {
        width: 100%;
        max-width: 21.25rem;
        padding: 1.45rem 0.95rem 0.95rem;
    }

    .betwatch-leagues-title {
        margin: 0;
        font-size: 1rem;
        line-height: 1.2;
        font-weight: 600;
        color: var(--betwatch-page-text);
    }

    .betwatch-filter-box {
        position: relative;
        margin-top: 1.3rem;
    }

    .betwatch-filter-input {
        width: 100%;
        height: 2.45rem;
        padding: 0 1rem 0 2.95rem;
        font-size: 0.9rem;
        outline: none;
    }

    .betwatch-filter-input::placeholder {
        color: var(--betwatch-page-text-muted);
    }

    .betwatch-filter-icon {
        position: absolute;
        left: 1.05rem;
        top: 50%;
        width: 1rem;
        height: 1rem;
        color: var(--betwatch-page-text-muted);
        transform: translateY(-50%);
    }

    .betwatch-country-list {
        margin-top: 1rem;
    }

    .betwatch-country-item {
        display: flex;
        align-items: center;
        gap: 0.62rem;
        width: 100%;
        min-height: 1.92rem;
        padding: 0.15rem 0;
        border: 0;
        background: transparent;
        color: var(--betwatch-page-text);
        text-align: left;
        cursor: pointer;
        text-decoration: none;
        accent-color:var(--betwatch-page-text);
    }

    .betwatch-country-check {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 0.92rem;
        height: 0.92rem;
        flex: 0 0 0.92rem;
        border-radius: 0.16rem;
        border: 1px solid var(--betwatch-page-active-bg);
        background: var(--betwatch-page-surface);
    }

    .betwatch-country-check.is-checked {
        background: var(--betwatch-page-active-bg);
    }

    .betwatch-country-check svg {
        width: 0.54rem;
        height: 0.54rem;
        stroke: var(--betwatch-page-active-text);
        opacity: 0;
    }

    .betwatch-country-check.is-checked svg {
        opacity: 1;
    }

    .betwatch-flag {
        width: 1.42rem;
        height: 0.98rem;
        flex: 0 0 1.42rem;
        border: 1px solid var(--betwatch-page-border);
        border-radius: 0.12rem;
        background: var(--betwatch-page-surface);
        overflow: hidden;
    }

    .betwatch-flag--albania {
        background:
            radial-gradient(circle at 50% 50%, rgb(var(--text-main)) 0 16%, transparent 17%),
            linear-gradient(rgb(var(--danger)), rgb(var(--danger)));
    }

    .betwatch-flag--algeria {
        background:
            radial-gradient(circle at 58% 50%, rgb(var(--danger)) 0 14%, transparent 15%),
            linear-gradient(90deg, rgb(var(--surface)) 0 50%, rgb(var(--success)) 50% 100%);
    }

    .betwatch-flag--argentina {
        background: linear-gradient(rgb(var(--secondary)) 0 33%, rgb(var(--surface)) 33% 66%, rgb(var(--secondary)) 66% 100%);
    }

    .betwatch-flag--armenia {
        background: linear-gradient(rgb(var(--primary)) 0 33%, rgb(var(--danger)) 33% 66%, rgb(var(--warning)) 66% 100%);
    }

    .betwatch-flag--australia {
        background:
            radial-gradient(circle at 72% 38%, rgb(var(--surface)) 0 7%, transparent 8%),
            radial-gradient(circle at 79% 68%, rgb(var(--surface)) 0 5%, transparent 6%),
            linear-gradient(135deg, rgb(var(--surface)) 0 10%, transparent 10% 100%),
            linear-gradient(45deg, transparent 0 40%, rgb(var(--danger)) 40% 52%, transparent 52% 100%),
            linear-gradient(rgb(var(--primary)), rgb(var(--primary-strong)));
    }

    .betwatch-flag--austria {
        background: linear-gradient(rgb(var(--danger)) 0 33%, rgb(var(--surface)) 33% 66%, rgb(var(--danger)) 66% 100%);
    }

    .betwatch-flag--azerbaijan {
        background:
            radial-gradient(circle at 48% 50%, rgb(var(--surface)) 0 8%, transparent 9%),
            linear-gradient(rgb(var(--secondary)) 0 33%, rgb(var(--danger)) 33% 66%, rgb(var(--success)) 66% 100%);
    }

    .betwatch-flag--bahrain {
        background:
            linear-gradient(90deg, rgb(var(--surface)) 0 34%, transparent 34% 100%),
            linear-gradient(144deg, transparent 0 24%, rgb(var(--surface)) 24% 31%, transparent 31% 38%, rgb(var(--surface)) 38% 45%, transparent 45% 52%, rgb(var(--surface)) 52% 59%, transparent 59% 66%, rgb(var(--surface)) 66% 73%, transparent 73% 100%),
            linear-gradient(rgb(var(--danger)), rgb(var(--danger)));
    }

    .betwatch-flag--belgium {
        background: linear-gradient(90deg, rgb(var(--text-main)) 0 33%, rgb(var(--warning)) 33% 66%, rgb(var(--danger)) 66% 100%);
    }

    .betwatch-flag--bolivia {
        background: linear-gradient(rgb(var(--danger)) 0 33%, rgb(var(--warning)) 33% 66%, rgb(var(--success)) 66% 100%);
    }

    .betwatch-flag--brazil {
        background:
            radial-gradient(circle at 50% 50%, rgb(var(--primary)) 0 18%, transparent 19%),
            linear-gradient(45deg, transparent 0 34%, rgb(var(--warning)) 34% 66%, transparent 66% 100%),
            linear-gradient(135deg, transparent 0 34%, rgb(var(--warning)) 34% 66%, transparent 66% 100%),
            linear-gradient(rgb(var(--success)), rgb(var(--success)));
    }

    .betwatch-flag--bulgaria {
        background: linear-gradient(rgb(var(--surface)) 0 33%, rgb(var(--success)) 33% 66%, rgb(var(--danger)) 66% 100%);
    }

    .betwatch-flag--cameroon {
        background:
            radial-gradient(circle at 50% 50%, rgb(var(--warning)) 0 13%, transparent 14%),
            linear-gradient(90deg, rgb(var(--success)) 0 33%, rgb(var(--danger)) 33% 66%, rgb(var(--warning)) 66% 100%);
    }

    .betwatch-flag--chile {
        background:
            radial-gradient(circle at 25% 24%, rgb(var(--surface)) 0 7%, transparent 8%),
            linear-gradient(90deg, rgb(var(--primary)) 0 40%, transparent 40% 100%),
            linear-gradient(rgb(var(--surface)) 0 50%, rgb(var(--danger)) 50% 100%);
    }

    .betwatch-flag--kazakhstan {
        background:
            radial-gradient(circle at 50% 50%, rgb(var(--warning)) 0 12%, transparent 13%),
            linear-gradient(rgb(var(--secondary)), rgb(var(--secondary)));
    }

    .betwatch-flag--japan {
        background:
            radial-gradient(circle at 50% 50%, rgb(var(--danger)) 0 18%, transparent 19%),
            linear-gradient(rgb(var(--surface)), rgb(var(--surface)));
    }

    .betwatch-flag--united-kingdom {
        background:
            linear-gradient(90deg, transparent 0 40%, rgb(var(--surface)) 40% 60%, transparent 60% 100%),
            linear-gradient(transparent 0 40%, rgb(var(--surface)) 40% 60%, transparent 60% 100%),
            linear-gradient(90deg, transparent 0 44%, rgb(var(--danger)) 44% 56%, transparent 56% 100%),
            linear-gradient(transparent 0 44%, rgb(var(--danger)) 44% 56%, transparent 56% 100%),
            linear-gradient(rgb(var(--primary-strong)), rgb(var(--primary-strong)));
    }

    .betwatch-flag--norway {
        background:
            linear-gradient(90deg, transparent 0 34%, rgb(var(--surface)) 34% 46%, transparent 46% 100%),
            linear-gradient(transparent 0 40%, rgb(var(--surface)) 40% 60%, transparent 60% 100%),
            linear-gradient(90deg, transparent 0 37%, rgb(var(--primary)) 37% 43%, transparent 43% 100%),
            linear-gradient(transparent 0 44%, rgb(var(--primary)) 44% 56%, transparent 56% 100%),
            linear-gradient(rgb(var(--danger)), rgb(var(--danger)));
    }

    .betwatch-flag--china {
        background:
            radial-gradient(circle at 24% 26%, rgb(var(--warning)) 0 10%, transparent 11%),
            linear-gradient(rgb(var(--danger)), rgb(var(--danger)));
    }

    .betwatch-flag--croatia {
        background:
            radial-gradient(circle at 50% 50%, rgb(var(--primary)) 0 9%, transparent 10%),
            linear-gradient(rgb(var(--danger)) 0 33%, rgb(var(--surface)) 33% 66%, rgb(var(--primary)) 66% 100%);
    }

    .betwatch-flag--czechia {
        background:
            linear-gradient(145deg, rgb(var(--primary)) 0 49%, transparent 50% 100%),
            linear-gradient(rgb(var(--surface)) 0 50%, rgb(var(--danger)) 50% 100%);
    }

    .betwatch-flag--dominican-republic {
        background:
            linear-gradient(90deg, transparent 0 44%, rgb(var(--surface)) 44% 56%, transparent 56% 100%),
            linear-gradient(transparent 0 44%, rgb(var(--surface)) 44% 56%, transparent 56% 100%),
            linear-gradient(90deg, rgb(var(--primary)) 0 50%, rgb(var(--danger)) 50% 100%),
            linear-gradient(rgb(var(--primary)) 0 50%, rgb(var(--danger)) 50% 100%);
    }

    .betwatch-flag--finland {
        background:
            linear-gradient(90deg, transparent 0 28%, rgb(var(--primary)) 28% 42%, transparent 42% 100%),
            linear-gradient(transparent 0 42%, rgb(var(--primary)) 42% 58%, transparent 58% 100%),
            linear-gradient(rgb(var(--surface)), rgb(var(--surface)));
    }

    .betwatch-flag--france {
        background: linear-gradient(90deg, rgb(var(--primary)) 0 33%, rgb(var(--surface)) 33% 66%, rgb(var(--danger)) 66% 100%);
    }

    .betwatch-flag--germany {
        background: linear-gradient(rgb(var(--text-main)) 0 33%, rgb(var(--danger)) 33% 66%, rgb(var(--warning)) 66% 100%);
    }

    .betwatch-flag--india {
        background: linear-gradient(rgb(var(--warning)) 0 33%, rgb(var(--surface)) 33% 66%, rgb(var(--success)) 66% 100%);
    }

    .betwatch-flag--pakistan {
        background: linear-gradient(90deg, rgb(var(--surface)) 0 22%, rgb(var(--success)) 22% 100%);
    }

    .betwatch-flag--korea {
        background:
            radial-gradient(circle at 50% 42%, rgb(var(--danger)) 0 18%, transparent 19%),
            radial-gradient(circle at 50% 58%, rgb(var(--primary)) 0 18%, transparent 19%),
            linear-gradient(180deg, rgb(var(--surface)) 0 100%);
        border: 1px solid rgb(var(--border));
    }

    .betwatch-flag--new-zealand {
        background:
            radial-gradient(circle at 73% 34%, rgb(var(--danger)) 0 6%, rgb(var(--surface)) 6% 9%, transparent 10%),
            radial-gradient(circle at 80% 58%, rgb(var(--danger)) 0 5%, rgb(var(--surface)) 5% 8%, transparent 9%),
            linear-gradient(rgb(var(--primary-strong)), rgb(var(--primary-strong)));
    }

    .betwatch-flag--poland {
        background: linear-gradient(rgb(var(--surface)) 0 50%, rgb(var(--danger)) 50% 100%);
    }

    .betwatch-flag--international {
        background:
            radial-gradient(circle at 24% 28%, rgb(var(--warning)) 0 4%, transparent 5%),
            radial-gradient(circle at 50% 18%, rgb(var(--warning)) 0 4%, transparent 5%),
            radial-gradient(circle at 76% 28%, rgb(var(--warning)) 0 4%, transparent 5%),
            radial-gradient(circle at 34% 56%, rgb(var(--warning)) 0 4%, transparent 5%),
            radial-gradient(circle at 66% 56%, rgb(var(--warning)) 0 4%, transparent 5%),
            linear-gradient(rgb(var(--primary)), rgb(var(--primary)));
    }

    .betwatch-flag--romania {
        background: linear-gradient(90deg, rgb(var(--primary)) 0 33%, rgb(var(--warning)) 33% 66%, rgb(var(--danger)) 66% 100%);
    }

    .betwatch-flag--israel {
        background:
            linear-gradient(rgb(var(--primary)) 0 12%, transparent 12% 88%, rgb(var(--primary)) 88% 100%),
            linear-gradient(rgb(var(--surface)), rgb(var(--surface)));
    }

    .betwatch-flag--turkey {
        background:
            radial-gradient(circle at 42% 50%, rgb(var(--surface)) 0 12%, transparent 13%),
            radial-gradient(circle at 45% 50%, rgb(var(--danger)) 0 8%, transparent 9%),
            linear-gradient(rgb(var(--danger)), rgb(var(--danger)));
    }

    .betwatch-flag--serbia {
        background: linear-gradient(rgb(var(--danger)) 0 33%, rgb(var(--primary)) 33% 66%, rgb(var(--surface)) 66% 100%);
    }

    .betwatch-flag--slovakia {
        background: linear-gradient(rgb(var(--surface)) 0 33%, rgb(var(--primary)) 33% 66%, rgb(var(--danger)) 66% 100%);
    }

    .betwatch-country-name {
        font-size: 0.9rem;
        line-height: 1.25;
        color: var(--betwatch-page-text);
    }

    .betwatch-ui-pill,
    .countrywise-pill,
    .countrywise-date-button,
    .countrywise-date-button__grow {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .betwatch-ui-pill,
    .betwatch-filter-input,
    .betwatch-support-custom,
    .countrywise-pill,
    .countrywise-date-button {
        border: 1px solid var(--betwatch-page-border);
        border-radius: 9999px;
        background: var(--betwatch-page-surface);
        color: var(--betwatch-page-text);
    }

    .betwatch-ui-circle,
    .countrywise-flag-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
    }

    .betwatch-show-more {
        gap: 0.55rem;
        width: 9.65rem;
        height: 2.55rem;
        margin-top: 0.95rem;
        margin-inline: auto;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .betwatch-show-more svg {
        display: block;
        width: 0.95rem;
        height: 0.95rem;
        flex-shrink: 0;
        stroke: currentColor;
    }

    .betwatch-main-sidebar-spacer {
        display: grid;
        min-width: 0;
        justify-self: stretch;
    }

    .betwatch-support-card {
        width: 100%;
        max-width: none;
        padding: 1.4rem 1.25rem 1.3rem;
    }

    .betwatch-support-title {
        margin: 0;
        font-size: 0.98rem;
        line-height: 1.2;
        font-weight: 600;
        color: var(--betwatch-page-text);
    }

    .betwatch-support-copy {
        margin-top: 0.95rem;
        font-size: 0.88rem;
        line-height: 1.5;
        color: var(--betwatch-page-text);
    }

    .betwatch-support-progress {
        margin-top: 1rem;
    }

    .betwatch-support-track {
        overflow: hidden;
        width: 100%;
        height: 0.52rem;
        border-radius: 9999px;
        background: var(--betwatch-page-surface-soft);
    }

    .betwatch-support-fill {
        width: 31%;
        height: 100%;
        border-radius: 9999px;
        background: var(--betwatch-page-support-gradient);
    }

    .betwatch-support-count {
        margin-top: 0.18rem;
        font-size: 0.78rem;
        text-align: center;
        color: var(--betwatch-page-text);
    }

    .betwatch-support-amounts {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.5rem;
        margin-top: 1.1rem;
    }

    .betwatch-support-pill {
        width: 100%;
        height: 2.45rem;
        font-size: 0.87rem;
        font-weight: 500;
    }

    .betwatch-support-pill.is-active {
        border-color: var(--betwatch-page-active-bg);
        background: var(--betwatch-page-active-bg);
        color: var(--betwatch-page-active-text);
    }

    .betwatch-support-custom {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 0.95rem;
        align-items: center;
        gap: 0.75rem;
        width: 100%;
        height: 2.45rem;
        margin-top: 0.62rem;
        padding: 0 1rem;
        font-size: 0.87rem;
    }

    .betwatch-support-custom span:first-child {
        text-align: center;
    }

    .betwatch-support-check {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        margin-top: 1.15rem;
        font-size: 0.86rem;
        color: var(--betwatch-page-text);
    }

    .betwatch-support-check-box {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1rem;
        height: 1rem;
        flex: 0 0 1rem;
        border-radius: 0.16rem;
        background: var(--betwatch-page-active-bg);
    }

    .betwatch-support-check-box::after {
        content: "";
        position: absolute;
        left: 0.34rem;
        top: 0.16rem;
        width: 0.18rem;
        height: 0.42rem;
        border-right: 1.8px solid var(--betwatch-page-active-text);
        border-bottom: 1.8px solid var(--betwatch-page-active-text);
        transform: rotate(42deg);
    }

    .betwatch-support-donate {
        gap: 0.45rem;
        width: 100%;
        height: 2.85rem;
        margin-top: 1.55rem;
        font-size: 0.95rem;
        font-weight: 600;
    }

    .betwatch-support-donate svg {
        width: 0.95rem;
        height: 0.95rem;
        fill: currentColor;
    }

    .betwatch-toolbar {
        position: relative;
        z-index: 12;
        overflow: visible;
    }

    .betwatch-toolbar-head-shell {
        position: relative;
        z-index: 8;
    }

    .betwatch-toolbar-head {
        display: grid;
        grid-template-columns: 2.7rem minmax(0, 1fr) 2.7rem;
        align-items: center;
        gap: 0.8rem;
        min-height: 4.05rem;
        padding: 0 1rem;
        border-bottom: 1px solid var(--betwatch-page-border);
    }

    .betwatch-toolbar-nav {
        width: 2.55rem;
        height: 2.55rem;
        background: var(--betwatch-page-chip-tint);
        color: var(--betwatch-page-text);
    }

    .betwatch-toolbar-nav svg {
        width: 1rem;
        height: 1rem;
        stroke: currentColor;
    }

    .betwatch-toolbar-day {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.42rem;
        font-size: 0.89rem;
        font-weight: 600;
        color: var(--betwatch-page-text);
    }

    .betwatch-toolbar-day.is-open svg {
        transform: rotate(180deg);
    }

    .betwatch-toolbar-day svg {
        width: 0.92rem;
        height: 0.92rem;
        stroke: currentColor;
        transition: transform 0.2s ease;
    }

    .betwatch-toolbar-calendar-shell {
        position: absolute;
        top: calc(100% - 0.18rem);
        left: 0;
        right: 0;
        z-index: 20;
        width: min(22.5rem, calc(100vw - 2.1rem));
        margin-inline: auto;
        transform-origin: top center;
    }

    .betwatch-toolbar-dropdown-enter,
    .betwatch-toolbar-dropdown-leave {
        transition: opacity 0.18s ease, transform 0.18s ease;
    }

    .betwatch-toolbar-dropdown-enter-start,
    .betwatch-toolbar-dropdown-leave-end {
        opacity: 0;
        transform: translateY(-0.28rem);
    }

    .betwatch-toolbar-dropdown-enter-end,
    .betwatch-toolbar-dropdown-leave-start {
        opacity: 1;
        transform: translateY(0);
    }

    .betwatch-toolbar-calendar {
        padding: 1.2rem 1.15rem 1.1rem;
        border: 1px solid var(--betwatch-page-border);
        border-radius: 1rem;
        background: var(--betwatch-page-surface);
        box-shadow: var(--betwatch-page-shadow-lg);
    }

    .betwatch-toolbar-calendar-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .betwatch-toolbar-calendar-title {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--betwatch-page-text);
    }

    .betwatch-toolbar-calendar-controls {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .betwatch-toolbar-calendar-nav {
        width: 2rem;
        height: 2rem;
        color: var(--betwatch-page-text-soft);
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    .betwatch-toolbar-calendar-nav:hover {
        background: var(--betwatch-page-hover-tint);
        color: var(--betwatch-page-text);
    }

    .betwatch-toolbar-calendar-nav svg {
        width: 1.05rem;
        height: 1.05rem;
        stroke: currentColor;
    }

    .betwatch-toolbar-weekdays,
    .betwatch-toolbar-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 0.28rem;
    }

    .betwatch-toolbar-weekdays {
        margin-bottom: 0.34rem;
    }

    .betwatch-toolbar-weekdays span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2rem;
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--betwatch-page-text-soft);
    }

    .betwatch-toolbar-calendar-day {
        min-height: 2.5rem;
        font-size: 0.96rem;
        font-weight: 500;
        color: var(--betwatch-page-text);
        transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    }

    .betwatch-toolbar-calendar-day.is-outside {
        color: var(--betwatch-page-text-muted);
    }

    .betwatch-toolbar-calendar-day.is-selected {
        background: var(--betwatch-page-active-bg);
        color: var(--betwatch-page-active-text);
    }

    .betwatch-toolbar-calendar-day.is-today {
        box-shadow: inset 0 0 0 1px var(--betwatch-page-border-strong);
    }

    .betwatch-toolbar-calendar-day:hover {
        background: var(--betwatch-page-hover-tint);
    }

    .betwatch-toolbar-calendar-day.is-selected:hover {
        background: var(--betwatch-page-active-bg);
    }

    .betwatch-toolbar-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.62rem;
        padding: 0.8rem 1rem 0.95rem;
    }

    .betwatch-toolbar-pill {
        height: 2.4rem;
        padding: 0 1.3rem;
        font-size: 0.82rem;
        font-weight: 500;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .betwatch-toolbar-pill.is-active {
        border-color: var(--betwatch-page-border-strong);
        box-shadow: inset 0 0 0 1px var(--betwatch-page-border);
    }

    .betwatch-match-card {
        overflow: hidden;
        border-radius: 0.95rem;
    }

    .betwatch-match-head {
        grid-template-columns: 1.5rem minmax(0, 1fr) 3.2rem 3.2rem 3.2rem;
        min-height: 2.85rem;
        background: var(--betwatch-page-header-tint);
    }

    .betwatch-star-icon {
        width: 1.1rem;
        height: 1.1rem;
        color: var(--betwatch-page-text-muted);
        stroke: currentColor;
    }

    .betwatch-star-icon[data-favorite-trigger] {
        cursor: pointer;
        transition: color 0.18s ease, transform 0.18s ease, fill 0.18s ease;
    }

    .betwatch-star-icon[data-favorite-trigger] path {
        fill: transparent;
        transition: fill 0.18s ease, stroke 0.18s ease;
    }

    .betwatch-star-icon[data-favorite-trigger]:hover {
        color: var(--betwatch-page-text-soft);
        transform: scale(1.03);
    }

    .betwatch-star-icon[data-favorite-trigger].is-favorite,
    .betwatch-star-icon[data-favorite-trigger][data-favorite-active="1"],
    .leaguewise-page .betwatch-star-icon[data-favorite-trigger].is-favorite,
    .leaguewise-page .betwatch-star-icon[data-favorite-trigger][data-favorite-active="1"] {
        color: var(--betwatch-page-accent) !important;
        fill: currentColor !important;
    }

    .betwatch-star-icon[data-favorite-trigger].is-favorite path,
    .betwatch-star-icon[data-favorite-trigger][data-favorite-active="1"] path,
    .leaguewise-page .betwatch-star-icon[data-favorite-trigger].is-favorite path,
    .leaguewise-page .betwatch-star-icon[data-favorite-trigger][data-favorite-active="1"] path {
        fill: currentColor !important;
        stroke: currentColor !important;
    }

    .betwatch-match-league {
        display: flex;
        align-items: center;
        gap: 0.46rem;
        min-width: 0;
        font-size: 0.8rem;
        color: var(--betwatch-page-text-soft);
    }

    .betwatch-match-league span:last-child {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .betwatch-odds-head {
        font-size: 0.77rem;
        font-weight: 500;
        text-align: center;
        color: var(--betwatch-page-text);
    }

    .betwatch-match-row {
        grid-template-columns: 1.5rem 4.45rem minmax(0, 1fr) 3.2rem 3.2rem 3.2rem;
        min-height: 3rem;
        border-top: 1px solid var(--betwatch-page-border);
        background: var(--betwatch-page-surface);
    }

    .betwatch-match-head,
    .betwatch-match-row {
        display: grid;
        align-items: center;
        gap: 0.65rem;
        padding: 0 0.9rem;
    }

    .betwatch-match-row-shell--expandable .betwatch-match-row {
        cursor: pointer;
        transition: background-color 0.18s ease;
    }

    .betwatch-match-row-shell--expandable .betwatch-match-row:hover,
    .betwatch-match-row-shell--expandable .betwatch-match-row.is-open {
        background: var(--betwatch-page-surface-soft);
    }

    .betwatch-market-collapse {
        border-top: 1px solid var(--betwatch-page-border);
        background: var(--betwatch-page-surface-soft);
        padding: 0.4rem 0 0.45rem;
    }

    .betwatch-market-collapse__row {
        display: grid;
        grid-template-columns: 1rem 1rem minmax(0, 1fr) 6.5rem 6.5rem;
        align-items: center;
        gap: 0.75rem;
        min-height: 3.05rem;
        padding-inline: 15%;
        /* padding: 0 1rem 0 clamp(8.2rem, 12vw, 10.2rem); */
    }

    .betwatch-market-collapse__row+.betwatch-market-collapse__row {
        margin-top: 0.16rem;
    }

    .betwatch-market-collapse__star,
    .betwatch-market-collapse__chart-icon {
        width: 1rem;
        height: 1rem;
        color: var(--betwatch-page-text-soft);
    }

    .betwatch-market-collapse__name {
        display: flex;
        align-items: center;
        min-width: 0;
        font-size: 0.81rem;
        font-weight: 600;
        color: var(--betwatch-page-text);
    }

    .betwatch-market-collapse__name span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .betwatch-market-collapse__selection {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 0;
    }

    .betwatch-market-collapse__selection-grid {
        display: grid;
        grid-template-columns: auto auto;
        align-items: center;
        justify-content: center;
        column-gap: 0.45rem;
        width: 100%;
    }

    .betwatch-market-collapse__label {
        font-size: 0.79rem;
        line-height: 1.15;
        color: var(--betwatch-page-text);
        text-align: left;
        white-space: nowrap;
    }

    .betwatch-market-collapse__values {
        display: grid;
        grid-template-rows: auto auto;
        justify-items: center;
        align-content: center;
        row-gap: 0.04rem;
        min-width: 0;
    }

    .betwatch-market-collapse__volume {
        font-size: 0.79rem;
        line-height: 1.15;
        color: var(--betwatch-page-text);
        text-align: center;
    }

    .betwatch-market-collapse__selection .betwatch-odd {
        font-size: 0.79rem;
        line-height: 1.15;
        justify-content: center;
    }

    .betwatch-market-collapse__scroll-shell {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        padding-bottom: 0.25rem;
        scrollbar-width: thin;
    }

    .betwatch-market-collapse__scroll-shell::-webkit-scrollbar {
        height: 8px;
    }

    .betwatch-market-collapse__scroll-shell::-webkit-scrollbar-track {
        background: var(--betwatch-page-scroll-track);
        border-radius: 999px;
    }

    .betwatch-market-collapse__scroll-shell::-webkit-scrollbar-thumb {
        background: var(--betwatch-page-scroll-thumb);
        border-radius: 999px;
    }

    .betwatch-market-collapse__scroll-track {
        display: grid;
        gap: 0.75rem;
        min-width: max-content;
    }

    .betwatch-status {
        font-size: 0.73rem;
        line-height: 1;
        color: var(--betwatch-page-text);
        white-space: nowrap;
    }

    .betwatch-status--live {
        color: var(--betwatch-page-danger);
    }

    .betwatch-live-minute-mark {
        animation: betwatch-live-minute-mark-blink 0.8s step-end infinite;
    }

    @keyframes betwatch-live-minute-mark-blink {
        50% {
            opacity: 0;
        }
    }

    .betwatch-teams {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.48rem;
        min-width: 0;
        font-size: 0.73rem;
        color: var(--betwatch-page-text);
        white-space: nowrap;
    }

    .betwatch-teams__label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .betwatch-score {
        font-size: 0.83rem;
        color: var(--betwatch-page-danger);
        flex: 0 0 auto;
    }

    .betwatch-score--finished {
        color: var(--betwatch-page-text);
    }

    .betwatch-odd {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.15rem;
        font-size: 0.73rem;
        color: var(--betwatch-page-text);
        white-space: nowrap;
    }

    .betwatch-odd--up {
        color: var(--betwatch-page-text);
    }

    .betwatch-odd--down {
        color: var(--betwatch-page-text);
    }

    .betwatch-odd--neutral {
        color: var(--betwatch-page-text);
    }

    .betwatch-trend {
        display: inline-block;
        width: 0.72rem;
        font-size: 0;
        line-height: 1;
    }

    .betwatch-odd--up .betwatch-trend {
        color: var(--betwatch-page-success);
    }

    .betwatch-odd--down .betwatch-trend {
        color: var(--betwatch-page-danger);
    }

    .betwatch-odd--up .betwatch-trend::before {
        content: "\2191";
        font-size: 0.95rem;
        line-height: 1;
    }

    .betwatch-odd--down .betwatch-trend::before {
        content: "\2193";
        font-size: 0.95rem;
        line-height: 1;
    }

    .betwatch-trend-tooltip {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .betwatch-trend-tooltip__bubble {
        position: absolute;
        left: 50%;
        bottom: calc(100% + 12px);
        z-index: 9999;
        display: inline-flex;
        align-items: center;
        gap: 0.22rem;
        min-width: max-content;
        padding: 10px;
        color: #ffffff;
        background: #343b48;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transform: translateX(-50%) translateY(5px);
        transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
    }

    .betwatch-trend-tooltip__bubble::after {
        content: "";
        position: absolute;
        left: 50%;
        top: 100%;
        width: 0;
        height: 0;
        border-left: 9px solid transparent;
        border-right: 9px solid transparent;
        border-top: 9px solid #343b48;
        transform: translateX(-50%);
    }

    .betwatch-trend-tooltip__arrow {
        font-size: 1.2em;
        line-height: 1;
        opacity: 0.8;
    }

    .betwatch-trend-tooltip:hover .betwatch-trend-tooltip__bubble,
    .betwatch-trend-tooltip:focus-visible .betwatch-trend-tooltip__bubble {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) translateY(0);
    }

    .countrywise-hero {
        padding: 1.2rem 1.2rem 0.95rem;
    }

    .countrywise-hero-top,
    .countrywise-main-tabs,
    .countrywise-filter-pills,
    .countrywise-filter-bar {
        display: flex;
    }

    .countrywise-main-tabs,
    .countrywise-filter-pills,
    .countrywise-filter-bar {
        flex-wrap: wrap;
    }

    .countrywise-hero-top,
    .countrywise-filter-bar {
        align-items: center;
    }

    .countrywise-hero-top {
        gap: 1rem;
        min-height: 5.2rem;
    }

    .countrywise-flag-badge {
        width: 3.75rem;
        height: 3.75rem;
        flex: 0 0 3.75rem;
        border: 1px solid var(--betwatch-page-border);
        background: var(--betwatch-page-surface);
    }

    .countrywise-flag-badge .betwatch-flag {
        width: 1.9rem;
        height: 1.3rem;
        flex-basis: 1.9rem;
        border-radius: 0.12rem;
    }

    .countrywise-title {
        margin: 0;
        font-size: 1.85rem;
        line-height: 1.05;
        font-weight: 400;
        color: var(--betwatch-page-text);
    }

    .countrywise-main-tabs {
        gap: 1.6rem;
        margin-top: 1.15rem;
    }

    .countrywise-main-tab {
        position: relative;
        border: 0;
        background: transparent;
        padding: 0 0 0.8rem;
        font-size: 0.98rem;
        color: var(--betwatch-page-text);
    }

    .countrywise-main-tab.is-active::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 3px;
        border-radius: 9999px;
        background: var(--betwatch-page-text);
    }

    .countrywise-filter-bar {
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.55rem 0.95rem;
    }

    .countrywise-filter-pills {
        gap: 0.55rem;
    }

    .countrywise-pill,
    .countrywise-date-button {
        height: 2.38rem;
        font-size: 0.88rem;
    }

    .countrywise-pill {
        padding: 0 1.35rem;
        font-weight: 500;
    }

    .countrywise-pill.is-active {
        border-color: var(--betwatch-page-border-strong);
        box-shadow: inset 0 0 0 1px var(--betwatch-page-border);
    }

    .countrywise-date-button {
        gap: 0.6rem;
        min-width: 12.6rem;
        padding: 0 0.9rem;
    }

    .countrywise-date-button svg {
        width: 1rem;
        height: 1rem;
        stroke: currentColor;
    }

    .countrywise-date-button__grow {
        gap: 0.55rem;
        flex: 1 1 auto;
    }

    @media (min-width: 640px) {
        :root {
            --betwatch-shell-width: calc(100% - 2rem);
        }
        .betwatch-strip-track {
            min-width: 1000px;
            grid-template-columns:
                minmax(1rem, 1fr) 6.45rem 6.45rem 6.6rem 6.45rem 8rem 6.2rem 6.2rem 6.45rem 5.7rem minmax(1rem, 1fr);
        }

        .betwatch-leagues-card {
            padding-inline: 1rem;
        }

        .betwatch-toolbar-head {
            padding-inline: 1.15rem;
        }

        .betwatch-toolbar-calendar-shell {
            width: min(21rem, calc(100vw - 2.35rem));
        }

        .betwatch-toolbar-tabs {
            padding-inline: 1.15rem;
        }

        .betwatch-match-head,
        .betwatch-match-row {
            padding-inline: 1rem;
        }

        .countrywise-hero {
            padding-inline: 1.45rem;
        }

        .countrywise-filter-bar {
            padding-inline: 1.15rem;
        }
    }

    @media (min-width: 1024px) {
        :root {
            --betwatch-shell-width: min(calc(100% - clamp(5rem, 12vw, 16rem)), 75rem);
        }
        .betwatch-main-grid {
            grid-template-columns: minmax(17rem, 19.25rem) minmax(0, 1fr);
        }

        .countrywise-grid {
            grid-template-columns: minmax(17rem, 19rem) minmax(0, 1fr);
        }

        .countrywise-grid>.betwatch-main-sidebar-spacer {
            grid-column: 1 / -1;
        }
    }

    @media (min-width: 1280px) {
        body>header>div:first-of-type {
            padding-inline: 0.55rem !important;
        }

        .betwatch-strip-track {
            min-width: 100%;
            grid-template-columns:
                minmax(1.15rem, 1fr) 6.6rem 6.6rem 6.85rem 6.6rem 8.2rem 6.25rem 6.25rem 6.45rem 5.8rem minmax(1.15rem, 1fr);
        }

        .betwatch-strip-spacer,
        .betwatch-sport-button {
            min-height: 3.4rem;
        }

        .betwatch-main-grid {
            grid-template-columns: minmax(17rem, 19.25rem) minmax(38rem, 52rem);
            gap: 1.25rem;
        }

        .betwatch-main-grid.betwatch-main-grid--with-support {
            grid-template-columns: minmax(17rem, 19.25rem) minmax(38rem, 52rem) minmax(15rem, 18rem);
        }

        .countrywise-grid {
            grid-template-columns: minmax(17rem, 19rem) minmax(0, 1fr) minmax(17rem, 18rem);
            gap: 1.2rem;
        }

        .countrywise-grid.countrywise-grid--no-support {
            grid-template-columns: minmax(17rem, 19rem) minmax(0, 1fr);
        }

        .betwatch-main-grid.betwatch-main-grid--with-support .betwatch-main-sidebar-spacer {
            display: block;
        }

        .countrywise-grid>.betwatch-main-sidebar-spacer {
            grid-column: auto;
        }

        .betwatch-support-card {
            max-width: 18rem;
        }
    }

    .betwatch-match-row--empty {
        grid-template-columns: 1fr;
        justify-items: center;
    }

    .betwatch-empty-label {
        justify-self: center;
    }

    .betwatch-empty-label--small {
        font-size: 0.78rem;
    }

    .betwatch-match-head--compact {
        grid-template-columns: 1.5rem minmax(0, 1fr);
    }

    .betwatch-match-league--compact {
        font-size: 0.74rem;
    }

    .betwatch-match-row-shell--divided {
        border-top: 1px solid var(--betwatch-page-scroll-track);
    }

    .betwatch-match-head--two-way {
        grid-template-columns: 1.5rem minmax(0, 1fr) 3.2rem 3.2rem;
    }

    .betwatch-match-head--three-way {
        grid-template-columns: 1.5rem minmax(0, 1fr) 3.2rem 3.2rem 3.2rem;
    }

    .betwatch-match-row--two-way {
        grid-template-columns: 1.5rem 4.45rem minmax(0, 1fr) 3.2rem 3.2rem;
    }

    .betwatch-match-row--three-way {
        grid-template-columns: 1.5rem 4.45rem minmax(0, 1fr) 3.2rem 3.2rem 3.2rem;
    }

    .betwatch-country-toggle-icon.is-open {
        transform: rotate(180deg);
    }

    .betwatch-market-collapse__row--two-way {
        grid-template-columns: 1rem 1rem minmax(0, 1fr) 6.5rem 6.5rem;
    }

    .betwatch-market-collapse__row--three-way {
        grid-template-columns: 1rem 1rem minmax(0, 1fr) 5.2rem 5.2rem 5.2rem;
    }

    .betwatch-market-collapse__row--generic {
        grid-template-columns: 1rem 1rem minmax(0, 11rem) minmax(0, 1fr);
        padding-inline: 1rem;
    }

    .betwatch-tennis-row {
        grid-template-columns: 1.5rem 4.4rem minmax(0, 1.6fr) minmax(240px, 1fr);
        gap: 12px;
        align-items: center;
        padding-block: 0.72rem;
        border-top: 0;
    }

    .betwatch-tennis-status-stack {
        display: grid;
        gap: 4px;
        align-content: center;
        min-height: 58px;
    }

    .betwatch-tennis-status {
        white-space: nowrap;
        font-size: 0.67rem;
        color: var(--betwatch-page-text-soft);
    }

    .betwatch-tennis-status--live {
        color: var(--betwatch-page-danger);
    }

    .betwatch-tennis-team-stack {
        display: grid;
        gap: 8px;
        min-width: 0;
    }

    .betwatch-tennis-team-row {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    .betwatch-tennis-team-name {
        overflow: hidden;
        color: var(--betwatch-page-text);
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.75rem;
        line-height: 1.1;
    }

    .betwatch-tennis-score-stack {
        display: grid;
        gap: 8px;
        align-content: center;
        justify-self: start;
    }

    .betwatch-tennis-score-grid {
        display: grid;
        gap: 12px;
        justify-content: start;
        align-items: center;
    }

    .betwatch-tennis-score-grid--1 {
        grid-template-columns: repeat(1, minmax(18px, 28px));
    }

    .betwatch-tennis-score-grid--2 {
        grid-template-columns: repeat(2, minmax(18px, 28px));
    }

    .betwatch-tennis-score-grid--3 {
        grid-template-columns: repeat(3, minmax(18px, 28px));
    }

    .betwatch-tennis-score-grid--4 {
        grid-template-columns: repeat(4, minmax(18px, 28px));
    }

    .betwatch-tennis-score-grid--5 {
        grid-template-columns: repeat(5, minmax(18px, 28px));
    }

    .betwatch-tennis-score-grid--6 {
        grid-template-columns: repeat(6, minmax(18px, 28px));
    }

    .betwatch-tennis-score-cell {
        color: var(--betwatch-page-text-soft);
        text-align: center;
        font-size: 0.83rem;
        line-height: 1;
    }

    .betwatch-tennis-score-cell--highlight {
        border-radius: 6px;
        background: var(--betwatch-page-danger);
        color: var(--betwatch-page-active-text);
        padding: 3px 0;
    }

    .betwatch-icon-box {
        display: inline-flex;
        width: 1rem;
        height: 1rem;
        align-items: center;
        justify-content: center;
    }

    .betwatch-chart-icon-muted {
        opacity: 0.38;
    }

    [data-tennis-row-shell][data-open="0"] [data-tennis-market-collapse] {
        display: none;
    }

    [data-tennis-row-shell][data-open="1"] [data-tennis-market-collapse] {
        display: block;
    }

    .betwatch-market-collapse__scroll-track--two-way {
        grid-template-columns: repeat(2, minmax(6.5rem, 6.5rem));
    }

    .betwatch-market-collapse__scroll-track--three-way {
        grid-template-columns: repeat(3, minmax(7.6rem, 7.6rem));
    }

    .betwatch-market-collapse__label--truncate {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .betwatch-moneyway-row {
        grid-template-columns: 74px 96px minmax(0, 2.12fr) minmax(260px, 0.95fr);
        gap: 12px;
        align-items: center;
        padding-block: 1rem;
    }

    .betwatch-moneyway-icon-stack {
        display: grid;
        justify-items: center;
        align-content: center;
        gap: 10px;
        min-height: 66px;
    }

    .betwatch-moneyway-info-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 13px;
        height: 13px;
        border: 1px solid var(--betwatch-page-text);
        border-radius: 999px;
        font-size: 0.64rem;
        line-height: 1;
        color: var(--betwatch-page-text);
    }

    .betwatch-moneyway-status-stack {
        display: grid;
        gap: 3px;
        justify-items: start;
        align-content: center;
        min-height: 66px;
    }

    .betwatch-moneyway-status {
        font-size: 0.72rem;
        line-height: 1.05;
        white-space: nowrap;
    }

    .betwatch-moneyway-status--default,
    .betwatch-moneyway-odd-value--neutral {
        color: var(--betwatch-page-text);
    }

    .betwatch-moneyway-status--live,
    .betwatch-moneyway-odd-value--down {
        color: var(--betwatch-page-danger);
    }

    .betwatch-moneyway-odd-value--up {
        color: var(--betwatch-page-success);
    }

    .betwatch-moneyway-match-column {
        display: grid;
        align-content: center;
        justify-items: center;
        min-width: 0;
        gap: 3px;
        text-align: center;
    }

    .betwatch-moneyway-league-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.76rem;
        line-height: 1.05;
        color: var(--betwatch-page-text);
    }

    .betwatch-moneyway-match-link {
        font-size: 0.76rem;
        font-weight: 700;
        line-height: 1.05;
        color: var(--betwatch-page-text);
    }

    .betwatch-moneyway-market-caption {
        font-size: 0.72rem;
        line-height: 1.05;
        color: var(--betwatch-page-text-soft);
    }

    .betwatch-moneyway-options {
        display: grid;
        align-items: center;
        justify-content: center;
        gap: 18px;
        min-height: 66px;
    }

    .betwatch-moneyway-options--1 {
        grid-template-columns: minmax(78px, 92px);
    }

    .betwatch-moneyway-options--2 {
        grid-template-columns: repeat(2, minmax(78px, 92px));
    }

    .betwatch-moneyway-options--3 {
        grid-template-columns: repeat(3, minmax(78px, 92px));
    }

    .betwatch-moneyway-option {
        display: grid;
        align-content: center;
        justify-items: center;
        min-height: 66px;
        gap: 2px;
        text-align: center;
    }

    .betwatch-moneyway-option-label {
        font-size: 0.72rem;
        font-weight: 500;
        line-height: 1;
        color: var(--betwatch-page-text);
    }

    .betwatch-moneyway-option-value {
        font-size: 0.88rem;
        font-weight: 700;
        line-height: 1;
        color: var(--betwatch-page-text);
    }

    .betwatch-moneyway-option-percent {
        font-size: 0.66rem;
        line-height: 1;
        color: var(--betwatch-page-text-soft);
    }

    .betwatch-moneyway-odd-value {
        font-size: 0.82rem;
        font-weight: 700;
    }

    .betwatch-sentinel {
        height: 1px;
    }

    .betwatch-loading-center {
        padding: 1rem 0;
        text-align: center;
    }

    .betwatch-spinner {
        display: inline-block;
        width: 1.25rem;
        height: 1.25rem;
        border: 2px solid rgb(var(--text-muted) / 0.20);
        border-top-color: var(--betwatch-page-text-soft);
        border-radius: 999px;
        animation: betwatchSpin 0.8s linear infinite;
    }

    .betwatch-support-empty-check {
        width: 1rem;
        height: 1rem;
        flex: 0 0 1rem;
        border: 1px solid var(--betwatch-page-border-strong);
        border-radius: 0.18rem;
    }

    @media (max-width: 768px) {

        .chart-grid,
        .chart-grid.chart-count-2,
        .chart-grid.chart-count-3 {
            grid-template-columns: 1fr;
        }

        .chart-item iframe {
            height: 260px;
        }
    }

    .leaguewise-market-summary-option {
        display: flex;
        justify-content: center;
    }

    .leaguewise-market-summary-inner {
        display: grid;
        grid-template-columns: auto auto;
        align-items: center;
        justify-content: center;
        column-gap: 0.45rem;
    }

    .leaguewise-market-summary-label {
        font-size: 0.82rem;
        line-height: 1.15;
        color: var(--betwatch-page-text);
        text-align: left;
        white-space: nowrap;
    }

    .leaguewise-market-summary-values {
        display: grid;
        grid-template-rows: auto auto auto;
        justify-items: center;
        row-gap: 0.08rem;
    }

    .leaguewise-market-summary-money {
        font-size: 0.82rem;
        line-height: 1.1;
        color: var(--betwatch-page-text);
    }

    .leaguewise-market-summary-percent {
        font-size: 0.64rem;
        line-height: 1.1;
        color: var(--betwatch-page-text-soft);
    }

    .leaguewise-market-summary-price {
        font-size: 0.9rem;
        line-height: 1.1;
        color: var(--betwatch-page-text);
    }

    .leaguewise-market-panel-state {
        padding: 1rem 0;
        text-align: center;
        font-size: 0.85rem;
        color: var(--betwatch-page-text-soft);
    }

    .leaguewise-market-panel-image-wrap {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .leaguewise-market-panel-image {
        display: block;
        width: 100%;
        max-width: 100%;
        height: auto;
    }

    .leaguewise-scoreboard {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        align-items: center;
        gap: 1.8rem;
        padding: 1.8rem 0 1.5rem;
        text-align: center;
    }

    .leaguewise-team {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.85rem;
        min-width: 0;
    }

    .leaguewise-team--home {
        justify-self: end;
    }

    .leaguewise-team--away {
        justify-self: start;
    }

    .leaguewise-team-name {
        font-size: clamp(1.3rem, 2vw, 1.9rem);
        line-height: 1.1;
        font-weight: 400;
        color: var(--betwatch-page-text);
        white-space: nowrap;
    }

    .leaguewise-scorebox {
        display: grid;
        justify-items: center;
        align-content: center;
        gap: 0.35rem;
        min-width: 7rem;
    }

    .leaguewise-scoreboard .betwatch-star-icon {
        width: 1.45rem;
        height: 1.45rem;
        flex: 0 0 1.45rem;
        color: var(--betwatch-page-text-muted);
    }

    /* Live state */
    .leaguewise-scoreboard--live .leaguewise-score {
        font-size: clamp(2.4rem, 4vw, 3.35rem);
        line-height: 1;
        font-weight: 500;
        letter-spacing: -0.04em;
        color: var(--betwatch-page-danger);
    }

    .leaguewise-scoreboard--live .leaguewise-minute {
        font-size: 1.05rem;
        line-height: 1;
        font-weight: 600;
        color: var(--betwatch-page-danger);
    }

    /* Upcoming state */
    .leaguewise-scoreboard--upcoming .leaguewise-kickoff {
        font-size: clamp(2.2rem, 3.8vw, 3rem);
        line-height: 1;
        font-weight: 700;
        color: var(--betwatch-page-text);
        letter-spacing: -0.04em;
    }

    .leaguewise-scoreboard--upcoming .leaguewise-kickoff-day {
        font-size: 0.95rem;
        line-height: 1.1;
        font-weight: 600;
        color: var(--betwatch-page-text-muted);
    }

    @media (max-width: 768px) {
        .leaguewise-scoreboard {
            gap: 1rem;
            padding: 1.35rem 0 1.15rem;
        }

        .leaguewise-team-name {
            font-size: 1.05rem;
        }

        .leaguewise-scorebox {
            min-width: 5.5rem;
        }

        .leaguewise-scoreboard .betwatch-star-icon {
            width: 1.2rem;
            height: 1.2rem;
            flex-basis: 1.2rem;
        }

        .leaguewise-scoreboard--live .leaguewise-score {
            font-size: 2rem;
        }

        .leaguewise-scoreboard--upcoming .leaguewise-kickoff {
            font-size: 1.95rem;
        }
    }

</style>
