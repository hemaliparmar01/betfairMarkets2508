import assert from 'node:assert/strict';
import test from 'node:test';
import { createMarketChartLoader } from '../../resources/users/js/marketCharts.js';

function chart(key) {
    const listeners = new Map();
    let source = 'data:image/svg+xml,placeholder';
    return {
        dataset: { chartKey: key, chartUrl: `/open-chart?marketId=1&selectionId=${key}` },
        isConnected: true,
        requests: [],
        get src() { return source; },
        set src(value) { source = value; if (value.startsWith('http')) this.requests.push(value); },
        getAttribute() { return source; },
        addEventListener(name, callback) { if (!listeners.has(name)) listeners.set(name, new Set()); listeners.get(name).add(callback); },
        removeEventListener(name, callback) { listeners.get(name)?.delete(callback); },
        fire(name) { for (const callback of [...(listeners.get(name) ?? [])]) callback(); },
        replaceWith(other) {
            const parent = this.parent;
            const index = parent.children.indexOf(this);
            if (other.parent) other.parent.children = other.parent.children.filter(child => child !== other);
            parent.children[index] = other;
            other.parent = parent;
            this.isConnected = false;
        },
    };
}

function container(images, document) {
    const node = {
        ownerDocument: document,
        children: images,
        get childNodes() { return this.children; },
        querySelectorAll() { return this.children; },
        replaceChildren(...children) {
            for (const child of this.children) child.isConnected = false;
            this.children = children;
            for (const child of children) { child.isConnected = true; child.parent = this; }
        },
    };
    for (const image of images) image.parent = node;
    return node;
}

function fixture(count = 5) {
    let time = 100_000;
    let tick;
    let observer;
    const deadlines = new Map();
    let nextDeadline = 0;
    const doc = { visibilityState: 'visible', baseURI: 'https://example.test', addEventListener() {}, removeEventListener() {} };
    const images = Array.from({ length: count }, (_, i) => chart(String(i + 1)));
    const root = container(images, doc);
    class Observer {
        constructor(callback) { this.callback = callback; observer = this; }
        observe() {}
        unobserve() {}
        disconnect() {}
    }
    const loader = createMarketChartLoader({
        root, Observer, now: () => time,
        every(callback) { tick = callback; return 1; }, stopEvery() {},
        later(callback) { deadlines.set(++nextDeadline, callback); return nextDeadline; },
        stopLater(id) { deadlines.delete(id); },
    });
    return {
        root, images, loader, doc,
        visible(targets, visible = true) { observer.callback(targets.map(target => ({ target, isIntersecting: visible }))); },
        advance(ms) { time += ms; tick(); },
        timeout() { [...deadlines.values()][0]?.(); },
    };
}

test('charts wait until visible and only three requests run at once', () => {
    const f = fixture();
    assert.equal(f.images.flatMap(image => image.requests).length, 0);
    f.visible(f.images);
    assert.deepEqual(f.images.map(image => image.requests.length), [1, 1, 1, 0, 0]);
    f.images[0].fire('load');
    assert.deepEqual(f.images.map(image => image.requests.length), [1, 1, 1, 1, 0]);
    f.images[1].fire('error');
    assert.deepEqual(f.images.map(image => image.requests.length), [1, 1, 1, 1, 1]);
    assert.equal(f.images[1].src, 'data:image/svg+xml,placeholder');
    assert.ok(f.images.every(image => image.requests.every(url => url.startsWith('https://example.test/open-chart?'))));
    f.loader.destroy();
});

test('loaded charts refresh at twenty seconds while offscreen and hidden charts do not', () => {
    const f = fixture(2);
    f.visible(f.images);
    f.images.forEach(image => image.fire('load'));
    f.visible([f.images[1]], false);
    f.advance(19_000);
    assert.deepEqual(f.images.map(image => image.requests.length), [1, 1]);
    f.advance(1_000);
    assert.deepEqual(f.images.map(image => image.requests.length), [2, 1]);
    f.images[0].fire('load');
    f.doc.visibilityState = 'hidden';
    f.advance(20_000);
    assert.equal(f.images[0].requests.length, 2);
    f.loader.destroy();
});

test('live HTML replacement preserves existing chart nodes and registers new charts lazily', () => {
    const f = fixture(1);
    f.visible(f.images);
    f.images[0].fire('load');
    const incoming = container([chart('1'), chart('2')], f.doc);
    f.loader.replaceContent(f.root, incoming);
    assert.equal(f.root.children[0], f.images[0]);
    assert.equal(f.root.children[0].requests.length, 1);
    assert.equal(f.root.children[1].requests.length, 0);
    f.visible([f.root.children[1]]);
    assert.equal(f.root.children[1].requests.length, 1);
    f.loader.destroy();
});

test('stalled requests release their slot and failures retry on the next refresh', () => {
    const f = fixture(4);
    f.visible(f.images);
    f.timeout();
    assert.equal(f.images[0].src, 'data:image/svg+xml,placeholder');
    assert.equal(f.images[3].requests.length, 1);
    f.images.slice(1).forEach(image => image.fire('load'));
    f.advance(20_000);
    assert.equal(f.images[0].requests.length, 2);
    f.loader.destroy();
});
