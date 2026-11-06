import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/views/users/sports/football/marketActivity.blade.php', import.meta.url), 'utf8');
const helpers = source.slice(source.indexOf('                const sportKey ='), source.indexOf('                const filtered ='));
const renderer = source.slice(source.indexOf('                const filtered ='), source.indexOf('                const openModal ='));
const base = { alert_key: 'first', sport: 'soccer', home: 'Home Team', away: 'Away Team', league: 'Super League', sect: 'Total', point: 2.5, outcome: 'Over', from_price: 1.63, to_price: 1.47, nvp: 1.52, bf_back: 1.54, alerted: 1000 };
function harness(alerts = [base], options = {}) {
    let now = options.now ?? 1000000;
    const translations = JSON.parse(readFileSync(new URL(`../../lang/${options.locale ?? 'en'}.json`, import.meta.url), 'utf8'));
    const matchStartLabels = {
        minute: translations['Starts in :count min'],
        minutes: translations['Starts in :count mins'],
        hour: translations['Starts in :count hour'],
        hours: translations['Starts in :count hours'],
        day: translations['Starts in :count day'],
        days: translations['Starts in :count days'],
        now: translations['Starting now'],
    };
    const output = { innerHTML: '', textContent: '' };
    const state = { alerts, view: 'advanced', mode: 'prematch', sport: 'all', collapsedGroups: new Set(), flashes: new Map() };
    const context = vm.createContext({ state, matchStartLabels, root: { dataset: { timezone: options.timezone ?? 'UTC' }, querySelectorAll: () => [], querySelector: () => output }, Date: { now: () => now }, document: { documentElement: { lang: options.locale ?? 'en' }, createElement: () => ({ set textContent(value) { this.innerHTML = String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('"', '&quot;'); } }) } });
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

test('simple has exactly four columns; advanced adds fair price, both edges and time', () => {
    const app = harness();
    app.evaluate('render()');
    assert.equal((app.output.innerHTML.match(/scope="col"/g) || []).length, 7);
    assert.match(app.output.innerHTML, /BF: \+1.3%/);
    assert.match(app.output.innerHTML, /About NVP/);
    assert.match(app.output.innerHTML, /aria-expanded="true"/);
    assert.doesNotMatch(app.output.innerHTML, /min-w-\[/);
    app.state.view = 'simple';
    app.evaluate('render()');
    assert.equal((app.output.innerHTML.match(/scope="col"/g) || []).length, 4);
    assert.doesNotMatch(app.output.innerHTML, /About NVP|BF:/);
    assert.match(app.output.innerHTML, /Home Team v Away Team/);
    assert.match(app.output.innerHTML, /Over 2.5 Goals/);
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

test('start windows are cumulative, include boundaries, and exclude missing, past and live starts', () => {
    const now = Date.parse('2026-11-06T12:00:00Z');
    const alerts = [-1, 0, 3600, 3601, 10800, 10801].map(seconds => ({
        ...base, alert_key: String(seconds), start_timestamp: now / 1000 + seconds,
    }));
    alerts.push(
        {...base, alert_key: 'missing'},
        {...base, alert_key: 'invalid', start_timestamp: 'bad'},
        {...base, alert_key: 'too-large', start_timestamp: 1e20},
        {...base, alert_key: 'live', start_timestamp: now / 1000, is_live: true},
    );
    const app = harness(alerts, {now});
    app.state.timeFilter = '1h';
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), '0,3600');
    app.state.timeFilter = '3h';
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), '0,3600,3601,10800');
    app.state.mode = 'live';
    assert.equal(app.evaluate('filtered().length'), 0);
    app.state.timeFilter = 'all';
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'live');
    app.state.mode = 'prematch';
    assert.equal(app.evaluate('filtered().length'), alerts.length - 1);
});

