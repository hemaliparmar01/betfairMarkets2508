import { renderOddsHistory } from '../oddsHistory';
let oddsTooltip = null;
const hideOddsTooltip = () => { oddsTooltip?.remove(); oddsTooltip = null; };
const showOddsTooltip = (event) => {
    const target = event.target.closest('[data-tennis-odds-tooltip]');
    if (!target || target.contains(event.relatedTarget)) return;
    hideOddsTooltip();
    oddsTooltip = document.createElement('div');
    oddsTooltip.setAttribute('role', 'tooltip');
    oddsTooltip.dataset.tennisTooltip = '';
    oddsTooltip.className = 'pointer-events-none fixed z-[10001] whitespace-nowrap rounded-md bg-[var(--surface-heading)] px-2 py-1 text-[11px] font-normal text-white shadow-sm dark:bg-[rgb(var(--surface))] dark:text-[var(--text-on-primary)]';
    oddsTooltip.textContent = target.dataset.tennisOddsTooltip;
    document.body.append(oddsTooltip);
    const rect = target.getBoundingClientRect();
    const width = oddsTooltip.offsetWidth;
    oddsTooltip.style.left = `${Math.max(8, Math.min(window.innerWidth - width - 8, rect.left + rect.width / 2 - width / 2))}px`;
    oddsTooltip.style.top = `${rect.top > oddsTooltip.offsetHeight + 8 ? rect.top - oddsTooltip.offsetHeight - 6 : rect.bottom + 6}px`;
};
document.addEventListener('pointerover', showOddsTooltip);
document.addEventListener('focusin', showOddsTooltip);
for (const type of ['pointerout', 'focusout']) {
    document.addEventListener(type, (event) => {
        const target = event.target.closest('[data-tennis-odds-tooltip]');
        if (target && !target.contains(event.relatedTarget)) hideOddsTooltip();
    });
}
document.addEventListener('scroll', hideOddsTooltip, true);
document.addEventListener('click', hideOddsTooltip);
window.addEventListener('resize', hideOddsTooltip);

