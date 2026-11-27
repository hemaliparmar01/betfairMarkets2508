import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/views/users/sports/football/marketActivity.blade.php', import.meta.url), 'utf8').replace(/@json\(__\('([^']+)'\)\)/g, (_, label) => JSON.stringify(label));
const helpers = source.slice(source.indexOf('                const sportKey ='), source.indexOf('                const filtered ='));
const renderer = source.slice(source.indexOf('                const filtered ='), source.indexOf('                const openModal ='));
const base = { alert_key: 'first', sport: 'soccer', home: 'Home Team', away: 'Away Team', league: 'Super League', sect: 'Total', point: 2.5, outcome: 'Over', from_price: 1.63, to_price: 1.47, nvp: 1.52, bf_back: 1.54, alerted: 1000 };
function harness(alerts = [base], options = {}) {
    let now = options.now ?? 1000000;
    const translations = JSON.parse(readFileSync(new URL(`../../lang/${options.locale ?? 'en'}.json`, import.meta.url), 'utf8'));
    const matchStartLabels = {
        minutes: translations['Starts in :minutesm'],
        hoursMinutes: translations['Starts in :hoursh :minutesm'],
        daysHours: translations['Starts in :daysd :hoursh'],
        now: translations['Starting now'],
    };
    const output = { innerHTML: '', textContent: '' };
    const state = { alerts, view: 'advanced', mode: 'prematch', sport: 'all', collapsedGroups: new Set(), flashes: new Map() };
    const context = vm.createContext({ AbortController, setTimeout() {}, clearTimeout() {}, state, matchStartLabels, URL, root: { dataset: { timezone: options.timezone ?? 'UTC' }, querySelectorAll: () => [], querySelector: selector => ({
        '[data-pinnacle-higher]': options.higher ? { checked: true } : null,
        '[data-pinnacle-shock]': options.shock ? { checked: true } : null,
        '[data-pinnacle-league-picker]': {
            querySelector: selector => selector === '[data-pinnacle-leagues]' ? {
                querySelectorAll: selector => selector === '[data-pinnacle-league]'
                    ? (options.leagues ?? []).map(value => ({ value }))
                    : [],
            } : null,
            querySelectorAll: () => (options.leagues ?? []).map(value => ({ value })),
        },
    })[selector] ?? (selector === '[data-pinnacle-league-picker]' ? null : output) }, Date: { now: () => now }, document: { documentElement: { lang: options.locale ?? 'en' }, createElement: () => ({ set textContent(value) { this.innerHTML = String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('"', '&quot;'); } }) } });
    vm.runInContext(helpers + renderer, context);
    return { state, output, evaluate: code => vm.runInContext(code, context), advance: ms => { now += ms; } };
}

test('Betfair edge uses displayed valid odds and fair price without suppressing positive or negative values', () => {
    const app = harness();
    assert.equal(app.evaluate('betfairEdgeText(state.alerts[0])'), '+1.3%');
    assert.equal(app.evaluate('betfairEdgeText({...state.alerts[0], bf_back: 1.47})'), '-3.3%');
    assert.equal(app.evaluate('betfairEdgeText({...state.alerts[0], bf_back: null})'), 'N/A');
    assert.equal(app.evaluate('betfairEdgeText({...state.alerts[0], bf_back: 0})'), 'N/A');
    assert.equal(app.evaluate('betfairEdgeText({...state.alerts[0], bf_back: 4})'), '+163.2%');
    assert.equal(app.evaluate('betfairEdgeText({...state.alerts[0], nvp: null})'), '— (fair price pending)');
    assert.equal(app.evaluate('edgeClass(-1)'), 'text-[var(--text-danger-edge)] dark:text-[var(--text-red-400)]');
    assert.equal(app.evaluate('edgeClass(1)'), 'text-[var(--text-emerald-600)] dark:text-[var(--text-emerald-400)]');
});

test('market and position names keep the affected side and line explicit', () => {
    const app = harness();
    assert.equal(app.evaluate('marketLabel(state.alerts[0])'), 'Over/Under 2.5 Goals');
    assert.equal(app.evaluate('marketPosition(state.alerts[0])'), 'Over 2.5 Goals');
    assert.equal(app.evaluate('marketPosition({...state.alerts[0], sect: "Asian Handicap", point: -1.25, outcome: "Home"})'), 'Home (-1.25)');
    assert.equal(app.evaluate('marketPosition({...state.alerts[0], sect: "Asian Handicap", point: -1.25, outcome: "Away"})'), 'Away (+1.25)');
});

