import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/users/js/app.js', import.meta.url), 'utf8');
const popupSource = source.slice(source.indexOf('if (alertContainer) {\n    const receivedAlerts')).replace('    pollPlatformAlerts();\n}', '    globalThis.initialPoll = pollPlatformAlerts();\n}');
const templates = {
    en: { title: 'LTD ALERT', score_label: 'Score', minute_label: 'min', odds_label: 'Draw Odds', view_btn: 'View', mute_btn: 'Mute', status_label: 'LIVE', strategy_label: '1st Half LTD' },
    ka: { title: 'LTD შეტყობინება', score_label: 'ანგარიში', minute_label: 'წთ', odds_label: 'ფრის კოეფიციენტი', view_btn: 'ნახვა', mute_btn: 'გამორთვა', status_label: 'LIVE', strategy_label: 'პირველი ტაიმი LTD' },
};
const alert = { alert_id: '1', alert_type: 'ltd', match: 'Home v Away', league: 'League', score: '0-0', minute: 10, draw_lay: 2.1, url: '/football/market-activity?match=1', templates };
function harness(locale = 'en', response = { alerts: [alert], cursor: 1, popup_enabled: true }) {
    const events = {};
    const children = [];
    const container = { dataset: { customerId: '5', popupEnabled: '1', popupsUrl: '/notifications/popups', since: '1000' }, children,
        append(node) { children.push(node); }, replaceChildren() { children.splice(0); } };
    const requests = [];
    const context = vm.createContext({ URL, Set, console, performance: { now: () => 0 }, setTimeout() {}, clearTimeout() {},
        alertContainer: container, notificationViewActive: true, notificationFeedback: null,
        document: { documentElement: { lang: locale }, hidden: false, addEventListener(name, fn) { events[name] = fn; },
            createElement() { const nodes = {}; return { setAttribute() {}, querySelector(selector) { return nodes[selector] ??= { textContent: '', addEventListener() {} }; }, addEventListener() {}, remove() {} }; } },
        window: { location: { origin: 'https://example.test' }, addEventListener() {}, setInterval(fn) { events.poll = fn; },
            Echo: { private() { const channel = { listen(name, fn) { events[name] = fn; return channel; } }; return channel; } } },
        updateNotificationStatus() {},
        async notificationRequest(url) { requests.push(String(url)); return context.response; }, response,
    });
    vm.runInContext(popupSource, context);
    return { context, events, children, requests, container };
}

for (const locale of ['en', 'ka']) {
    test(`polling renders ${locale} popups and deduplicates WebSocket delivery`, async () => {
        const app = harness(locale);
        await app.context.initialPoll;
        assert.equal(app.children.length, 1);
        assert.equal(app.children[0].querySelector('[data-title]').textContent, `🔔 ${templates[locale].title}`);
        assert.match(app.children[0].querySelector('[data-odds]').textContent, /2.10/);
        app.events['.alert.received'](alert);
        assert.equal(app.children.length, 1);
        app.context.response = { alerts: [{ ...alert, alert_id: '2', alert_type: 'goal' }], cursor: 2, popup_enabled: true };
        await app.events.poll();
        assert.equal(app.children.length, 2);
        assert.match(app.children[1].querySelector('[data-title]').textContent, /⚽/);
        assert.match(app.requests[1], /after=1/);
        app.context.response = { alerts: [], cursor: 2, popup_enabled: false };
        await app.events.poll();
        assert.equal(app.children.length, 0);
        app.events['.alert.received']({ ...alert, alert_id: '3' });
        assert.equal(app.children.length, 0);
    });
}

test('failed popup polls retain the cursor and retry; hidden pages do not fetch', async () => {
    const app = harness();
    await app.context.initialPoll;
    app.context.notificationRequest = async () => { throw new Error('network unavailable'); };
    await app.events.poll();
    app.context.notificationRequest = async url => { app.requests.push(String(url)); return { alerts: [], cursor: 1, popup_enabled: true }; };
    await app.events.poll();
    assert.match(app.requests.at(-1), /after=1/);
    const count = app.requests.length;
    app.context.document.hidden = true;
    await app.events.poll();
    assert.equal(app.requests.length, count);
});
