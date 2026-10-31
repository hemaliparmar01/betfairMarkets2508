import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/users/js/sports/football.js', import.meta.url), 'utf8');
const highlightCode = source.slice(source.indexOf('const footballGoalState ='), source.indexOf('let openLiveStatsMatchId'));

test('football realtime handler registers once and coalesces a burst while preserving selected filters', () => {
    const subscriptions = [];
    const timers = new Map();
    const updates = [];
    let serial = 0;
    const context = vm.createContext({
        document: { visibilityState: 'visible' },
        window: { Echo: { channel: channel => ({ listen: (name, callback) => subscriptions.push({ channel, name, callback }) }) } },
        setTimeout: callback => { timers.set(++serial, callback); return serial; },
        updates,
    });
    vm.runInContext(`let footballLoading = false, footballRealtimeLoading = false, footballPendingRealtime = false, footballRealtimeTimer = null;
        const getSelectedFilters = () => ({ type: 'by_time', market_name: 'active' });
        const callFixtureData = (filters, options) => updates.push({ filters, options });`, context);
    vm.runInContext(source.slice(source.indexOf('const scheduleFootballRealtimeRefresh ='), source.indexOf('if (footballLazyLoader) {', source.indexOf('const scheduleFootballRealtimeRefresh ='))), context);
    vm.runInContext(source.slice(source.indexOf("window.Echo.channel('sports.football.live')"), source.indexOf("window.Echo.channel('sports.football.live')") + source.slice(source.indexOf("window.Echo.channel('sports.football.live')")).indexOf(';') + 1), context);
    assert.equal(subscriptions.length, 1);
    assert.equal(subscriptions[0].channel, 'sports.football.live');
    assert.equal(subscriptions[0].name, '.score.updated');
    for (let index = 0; index < 10; index++) subscriptions[0].callback({ refresh: true });
    assert.equal(timers.size, 1);
    for (const [id, callback] of timers) { timers.delete(id); callback(); }
    assert.equal(updates.length, 1);
    assert.equal(updates[0].filters.type, 'by_time');
    assert.equal(updates[0].options.preserveLoaded, true);
    assert.equal(updates[0].options.realtime, true);
    vm.runInContext('footballRealtimeLoading = true', context);
    subscriptions[0].callback({ refresh: true });
    assert.equal(vm.runInContext('footballPendingRealtime', context), true);
    assert.equal(timers.size, 0);
    context.document.visibilityState = 'hidden';
    subscriptions[0].callback({ refresh: true });
    assert.equal(updates.length, 1);
});

test('shock countdown uses original timestamps across rerenders and never revives expired alerts', () => {
    let now = 1000;
    let cards = [];
    const card = expires => ({ dataset: { shockExpiresAt: String(expires) }, counter: { textContent: '' }, querySelector() { return this.counter; }, remove() { cards = cards.filter(item => item !== this); } });
    const context = vm.createContext({ Date: { now: () => now * 1000 }, document: { querySelector: () => null, querySelectorAll: selector => selector === '[data-shock-alert]' ? cards : [] } });
    const start = source.indexOf('const refreshFootballTimelineClocks =');
    vm.runInContext('const footballTimelineClockAnchors = new WeakMap();' + source.slice(start, source.indexOf('\n};', start) + 4), context);
    const refresh = () => vm.runInContext('refreshFootballTimelineClocks()', context);
    cards = [card(1900), card(1250)];
    refresh();
    assert.equal(cards[0].counter.textContent, 'Expires in 15:00');
    assert.equal(cards[1].counter.textContent, 'Expires in 04:10');
    now = 1100;
    cards = [card(1900)];
    refresh();
    assert.equal(cards[0].counter.textContent, 'Expires in 13:20');
    now = 1900;
    refresh();
    assert.equal(cards.length, 0);
    cards = [card(1900)];
    now++;
    refresh();
    assert.equal(cards.length, 0);
});