test('both table views display one unified edge and advanced adds fair price and time', () => {
    const app = harness([{ ...base, limit: '2500.50' }]);
    app.evaluate('render()');
    assert.equal((app.output.innerHTML.match(/scope="col"/g) || []).length, 8);
    assert.match(app.output.innerHTML, /<th scope="col">Limit/);
    assert.match(app.output.innerHTML, /<td>2,500\.5<\/td>/);
    assert.match(app.output.innerHTML, /data-edge-percent[^>]*>\+1.3%<\/td>/);
    assert.match(app.output.innerHTML, /About NVP/);
    assert.match(app.output.innerHTML, /aria-expanded="true"/);
    assert.doesNotMatch(app.output.innerHTML, /min-w-\[/);
    app.state.view = 'simple';
    app.evaluate('render()');
    assert.equal((app.output.innerHTML.match(/scope="col"/g) || []).length, 6);
    assert.match(app.output.innerHTML, /<td>2,500\.5<\/td>/);
    assert.doesNotMatch(app.output.innerHTML, /About NVP|BF:/);
    assert.match(app.output.innerHTML, /Home Team v Away Team/);
    assert.match(app.output.innerHTML, /Over 2.5 Goals/);
    delete app.state.alerts[0].limit;
    app.evaluate('render()');
    assert.match(app.output.innerHTML, /<td>—<\/td>/);
});

test('missing fixture names never turn BTTS selections into a match title in either view', () => {
    const app = harness([{ ...base, home: null, away: null, sect: 'btts', outcome: 'Yes', point: null }]);
    for (const view of ['simple', 'advanced']) {
        app.state.view = view;
        app.evaluate('render()');
        assert.match(app.output.innerHTML, /No data available/);
        assert.match(app.output.innerHTML, /aria-label="View btts: Yes"/);
        assert.match(app.output.innerHTML, />Yes<\/span>/);
        assert.doesNotMatch(app.output.innerHTML, /Yes v No|null v null|undefined v undefined/);
    }
});

test('new or changed drops flash once; initial render and identical polls do not flash', () => {
    const app = harness();
    app.evaluate('render()');
    assert.doesNotMatch(app.output.innerHTML, /drop-row drop-flash/);
    app.evaluate('receiveAlerts(state.alerts.map(alert => ({...alert})))');
    assert.equal(app.state.flashes.size, 0);
    app.evaluate('receiveAlerts([{...state.alerts[0], alert_key: "new"}]); render()');
    assert.match(app.output.innerHTML, /drop-row drop-flash/);
    const expires = app.state.flashes.get('new');
    app.advance(500);
    app.evaluate('receiveAlerts(state.alerts.map(alert => ({...alert})))');
    assert.equal(app.state.flashes.get('new'), expires);
    app.advance(1001);
    app.evaluate('render()');
    assert.doesNotMatch(app.output.innerHTML, /drop-row drop-flash/);
});

test('archive renders retained drops without flashing and hides expired details', () => {
    const app = harness([base, { ...base, alert_key: 'handicap', sect: 'Asian Handicap', point: 4 }]);
    app.state.archive = true;
    app.state.marketFilter = 'all';
    app.advance(12 * 60000);
    app.evaluate('receiveAlerts(state.alerts); render()');
    assert.equal(app.state.flashes.size, 0);
    assert.equal((app.output.innerHTML.match(/data-alert-index=/g) ?? []).length, 2);
    assert.match(app.output.innerHTML, /Asian Handicap/);
    assert.doesNotMatch(app.output.innerHTML, /drop-row drop-flash/);
    app.advance(18 * 60000);
    app.evaluate('render()');
    assert.doesNotMatch(app.output.innerHTML, /data-alert-index=/);
});

test('prematch and live drops expire at seven minutes while archive history remains available', () => {
    const now = Date.parse('2026-11-07T12:00:00Z');
    const app = harness([
        {...base, alerted: now / 1000},
        {...base, alert_key: 'live', is_live: true, alerted: now / 1000},
        {...base, alert_key: 'edge', alerted: now - 419999},
        {...base, alert_key: 'expired', alerted: now / 1000 - 420},
        {...base, alert_key: 'older', alerted: now / 1000 - 421},
        {...base, alert_key: 'missing', alerted: null},
    ], {now});
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'first,edge');
    app.advance(1);
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'first');
    app.advance(419999);
    for (const mode of ['prematch', 'live']) {
        app.state.mode = mode;
        assert.equal(app.evaluate('filtered().length'), 0);
    }
    app.state.archive = true;
    app.state.mode = 'prematch';
    assert.equal(app.evaluate('filtered().length'), 4);
});

test('archive retention uses the server expiry and keeps an alert only until its exact boundary', () => {
    const app = harness([{...base, archive_expires_at: 1001}]);
    app.state.archive = true;
    assert.equal(app.evaluate('filtered().length'), 1);
    app.advance(1000);
    assert.equal(app.evaluate('filtered().length'), 0);
});