const dashboard = document.getElementById('tennisDashboard');
if (dashboard) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const filters = document.getElementById('tennisFilters');
    const dropdown = document.getElementById('marketDropdown');
    const markets = dashboard.dataset.sourceKind === 'markets';
    let selectedFilter = filters?.querySelector('.filter-pill.active')?.dataset.filter ?? 'all';
    let loadedPages = 1;
    let loading = false;
    let refreshPending = false;
    let pendingAction = null;
    let refreshTimer = null;
    let activeModal = null;
    let highlightTimer = null;
    let pointHighlightTimer = null;
    const pointStates = new Map();
    const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
    const number = (value) => Number(value) > 0 ? Number(value).toFixed(2) : '—';
    const parse = (value) => { try { return JSON.parse(value ?? '{}'); } catch { return {}; } };
    const loader = () => dashboard.querySelector('[data-tennis-lazy-loader]');
    const content = () => dashboard.querySelector(markets ? '[data-tennis-market-rows]' : '.fixtures-container');
    const pointValue = (value) => value == null ? null : String(value).toUpperCase().replace(/^AD$/, 'A');
    const pointWinners = (previous, current) => {
        if (['home', 'away'].some((side) => pointValue(previous[side]?.point) === null || pointValue(current[side]?.point) === null)) return [];
        if (pointValue(current.home.point) === '0' && pointValue(current.away.point) === '0') return [];
        const period = `period${current.current_set ?? 1}`;
        const newGame = previous.current_set !== current.current_set || previous.home?.[period] !== current.home?.[period] || previous.away?.[period] !== current.away?.[period];
        if (newGame) {
            return ['home', 'away'].filter((side) => pointValue(current[side].point) !== '0');
        }
        const winners = new Set();
        for (const side of ['home', 'away']) {
            const other = side === 'home' ? 'away' : 'home';
            const before = pointValue(previous[side].point); const after = pointValue(current[side].point);
            if (before !== after && (after === 'A' || Number(after) > Number(before))) winners.add(side);
            if (before === 'A' && after === '40') winners.add(other);
        }
        return [...winners];
    };
    function paintPoints(row, state) {
        row.dataset.matchStatus = state.status;
        row.querySelectorAll('.match-score[data-score-status]').forEach((score) => { score.dataset.scoreStatus = state.status; });
        const points = row.querySelector('[data-tennis-points]');
        if (!points) return;
        const visible = state.status === 'Live' && state.home?.point != null && state.away?.point != null;
        points.classList.toggle('hidden', !visible);
        for (const side of ['home', 'away']) {
            const number = points.querySelector(`[data-tennis-point="${side}"]`);
            number.textContent = state[side]?.point ?? '—';
            number.classList.toggle('highlight-purple', visible && state.highlights[side] > Date.now());
        }
        const stats = parse(row.dataset.tennisStats);
        row.dataset.tennisStats = JSON.stringify({ ...stats, home: state.home, away: state.away, current_set: state.current_set });
        row.dataset.tennisScoreVersion = String(state.version);
    }
    function expirePointHighlights() {
        window.clearTimeout(pointHighlightTimer);
        let nextExpiry = Infinity;
        dashboard.querySelectorAll('[data-tennis-points]').forEach((points) => {
            const row = points.closest('[data-match-id]');
            const state = pointStates.get(row.dataset.matchId);
            if (!state) return;
            for (const side of ['home', 'away']) {
                const remaining = state.highlights[side] - Date.now();
                points.querySelector(`[data-tennis-point="${side}"]`).classList.toggle('highlight-purple', state.status === 'Live' && remaining > 0);
                if (remaining > 0) nextExpiry = Math.min(nextExpiry, remaining);
            }
        });
        pointHighlightTimer = Number.isFinite(nextExpiry) ? window.setTimeout(expirePointHighlights, nextExpiry) : null;
    }
    function rememberPoints(row, score, liveUpdate = false) {
        const previous = pointStates.get(row.dataset.matchId);
        const version = Number(score.version ?? 0);
        if (previous && previous.version > 0 && version <= previous.version) { paintPoints(row, previous); return; }
        const highlights = { ...(previous?.highlights ?? { home: 0, away: 0 }) };
        if (previous && liveUpdate) {
            for (const side of ['home', 'away']) {
                if (pointValue(previous[side]?.point) !== pointValue(score[side]?.point)) highlights[side] = 0;
            }
            const winners = score.status === 'Live' ? pointWinners(previous, score) : [];
            for (const winner of winners) highlights[winner] = Date.now() + 5000;
        }
        const state = { ...score, version, highlights };
        pointStates.set(row.dataset.matchId, state);
        paintPoints(row, state);
    }
    function syncPointRows() {
        const visibleIds = new Set();
        dashboard.querySelectorAll('[data-tennis-stats][data-match-id]').forEach((row) => {
            const data = parse(row.dataset.tennisStats);
            visibleIds.add(row.dataset.matchId);
            rememberPoints(row, { version: Number(row.dataset.tennisScoreVersion ?? 0), status: row.dataset.tennisStatus, current_set: data.current_set, home: data.home ?? {}, away: data.away ?? {} }, true);
        });
        for (const id of pointStates.keys()) { if (!visibleIds.has(id)) pointStates.delete(id); }
        expirePointHighlights();
    }
    const showError = (message) => {
        let notice = dashboard.querySelector('[data-tennis-error]');
        if (!notice) { notice = document.createElement('p'); notice.dataset.tennisError = ''; notice.className = "p-3 text-center text-sm text-[var(--text-red-600)]"; dashboard.prepend(notice); }
        notice.textContent = message;
    };
    function scheduleRefresh() {
        if (refreshTimer !== null) return;
        refreshTimer = window.setTimeout(() => { refreshTimer = null; load(); }, 700);
    }
    async function load({ append = false, reset = false, prediction = null } = {}) {
        if (reset && !markets) {
            content()?.classList.add('fixtures-loading');
            content()?.setAttribute('aria-busy', 'true');
        }
        if (loading) { if (reset || prediction) pendingAction = { reset, prediction }; else if (!append) refreshPending = true; return; }
        if (document.visibilityState !== 'visible' && !reset && !prediction) { refreshPending = true; return; }
        if (append && loader()?.dataset.hasMore !== 'true') return;
        loading = true;
        const page = append ? Number(loader().dataset.nextPage) : 1;
        const perPage = append || reset ? 20 : loadedPages * 20;
        const params = { type: selectedFilter, market_name: dropdown?.value ?? '', page, per_page: perPage, append };
        if (prediction) params.prediction = prediction;
        const text = loader()?.querySelector('[data-lazy-loader-text]');
        if (text) text.textContent = (globalThis.bfTranslations?.["Loading matches…"] ?? "Loading matches…");
        try {
            const url = new URL(dashboard.dataset.sourceUrl, window.location.origin);
            const options = { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' };
            if (dashboard.dataset.sourceMethod === 'POST') { options.method = 'POST'; options.headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(params); }
            else Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value === true ? '1' : value === false ? '0' : String(value)));
            const response = await fetch(url, options);
            if (response.status === 401) { window.location.assign(dashboard.dataset.loginUrl); return; }
            if (!response.ok) throw new Error((globalThis.bfTranslations?.["Unable to load matches. Please try again."] ?? "Unable to load matches. Please try again."));
            const result = await response.json();
            hideOddsTooltip();
            const target = content();
            const scrollLeft = target.closest('.overflow-x-auto')?.scrollLeft ?? target.scrollLeft;
            if (append) {
                target.querySelector('[data-tennis-empty]')?.remove();
                const fragment = document.createElement('template');
                fragment.innerHTML = markets ? `<table><tbody>${result.html}</tbody></table>` : result.html;
                const nodes = markets ? [...fragment.content.querySelector('tbody').children] : [...fragment.content.children];
                if (!markets && nodes[0]?.dataset.leagueHeader === [...target.querySelectorAll('[data-league-header]')].at(-1)?.dataset.leagueHeader) nodes.shift();
                nodes.forEach((node) => target.append(node));
                loadedPages++;
            } else {
                if (markets) target.innerHTML = result.html;
                else {
                    const fragment = document.createElement('template'); fragment.innerHTML = result.html;
                    target.replaceChildren(...fragment.content.querySelector('.fixtures-container').childNodes);
                }
                if (reset) loadedPages = 1;
            }
            const overflow = target.closest('.overflow-x-auto') ?? target; overflow.scrollLeft = scrollLeft;
            loader().dataset.hasMore = result.has_more ? 'true' : 'false';
            loader().dataset.nextPage = result.has_more ? String(loadedPages + 1) : '';
            loader().classList.toggle('hidden', !result.has_more);
            dashboard.querySelector('[data-tennis-error]')?.remove();
            syncPointRows();
            if (activeModal) renderModal();
            expireHighlights();
        } catch (error) { showError(error.message); }
        finally {
            loading = false;
            content()?.classList.remove('fixtures-loading');
            content()?.removeAttribute('aria-busy');
            const text = loader()?.querySelector('[data-lazy-loader-text]');
            if (text) text.textContent = (globalThis.bfTranslations?.["↓ Scroll for more matches ↓"] ?? "↓ Scroll for more matches ↓");
            if (pendingAction) { const action = pendingAction; pendingAction = null; load(action); } else if (refreshPending && document.visibilityState === 'visible') { refreshPending = false; scheduleRefresh(); }
        }
    }
    function expireHighlights() {
        window.clearTimeout(highlightTimer);
        let nextExpiry = Infinity;
        dashboard.querySelectorAll('[data-incident-highlight-until]').forEach((row) => {
            const remaining = Number(row.dataset.incidentHighlightUntil) * 1000 - Date.now();
            if (remaining <= 0) { row.classList.remove('!bg-[var(--surface-goal)]', 'dark:!bg-[var(--status-accent-surface)]'); row.querySelector('[data-incident-indicator]')?.remove(); delete row.dataset.incidentHighlightUntil; }
            else nextExpiry = Math.min(nextExpiry, remaining);
        });
        if (Number.isFinite(nextExpiry)) highlightTimer = window.setTimeout(expireHighlights, nextExpiry + 50);
    }
    function closeModal() {
        const modal = dashboard.querySelector('#tennisModal');
        const wasOpen = activeModal !== null || (modal && !modal.classList.contains('hidden'));
        modal?.classList.add('hidden');
        modal?.classList.remove('flex');
        activeModal = null;
        if (wasOpen && !document.querySelector('dialog[open], #subscriptionModal, [aria-modal="true"]:not(.hidden), .panel-expanded')) {
            document.body.classList.remove('!overflow-hidden');
            document.documentElement.classList.remove('!overflow-hidden');
        }
    }
    function renderModal() {
        const rows = [...dashboard.querySelectorAll('[data-match-id]')];
        const row = rows.find((candidate) => candidate.dataset.matchId === activeModal.matchId && (!activeModal.marketId || candidate.dataset.marketId === activeModal.marketId));
        if (!row) { closeModal(); return; }
        const modal = dashboard.querySelector('#tennisModal');
        const panel = modal.querySelector('[data-tennis-modal-panel]');
        const scrollTop = (panel.querySelector('[data-tennis-statistics-scroll]') ?? panel).scrollTop;
        const scoreStatus = escape(row.dataset.matchStatus ?? row.dataset.tennisStatus ?? '');
        let title = ''; let body = '';
        if (activeModal.action === 'stats') {
            const data = parse(row.dataset.tennisStats); title = (globalThis.bfTranslations?.["Live Statistics"] ?? "Live Statistics");
            const statistics = (Array.isArray(data.statistics) ? data.statistics : [data.statistics]).filter((period) => period && Array.isArray(period.groups));
            const periods = statistics.length ? statistics : [{ period: 'ALL', groups: [] }];
            const selectedPeriod = periods.find((period, index) => String(period.period ?? index) === activeModal.period) ?? periods[0];
            activeModal.period = String(selectedPeriod.period ?? periods.indexOf(selectedPeriod));
            body = `<header class="relative shrink-0 bg-[var(--surface-heading)] px-4 py-5 text-center text-white sm:px-8">
                <button type="button" data-action="close-modal" aria-label="${(globalThis.bfTranslations?.["Close"] ?? "Close")}" class="absolute right-3 top-3 flex size-[30px] items-center justify-center rounded-full bg-[rgb(var(--surface))]/20 text-lg transition hover:bg-[rgb(var(--surface))]/40 focus-visible:outline-2 focus-visible:outline-white">×</button>
                <h2 id="tennisModalTitle" class="px-7 text-[10px] uppercase tracking-wider text-[var(--text-brand-15-3)] sm:text-xs">${escape([data.tournament, data.surface, title].filter(Boolean).join(' · '))}</h2>
                <div class="mt-2 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 text-sm font-bold sm:gap-4 sm:text-lg">
                    <span class="min-w-0 break-words text-right">${escape(data.team1)}${data.serving === 'home' ? ` <span class="text-xs text-[var(--text-amber-400)]" aria-label="${(globalThis.bfTranslations?.["Serving"] ?? "Serving")}">●</span>` : ''}</span>
                    <span class="match-score match-score-on-dark whitespace-nowrap rounded-md bg-[rgb(var(--surface))]/10 px-3 py-1 text-xl font-extrabold tabular-nums sm:text-[26px]" data-score-status="${scoreStatus}" aria-label="${(globalThis.bfTranslations?.["Sets won"] ?? "Sets won")}">${escape(data.home?.current ?? data.home?.display ?? '—')} - ${escape(data.away?.current ?? data.away?.display ?? '—')}</span>
                    <span class="min-w-0 break-words text-left">${data.serving === 'away' ? `<span class="text-xs text-[var(--text-amber-400)]" aria-label="${(globalThis.bfTranslations?.["Serving"] ?? "Serving")}">●</span> ` : ''}${escape(data.team2)}</span>
                </div>
                <div class="mt-2 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs font-semibold text-[var(--text-brand-15-3)]">
                    <span class="${scoreStatus === 'Live' ? 'text-[var(--text-danger-0)]' : ''}">${scoreStatus === 'Live' ? '● ' : ''}${escape(globalThis.bfTranslations?.[data.minutes || row.dataset.matchStatus || row.dataset.tennisStatus] ?? (data.minutes || row.dataset.matchStatus || row.dataset.tennisStatus || '—'))}</span>
                    ${['home', 'away'].includes(data.serving) ? `<span>Serving: ${escape(data.serving === 'home' ? data.team1 : data.team2)}</span>` : ''}
                    ${data.home?.point != null || data.away?.point != null ? `<span>${(globalThis.bfTranslations?.["Current point:"] ?? "Current point:")} <b class="text-white">${escape(data.home?.point ?? '—')} - ${escape(data.away?.point ?? '—')}</b></span>` : ''}
                </div>
                <div class="mt-3 flex flex-wrap justify-center gap-2 text-[11px] font-semibold sm:text-xs">${[1, 2, 3, 4, 5].filter((set) => ['home', 'away'].some((side) => data[side]?.[`period${set}`] != null && Number(data[side][`period${set}`]) >= 0)).map((set) => `<span class="rounded-md border px-2 py-1 ${Number(data.current_set) === set ? 'border-[var(--border-danger-0-2)] bg-[rgb(var(--surface))]/20 text-white' : 'border-transparent bg-[rgb(var(--surface))]/10 text-[var(--text-brand-15-3)]'}">${escape(data.periods?.[`period${set}`] ?? `Set ${set}`)}: ${escape(data.home?.[`period${set}`] ?? '—')}-${escape(data.away?.[`period${set}`] ?? '—')}${['home', 'away'].some((side) => data[side]?.[`period${set}TieBreak`] != null) ? ` (TB ${escape(data.home?.[`period${set}TieBreak`] ?? '—')}-${escape(data.away?.[`period${set}TieBreak`] ?? '—')})` : ''}</span>`).join('')}</div>
            </header>
            <div role="tablist" aria-label="${(globalThis.bfTranslations?.["Statistics periods"] ?? "Statistics periods")}" class="flex shrink-0 overflow-x-auto border-b border-[var(--border-neutral-10-7)] bg-[var(--surface-neutral-5-11)] [scrollbar-width:thin] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))]">${periods.map((period, index) => {
                const key = String(period.period ?? index);
                const set = key.match(/^(?:period|set)?([1-5])(?:st|nd|rd|th)?$/i)?.[1];
                const label = key === 'ALL' ? (globalThis.bfTranslations?.["Full Match"] ?? "Full Match") : data.periods?.[key] ?? (set ? data.periods?.[`period${set}`] ?? `Set ${set}` : key);
                return `<button type="button" role="tab" id="tennis-stat-tab-${index}" aria-controls="tennis-stat-period" aria-selected="${key === activeModal.period}" data-action="stats-period" data-period="${escape(key)}" class="min-w-20 flex-1 whitespace-nowrap border-b-[3px] px-3 py-3 text-[11px] font-bold uppercase tracking-wide transition hover:bg-[var(--surface-hover)] hover:text-[rgb(var(--text-main))] focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--border-brand-40)] dark:hover:bg-[rgb(var(--surface))] dark:hover:text-white ${key === activeModal.period ? 'border-[var(--border-heading)] bg-[rgb(var(--surface))] text-[rgb(var(--text-main))] dark:border-[var(--border-sky-300)] dark:bg-[rgb(var(--surface))] dark:text-white' : 'border-transparent text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]'}">${escape(label)}</button>`;
            }).join('')}</div>
            <div id="tennis-stat-period" role="tabpanel" aria-labelledby="tennis-stat-tab-${periods.indexOf(selectedPeriod)}" data-tennis-statistics-scroll class="min-h-0 overflow-x-hidden overflow-y-auto overscroll-contain bg-[var(--surface-neutral-0-30)] p-3 sm:p-5 dark:bg-[rgb(var(--bg))]">
                ${(selectedPeriod.groups ?? []).map((group) => `<section class="mb-4 rounded-lg border border-[var(--border-neutral-5-2)] bg-[rgb(var(--surface))] p-3 shadow-sm last:mb-0 sm:p-4 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]">
                    <h3 class="mb-3 border-b border-[var(--border-neutral-5-12)] pb-2 text-[11px] font-bold uppercase tracking-wide text-[rgb(var(--text-muted))] dark:border-[rgb(var(--border))] dark:text-[rgb(var(--text-soft))]">${escape(globalThis.bfTranslations?.[group.groupName] ?? group.groupName)}</h3>
                    ${(group.statisticsItems ?? []).map((item) => {
                        const values = ['home', 'away'].map((side) => {
                            const displayed = [item[side], item[`${side}Value`]].find((value) => (typeof value === 'string' && value.trim() !== '' && !/^(?:null|undefined|NaN|Infinity|N\/A|[-—–])$/i.test(value.trim())) || (typeof value === 'number' && Number.isFinite(value)));
                            const text = String(displayed ?? '—');
                            const percentage = text.match(/(-?\d+(?:[.,]\d+)?)\s*%/);
                            const raw = item[`${side}Value`];
                            const fraction = text.match(/^(-?\d+(?:[.,]\d+)?)\s*\/\s*(\d+(?:[.,]\d+)?)/);
                            const numeric = percentage ? Number(percentage[1].replace(',', '.')) : fraction && Number(fraction[2].replace(',', '.')) > 0 ? Number(fraction[1].replace(',', '.')) / Number(fraction[2].replace(',', '.')) : typeof raw === 'number' && Number.isFinite(raw) ? raw : /^-?\d+(?:[.,]\d+)?$/.test(String(raw)) ? Number(String(raw).replace(',', '.')) : /^-?\d+(?:[.,]\d+)?(?:\s|\(|$)/.test(text) ? Number.parseFloat(text.replace(',', '.')) : null;
                            return { text, numeric, percentage: percentage !== null };
                        });
                        const comparable = values.every((value) => value.numeric !== null && Number.isFinite(value.numeric));
                        const bar = /^(?:total points won|points won percentage)$/i.test(item.name ?? '') && values.every((value) => value.percentage && value.numeric >= 0 && value.numeric <= 100) && Math.abs(values[0].numeric + values[1].numeric - 100) < 1;
                        return `<div class="py-1.5"><div class="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)_minmax(0,1fr)] items-center gap-2 text-xs sm:text-[13px]">
                            ${values.map((value, index) => `${index === 1 ? `<span class="min-w-0 break-words text-center font-medium text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">${escape(globalThis.bfTranslations?.[item.name] ?? item.name)}</span>` : ''}<span class="min-w-0 break-words tabular-nums ${index === 0 ? "justify-self-start text-left" : "justify-self-end text-right"} ${comparable && value.numeric > values[1 - index].numeric ? 'font-extrabold text-[rgb(var(--text-main))] dark:text-white' : comparable && value.numeric < values[1 - index].numeric ? 'font-medium text-[var(--text-muted-soft)] dark:text-[var(--text-slate-400)]' : 'font-semibold text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]'}">${escape(value.text)}</span>`).join('')}
                        </div>${bar ? `<div class="mt-2 flex h-2 overflow-hidden rounded-sm" aria-hidden="true"><span class="bg-[var(--surface-success)]" style="width:${values[0].numeric / (values[0].numeric + values[1].numeric) * 100}%"></span><span class="bg-[var(--surface-live)]" style="width:${values[1].numeric / (values[0].numeric + values[1].numeric) * 100}%"></span></div>` : ''}</div>`;
                    }).join('') || `<p class="py-2 text-center text-xs text-[var(--text-muted-soft)]">${(globalThis.bfTranslations?.["No statistics available for this section."] ?? "No statistics available for this section.")}</p>`}
                </section>`).join('') || `<p class="rounded-lg border border-[var(--border-neutral-5-2)] bg-[rgb(var(--surface))] p-5 text-center text-sm text-[rgb(var(--text-muted))] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))] dark:text-[rgb(var(--text-soft))]">${(globalThis.bfTranslations?.["No statistics available for this period yet."] ?? "No statistics available for this period yet.")}</p>`}
            </div>`;
        } else if (activeModal.action === 'chart') {
            const data = parse(row.dataset.tennisChart); title = `${data.title ?? (globalThis.bfTranslations?.["Match Winner"] ?? "Match Winner")} · Odds Movement`;
            if (data.locked) body = `<p>${(globalThis.bfTranslations?.["Login with an active subscription to view odds."] ?? "Login with an active subscription to view odds.")}</p>`;
            else body = (data.runners ?? []).map((runner) => {
                const initial = Number(runner.initialBackOdds ?? 0); const current = Number(runner.currentBackOdds ?? 0);
                const history = (runner.backOddsHistory ?? []).filter((point) => initial > 1 && Number(point.timestamp) >= Number(runner.initialBackOddsTimestamp) && Number(point.odds) > 1);
                const values = history.map((point) => Number(point.odds));
                const minimum = Math.min(...values); const maximum = Math.max(...values); const range = maximum - minimum || 1;
                const points = values.map((value, index) => `${10 + index * 380 / Math.max(1, values.length - 1)},${90 - (value - minimum) / range * 70}`).join(' ');
                return `<section class="mb-4 rounded-xl border border-[var(--border-control)] p-4"><h3 class="font-bold">${escape(runner.runner)}</h3><p class="mt-1 text-sm">${(globalThis.bfTranslations?.["Initial"] ?? "Initial")} ${number(initial)} → Current ${number(current)}</p>${values.length ? `<svg viewBox="0 0 400 110" class="mt-3 h-32 w-full" role="img" aria-label="${(globalThis.bfTranslations?.["Back odds history"] ?? "Back odds history")}"><polyline fill="none" stroke="rgb(var(--primary))" stroke-width="3" points="${points}" /></svg>` : `<p class="mt-3 text-sm">${(globalThis.bfTranslations?.["No odds history available."] ?? "No odds history available.")}</p>`}</section>`;
            }).join('') || `<p>${(globalThis.bfTranslations?.["Market data is not available yet."] ?? "Market data is not available yet.")}</p>`;
        } else if (activeModal.action === 'prediction') {
            const data = parse(row.dataset.tennisPrediction); title = (globalThis.bfTranslations?.["Match Predictions"] ?? "Match Predictions");
            const total = Object.values(data.counts ?? {}).reduce((sum, count) => sum + Number(count), 0);
            body = `<p class="mb-4 text-sm">${data.locked ? (globalThis.bfTranslations?.["Voting closes when the match starts."] ?? "Voting closes when the match starts.") : data.vote ? (globalThis.bfTranslations?.["Your prediction has been recorded."] ?? "Your prediction has been recorded.") : (globalThis.bfTranslations?.["Who will win this match?"] ?? "Who will win this match?")}</p><div class="grid gap-3">${Object.entries(data.choices ?? {}).map(([value, label]) => `<button type="button" data-action="vote" data-value="${escape(value)}" ${data.locked || data.vote ? 'disabled' : ''} class="rounded-xl border border-[var(--border-brand-5-5)] p-3 text-left disabled:cursor-default ${data.vote === value ? 'bg-[var(--surface-brand-0-2)] text-[rgb(var(--primary))]' : ''}"><b>${escape(label)}</b><span class="float-right">${Number(data.counts?.[value] ?? 0)} votes · ${total ? Math.round(Number(data.counts?.[value] ?? 0) / total * 100) : 0}%</span></button>`).join('')}</div>`;
        } else {
            const data = parse(row.dataset.tennisMarket); title = data.title ?? (globalThis.bfTranslations?.["Market Details"] ?? "Market Details");
            body = `<p class="mb-2 font-bold">${escape(data.team1)} vs ${escape(data.team2)}</p><p class="mb-4 text-sm"><span class="match-score" data-score-status="${escape(data.status)}">${escape(data.score)}</span> · ${escape(globalThis.bfTranslations?.[data.status] ?? data.status)} · ${escape(globalThis.bfTranslations?.[data.minutes] ?? data.minutes)}</p>`;
            if (data.locked) body += `<p>${(globalThis.bfTranslations?.["Login with an active subscription to view odds."] ?? "Login with an active subscription to view odds.")}</p>`;
            else body += `<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr>${['Selection', 'Initial', (globalThis.bfTranslations?.["Back"] ?? "Back"), 'Size', (globalThis.bfTranslations?.["Lay"] ?? "Lay"), 'Size'].map((label) => `<th class="px-2 py-2">${label}</th>`).join('')}</tr></thead><tbody>${(data.runners ?? []).map((runner) => {
                const initial = Number(runner.initialBackOdds ?? 0); const current = Number(runner.currentBackOdds ?? runner.backOdds ?? 0);
                const arrow = initial > 0 && current > 0 ? current > initial ? ' ↑' : current < initial ? ' ↓' : '' : '';
                return `<tr class="border-t border-[var(--border-neutral-5-2)]"><td class="px-2 py-3">${escape(runner.runner)}${renderOddsHistory(runner)}</td>${[initial, current, runner.backSize, runner.layOdds, runner.laySize].map((value, index) => `<td ${index === 1 && current > 0 ? `tabindex="0" data-tennis-odds-tooltip="${number(initial)} ${current > initial ? '&lt;&lt;' : '&gt;&gt;'} ${number(current)}"` : ''} class="whitespace-nowrap px-2 py-3 font-semibold">${number(value)}${index === 1 ? arrow : ''}</td>`).join('')}</tr>`;
            }).join('')}</tbody></table></div><a class="mt-4 inline-block rounded-xl bg-[var(--surface-heading)] px-4 py-2 text-sm font-bold text-white" href="${escape(row.dataset.tennisMatchUrl)}">${(globalThis.bfTranslations?.["Match markets &amp; charts →"] ?? "Match markets &amp; charts →")}</a>`;
        }
        hideOddsTooltip();
        panel.dataset.statistics = String(activeModal.action === 'stats');
        panel.innerHTML = activeModal.action === 'stats' ? body : `<div class="mb-5 flex items-center justify-between gap-3"><h2 id="tennisModalTitle" class="text-lg font-extrabold">${escape(title)}</h2><button type="button" data-action="close-modal" aria-label="${(globalThis.bfTranslations?.["Close"] ?? "Close")}" class="px-2 text-2xl">×</button></div>${body}`;
        (panel.querySelector('[data-tennis-statistics-scroll]') ?? panel).scrollTop = scrollTop;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('!overflow-hidden');
        document.documentElement.classList.add('!overflow-hidden');
    }
    filters?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-filter]'); if (!button) return;
        selectedFilter = button.dataset.filter;
        filters.querySelectorAll('[data-filter]').forEach((item) => item.classList.toggle('active', item === button));
        closeModal(); load({ reset: true });
    });
    dropdown?.addEventListener('change', () => { closeModal(); load({ reset: true }); });
    dashboard.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (event.target.id === 'tennisModal') { closeModal(); return; }
        if (!button) {
            const row = event.target.closest('[data-tennis-match-url]');
            if (row && !event.target.closest('a, button, input, select, textarea, [role="dialog"]')) {
                window.location.assign(row.dataset.tennisMatchUrl);
            }
            return;
        }
        if (button.dataset.requiresLogin === 'true') { window.location.assign(button.dataset.loginUrl); return; }
        const action = button.dataset.action;
        if (action === 'close-modal') { closeModal(); return; }
        if (action === 'stats-period' && activeModal?.action === 'stats') {
            activeModal.period = button.dataset.period;
            dashboard.querySelector('[data-tennis-statistics-scroll]').scrollTop = 0;
            renderModal();
            dashboard.querySelector(`[data-action="stats-period"][aria-selected="true"]`)?.focus({ preventScroll: true });
            return;
        }
        const row = button.closest('[data-match-id]');
        if (action === 'open-match') { window.location.assign(row.dataset.tennisMatchUrl); return; }
        if (['stats', 'prediction', 'market', 'chart'].includes(action)) { activeModal = { action, matchId: row.dataset.matchId, marketId: row.dataset.marketId ?? null }; renderModal(); return; }
        if (action === 'vote' && activeModal) { button.disabled = true; await load({ prediction: { match_id: activeModal.matchId, poll: '1x2', value: button.dataset.value } }); return; }
        if (action !== 'favorite' || button.disabled) return;
        button.disabled = true;
        try {
            const response = await fetch(dashboard.dataset.favoriteUrl, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ sport: 'tennis', event_key: button.dataset.eventKey, event_id: button.dataset.eventId, team1: button.dataset.team1, team2: button.dataset.team2, start_timestamp: button.dataset.startTimestamp }) });
            if (response.status === 401) { window.location.assign(button.dataset.loginUrl); return; }
            if (!response.ok) throw new Error((globalThis.bfTranslations?.["Unable to save favorite. Please try again."] ?? "Unable to save favorite. Please try again."));
            await load();
        } catch (error) { showError(error.message); }
        finally { button.disabled = false; }
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeModal(); });
    new IntersectionObserver((entries) => { if (entries.some((entry) => entry.isIntersecting)) load({ append: true }); }, { rootMargin: '100px' }).observe(loader());
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') scheduleRefresh(); });
    const receiveScoreUpdate = (event) => {
        const rows = new Map([...dashboard.querySelectorAll('[data-tennis-points]')].map((points) => { const row = points.closest('[data-match-id]'); return [row.dataset.matchId, row]; }));
        for (const score of event.scores ?? []) {
            const row = rows.get(String(score.id));
            if (row) rememberPoints(row, score, true);
        }
        expirePointHighlights();
        if (activeModal?.action === 'stats') renderModal();
        scheduleRefresh();
    };
    const subscribe = () => window.Echo?.channel('sports.tennis.live').listen('.score.updated', receiveScoreUpdate);
    if (window.Echo) subscribe(); else window.addEventListener('load', subscribe, { once: true });
    expireHighlights();
    syncPointRows();
}
