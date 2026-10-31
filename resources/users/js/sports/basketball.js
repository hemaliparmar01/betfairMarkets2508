import { renderOddsHistory } from '../oddsHistory';
let oddsTooltip = null;
const hideOddsTooltip = () => { oddsTooltip?.remove(); oddsTooltip = null; };
const showOddsTooltip = (event) => {
    const target = event.target.closest('[data-basketball-odds-tooltip]');
    if (!target || target.contains(event.relatedTarget)) return;
    hideOddsTooltip();
    oddsTooltip = document.createElement('div');
    oddsTooltip.setAttribute('role', 'tooltip');
    oddsTooltip.dataset.basketballTooltip = '';
    oddsTooltip.className = 'pointer-events-none fixed z-[10001] whitespace-nowrap rounded-md bg-[var(--surface-heading)] px-2 py-1 text-[11px] font-normal text-white shadow-sm dark:bg-[rgb(var(--surface))] dark:text-[var(--text-on-primary)]';
    oddsTooltip.textContent = target.dataset.basketballOddsTooltip;
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
        const target = event.target.closest('[data-basketball-odds-tooltip]');
        if (target && !target.contains(event.relatedTarget)) hideOddsTooltip();
    });
}
document.addEventListener('scroll', hideOddsTooltip, true);
document.addEventListener('click', hideOddsTooltip);
window.addEventListener('resize', hideOddsTooltip);