test('separate drops for one outcome keep their own expiry and render in arrival order', () => {
    const now = 1000000;
    const app = harness([
        { ...base, monitor_received_at: 900 },
        { ...base, alert_key: 'other', home: 'Another Team', monitor_received_at: 950 },
        { ...base, alert_key: 'latest', to_price: 1.42, monitor_received_at: 1000 },
    ], { now });
    app.evaluate('render()');
    assert.deepEqual([...app.output.innerHTML.matchAll(/data-alert-key="([^"]+)"/g)].map(match => match[1]), ['latest', 'other', 'first']);
    app.advance(320000);
    app.evaluate('render()');
    assert.deepEqual([...app.output.innerHTML.matchAll(/data-alert-key="([^"]+)"/g)].map(match => match[1]), ['latest', 'other']);
});

test('expired drops disappear on the existing refresh even if the request fails', async () => {
    const app = harness();
    app.evaluate('render()');
    assert.match(app.output.innerHTML, /data-alert-index=/);
    app.advance(420000);
    app.evaluate(`
        globalThis.window = {location: {origin: 'https://example.test'}};
        root.dataset.sourceUrl = '/pinnacle';
        globalThis.fetch = async () => {throw new Error('offline');};
        globalThis.updateStatus = () => {};
    `);
    app.evaluate(source.slice(source.indexOf('                let refreshing ='), source.indexOf('                let realtimeAttempts =')));
    await app.evaluate('refresh()');
    assert.doesNotMatch(app.output.innerHTML, /data-alert-index=/);
});

test('a stalled refresh is aborted after twenty seconds and releases the request guard', async () => {
    const app = harness();
    app.evaluate(`
        globalThis.window = {location: {origin: 'https://example.test'}};
        root.dataset.sourceUrl = '/pinnacle';
        globalThis.updateStatus = () => {};
        globalThis.timeoutDelay = null;
        globalThis.timeoutCallback = null;
        globalThis.timeoutCleared = false;
        globalThis.setTimeout = (callback, delay) => {
            if (delay === 20000) { timeoutDelay = delay; timeoutCallback = callback; }
            return 77;
        };
        globalThis.clearTimeout = id => { if (id === 77) timeoutCleared = true; };
        globalThis.fetch = (url, options) => new Promise((resolve, reject) => {
            globalThis.requestSignal = options.signal;
            options.signal.addEventListener('abort', () => reject(new Error('timeout')), {once: true});
        });
    `);
    app.evaluate(source.slice(source.indexOf('                let refreshing ='), source.indexOf('                let realtimeAttempts =')));
    const pending = app.evaluate('refresh()');
    assert.equal(app.evaluate('timeoutDelay'), 20000);
    assert.equal(app.evaluate('refreshing'), true);
    app.evaluate('timeoutCallback()');
    await pending;
    assert.equal(app.evaluate('requestSignal.aborted'), true);
    assert.equal(app.evaluate('refreshing'), false);
    assert.equal(app.evaluate('timeoutCleared'), true);
    assert.equal(app.state.alerts.length, 1);
});

test('archive load-more sends its keyset and active filters, appends once, and makes no automatic request', async () => {
    const app = harness();
    Object.assign(app.state, { archive: true, archiveHasMore: true, archiveCursor: { time: 1000, key: 'before' }, marketFilter: 'all', timeFilter: 'today', sport: 'soccer' });
    app.evaluate(`
        globalThis.window = {location: {origin: 'https://example.test'}};
        globalThis.requests = [];
        root.dataset.sourceUrl = '/pinnacle';
        root.querySelector('[data-load-archive]').classList = {toggle() {}};
        globalThis.updateStatus = () => {};
        globalThis.fetch = async url => {
            requests.push(Object.fromEntries(url.searchParams));
            return {ok: true, json: async () => ({data: [{...state.alerts[0], alert_key: 'older', alerted: 900}], has_more: false, next_cursor: null})};
        };
    `);
    app.evaluate(source.slice(source.indexOf('                let refreshing ='), source.indexOf('                let realtimeAttempts =')));
    app.evaluate('scheduleRefresh()');
    assert.equal(app.evaluate('requests.length'), 0);
    await app.evaluate('refresh(true)');
    assert.equal(app.state.alerts.length, 2);
    assert.equal(app.state.flashes.size, 0);
    assert.equal(app.state.archiveHasMore, false);
    assert.equal(app.state.archiveCursor, null);
    assert.equal(app.evaluate('requests[0].before_time'), '1000');
    assert.equal(app.evaluate('requests[0].before_key'), 'before');
    assert.equal(app.evaluate('requests[0].market_filter'), 'all');
    assert.equal(app.evaluate('requests[0].sport'), 'soccer');
    assert.equal(app.evaluate('requests[0].time_filter'), 'today');
});

test('archive reuses the existing timer only to roll over at midnight without normal feed polling', () => {
    const app = harness();
    app.state.archive = true;
    app.state.archiveDayEndsAt = 1100000;
    app.evaluate(`
        globalThis.reloads = 0;
        globalThis.polls = 0;
        globalThis.scheduleRefresh = () => { polls++; };
        globalThis.window = {setInterval(callback) {this.tick = callback;}, location: {reload() {reloads++;}}};
    `);
    app.evaluate(source.slice(source.indexOf('                window.setInterval(() => {'), source.indexOf('                const tooltipTexts =')));
    app.evaluate('window.tick()');
    assert.equal(app.evaluate('polls'), 0);
    assert.equal(app.evaluate('reloads'), 0);
    app.advance(100000);
    app.evaluate('window.tick()');
    assert.equal(app.evaluate('reloads'), 1);
});

test('archive info tooltip opens immediately on hover and toggles on mobile tap in both languages', () => {
    const key = 'Incoming alerts are logged in the daily statistics counters, remain visible in the archive for 30 minutes, and are then automatically purged as outdated data.';
    const button = source.split('\n').find(line => line.includes('data-archive-info aria-label='));
    const click = button.match(/onclick="([^"]+)"/)[1];
    for (const locale of ['en', 'ka']) {
        const expected = JSON.parse(readFileSync(new URL(`../../lang/${locale}.json`, import.meta.url), 'utf8'))[key];
        const app = harness([], {locale});
        app.evaluate(`
            globalThis.tooltipTexts = {archive: ${JSON.stringify(expected)}};
            globalThis.tooltip = {style: {}, offsetWidth: 280, offsetHeight: 80};
            globalThis.button = {attrs: {'aria-expanded': 'false'}, setAttribute(key, value) {this.attrs[key] = value;}, getAttribute(key) {return this.attrs[key];}, getBoundingClientRect() {return {left: 100, top: 200, bottom: 235, width: 35};}};
            document.getElementById = () => tooltip;
            root.querySelector = () => button;
            globalThis.window = {innerWidth: 1000, innerHeight: 800};
        `);
        app.evaluate(source.slice(source.indexOf('                window.showOddsTooltip ='), source.indexOf('            })();')));
        app.evaluate("window.showOddsTooltip({currentTarget: button}, 'archive')");
        assert.equal(app.evaluate('tooltip.textContent'), expected);
        assert.equal(app.evaluate('tooltip.style.display'), 'block');
        app.evaluate('window.hideOddsTooltip()');
        assert.equal(app.evaluate('tooltip.style.display'), 'none');
        app.evaluate(`(function(event) {${click}}).call(button, {currentTarget: button})`);
        assert.equal(app.evaluate("button.getAttribute('aria-expanded')"), 'true');
        assert.equal(app.evaluate('tooltip.style.display'), 'block');
        app.evaluate(`(function(event) {${click}}).call(button, {currentTarget: button})`);
        assert.equal(app.evaluate("button.getAttribute('aria-expanded')"), 'false');
        assert.equal(app.evaluate('tooltip.style.display'), 'none');
    }
});

test('start windows are cumulative, include boundaries, and exclude missing, past and live starts', () => {
    const now = Date.parse('2026-11-06T12:00:00Z');
    const alerts = [-1, 0, 3600, 3601, 10800, 10801].map(seconds => ({
        ...base, alerted: now / 1000, alert_key: String(seconds), start_timestamp: now / 1000 + seconds,
    }));
    alerts.push(
        {...base, alerted: now / 1000, alert_key: 'missing'},
        {...base, alerted: now / 1000, alert_key: 'invalid', start_timestamp: 'bad'},
        {...base, alerted: now / 1000, alert_key: 'too-large', start_timestamp: 1e20},
        {...base, alerted: now / 1000, alert_key: 'live', start_timestamp: now / 1000, is_live: true},
    );
    const app = harness(alerts, {now});
    app.state.timeFilter = '1h';
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), '3600');
    app.state.timeFilter = '3h';
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), '3600,3601,10800');
    app.state.mode = 'live';
    assert.equal(app.evaluate('filtered().length'), 0);
    app.state.timeFilter = 'all';
    assert.equal(app.evaluate('filtered().length'), 0);
    app.state.mode = 'prematch';
    assert.equal(app.evaluate('filtered().length'), alerts.length - 3);
});

