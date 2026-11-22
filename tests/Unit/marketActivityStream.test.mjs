import assert from 'node:assert/strict';
import test from 'node:test';
import { createMarketQuoteStream, mergeMarketQuote } from '../../resources/users/js/marketActivityStream.js';

const event = () => ({ id: 7, streamMatchKey: 'a', streamMarketKey: '123_WIN', lastMarketUpdate: 1, runners: [{ id: 1, name: 'Favorite', back: 1.8, backSize: 1, laySize: 1 }], locked: false });
const quote = (at = 2) => ({ matchKey: 'a', marketKey: '123_WIN', lastMarketUpdate: at, deltaValue: 1.25, balance: 'lay', runners: [
    { id: 1, name: 'Favorite', back: 1.8, lay: 1.82, backSize: 1600, laySize: 40.72 },
    { id: 2, name: 'Draw', back: 3.2, lay: 3.3, backSize: 1600, laySize: 3000 },
] });
function clock() {
    let at = 0, id = 0;
    const timers = new Map();
    return { now: () => at, schedule(fn, delay) { timers.set(++id, { fn, at: at + delay }); return id; }, cancel(id) { timers.delete(id); },
        advance(ms) { at += ms; for (const [id, timer] of timers) if (timer.at <= at) { timers.delete(id); timer.fn(); } }, get size() { return timers.size; } };
}
test('favorite pressure cross-maps the current book and excludes other runners', () => {
    const next = mergeMarketQuote(event(), quote());
    assert.ok(Math.abs(next.backPercent - 2.4818372428) < .0001);
    assert.ok(Math.abs(next.layPercent - 97.5181627572) < .0001);
    assert.equal(next.balance, 'lay');
    assert.equal(next.delta, '+1.3 pp');
    assert.equal(next.totalBackSize, 3200);
});
test('outdated snapshots, other markets, and locked values never overwrite the displayed quote', () => {
    const previous = event();
    assert.equal(mergeMarketQuote(previous, quote(0)), previous);
    assert.equal(mergeMarketQuote(previous, { ...quote(), marketKey: 'another' }), previous);
    const locked = { ...previous, locked: true };
    assert.equal(mergeMarketQuote(locked, quote()), locked);
    assert.equal(mergeMarketQuote(previous, { ...quote(), liquidityValid: false }).backPercent, null);
});
for (const interval of [140, 1000, 2000, 3000, 5000, 8000, 10000]) {
    test(`WS bursts apply only the latest real quote at the selected ${interval}ms speed`, () => {
        const timer = clock(), painted = [];
        const stream = createMarketQuoteStream(rows => painted.push(rows), { ...timer, visible: () => true, interval });
        stream.start(); stream.receive({ sent_at: 1, markets: [quote(2)] }); timer.advance(0);
        for (let i = 3; i < 50; i++) stream.receive({ sent_at: i, markets: [quote(i)] });
        assert.equal(timer.size, 1); timer.advance(interval - 1); assert.equal(painted.length, 1);
        timer.advance(1); assert.equal(painted.length, 2); assert.equal(painted[1][0].lastMarketUpdate, 49);
        stream.stop(); assert.equal(timer.size, 0);
    });
}
test('hidden pages buffer updates, resume once, and use new speed without duplicate timers', () => {
    const timer = clock(); let hidden = true; const painted = [];
    const stream = createMarketQuoteStream(rows => painted.push(rows), { ...timer, visible: () => !hidden });
    stream.start(); stream.receive({ sent_at: 1, markets: [quote()] }); timer.advance(5000); assert.equal(painted.length, 0);
    hidden = false; stream.request(); timer.advance(0); assert.equal(painted.length, 1);
    stream.receive({ sent_at: 2, markets: [quote(3)] }); stream.setSpeed(140); timer.advance(140); assert.equal(painted.length, 2);
});
test('HTTP recovery cannot roll prices backwards or bypass the selected stream interval', () => {
    const timer = clock(); const stream = createMarketQuoteStream(() => {}, { ...timer, visible: () => true });
    stream.start(); stream.receive({ sent_at: 1, markets: [quote()] });
    assert.equal(stream.reconcile([event()])[0].lastMarketUpdate, 1);
    timer.advance(0);
    assert.equal(stream.reconcile([event()])[0].lastMarketUpdate, 2);
    stream.receive({ sent_at: 2, markets: [quote(3)] });
    assert.equal(stream.reconcile([event()])[0].lastMarketUpdate, 2);
    timer.advance(3000); assert.equal(stream.reconcile([event()])[0].lastMarketUpdate, 3);
});
test('a large book is applied atomically only after all fragments arrive', () => {
    const timer = clock(), painted = [];
    const stream = createMarketQuoteStream(rows => painted.push(rows), { ...timer, visible: () => true });
    const original = quote();
    stream.start();
    stream.receive({ sent_at: 1, markets: [{ ...original, runners: [original.runners[1]], part: 1, parts: 2, revision: 'one' }] });
    timer.advance(0); assert.equal(painted.length, 0);
    stream.receive({ sent_at: 2, markets: [{ ...original, runners: [original.runners[0]], part: 0, parts: 2, revision: 'one' }] });
    timer.advance(0); assert.deepEqual(painted[0][0].runners, original.runners);
});
test('equal-time stale deliveries and unloaded markets cannot accumulate or override current books', () => {
    const timer = clock(), painted = [];
    const stream = createMarketQuoteStream(rows => painted.push(rows), { ...timer, visible: () => true, accepts: row => row.matchKey === 'a' });
    stream.start(); stream.receive({ sent_at: 5, markets: [quote(4)] }); timer.advance(0);
    stream.receive({ sent_at: 4, markets: [{ ...quote(4), deltaValue: 99 }, { ...quote(), matchKey: 'unloaded' }] });
    timer.advance(5000); assert.equal(painted.length, 1);
});

