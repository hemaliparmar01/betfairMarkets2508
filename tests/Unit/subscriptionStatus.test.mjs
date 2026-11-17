import assert from 'node:assert/strict';
import test from 'node:test';
import { setImmediate } from 'node:timers/promises';
import { createSubscriptionStatusPoller, watchSubscriptionStatus } from '../../resources/users/js/subscriptionStatus.js';

function rootFixture() {
    let html = '<p>Pending</p>';
    return { dataset: { statusUrl: '/subscriptions/status', statusVersion: 'pending' }, writes: 0,
        get innerHTML() { return html; }, set innerHTML(value) { html = value; this.writes++; } };
}
const response = (html = '<p>Active</p>', version = 'active') => ({ ok: true, status: 200, json: async () => ({ html, version }) });

test('confirmed backend changes replace subscription content without a page reload', async () => {
    const root = rootFixture();
    const requests = [];
    const poller = createSubscriptionStatusPoller(root, { isHidden: () => false, fetcher: async (url, options) => {
        requests.push({ url, options }); return response();
    } });
    await poller.refresh();
    assert.equal(root.innerHTML, '<p>Active</p>');
    assert.equal(root.dataset.statusVersion, 'active');
    assert.equal(requests[0].url, '/subscriptions/status');
    assert.equal(requests[0].options.cache, 'no-store');
    await poller.refresh();
    assert.equal(root.writes, 1);
});

test('hidden pages and missing endpoint configuration do not send requests', async () => {
    const root = rootFixture();
    let hidden = true;
    let requests = 0;
    const poller = createSubscriptionStatusPoller(root, { isHidden: () => hidden, fetcher: async () => { requests++; return response(); } });
    await poller.refresh();
    hidden = false;
    root.dataset.statusUrl = '';
    await poller.refresh();
    assert.equal(requests, 0);
});

test('overlapping refreshes are suppressed and late responses after stopping are ignored', async () => {
    const root = rootFixture();
    let resolve;
    let requests = 0;
    const poller = createSubscriptionStatusPoller(root, { isHidden: () => false, fetcher: () => {
        requests++; return new Promise(done => { resolve = done; });
    } });
    const pending = poller.refresh();
    await poller.refresh();
    assert.equal(requests, 1);
    poller.stop();
    resolve(response());
    await pending;
    assert.equal(root.innerHTML, '<p>Pending</p>');
    await poller.refresh();
    assert.equal(requests, 1);
});

test('transient network errors preserve the last confirmed content and allow recovery', async () => {
    const root = rootFixture();
    let fail = true;
    const poller = createSubscriptionStatusPoller(root, { isHidden: () => false, fetcher: async () => {
        if (fail) throw new Error('Network unavailable');
        return response();
    } });
    await poller.refresh();
    assert.equal(root.innerHTML, '<p>Pending</p>');
    fail = false;
    await poller.refresh();
    assert.equal(root.innerHTML, '<p>Active</p>');
});

test('expired authentication stops background requests without replacing private content', async () => {
    for (const status of [401, 403, 419]) {
        let requests = 0;
        const root = rootFixture();
        const poller = createSubscriptionStatusPoller(root, { isHidden: () => false, fetcher: async () => {
            requests++; return { ok: false, status };
        } });
        await poller.refresh();
        await poller.refresh();
        assert.equal(requests, 1);
        assert.equal(root.writes, 0);
    }
});

test('polling resumes after returning from browser back cache and stops during cancellation submission', async () => {
    const originals = { window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch };
    const events = {};
    const root = rootFixture();
    const timers = [];
    let requests = 0;
    root.addEventListener = (name, callback) => { events[name] = callback; };
    globalThis.document = { hidden: false, addEventListener: (name, callback) => { events[name] = callback; } };
    globalThis.window = {
        setInterval(callback, interval) { timers.push({ callback, interval }); return timers.length; }, clearInterval() {},
        addEventListener(name, callback) { events[name] = callback; },
    };
    globalThis.fetch = async () => { requests++; return response(); };
    try {
        watchSubscriptionStatus(root);
        await setImmediate();
        assert.equal(requests, 1);
        assert.equal(timers[0].interval, 5000);
        events.pagehide();
        await timers[0].callback();
        assert.equal(requests, 1);
        events.pageshow();
        await setImmediate();
        assert.equal(requests, 2);
        events.submit();
        await timers[1].callback();
        assert.equal(requests, 2);
    } finally {
        Object.assign(globalThis, originals);
    }
});