test('calendar filters use the customer timezone across midnight and daylight saving changes', () => {
    const now = Date.parse('2026-11-06T19:00:00Z');
    const app = harness([
        {...base, alerted: now / 1000, alert_key: 'today', start_timestamp: Date.parse('2026-11-07T01:00:00Z') / 1000},
        {...base, alerted: now / 1000, alert_key: 'tomorrow', start_timestamp: Date.parse('2026-11-07T05:00:00Z') / 1000},
    ], {now, timezone: 'America/New_York'});
    app.state.timeFilter = 'today';
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'today');
    app.state.timeFilter = 'future';
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'tomorrow');

    const dst = harness([
        {...base, alerted: Date.parse('2026-11-01T04:30:00Z') / 1000, alert_key: 'same-day', start_timestamp: Date.parse('2026-11-02T04:30:00Z') / 1000},
        {...base, alerted: Date.parse('2026-11-01T04:30:00Z') / 1000, alert_key: 'next-day', start_timestamp: Date.parse('2026-11-02T05:00:00Z') / 1000},
    ], {now: Date.parse('2026-11-01T04:30:00Z'), timezone: 'America/New_York'});
    dst.state.timeFilter = 'today';
    assert.equal(dst.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'same-day');
    dst.state.timeFilter = 'future';
    assert.equal(dst.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'next-day');
    for (const [timezone, time] of [['Asia/Tbilisi', '16:30 (GMT+4)'], ['Asia/Kolkata', '18:00 (GMT+5:30)']]) {
        const local = harness([{...base, alerted: now / 1000, start_timestamp: Date.parse('2026-11-07T12:30:00Z') / 1000}], {now, timezone});
        local.evaluate('render()');
        assert.ok(local.output.innerHTML.includes(`07 Nov 2026, ${time}`));
        assert.doesNotMatch(local.output.innerHTML, /Asia\/Tbilisi|Asia\/Kolkata/);
    }
});

test('start filters preserve market and sport selection and reevaluate incoming updates and elapsed time', () => {
    const now = Date.parse('2026-11-06T12:00:00Z');
    const app = harness([
        {...base, alerted: now / 1000, start_timestamp: now / 1000 + 1800},
        {...base, alerted: now / 1000, alert_key: 'tennis', sport: 'tennis', home: 'Player A', away: 'Player B', sect: 'Moneyline', point: null, start_timestamp: now / 1000 + 1800},
        {...base, alerted: now / 1000, alert_key: 'handicap', sect: 'Asian Handicap', start_timestamp: now / 1000 + 1800},
    ], {now});
    app.state.timeFilter = '1h';
    app.state.marketFilter = 'main';
    app.state.sport = 'soccer';
    app.evaluate('render()');
    assert.match(app.output.innerHTML, /Home Team v Away Team/);
    assert.doesNotMatch(app.output.innerHTML, /Player A v Player B|Asian Handicap/);
    assert.match(app.output.innerHTML, /text-\[var\(--text-amber-700\)\]/);
    assert.match(app.output.innerHTML, /06 Nov 2026, 12:30/);
    app.evaluate('receiveAlerts([{...state.alerts[0], start_timestamp: state.alerts[0].start_timestamp + 7200}]); render()');
    assert.doesNotMatch(app.output.innerHTML, /Home Team v Away Team/);
    assert.equal(app.state.timeFilter, '1h');
    assert.equal(app.state.marketFilter, 'main');
    assert.equal(app.state.sport, 'soccer');
    app.state.timeFilter = '3h';
    app.evaluate('render()');
    assert.match(app.output.innerHTML, /Home Team v Away Team/);
    app.advance(10800000 + 1);
    app.evaluate('render()');
    assert.doesNotMatch(app.output.innerHTML, /Home Team v Away Team/);
});