test('the real table renderer patches cells in place and flashes only changed prices and sizes', async () => {
    const { readFileSync } = await import('node:fs');
    const vm = await import('node:vm');
    const source = readFileSync(new URL('../../resources/views/users/sports/Football/marketActivity.blade.php', import.meta.url), 'utf8');
    const start = source.indexOf('function paintMarketQuotes(changed)');
    const end = source.indexOf('function paintMarketDetail(event)', start);
    const element = text => ({ textContent: text, classList: { [Symbol.iterator]: function* () {}, remove() {}, add() {} } });
    const cells = Object.fromEntries(['.odds-cell-back', '.size-cell-back', '.odds-cell-lay', '.size-cell-lay', '.col-fav .compact-name'].map(selector => [selector, element('0')]));
    const indicators = Array.from({ length: 4 }, () => element('—'));
    const row = { dataset: { id: '7' }, querySelector: selector => cells[selector], querySelectorAll: () => indicators };
    Object.defineProperty(row, 'innerHTML', { set() { throw new Error('A live update must preserve the row'); } });
    const flashes = [];
    const context = { streamTableRows: new Map([['7', row]]), eventsBody: { querySelectorAll: () => [row] }, currentEventId: null, fmtSize: value => String(value),
        balanceText: value => value, balanceClass: value => 'ind-' + value, momentumText: value => value,
        momentumClass: value => 'ind-mom-' + value, deltaClass: () => 'ind-delta-pos', volText: value => value,
        volClass: value => 'ind-vol-' + value, flashMarketValue: (...args) => flashes.push(args) };
    vm.createContext(context);
    vm.runInContext(source.slice(start, end), context);
    const next = { ...mergeMarketQuote(event(), quote()), momentum: 'back', volatility: 'low' };
    context.paintMarketQuotes([next]);
    assert.equal(cells['.odds-cell-back'].textContent, '1.80');
    assert.equal(cells['.size-cell-back'].textContent, '1600');
    assert.equal(cells['.odds-cell-lay'].textContent, '1.82');
    assert.equal(cells['.size-cell-lay'].textContent, '40.72');
    assert.equal(flashes.length, 4);
    context.paintMarketQuotes([next]);
    assert.equal(flashes.length, 4);
});