test('calendar filters use the customer timezone across midnight and daylight saving changes', () => {
    const now = Date.parse('2026-11-06T19:00:00Z');
    const app = harness([
        {...base, alert_key: 'today', start_timestamp: Date.parse('2026-11-07T01:00:00Z') / 1000},
        {...base, alert_key: 'tomorrow', start_timestamp: Date.parse('2026-11-07T05:00:00Z') / 1000},
    ], {now, timezone: 'America/New_York'});
    app.state.timeFilter = 'today';
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'today');
    app.state.timeFilter = 'future';
    assert.equal(app.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'tomorrow');

    const dst = harness([
        {...base, alert_key: 'same-day', start_timestamp: Date.parse('2026-11-02T04:30:00Z') / 1000},
        {...base, alert_key: 'next-day', start_timestamp: Date.parse('2026-11-02T05:00:00Z') / 1000},
    ], {now: Date.parse('2026-11-01T04:30:00Z'), timezone: 'America/New_York'});
    dst.state.timeFilter = 'today';
    assert.equal(dst.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'same-day');
    dst.state.timeFilter = 'future';
    assert.equal(dst.evaluate('filtered().map(alert => alert.alert_key).join(",")'), 'next-day');
});

test('start filters preserve market and sport selection and reevaluate incoming updates and elapsed time', () => {
    const now = Date.parse('2026-11-06T12:00:00Z');
    const app = harness([
        {...base, start_timestamp: now / 1000 + 1800},
        {...base, alert_key: 'tennis', sport: 'tennis', home: 'Player A', away: 'Player B', sect: 'Moneyline', point: null, start_timestamp: now / 1000 + 1800},
        {...base, alert_key: 'handicap', sect: 'Asian Handicap', start_timestamp: now / 1000 + 1800},
    ], {now});
    app.state.timeFilter = '1h';
    app.state.marketFilter = 'main';
    app.state.sport = 'soccer';
    app.evaluate('render()');
    assert.match(app.output.innerHTML, /Home Team v Away Team/);
    assert.doesNotMatch(app.output.innerHTML, /Player A v Player B|Asian Handicap/);
    assert.match(app.output.innerHTML, /text-\[var\(--text-amber-700\)\]/);
    assert.match(app.output.innerHTML, /06 Nov 2026, 12:30 UTC/);
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
    for (const locale of ['en', 'ka']) {
        const app = harness([{...base, league: 'Country - League', start_timestamp: now / 1000 + 600}], {now, locale});
        for (const view of ['simple', 'advanced']) {
            app.state.view = view;
            app.evaluate('render()');
            assert.match(app.output.innerHTML, /Country - League<\/small><small[^>]*text-\[var\(--text-amber-700\)\]/);
            assert.ok(app.output.innerHTML.includes(locale === 'en' ? ' · Starts in 10 mins' : ' · იწყება 10 წუთში'));
            for (const [seconds, english, georgian] of [
                [60, 'Starts in 1 min', 'იწყება 1 წუთში'],
                [3600, 'Starts in 1 hour', 'იწყება 1 საათში'],
                [7200, 'Starts in 2 hours', 'იწყება 2 საათში'],
                [86400, 'Starts in 1 day', 'იწყება 1 დღეში'],
                [172800, 'Starts in 2 days', 'იწყება 2 დღეში'],
                [0, 'Starting now', 'ახლა იწყება'],
            ]) {
                app.state.alerts[0].start_timestamp = now / 1000 + seconds;
                app.evaluate('render()');
                assert.ok(app.output.innerHTML.includes(locale === 'en' ? english : georgian));
            }
            app.state.alerts[0].start_timestamp = now / 1000 + 600;
        }
        app.advance(300000);
        app.evaluate('render()');
        assert.ok(app.output.innerHTML.includes(locale === 'en' ? ' · Starts in 5 mins' : ' · იწყება 5 წუთში'));
        app.advance(600000);
        app.evaluate('render()');
        assert.ok(!app.output.innerHTML.includes(locale === 'en' ? ' · Starts in ' : ' · იწყება '));
        app.state.alerts[0].start_timestamp = now / 1000 + 3600;
        app.state.alerts[0].is_live = true;
        app.state.mode = 'live';
        app.evaluate('render()');
        assert.ok(!app.output.innerHTML.includes(locale === 'en' ? ' · Starts in ' : ' · იწყება '));
    }
});