test('start countdown appears below the league in both languages and views and advances with the existing render', () => {
    const now = Date.parse('2026-11-06T12:00:00Z');
    const countdownText = app => app.output.innerHTML.replaceAll(/<[^>]+>/g, '');
    for (const locale of ['en', 'ka']) {
        const app = harness([{...base, alerted: now / 1000, league: 'Country - League', start_timestamp: now / 1000 + 600}], {now, locale});
        for (const view of ['simple', 'advanced']) {
            app.state.view = view;
            app.evaluate('render()');
            assert.match(app.output.innerHTML, /Country - League<\/small><small[^>]*text-\[var\(--text-amber-700\)\]/);
            assert.ok(countdownText(app).includes(locale === 'en' ? ' · Starts in 10m' : ' · იწყება 10 წთ-ში'));
            for (const [seconds, english, georgian] of [
                [60, 'Starts in 1m', 'იწყება 1 წთ-ში'],
                [3600, 'Starts in 1h 0m', 'იწყება 1 სთ 0 წთ-ში'],
                [6420, 'Starts in 1h 47m', 'იწყება 1 სთ 47 წთ-ში'],
                [7200, 'Starts in 2h 0m', 'იწყება 2 სთ 0 წთ-ში'],
                [86400, 'Starts in 1d 0h', 'იწყება 1 დღეში 0 სთ-ში'],
                [172800, 'Starts in 2d 0h', 'იწყება 2 დღეში 0 სთ-ში'],
                [86340, 'Starts in 23h 59m', 'იწყება 23 სთ 59 წთ-ში'],
                [516960, 'Starts in 5d 23h', 'იწყება 5 დღეში 23 სთ-ში'],
            ]) {
                app.state.alerts[0].start_timestamp = now / 1000 + seconds;
                app.evaluate('render()');
                assert.ok(countdownText(app).includes(locale === 'en' ? english : georgian));
            }
            app.state.alerts[0].start_timestamp = now / 1000 + 600;
        }
        app.advance(300000);
        app.evaluate('render()');
        assert.ok(countdownText(app).includes(locale === 'en' ? ' · Starts in 5m' : ' · იწყება 5 წთ-ში'));
        app.advance(600000);
        app.evaluate('render()');
        assert.ok(!countdownText(app).includes(locale === 'en' ? ' · Starts in ' : ' · იწყება '));
        app.state.alerts[0].start_timestamp = now / 1000 + 3600;
        app.state.alerts[0].is_live = true;
        app.state.mode = 'live';
        app.evaluate('render()');
        assert.ok(!countdownText(app).includes(locale === 'en' ? ' · Starts in ' : ' · იწყება '));
    }
});

test('kickoff displays the configured timezone offset at the match timestamp', () => {
    const now = Date.parse('2026-11-15T11:43:00Z');
    const match = { ...base, alerted: now / 1000, start_timestamp: Date.parse('2026-11-15T13:30:00Z') / 1000 };
    for (const [timezone, kickoff] of [
        ['Asia/Tbilisi', '17:30 (GMT+4)'],
        ['Asia/Kolkata', '19:00 (GMT+5:30)'],
        ['America/New_York', '08:30 (GMT-5)'],
    ]) {
        const app = harness([match], { now, timezone });
        app.evaluate('render()');
        assert.ok(app.output.innerHTML.includes(kickoff + ' · Starts in 1h 47m'));
    }
});


test('Pinnacle filters require a selected league and Betfair strictly above the new price', () => {
    const drop = { ...base, league_filter_key: 'selected' };
    const app = harness([
        drop,
        { ...drop, alert_key: 'equal', bf_back: drop.to_price },
        { ...drop, alert_key: 'missing', bf_back: null },
        { ...drop, alert_key: 'other', league_filter_key: 'other' },
    ], { higher: true, leagues: ['selected'] });

    assert.equal(app.evaluate("filtered().map(alert => alert.alert_key).join(',')"), 'first');
});

test('minimum drop defaults to one percent and includes exact percentage boundaries', () => {
    const app = harness([
        { ...base, alert_key: 'below-one', from_price: 2, to_price: 1.9802 },
        { ...base, alert_key: 'one', from_price: 2, to_price: 1.98 },
        { ...base, alert_key: 'below-large', from_price: 2, to_price: 1.9302 },
        { ...base, alert_key: 'large', from_price: 1.2, to_price: 1.158 },
    ]);
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'one,below-large,large');
    app.state.minDrop = 3.5;
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'large');
    app.state.archive = true;
    assert.equal(app.evaluate('filtered().length'), 4);
});

test('minimum drop buttons update their selected state and restore smaller alerts without discarding them', () => {
    const app = harness([
        { ...base, alert_key: 'small', from_price: 2, to_price: 1.96 },
        { ...base, alert_key: 'large', from_price: 2, to_price: 1.9 },
    ]);
    app.evaluate(`
        globalThis.buttons = ['1', '3.5'].map(value => ({
            dataset: {minDrop: value}, attributes: {}, classes: new Set(),
            setAttribute(key, value) { this.attributes[key] = value; },
            classList: {toggle() {}},
        }));
        root.querySelectorAll = selector => selector === '[data-min-drop]' ? buttons : [];
        root.addEventListener = (name, listener) => { globalThis.clickHandler = listener; };
    `);
    app.evaluate(source.slice(source.indexOf("                root.addEventListener('click'"), source.indexOf("                root.addEventListener('keydown'")));
    app.evaluate("clickHandler({target: {closest: selector => selector === '[data-min-drop]' ? buttons[1] : null}})");
    assert.equal(app.state.minDrop, 3.5);
    assert.equal(app.evaluate("buttons.map(button => button.attributes['aria-pressed']).join(',')"), 'false,true');
    assert.doesNotMatch(app.output.innerHTML, /data-alert-key="small"/);
    assert.match(app.output.innerHTML, /data-alert-key="large"/);
    assert.equal(app.state.alerts.length, 2);
    app.evaluate("clickHandler({target: {closest: selector => selector === '[data-min-drop]' ? buttons[0] : null}})");
    assert.equal(app.state.minDrop, 1);
    assert.equal(app.evaluate("buttons.map(button => button.attributes['aria-pressed']).join(',')"), 'true,false');
    assert.match(app.output.innerHTML, /data-alert-key="small"/);
});