const dashboard = document.getElementById('basketballDashboard');
if (dashboard) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const filters = document.getElementById('basketballFilters');
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
    const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
    const number = (value) => Number(value) > 0 ? Number(value).toFixed(2) : '—';
    const parse = (value) => { try { return JSON.parse(value ?? '{}'); } catch { return {}; } };
    const loader = () => dashboard.querySelector('[data-basketball-lazy-loader]');
    const content = () => dashboard.querySelector(markets ? '[data-basketball-market-rows]' : '.fixtures-container');
    const showError = (message) => {
        let notice = dashboard.querySelector('[data-basketball-error]');
        if (!notice) { notice = document.createElement('p'); notice.dataset.basketballError = ''; notice.className = "p-3 text-center text-sm text-[var(--text-red-600)]"; dashboard.prepend(notice); }
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
                target.querySelector('[data-basketball-empty]')?.remove();
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
            dashboard.querySelector('[data-basketball-error]')?.remove();
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
        const modal = dashboard.querySelector('#basketballModal');
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
        const modal = dashboard.querySelector('#basketballModal');
        const panel = modal.querySelector('[data-basketball-modal-panel]');
        const scrollTop = (panel.querySelector('[data-basketball-statistics-scroll]') ?? panel).scrollTop;
        const scoreStatus = escape(row.dataset.matchStatus ?? '');
        let title = ''; let body = '';
        if (activeModal.action === 'stats') {
            const data = parse(row.dataset.basketballStats); title = (globalThis.bfTranslations?.["Live Statistics"] ?? "Live Statistics");
            const statistics = (Array.isArray(data.statistics) ? data.statistics : [data.statistics]).filter((period) => period && Array.isArray(period.groups)).map((period) => {
                const name = String(period.period ?? '').toUpperCase().replace(/[ _-]/g, '');
                const quarter = name.match(/^(?:Q|PERIOD)?([1-4])(?:ST|ND|RD|TH)?(?:QUARTER)?$/)?.[1];
                const overtime = name.match(/^(?:OT|OVERTIME)(\d*)$/) ?? name.match(/^(\d+)(?:ST|ND|RD|TH)?OT$/);
                return { ...period, key: quarter ? `Q${quarter}` : overtime ? `OT${overtime[1]}` : name };
            });
            const phase = String(data.phase ?? data.minutes ?? '');
            const currentQuarter = Number(phase.match(/^([1-4])(?:st|nd|rd|th)?\s*quarter/i)?.[1] ?? String(data.minutes ?? '').match(/^Q([1-4])\b/i)?.[1] ?? 0);
            const inOvertime = scoreStatus === 'Live' && (/overtime|\bOT(?:\d+)?\b/i.test(phase) || /^OT\b/i.test(data.minutes ?? ''));
            const playedQuarters = [1, 2, 3, 4].filter((quarter) => ['home', 'away'].some((side) => Number(data[side]?.[`period${quarter}`]) > 0));
            const statisticsQuarters = statistics.filter((period) => /^Q[1-4]$/.test(period.key) && period.groups.some((group) => (group.statisticsItems ?? []).some((item) => [item.home, item.away, item.homeValue, item.awayValue].some((value) => value != null && String(value).trim() !== '')))).map((period) => Number(period.key.slice(1)));
            const startedQuarter = scoreStatus === 'Prematch' ? 0 : currentQuarter || Math.max(inOvertime ? 4 : /halftime/i.test(phase) ? (/half/i.test(data.periods?.period1 ?? '') ? 1 : 2) : 0, ...playedQuarters, ...statisticsQuarters, 0);
            const periods = [{ key: 'ALL', label: (globalThis.bfTranslations?.["Full Match"] ?? "Full Match"), groups: statistics.find((period) => period.key === 'ALL')?.groups ?? [] }];
            for (let quarter = 1; quarter <= startedQuarter; quarter++) {
                periods.push({ key: `Q${quarter}`, label: data.periods?.[`period${quarter}`] ?? `${quarter}${['st', 'nd', 'rd', 'th'][quarter - 1]} Quarter`, scoreKey: `period${quarter}`, groups: statistics.find((period) => period.key === `Q${quarter}`)?.groups ?? [] });
            }
            const overtimeKeys = new Set([...Object.keys(data.home ?? {}), ...Object.keys(data.away ?? {}), ...statistics.filter((period) => /^OT\d*$/.test(period.key)).map((period) => period.key), ...(inOvertime ? ['overtime'] : [])]);
            const overtimePeriods = new Map();
            for (const scoreKey of overtimeKeys) {
                const match = scoreKey.match(/^(?:overtime|OT)(\d*)$/i) ?? scoreKey.match(/^(\d+)(?:st|nd|rd|th)?OT$/i);
                if (!match || scoreStatus === 'Prematch' || (currentQuarter > 0 && currentQuarter < 4)) continue;
                const key = `OT${match[1]}`;
                const period = statistics.find((period) => period.key === key);
                const hasScore = ['home', 'away'].some((side) => Number(data[side]?.[scoreKey]) > 0);
                const hasStatistics = period?.groups.some((group) => (group.statisticsItems ?? []).some((item) => Number(item.homeValue ?? item.home) > 0 || Number(item.awayValue ?? item.away) > 0));
                const currentOvertime = Number(phase.match(/(?:overtime|OT)\s*(\d+)/i)?.[1] ?? 1);
                if (!hasScore && !hasStatistics && !(inOvertime && (!match[1] || Number(match[1]) <= currentOvertime))) continue;
                overtimePeriods.set(key, { key, label: match[1] ? `OT ${match[1]}` : data.periods?.overtime ?? 'OT', scoreKey: ['home', 'away'].some((side) => data[side]?.[scoreKey] != null) ? scoreKey : overtimePeriods.get(key)?.scoreKey, groups: period?.groups ?? [] });
            }
            if ([...overtimePeriods.keys()].some((key) => /^OT\d+$/.test(key))) overtimePeriods.delete('OT');
            periods.push(...[...overtimePeriods.values()].sort((left, right) => Number(left.key.slice(2)) - Number(right.key.slice(2))));
            const selectedPeriod = periods.find((period) => period.key === activeModal.period) ?? periods[0];
            activeModal.period = selectedPeriod.key;
            body = `<header class="relative shrink-0 bg-[var(--surface-heading)] px-4 py-5 text-center text-white sm:px-8">
                <button type="button" data-action="close-modal" aria-label="${(globalThis.bfTranslations?.["Close"] ?? "Close")}" class="absolute right-3 top-3 flex size-[30px] items-center justify-center rounded-full bg-[rgb(var(--surface))]/20 text-lg transition hover:bg-[rgb(var(--surface))]/40 focus-visible:outline-2 focus-visible:outline-white">×</button>
                <h2 id="basketballModalTitle" class="px-7 text-[10px] uppercase tracking-wider text-[var(--text-brand-15-3)] sm:text-xs">${escape(data.tournament || 'Basketball')} · ${escape(title)}</h2>
                <div class="mt-2 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 text-sm font-bold sm:gap-4 sm:text-lg"><span class="min-w-0 break-words text-right">${escape(data.team1)}</span><span class="whitespace-nowrap rounded-md bg-[rgb(var(--surface))]/10 px-3 py-1 text-[22px] font-extrabold tabular-nums text-[var(--text-danger-0)] sm:text-[28px]" aria-label="${(globalThis.bfTranslations?.["Total score"] ?? "Total score")}">${escape(data.home?.current ?? data.home?.display ?? '—')} - ${escape(data.away?.current ?? data.away?.display ?? '—')}</span><span class="min-w-0 break-words text-left">${escape(data.team2)}</span></div>
                <p class="mt-2 text-xs font-bold text-[var(--text-danger-0)]">● ${escape(scoreStatus === 'Live' ? data.minutes || data.phase || scoreStatus : scoreStatus || '—')}</p>
                <div class="mt-3 flex flex-wrap justify-center gap-2 text-[11px] font-bold sm:text-[13px]">${periods.slice(1).map((period) => `<span class="rounded-md border px-2 py-1 ${period.key === `Q${currentQuarter}` || (inOvertime && period.key === periods.at(-1).key) ? 'border-[var(--border-danger-0-2)] bg-[rgb(var(--surface))]/20 text-white' : 'border-transparent bg-[rgb(var(--surface))]/10 text-[var(--text-brand-15-3)]'}">${escape(data.periods?.[period.scoreKey] ?? (period.key.startsWith('Q') ? period.key : period.label))}: ${escape(data.home?.[period.scoreKey] ?? '—')}-${escape(data.away?.[period.scoreKey] ?? '—')}</span>`).join('')}</div>
            </header>
            <div role="tablist" aria-label="${(globalThis.bfTranslations?.["Statistics periods"] ?? "Statistics periods")}" class="flex shrink-0 overflow-x-auto border-b border-[var(--border-neutral-10-7)] bg-[var(--surface-neutral-5-11)] [scrollbar-width:thin] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--bg))]">${periods.map((period, index) => `<button type="button" role="tab" id="basketball-stat-tab-${index}" aria-controls="basketball-stat-period" aria-selected="${period.key === activeModal.period}" data-action="stats-period" data-period="${escape(period.key)}" class="min-w-20 flex-1 whitespace-nowrap border-b-[3px] px-3 py-3 text-[11px] font-bold uppercase tracking-wide transition hover:bg-[var(--surface-hover)] hover:text-[rgb(var(--text-main))] focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--border-brand-40)] dark:hover:bg-[rgb(var(--surface))] dark:hover:text-white ${period.key === activeModal.period ? 'border-[var(--border-heading)] bg-[rgb(var(--surface))] text-[rgb(var(--text-main))] dark:border-[var(--border-sky-300)] dark:bg-[rgb(var(--surface))] dark:text-white' : 'border-transparent text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]'}">${escape(globalThis.bfTranslations?.[period.label] ?? period.label)}</button>`).join('')}</div>
            <div id="basketball-stat-period" role="tabpanel" aria-labelledby="basketball-stat-tab-${periods.indexOf(selectedPeriod)}" data-basketball-statistics-scroll class="min-h-0 overflow-x-hidden overflow-y-auto overscroll-contain bg-[var(--surface-neutral-0-30)] p-3 sm:p-5 dark:bg-[rgb(var(--bg))]">
                ${selectedPeriod.groups.map((group) => `<section class="mb-4 rounded-lg border border-[var(--border-neutral-5-2)] bg-[rgb(var(--surface))] p-3 shadow-sm last:mb-0 sm:p-4 dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))]"><h3 class="mb-3 border-b border-[var(--border-neutral-5-12)] pb-2 text-[11px] font-bold uppercase tracking-wide text-[rgb(var(--text-muted))] dark:border-[rgb(var(--border))] dark:text-[rgb(var(--text-soft))]">${escape(group.groupName === 'Other' ? (globalThis.bfTranslations?.["Other Stats"] ?? "Other Stats") : group.groupName)}</h3>
                    ${(group.statisticsItems ?? []).map((item) => {
                        const values = ['home', 'away'].map((side) => {
                            const displayed = [item[side], item[`${side}Value`]].find((value) => (typeof value === 'string' && value.trim() !== '' && !/^(?:null|undefined|NaN|Infinity|N\/A|[-—–])$/i.test(value.trim())) || (typeof value === 'number' && Number.isFinite(value)));
                            const text = String(displayed ?? '—');
                            const percentage = text.match(/(-?\d+(?:[.,]\d+)?)\s*%/);
                            const raw = item[`${side}Value`];
                            const numeric = percentage ? Number(percentage[1].replace(',', '.')) : typeof raw === 'number' && Number.isFinite(raw) ? raw : /^-?\d+(?:[.,]\d+)?$/.test(String(raw)) ? Number(String(raw).replace(',', '.')) : /^-?\d+(?:[.,]\d+)?$/.test(text) ? Number(text.replace(',', '.')) : null;
                            return { text, numeric };
                        });
                        const comparable = values.every((value) => value.numeric !== null && Number.isFinite(value.numeric));
                        return `<div class="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)_minmax(0,1fr)] items-center gap-2 py-1.5 text-xs sm:text-[13px]">${values.map((value, index) => `${index === 1 ? `<span class="min-w-0 break-words text-center font-medium text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]">${escape(globalThis.bfTranslations?.[item.name] ?? item.name)}</span>` : ''}<span class="min-w-0 break-words tabular-nums ${index === 0 ? "justify-self-start text-left" : "justify-self-end text-right"} ${comparable && value.numeric > values[1 - index].numeric ? 'highlight font-extrabold text-[rgb(var(--text-main))] dark:text-white' : comparable && value.numeric < values[1 - index].numeric ? 'dimmed font-medium text-[var(--text-muted-soft)] dark:text-[var(--text-slate-400)]' : 'font-semibold text-[rgb(var(--text-muted))] dark:text-[rgb(var(--text-soft))]'}">${escape(value.text)}</span>`).join('')}</div>`;
                    }).join('') || `<p class="py-2 text-center text-xs text-[var(--text-muted-soft)]">${(globalThis.bfTranslations?.["No statistics available for this section."] ?? "No statistics available for this section.")}</p>`}
                </section>`).join('') || `<p class="rounded-lg border border-[var(--border-neutral-5-2)] bg-[rgb(var(--surface))] p-5 text-center text-sm text-[rgb(var(--text-muted))] dark:border-[rgb(var(--border))] dark:bg-[rgb(var(--surface))] dark:text-[rgb(var(--text-soft))]">${(globalThis.bfTranslations?.["No statistics available for this period yet."] ?? "No statistics available for this period yet.")}</p>`}
            </div>`;
        } else if (activeModal.action === 'prediction') {
            const data = parse(row.dataset.basketballPrediction); title = (globalThis.bfTranslations?.["Match Predictions"] ?? "Match Predictions");
            const total = Object.values(data.counts ?? {}).reduce((sum, count) => sum + Number(count), 0);
            body = `<p class="mb-4 text-sm">${data.locked ? (globalThis.bfTranslations?.["Voting closes when the match starts."] ?? "Voting closes when the match starts.") : data.vote ? (globalThis.bfTranslations?.["Your prediction has been recorded."] ?? "Your prediction has been recorded.") : (globalThis.bfTranslations?.["Who will win this match?"] ?? "Who will win this match?")}</p><div class="grid gap-3">${Object.entries(data.choices ?? {}).map(([value, label]) => `<button type="button" data-action="vote" data-value="${escape(value)}" ${data.locked || data.vote ? 'disabled' : ''} class="rounded-xl border border-[var(--border-brand-5-5)] p-3 text-left disabled:cursor-default ${data.vote === value ? 'bg-[var(--surface-brand-0-2)] text-[rgb(var(--primary))]' : ''}"><b>${escape(label)}</b><span class="float-right">${Number(data.counts?.[value] ?? 0)} votes · ${total ? Math.round(Number(data.counts?.[value] ?? 0) / total * 100) : 0}%</span></button>`).join('')}</div>`;
        } else {
            const data = parse(row.dataset.basketballMarket); title = data.title ?? (globalThis.bfTranslations?.["Market Details"] ?? "Market Details");
            body = `<p class="mb-2 font-bold">${escape(data.team1)} vs ${escape(data.team2)}</p><p class="mb-4 text-sm"><span class="match-score" data-score-status="${escape(data.status)}">${escape(data.score)}</span> · ${escape(globalThis.bfTranslations?.[data.status] ?? data.status)} · ${escape(globalThis.bfTranslations?.[data.minutes] ?? data.minutes)}</p>`;
            if (data.locked) body += `<p>${(globalThis.bfTranslations?.["Login with an active subscription to view odds."] ?? "Login with an active subscription to view odds.")}</p>`;
            else body += `<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr>${['Selection', 'Initial', (globalThis.bfTranslations?.["Back"] ?? "Back"), 'Size', (globalThis.bfTranslations?.["Lay"] ?? "Lay"), 'Size'].map((label) => `<th class="px-2 py-2">${label}</th>`).join('')}</tr></thead><tbody>${(data.runners ?? []).map((runner) => {
                const initial = Number(runner.initialBackOdds ?? 0); const current = Number(runner.currentBackOdds ?? runner.backOdds ?? 0);
                const arrow = initial > 0 && current > 0 ? current > initial ? ' ↑' : current < initial ? ' ↓' : '' : '';
                return `<tr class="border-t border-[var(--border-neutral-5-2)]"><td class="px-2 py-3">${escape(runner.runner)}${renderOddsHistory(runner)}</td>${[initial, current, runner.backSize, runner.layOdds, runner.laySize].map((value, index) => `<td ${index === 1 && current > 0 ? `tabindex="0" data-basketball-odds-tooltip="${number(initial)} ${current > initial ? '&lt;&lt;' : '&gt;&gt;'} ${number(current)}"` : ''} class="whitespace-nowrap px-2 py-3 font-semibold">${number(value)}${index === 1 ? arrow : ''}</td>`).join('')}</tr>`;
            }).join('')}</tbody></table></div><a class="mt-4 inline-block rounded-xl bg-[var(--surface-heading)] px-4 py-2 text-sm font-bold text-white" href="${escape(row.dataset.basketballMatchUrl)}">${(globalThis.bfTranslations?.["Match markets &amp; charts →"] ?? "Match markets &amp; charts →")}</a>`;
        }
        hideOddsTooltip();
        panel.dataset.statistics = String(activeModal.action === 'stats');
        panel.innerHTML = activeModal.action === 'stats' ? body : `<div class="mb-5 flex items-center justify-between gap-3"><h2 id="basketballModalTitle" class="text-lg font-extrabold">${escape(title)}</h2><button type="button" data-action="close-modal" aria-label="${(globalThis.bfTranslations?.["Close"] ?? "Close")}" class="px-2 text-2xl">×</button></div>${body}`;
        (panel.querySelector('[data-basketball-statistics-scroll]') ?? panel).scrollTop = scrollTop;
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
        if (event.target.id === 'basketballModal') { closeModal(); return; }
        if (!button) {
            const row = event.target.closest('[data-basketball-match-url]');
            if (row && !event.target.closest('a, button, input, select, textarea, [role="dialog"]')) {
                window.location.assign(row.dataset.basketballMatchUrl);
            }
            return;
        }
        if (button.dataset.requiresLogin === 'true') { window.location.assign(button.dataset.loginUrl); return; }
        const action = button.dataset.action;
        if (action === 'close-modal') { closeModal(); return; }
        if (action === 'stats-period' && activeModal?.action === 'stats') {
            activeModal.period = button.dataset.period;
            dashboard.querySelector('[data-basketball-statistics-scroll]').scrollTop = 0;
            renderModal();
            dashboard.querySelector('[data-action="stats-period"][aria-selected="true"]')?.focus({ preventScroll: true });
            return;
        }
        const row = button.closest('[data-match-id]');
        if (action === 'open-match') { window.location.assign(row.dataset.basketballMatchUrl); return; }
        if (['stats', 'prediction', 'market'].includes(action)) { activeModal = { action, matchId: row.dataset.matchId, marketId: row.dataset.marketId ?? null }; renderModal(); return; }
        if (action === 'vote' && activeModal) { button.disabled = true; await load({ prediction: { match_id: activeModal.matchId, poll: '1x2', value: button.dataset.value } }); return; }
        if (action !== 'favorite' || button.disabled) return;
        button.disabled = true;
        try {
            const response = await fetch(dashboard.dataset.favoriteUrl, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ sport: 'basketball', event_key: button.dataset.eventKey, event_id: button.dataset.eventId, team1: button.dataset.team1, team2: button.dataset.team2, start_timestamp: button.dataset.startTimestamp }) });
            if (response.status === 401) { window.location.assign(button.dataset.loginUrl); return; }
            if (!response.ok) throw new Error((globalThis.bfTranslations?.["Unable to save favorite. Please try again."] ?? "Unable to save favorite. Please try again."));
            await load();
        } catch (error) { showError(error.message); }
        finally { button.disabled = false; }
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeModal(); });
    new IntersectionObserver((entries) => { if (entries.some((entry) => entry.isIntersecting)) load({ append: true }); }, { rootMargin: '100px' }).observe(loader());
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') scheduleRefresh(); });
    const subscribe = () => window.Echo?.channel('sports.basketball.live').listen('.score.updated', scheduleRefresh);
    if (window.Echo) subscribe(); else window.addEventListener('load', subscribe, { once: true });
    expireHighlights();
}
