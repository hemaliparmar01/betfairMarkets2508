import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/views/users/sports/football/marketActivity.blade.php', import.meta.url), 'utf8');
const helpers = source.slice(source.indexOf('                const sportKey ='), source.indexOf('                const filtered ='));
const renderer = source.slice(source.indexOf('                const filtered ='), source.indexOf('                const openModal ='));
const base = { alert_key: 'first', sport: 'soccer', home: 'Home Team', away: 'Away Team', league: 'Super League', sect: 'Total', point: 2.5, outcome: 'Over', from_price: 1.63, to_price: 1.47, nvp: 1.52, bf_back: 1.54, alerted: 1000 };
function harness(alerts = [base]) {
    let now = 1000000;
    const output = { innerHTML: '', textContent: '' };
    const state = { alerts, view: 'advanced', mode: 'prematch', sport: 'all', collapsedGroups: new Set(), flashes: new Map() };
    const context = vm.createContext({ state, root: { querySelectorAll: () => [], querySelector: () => output }, Date: { now: () => now }, document: { createElement: () => ({ set textContent(value) { this.innerHTML = String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('"', '&quot;'); } }) } });
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
    assert.equal(app.evaluate('edgeClass(-1)'), 'text-[#b52f3c] dark:text-red-400');
    assert.equal(app.evaluate('edgeClass(1)'), 'text-emerald-600 dark:text-emerald-400');
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