test('minimum drop persists through live updates and composes with league and Betfair filters', () => {
    const app = harness([
        { ...base, alert_key: 'selected', league_filter_key: 'selected' },
        { ...base, alert_key: 'other', league_filter_key: 'other' },
        { ...base, alert_key: 'lower-betfair', league_filter_key: 'selected', bf_back: 1.1 },
    ], { higher: true, leagues: ['selected'] });
    app.state.minDrop = 3.5;
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'selected');
    app.evaluate('receiveAlerts(state.alerts.map(alert => ({...alert, from_price: 2, to_price: 1.96})))');
    assert.equal(app.state.minDrop, 3.5);
    assert.equal(app.evaluate('filtered().length'), 0);
    app.state.minDrop = 1;
    app.evaluate('receiveAlerts(state.alerts.map(alert => ({...alert, bf_back: 2.1})))');
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'selected,lower-betfair');
    app.advance(420000);
    assert.equal(app.evaluate('filtered().length'), 0);
});

test('Pinnacle league filter turns off when no leagues are checked', () => {
    const app = harness([base], { leagues: [] });
    assert.equal(app.evaluate('filtered().length'), 1);
});

test('live Betfair refresh fallback runs every five seconds and skips hidden tabs', () => {
    const app = harness();
    app.evaluate(`
        globalThis.polls = 0;
        globalThis.scheduleRefresh = () => { polls++; };
        globalThis.window = {setInterval(callback, interval) {this.tick = callback; this.interval = interval;}};
    `);
    app.evaluate(source.slice(source.indexOf('                window.setInterval(() => {'), source.indexOf('                const tooltipTexts =')));
    assert.equal(app.evaluate('window.interval'), 5000);
    app.evaluate('window.tick()');
    assert.equal(app.evaluate('polls'), 1);
    app.evaluate('document.hidden = true; window.tick()');
    assert.equal(app.evaluate('polls'), 1);
});

test('falling Betfair prices stay displayed and recalculate the comparison filter without extending alert lifetime', () => {
    const app = harness([base], { higher: true });
    const receivedAt = app.evaluate('monitorTimestamp(state.alerts[0])');
    assert.equal(app.evaluate('filtered().length'), 1);
    app.evaluate('receiveAlerts([{...state.alerts[0], bf_back: 1.6}])');
    assert.equal(app.evaluate('betfairPrice(state.alerts[0])'), 1.6);
    app.evaluate('receiveAlerts([{...state.alerts[0], bf_back: 1.4}])');
    assert.equal(app.evaluate('betfairPrice(state.alerts[0])'), 1.4);
    assert.equal(app.evaluate('filtered().length'), 0);
    assert.equal(app.state.alerts.length, 1);
    assert.equal(app.evaluate('monitorTimestamp(state.alerts[0])'), receivedAt);
    app.evaluate('receiveAlerts([{...state.alerts[0], bf_back: 1.7}])');
    assert.equal(app.evaluate('filtered().length'), 1);
});

test('saved drop-time odds stay visible as history and never masquerade as a current quote', () => {
    const app = harness([{...base, bf_back: null, bf_at_drop: 3.1}], {higher: true});
    assert.equal(app.evaluate('betfairPrice(state.alerts[0])'), null);
    assert.equal(app.evaluate('filtered().length'), 0);
    const visible = harness([{...base, bf_back: null, bf_at_drop: 3.1}]);
    visible.evaluate('render()');
    assert.match(visible.output.innerHTML, /3\.10 \(at drop time\)/);
    visible.evaluate('receiveAlerts([{...state.alerts[0], bf_back: 1.6}]); render()');
    assert.equal(visible.evaluate('betfairPrice(state.alerts[0])'), 1.6);
    assert.match(visible.output.innerHTML, /3\.10 \(at drop time\)/);
});

test('main monitor renders every eligible row beyond the animation limit', () => {
    const app = harness(Array.from({length: 250}, (_, index) => ({...base, alert_key: `row-${index}`})));
    app.evaluate('render()');
    assert.equal((app.output.innerHTML.match(/data-alert-key=/g) || []).length, 250);
});

test('late provider alerts sort by monitor arrival and keep their full seven minutes', () => {
    const now = 1800000000000;
    const app = harness([
        { ...base, alerted: now / 1000 - 30, monitor_received_at: now / 1000 - 10 },
    ], { now });
    app.evaluate('receiveAlerts([...state.alerts, {...state.alerts[0], alert_key: "late", alerted: 1799999400, monitor_received_at: 1800000000}])');

    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'late,first');
    app.advance(419999);
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'late');
    app.advance(1);
    assert.equal(app.evaluate('filtered().length'), 0);
});