test('football clock lifecycle has one shared interval and resumes once after back navigation', () => {
    const handlers = {};
    const intervals = new Set();
    let serial = 0;
    const context = vm.createContext({ window: { addEventListener: (name, handler) => { handlers[name] = handler; } }, refreshFixtureMinutes() {}, refreshFootballTimelineClocks() {}, setInterval: () => { intervals.add(++serial); return serial; }, clearInterval: id => intervals.delete(id) });
    vm.runInContext('let footballClockInterval = setInterval(refreshFootballTimelineClocks, 1000);' + source.slice(source.indexOf("window.addEventListener('pageshow'"), source.indexOf("window.Echo.channel('sports.football.live')")), context);
    handlers.pageshow({ persisted: true });
    handlers.pageshow({ persisted: true });
    assert.equal(intervals.size, 1);
    handlers.pagehide();
    assert.equal(intervals.size, 0);
    handlers.pageshow({ persisted: true });
    handlers.pageshow({ persisted: true });
    assert.equal(intervals.size, 1);
});

function row(id, home = 0, away = 0, seconds = 40) {
    const classes = () => {
        const values = new Set();
        return { add: (...names) => names.forEach(name => values.add(name)), remove: (...names) => names.forEach(name => values.delete(name)), contains: name => values.has(name) };
    };
    const labels = [];
    const teams = Object.fromEntries(['home', 'away'].map(side => [side, { classList: classes(), append(label) { labels.push({ side, label }); label.remove = () => labels.splice(labels.findIndex(item => item.label === label), 1); } }]));
    return {
        dataset: { homeScore: String(home), awayScore: String(away), matchStatus: 'Live', goalHighlightSeconds: String(seconds) },
        classList: classes(), teams, labels,
        getAttribute: () => id, removeAttribute() {},
        querySelector: selector => teams[selector.includes('"home"') ? 'home' : 'away'],
        querySelectorAll: selector => selector === '[data-goal-indicator]' ? labels.map(item => item.label) : Object.values(teams).filter(team => team.classList.contains('fixture-goal-team')),
    };
}

function harness(initialRows) {
    let now = 100000;
    let rows = initialRows;
    let timerId = 0;
    const timers = new Map();
    const context = vm.createContext({
        Date: { now: () => now },
        document: { querySelectorAll: () => rows, createElement: () => ({ dataset: {} }) },
        window: {
            clearTimeout: id => timers.delete(id),
            setTimeout: (callback, delay) => { const id = ++timerId; timers.set(id, { callback, at: now + delay }); return id; },
        },
    });
    vm.runInContext(highlightCode, context);
    return {
        refresh: changed => vm.runInContext(`scheduleGoalHighlights(${Boolean(changed)})`, context),
        rows: value => { rows = value; },
        advance: milliseconds => { now += milliseconds; for (const [id, timer] of [...timers]) if (timer.at <= now) { timers.delete(id); timer.callback(); } },
        timers,
    };
}

test('initial render and initial snapshot do not flash; changed team flashes for 40 seconds', () => {
    const match = row('1'); const ui = harness([match]); ui.refresh(false);
    match.dataset.homeScore = '1'; ui.refresh(false);
    assert.equal(match.labels.length, 0);
    match.dataset.homeScore = '2'; ui.refresh(true);
    assert(match.classList.contains('fixture-goal-active'));
    assert(match.teams.home.classList.contains('fixture-goal-team'));
    assert.equal(match.labels[0].label.textContent, 'Goal');
    assert.equal(match.labels[0].side, 'home');
    ui.advance(39999); assert.equal(match.labels.length, 1);
    ui.advance(20); assert.equal(match.labels.length, 0);
    assert.equal(ui.timers.size, 0);
});

test('identical events do not extend the effect; a subsequent goal restarts one timer', () => {
    const match = row('1'); const other = row('2'); const ui = harness([match, other]); ui.refresh(false);
    match.dataset.homeScore = '1'; ui.refresh(true);
    ui.advance(20000); ui.refresh(true);
    assert.equal(ui.timers.size, 1);
    ui.advance(20020); assert.equal(match.labels.length, 0);
    match.dataset.homeScore = '2'; ui.refresh(true);
    ui.advance(20000); match.dataset.awayScore = '1'; ui.refresh(true);
    assert.equal(match.labels[0].side, 'away');
    assert.equal(ui.timers.size, 1);
    assert.equal(other.labels.length, 0);
    ui.advance(39999); assert.equal(match.labels.length, 1);
    ui.advance(20); assert.equal(match.labels.length, 0);
});