test('the real open-panel renderer updates runner boxes, favorite pressure, and indicators without replacing the panel', async () => {
    const { readFileSync } = await import('node:fs');
    const vm = await import('node:vm');
    const source = readFileSync(new URL('../../resources/views/users/sports/Football/marketActivity.blade.php', import.meta.url), 'utf8');
    const start = source.indexOf('function paintMarketDetail(event)');
    const end = source.indexOf('function applyMarketQuotes(quotes)', start);
    const code = source.slice(start, end).replace(/@json\(__\('([^']*)'\)\)/g, (_, text) => JSON.stringify(text));
    const node = text => ({ textContent: text, style: {}, setAttribute(name, value) { this[name] = value; } });
    const box = () => {
        const price = node('1.00'), size = node('1');
        return { querySelector: selector => selector === '[data-runner-price]' ? price : size, get textContent() { return price.textContent + ' ' + size.textContent; } };
    };
    const runners = [1, 2].map((id, index) => {
        const name = { dataset: { favorite: String(index === 0) } }, back = box(), lay = box();
        return { dataset: { runnerId: String(id) }, querySelector: selector => selector === '.runner-name' ? name : selector === '.runner-back' ? back : lay };
    });
    const labels = [node('Back 50%'), node('Lay 50%')], values = Array.from({ length: 4 }, () => node('—'));
    const fields = Object.fromEntries(['.market-book-status', '.liquidity-favorite', '.liq-back', '.liq-lay', '.liquidity-bar'].map(selector => [selector, node('')]));
    const panel = { querySelector: selector => fields[selector], querySelectorAll: selector => selector === '.runner-row' ? runners : selector === '.ind-value' ? values : labels };
    Object.defineProperty(panel, 'innerHTML', { set() { throw new Error('A live quote must preserve the panel and scroll position'); } });
    const context = { detailContent: panel, serverNow: () => 10, fmtSize: value => String(value), escapeHtml: value => value,
        openDetail() { throw new Error('Same runners must update in place'); }, balanceText: value => value,
        momentumText: value => value, volText: value => value, flashMarketValue() {} };
    vm.createContext(context); vm.runInContext(code, context);
    context.paintMarketDetail({ ...mergeMarketQuote(event(), quote()), freshUntil: 100, stale: false, momentum: 'back', volatility: 'low' });
    assert.equal(runners[0].querySelector('.runner-back').textContent, '1.80 1600');
    assert.equal(labels[0].textContent, 'Back 2%');
    assert.equal(labels[1].textContent, 'Lay 98%');
    assert.ok(Math.abs(parseFloat(fields['.liq-back'].style.width) - 2.4818372428) < .0001);
    assert.equal(values[0].textContent, 'lay');
    assert.equal(values[1].textContent, 'back');
    assert.equal(values[2].textContent, '+1.3 pp');
    assert.equal(values[3].textContent, 'low');
});

for (const onPage of [true, false]) test(`an open market keeps updating ${onPage ? 'with its table row visible' : 'after pagination removes its row'}, with recalculated favorite pressure`, async () => {
    const { readFileSync } = await import('node:fs');
    const vm = await import('node:vm');
    const source = readFileSync(new URL('../../resources/views/users/sports/Football/marketActivity.blade.php', import.meta.url), 'utf8');
    const apply = source.slice(source.indexOf('function applyMarketQuotes(quotes)'), source.indexOf('async function refreshSelectedMarket()'));
    const sync = source.slice(source.indexOf('function refreshOpenMarketDetail()'), source.indexOf('function paintMarketDetail(event)'));
    const acceptStart = source.indexOf('accepts: quote => ') + 'accepts: '.length;
    const acceptEnd = source.indexOf(',\n                    });', acceptStart);
    const drawn = [];
    const selected = event();
    const context = { selectedMarket: selected, events: onPage ? [selected] : [], currentEventId: 7, streamEventIndexes: new Map(), displayOdds: true,
        detailPanel: { classList: { contains: () => true } },
        window: { marketStreamKey: (await import('../../resources/users/js/marketActivityStream.js')).marketStreamKey, mergeMarketQuote, closeDetail() { throw new Error('An off-page market must stay open'); } },
        paintMarketDetail: market => drawn.push(market), applyStartFilter() {}, scheduleAvailability() {} };
    if (onPage) context.streamEventIndexes.set(context.window.marketStreamKey(selected), 0);
    vm.createContext(context); vm.runInContext(sync + apply, context);
    context.paintMarketQuotes = () => context.refreshOpenMarketDetail();
    const accepts = vm.runInContext('(' + source.slice(acceptStart, acceptEnd) + ')', context);
    const timer = clock();
    const stream = createMarketQuoteStream(context.applyMarketQuotes, { ...timer, visible: () => true, accepts, interval: 1000 });
    stream.start(); stream.retain([context.selectedMarket]);
    const first = quote(); first.runners[0].backSize = 400; first.runners[0].laySize = 100;
    stream.receive({ sent_at: 2, markets: [first] }); timer.advance(0);
    assert.equal(drawn.at(-1).runners[0].backSize, 400);
    assert.equal(drawn.at(-1).backPercent, 20);
    assert.equal(drawn.at(-1).layPercent, 80);
    const second = quote(3); second.runners[0].backSize = 100; second.runners[0].laySize = 400;
    stream.receive({ sent_at: 3, markets: [second] }); timer.advance(1000);
    assert.equal(drawn.at(-1).runners[0].backSize, 100);
    assert.equal(drawn.at(-1).backPercent, 80);
    assert.equal(drawn.at(-1).layPercent, 20);
    assert.equal(context.events.length, onPage ? 1 : 0);
});

test('sport tabs switch the market source and clear old quotes without navigating to fixtures', async () => {
    const { readFileSync } = await import('node:fs');
    const vm = await import('node:vm');
    const source = readFileSync(new URL('../../resources/views/users/sports/Football/marketActivity.blade.php', import.meta.url), 'utf8');
    const start = source.indexOf("document.querySelector('[data-market-activity-tabs]')?.addEventListener");
    const end = source.indexOf("document.addEventListener('DOMContentLoaded'", start);
    let click;
    const calls = [];
    const select = { value: 'MATCH_ODDS' };
    const tab = { dataset: { sport: 'tennis' }, href: 'https://example.test/tennis/market-activity', classList: { toggle() {} } };
    const context = { marketSport: 'football', marketSourceUrl: 'https://example.test/football/market-activity', marketSportRevision: 0,
        events: [event()], eventsBody: { replaceChildren() { calls.push('clear rows'); } },
        marketStream: { retain(rows) { assert.equal(rows.length, 0); calls.push('clear stream'); } },
        marketRequest: { abort() { calls.push('abort rows'); } }, selectedQuoteRequest: { abort() { calls.push('abort selected'); } },
        window: { location: { href: 'https://example.test/football/market-activity' }, closeDetail() { calls.push('close'); } },
        document: { querySelector: selector => selector === '.market-select' ? select : { addEventListener(name, handler) { click = handler; } }, querySelectorAll: () => [tab] },
        trackMarketEvents() {}, updateMarketLoader() {}, connectMarketSport() { calls.push('connect tennis'); }, reloadMarketActivity(reset) { assert.equal(reset, true); calls.push('load tennis'); } };
    vm.createContext(context);
    vm.runInContext(source.slice(start, end), context);
    click({ target: { closest: () => tab }, button: 0, preventDefault() { calls.push('prevent navigation'); } });
    assert.equal(context.marketSport, 'tennis');
    assert.equal(context.marketSourceUrl, tab.href);
    assert.equal(context.window.location.href, 'https://example.test/football/market-activity');
    assert.equal(context.marketSportRevision, 1);
    assert.equal(context.events.length, 0);
    assert.equal(context.needsSportMetadata, true);
    assert.equal(select.value, '');
    assert.deepEqual(calls, ['prevent navigation', 'abort rows', 'abort selected', 'close', 'clear rows', 'clear stream', 'connect tennis', 'load tennis']);
});

test('switching sports leaves old channels and ignores late messages from the previous sport', async () => {
    const { readFileSync } = await import('node:fs');
    const vm = await import('node:vm');
    const source = readFileSync(new URL('../../resources/views/users/sports/Football/marketActivity.blade.php', import.meta.url), 'utf8');
    const start = source.indexOf('function connectMarketSport()');
    const end = source.indexOf("document.querySelector('[data-market-activity-tabs]')", start);
    const listeners = new Map(), left = [], received = [];
    const chain = name => ({ subscribed() { return this; }, error() { return this; }, listen(event, callback) { listeners.set(name, callback); return this; } });
    const context = { marketSport: 'football', marketChannel: null, scoreChannel: null, displayOdds: true, updateMarketTransport() {},
        window: { Echo: { leave: name => left.push(name), private: chain, channel: chain } },
        marketStream: { receive: payload => received.push(payload) }, document: { getElementById: () => null } };
    vm.createContext(context);
    vm.runInContext(source.slice(start, end), context);
    context.connectMarketSport();
    const old = listeners.get('sports.football.market-activity');
    context.marketSport = 'tennis';
    context.connectMarketSport();
    old({ sport: 'football' });
    listeners.get('sports.tennis.market-activity')({ sport: 'tennis' });
    assert.deepEqual(left, ['sports.football.market-activity', 'sports.football.live']);
    assert.deepEqual(received, [{ sport: 'tennis' }]);
});

for (const speed of [140, 1000, 2000, 3000, 5000, 8000, 10000]) test(`the actual ${speed}ms speed button updates quote rendering and recovery timing`, async () => {
    const { readFileSync } = await import('node:fs');
    const vm = await import('node:vm');
    const source = readFileSync(new URL('../../resources/views/users/sports/Football/marketActivity.blade.php', import.meta.url), 'utf8');
    const start = source.indexOf("document.getElementById('speedBar').addEventListener");
    const end = source.indexOf("eventsBody.addEventListener('click'", start);
    let click;
    const speeds = [];
    const button = { dataset: { speed }, classList: { toggle() {} }, setAttribute(name, value) { this[name] = value; } };
    const context = { document: { getElementById: () => ({ addEventListener(name, handler) { click = handler; } }), querySelectorAll: () => [button] },
        marketRefreshLoop: { setSpeed: value => speeds.push(value) }, marketStream: { setSpeed: value => speeds.push(value) } };
    vm.createContext(context); vm.runInContext(source.slice(start, end), context);
    click({ target: { closest: () => button } });
    assert.deepEqual(speeds, [speed, speed]);
    assert.equal(button['aria-pressed'], 'true');
});

test('a delayed HTTP response from the previous sport cannot repopulate the new table', async () => {
    const { readFileSync } = await import('node:fs');
    const vm = await import('node:vm');
    const source = readFileSync(new URL('../../resources/views/users/sports/Football/marketActivity.blade.php', import.meta.url), 'utf8');
    const start = source.indexOf('async function reloadMarketActivity(');
    const end = source.indexOf('async function loadMoreMarketActivity()', start);
    let resolve;
    const pending = new Promise(done => { resolve = done; });
    const received = [];
    const context = { marketLoading: false, refreshPending: false, resetPending: false, marketLoadPending: false, marketHasMore: false,
        marketSportRevision: 0, marketLoadedPages: 1, marketRowVersions: {}, compactMarketActivity: true,
        document: { hidden: false, getElementById: () => null },
        window: { setTimeout() { return 1; }, clearTimeout() {} }, performance: { now: () => 0 }, AbortController,
        marketActivityUrl: () => new URL('https://example.test/football/market-activity'), fetch: () => pending,
        receiveMarketRows: payload => received.push(payload), updateMarketLoader() {}, console };
    vm.createContext(context);
    vm.runInContext(source.slice(start, end), context);
    const request = context.reloadMarketActivity(true);
    context.marketSportRevision = 1;
    resolve({ ok: true, json: async () => ({ data: [{ sport: 'football' }] }) });
    await request;
    assert.equal(received.length, 0);
    assert.equal(context.marketLoading, false);
});

test('time-to-start buttons reload the complete server-filtered dataset from page one', async () => {
    const { readFileSync } = await import('node:fs');
    const vm = await import('node:vm');
    const source = readFileSync(new URL('../../resources/views/users/sports/Football/marketActivity.blade.php', import.meta.url), 'utf8');
    const start = source.indexOf("document.getElementById('timeToStart').addEventListener");
    const end = source.indexOf('let marketLoading', start);
    let click;
    const reloads = [];
    const buttons = [600, 1200, 1800, 3600, 7200, 10800, 21600, 0].map(seconds => ({
        dataset: { startSeconds: String(seconds) }, classList: { toggle() {} }, setAttribute() {},
    }));
    const context = { activeStartSeconds: 0, marketSportRevision: 0, applyStartFilter() {}, scheduleAvailability() {},
        reloadMarketActivity(reset) { reloads.push(reset); },
        document: { getElementById: () => ({ addEventListener(_, callback) { click = callback; } }), querySelectorAll: () => buttons } };
    vm.createContext(context);
    vm.runInContext(source.slice(start, end), context);
    for (const button of buttons) {
        click({ target: { closest: () => button } });
        assert.equal(context.activeStartSeconds, Number(button.dataset.startSeconds));
    }
    assert.deepEqual(reloads, Array(8).fill(true));
    assert.equal(context.marketSportRevision, 8);
});

test('market pagination and refresh URLs retain the selected server-side start window', async () => {
    const { readFileSync } = await import('node:fs');
    const vm = await import('node:vm');
    const source = readFileSync(new URL('../../resources/views/users/sports/Football/marketActivity.blade.php', import.meta.url), 'utf8');
    const start = source.indexOf('function marketActivityUrl(');
    const end = source.indexOf('let refreshPending', start);
    const context = { URL, marketSourceUrl: '/football/market-activity', needsSportMetadata: false,
        activeFootballFilter: 'all', activeMarketFilter: 'active', activeStartSeconds: 21600, compactMarketActivity: true,
        window: { location: { origin: 'https://example.test' } } };
    vm.createContext(context);
    vm.runInContext(source.slice(start, end), context);
    const url = context.marketActivityUrl(2, 20);
    assert.equal(url.searchParams.get('start_seconds'), '21600');
    assert.equal(url.searchParams.get('page'), '2');
    assert.equal(url.searchParams.get('type'), 'all');
});