test('Betfair quotes survive gaps, show movement, and stay out of the current-price filter while unavailable', () => {
    const app = harness([{...base, bf_back: 2.1}]);
    app.evaluate('receiveAlerts([{...state.alerts[0], bf_back: 1.95}]); render()');
    assert.match(app.output.innerHTML, /2.10 → 1.95/);
    app.evaluate('receiveAlerts([{...state.alerts[0], bf_back: null}]); render()');
    assert.match(app.output.innerHTML, /2.10 → 1.95/);
    assert.match(app.output.innerHTML, /Last available quote/);
    assert.equal(app.evaluate('betfairPrice(state.alerts[0])'), null);
    assert.equal(app.evaluate('betfairDisplayPrice(state.alerts[0])'), 1.95);
    app.evaluate('receiveAlerts([{...state.alerts[0], bf_back: 1.8}]); render()');
    assert.match(app.output.innerHTML, /2.10 → 1.80/);
    assert.doesNotMatch(app.output.innerHTML, /Last available quote/);
    app.advance(420001);
    app.evaluate('receiveAlerts([]); render()');
    assert.equal(app.state.alerts.length, 0);
});

test('unmatched Betfair markets stay blank and archive quotes never inherit live movement', () => {
    const app = harness([{...base, bf_back: null}]);
    app.evaluate('receiveAlerts([{...state.alerts[0], bf_back: null}]); render()');
    assert.equal(app.evaluate('betfairDisplayPrice(state.alerts[0])'), null);
    assert.doesNotMatch(app.output.innerHTML, /Last available quote/);
    app.state.archive = true;
    app.evaluate('receiveAlerts([{...state.alerts[0], bf_back: 2.2, bf_first_back: 3.1, bf_last_back: 2.8}]); render()');
    assert.equal(app.evaluate('betfairMovement(state.alerts[0])'), '2.20');
});


test('partial snapshots retain the last Betfair quote without treating it as a fresh price', () => {
    const app = harness([base]);
    app.evaluate('receiveAlerts([]); render()');
    assert.equal(app.evaluate('betfairPrice(state.alerts[0])'), null);
    assert.equal(app.evaluate('betfairDisplayPrice(state.alerts[0])'), 1.54);
    assert.match(app.output.innerHTML, /Last available quote/);
});


test('Main Markets accepts full-match totals for every sport using its own scoring range', () => {
    const sports = [['soccer', 2.5], ['tennis', 22.5], ['basketball', 220.5], ['ice-hockey', 5.5], ['baseball', 8.5], ['cricket', 180.5], ['esports', 26.5], ['american-football', 47.5]];
    const app = harness(sports.map(([sport, point]) => ({...base, sport, point, sect: 'Total', alert_key: sport, period: 0, bf_back: null})));
    app.state.marketFilter = 'main';
    assert.equal(app.evaluate('filtered().length'), 8);
    for (const [sport] of sports) {
        app.state.sport = sport;
        app.evaluate('render()');
        assert.match(app.output.innerHTML, new RegExp(`data-alert-key="${sport}"`));
    }
    app.evaluate('receiveAlerts([{...state.alerts[0], alert_key: "football-large-total", sport: "soccer", point: 6.5}])');
    assert.equal(app.evaluate('filtered().some(alert => alert.alert_key === "football-large-total")'), false);
    app.state.marketFilter = 'handicap';
    assert.equal(app.evaluate('filtered().length'), 0);
});


test('unavailable Betfair tooltip anchors to its cell when no child element exists', () => {
    const app = harness([{...base, bf_back: null}]);
    app.evaluate(`
        globalThis.tooltipTexts = {betfair: 'Betfair quote unavailable'};
        globalThis.tooltip = {style: {}, offsetWidth: 280, offsetHeight: 80};
        globalThis.cell = {firstElementChild: null, setAttribute() {}, getBoundingClientRect() {return {left: 100, top: 200, bottom: 235, width: 100};}};
        document.getElementById = () => tooltip;
        globalThis.window = {innerWidth: 1000, innerHeight: 800};
    `);
    app.evaluate(source.slice(source.indexOf('                window.showOddsTooltip ='), source.indexOf('            })();')));
    app.evaluate("window.showOddsTooltip({currentTarget: cell}, 'betfair')");
    assert.equal(app.evaluate('tooltip.textContent'), 'Betfair quote unavailable');
    assert.equal(app.evaluate('tooltip.style.visibility'), 'visible');
    assert.equal(app.evaluate('tooltip.style.top'), '116px');
});


test('prematch views hide live and kicked-off rows even with legacy all or live filters', () => {
    const app = harness([base, {...base, alert_key: 'live', is_live: true}, {...base, alert_key: 'started', start_timestamp: 1000}, {...base, alert_key: 'future', start_timestamp: 1060}]);
    for (const archive of [false, true]) {
        app.state.archive = archive;
        app.state.alerts.forEach(alert => { alert.expires_at = 2000; });
        app.state.mode = 'all';
        assert.equal(app.evaluate('filtered().length'), 2);
        app.state.mode = 'live';
        assert.equal(app.evaluate('filtered().length'), 0);
    }
    assert.match(source, /mode: 'prematch', sportCounts:/);
    assert.doesNotMatch(source, /data-mode="(?:live|all)"/);
});


test('country precedes the league in live-refresh and archive tables without duplicate prefixes', () => {
    for (const archive of [false, true]) {
        for (const view of ['simple', 'advanced']) {
            const app = harness([{...base, country: 'Uruguay', league: 'Segunda Division'}]);
            Object.assign(app.state, {archive, view});
            app.evaluate('render()');
            assert.match(app.output.innerHTML, />Uruguay - Segunda Division<\/small>/);
            assert.equal(app.evaluate('competitionLabel({country: "Uruguay", league: "Uruguay - Segunda Division"})'), 'Uruguay - Segunda Division');
            assert.equal(app.evaluate('competitionLabel({country: "", league: "Premier League"})'), 'Unknown country - Premier League');
        }
    }
    assert.match(source, /escapeHtml\(competitionLabel\(alert\)\)/);
});