test('effect survives row replacement; corrections, finished status and removed rows clean up', () => {
    const match = row('1'); const ui = harness([match]); ui.refresh(false);
    match.dataset.homeScore = '1'; ui.refresh(true);
    const replaced = row('1', 1); ui.rows([replaced]); ui.refresh(false);
    assert.equal(replaced.labels.length, 1);
    replaced.dataset.homeScore = '0'; ui.refresh(true);
    assert.equal(replaced.labels.length, 0);
    replaced.dataset.awayScore = '1'; ui.refresh(true);
    replaced.dataset.matchStatus = 'Finished'; ui.refresh(true);
    assert.equal(replaced.labels.length, 0);
    ui.rows([]); ui.refresh(false); assert.equal(ui.timers.size, 0);
});

test('duration is configurable and missing scores never manufacture a goal', () => {
    const match = row('1', 0, 0, 5); match.dataset.homeScore = '';
    const ui = harness([match]); ui.refresh(false);
    match.dataset.homeScore = '1'; ui.refresh(true); assert.equal(match.labels.length, 0);
    match.dataset.awayScore = '1'; ui.refresh(true); assert.equal(match.labels.length, 1);
    ui.advance(5020); assert.equal(match.labels.length, 0);
});


test('a verified provider goal signal can highlight the first actual update without flashing snapshots', () => {
    const match = row('1'); match.dataset.goalSignal = '0';
    const ui = harness([match]); ui.refresh(false);
    match.dataset.homeScore = '1'; match.dataset.goalSignal = '999'; ui.refresh(false);
    assert.equal(match.labels.length, 1);
    ui.advance(20000); ui.refresh(false);
    ui.advance(20020); assert.equal(match.labels.length, 0);
});

test('language switching preserves current fixture filters and existing page query parameters', () => {
    const appSource = readFileSync(new URL('../../resources/users/js/app.js', import.meta.url), 'utf8');
    let submit;
    const form = { elements: { return_to: { value: '' } }, addEventListener: (name, callback) => { assert.equal(name, 'submit'); submit = callback; } };
    const context = vm.createContext({ URL, window: { location: { href: 'http://localhost/football?type=all&page=2&search=team#fixtures' } }, document: { querySelector: selector => selector === '[data-locale-switch]' ? form : selector === '.filter-pill.active' ? { dataset: { filter: 'by_time' } } : { value: 'OVER_UNDER_25' } } });
    vm.runInContext(appSource.slice(appSource.indexOf("document.querySelector('[data-locale-switch]')"), appSource.indexOf('// ===== SCROLL TOP =====')), context);
    submit({ currentTarget: form });
    const restored = new URL(form.elements.return_to.value, 'http://localhost');
    assert.equal(restored.searchParams.get('type'), 'by_time');
    assert.equal(restored.searchParams.get('market_name'), 'OVER_UNDER_25');
    assert.equal(restored.searchParams.get('page'), '2');
    assert.equal(restored.searchParams.get('search'), 'team');
    assert.equal(restored.hash, '#fixtures');
});

test('the shared theme toggle restores saved preference before markup and switches both ways without reloads', () => {
    const module = readFileSync(new URL('../../resources/users/js/auth/dom.js', import.meta.url), 'utf8');
    const main = readFileSync(new URL('../../resources/views/users/layout/main.blade.php', import.meta.url), 'utf8');
    const bootstrap = main.slice(main.indexOf('<body')).match(/<script>([\s\S]*?)<\/script>/)[1];
    const classes = new Set();
    const attributes = {};
    const icon = { tagName: 'I', className: '' };
    let click;
    let stored = 'dark';
    const context = vm.createContext({
        document: {
            body: { classList: { add: name => classes.add(name), contains: name => classes.has(name), toggle: name => { if (classes.has(name)) { classes.delete(name); return false; } classes.add(name); return true; } } },
            documentElement: { style: {} },
            getElementById: () => ({ querySelector: () => icon, setAttribute: (name, value) => { attributes[name] = value; }, addEventListener: (name, callback) => { assert.equal(name, 'click'); assert.equal(click, undefined); click = callback; } }),
        },
        localStorage: { getItem: key => { assert.equal(key, 'bf-markets-theme'); return stored; }, setItem: (key, value) => { assert.equal(key, 'bf-markets-theme'); stored = value; } },
    });
    vm.runInContext(bootstrap, context);
    assert.equal(classes.has('dark-mode'), true);
    vm.runInContext(module.slice(module.indexOf('const themeToggle =')), context);
    assert.equal(attributes['aria-pressed'], 'true');
    assert.equal(icon.className, 'fas fa-sun');
    click();
    assert.equal(stored, 'light');
    assert.equal(classes.has('dark-mode'), false);
    assert.equal(context.document.documentElement.style.colorScheme, 'light');
    assert.equal(icon.className, 'fas fa-moon');
    click();
    assert.equal(stored, 'dark');
    assert.equal(classes.has('dark-mode'), true);
    assert.equal(context.document.documentElement.style.colorScheme, 'dark');
    for (const path of ['../../resources/views/admin/layouts/main.blade.php', '../../resources/views/admin/auth/login.blade.php']) {
        const layout = readFileSync(new URL(path, import.meta.url), 'utf8');
        assert.equal(layout.slice(layout.indexOf('<body')).match(/<script>([\s\S]*?)<\/script>/)[1], bootstrap);
    }
});