test('country and championship labels escape HTML from incoming alerts', () => {
    const app = harness([{...base, country: '<Uruguay>', league: '<Segunda Division>'}]);
    app.evaluate('render()');
    assert.match(app.output.innerHTML, /&lt;Uruguay(?:>|&gt;) - &lt;Segunda Division(?:>|&gt;)/);
    assert.doesNotMatch(app.output.innerHTML, /<Uruguay>|<Segunda Division>/);
});


test('Market Shock includes exactly 8% and above without rounding smaller drops or accumulating moves', () => {
    const app = harness([], { shock: true });
    assert.equal(app.evaluate('isMarketShock({from_price: 2.5, to_price: 2.3})'), true);
    assert.equal(app.evaluate('isMarketShock({from_price: "3.00", to_price: "2.76"})'), true);
    assert.equal(app.evaluate('isMarketShock({from_price: 2.5, to_price: 2.301})'), false);
    assert.equal(app.evaluate('isMarketShock({from_price: 2.5, to_price: 2})'), true);
    assert.equal(app.evaluate('isMarketShock({from_price: 2.5, to_price: 2.5})'), false);
    assert.equal(app.evaluate('isMarketShock({from_price: 0, to_price: 2})'), false);
    assert.equal(app.evaluate('isMarketShock({from_price: Infinity, to_price: 2})'), false);
    assert.equal(app.evaluate('isMarketShock({from_price: 2.5, to_price: 1})'), false);
});

test('Market Shock checkbox filters raw current-to-new changes and combines with league and Betfair filters', () => {
    const alerts = [
        {...base, alert_key: 'eight', from_price: 2.5, to_price: 2.3, bf_back: 2.5, league_filter_key: 'a'},
        {...base, alert_key: 'below', from_price: 2.5, to_price: 2.301, bf_back: 2.5, league_filter_key: 'a'},
        {...base, alert_key: 'no-betfair', from_price: 2.5, to_price: 2, bf_back: null, league_filter_key: 'a'},
        {...base, alert_key: 'other-league', from_price: 2.5, to_price: 2, bf_back: 2.5, league_filter_key: 'b'},
    ];
    const plain = harness(alerts);
    assert.equal(plain.evaluate('filtered().length'), 4);
    const shock = harness(alerts, {shock: true});
    assert.equal(shock.evaluate('filtered().length'), 3);
    const combined = harness(alerts, {shock: true, higher: true, leagues: ['a']});
    assert.equal(combined.evaluate('filtered().length'), 1);
    assert.equal(combined.evaluate('filtered()[0].alert_key'), 'eight');
});

test('Market Shock archive requests carry the checkbox and ignore stale responses after toggling it', async () => {
    const app = harness([base], { shock: true });
    Object.assign(app.state, { archive: true, sport: 'soccer' });
    app.evaluate(`
        globalThis.window = {location: {origin: 'https://example.test'}};
        globalThis.requests = [];
        root.dataset.sourceUrl = '/pinnacle';
        globalThis.updateStatus = () => {};
        globalThis.fetch = async url => {
            requests.push(Object.fromEntries(url.searchParams));
            return {ok: true, json: async () => {
                shockOnly.checked = false;
                return {data: [], has_more: false, next_cursor: null};
            }};
        };
    `);
    app.evaluate(source.slice(source.indexOf('                let refreshing ='), source.indexOf('                let realtimeAttempts =')));
    await app.evaluate('refresh()');
    assert.equal(app.evaluate('requests[0].shock'), '1');
    assert.equal(app.state.alerts.length, 1);
    await app.evaluate('refresh()');
    assert.equal(app.evaluate('requests[1].shock'), '0');
    assert.equal(app.state.alerts.length, 0);
});

test('unified Edge % uses the same NVP comparison, sign and color across both languages and views', () => {
    for (const locale of ['en', 'ka']) {
        for (const view of ['simple', 'advanced']) {
            for (const [bf, nvp, expected, color] of [[4, 3.67, '+9.0%', 'emerald'], [7.2, 6.5, '+10.8%', 'emerald'], [7.2, 7.21, '-0.1%', 'danger-edge'], [3.67, 3.67, '0.0%', 'slate-500']]) {
                const app = harness([{...base, from_price: 3.82, to_price: 3.48, bf_back: bf, nvp}], {locale});
                app.state.view = view;
                app.evaluate('render()');
                assert.equal(app.evaluate('betfairEdgeText(state.alerts[0])'), expected);
                assert.match(app.evaluate('edgeClass(edge(state.alerts[0]))'), new RegExp(color));
                assert.equal((app.output.innerHTML.match(/data-edge-percent/g) || []).length, 1);
                assert.doesNotMatch(app.output.innerHTML, />BF:|>Pinnacle:/);
            }
        }
    }
});

test('unified Edge % never substitutes raw Pinnacle odds or stale Betfair display quotes for missing inputs', () => {
    const app = harness([{...base, to_price:3.48, nvp:null, bf_back:4, bf_last_back:4}]);
    assert.equal(app.evaluate('edge(state.alerts[0])'), null);
    assert.equal(app.evaluate('edge({...state.alerts[0], nvp:3.67, bf_back:null})'), null);
    assert.equal(app.evaluate('edge({...state.alerts[0], nvp:3.67, bf_back:Infinity})'), null);
    assert.equal(app.evaluate('edgeText(-0.00001)'), '0.0%');
    assert.match(app.evaluate('edgeClass(-0.00001)'), /slate-500/);
    assert.equal(app.evaluate('edge({...state.alerts[0], full_market:[{outcome:"Over", nvp:3.67}]})').toFixed(6), '8.991826');
});