test('theme switching remains usable with blocked storage, missing buttons and admin icons', () => {
    const module = readFileSync(new URL('../../resources/users/js/auth/dom.js', import.meta.url), 'utf8');
    for (const hasButton of [false, true]) {
        let dark = false;
        let click;
        const icon = { tagName: 'SPAN', textContent: '' };
        const context = vm.createContext({
            document: {
                body: { classList: { contains: () => dark, toggle: () => { dark = !dark; return dark; } } },
                documentElement: { style: {} },
                getElementById: () => hasButton ? { querySelector: () => icon, setAttribute: () => {}, addEventListener: (name, callback) => { click = callback; } } : null,
            },
            localStorage: { setItem: () => { throw new Error('Storage blocked'); } },
        });
        vm.runInContext(module.slice(module.indexOf('const themeToggle =')), context);
        if (hasButton) {
            assert.equal(icon.textContent, '☾');
            assert.doesNotThrow(() => click());
            assert.equal(dark, true);
            assert.equal(icon.textContent, '☀');
            click();
            assert.equal(dark, false);
        }
    }
});

test('shared theme aliases are acyclic and preserve readable semantic text, status and button pairs', () => {
    const css = readFileSync(new URL('../../resources/users/css/auth.css', import.meta.url), 'utf8');
    const palettes = {};
    for (const [name, expression] of [['light', /:root \{([\s\S]*?)\n\}/], ['dark', /\.dark-mode, \.dark-mode \.customer-auth \{([\s\S]*?)\n\}/]]) {
        const entries = [...css.match(expression)[1].matchAll(/(--[\w-]+):\s*([^;]+);/g)].map(match => [match[1], match[2]]);
        assert.equal(new Set(entries.map(([key]) => key)).size, entries.length, `${name} duplicate tokens`);
        palettes[name] = Object.fromEntries(entries);
    }
    const dark = { ...palettes.light, ...palettes.dark };
    for (const palette of [palettes.light, dark]) {
        for (const value of Object.values(palette)) {
            let resolved = value;
            for (let index = 0; index < 20; index++) {
                const next = resolved.replace(/var\((--[\w-]+)\)/g, (match, token) => palette[token] ?? match);
                if (next === resolved) break;
                resolved = next;
            }
            assert.doesNotMatch(resolved, /var\(--(?!color-)/, value);
        }
    }
    for (const [foreground, background] of [['--text-main', '--surface'], ['--text-soft', '--surface-soft'], ['--text-muted', '--bg'], ['--primary', '--surface'], ['--danger', '--surface'], ['--success', '--surface'], ['--warning', '--surface'], ['--text-on-primary', '--button-primary'], ['--text-on-primary', '--button-secondary']]) {
        const luminances = [foreground, background].map(key => (dark[key].startsWith('#') ? [...(dark[key].length === 4 ? dark[key].slice(1).split('').map(channel => channel + channel).join('') : dark[key].slice(1)).matchAll(/../g)].map(match => parseInt(match[0], 16)) : dark[key].split(' ').map(Number)).map(channel => channel / 255).map(channel => channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4).reduce((sum, channel, index) => sum + channel * [.2126, .7152, .0722][index], 0));
        const ratio = (Math.max(...luminances) + .05) / (Math.min(...luminances) + .05);
        assert.ok(ratio >= 4.5, `${foreground} on ${background}: ${ratio}`);
    }
});
