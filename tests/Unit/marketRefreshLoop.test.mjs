import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { createMarketRefreshLoop } from '../../resources/users/js/marketRefreshLoop.js';

function clock() {
    let time = 0;
    let id = 0;
    const timers = new Map();
    return {
        now: () => time,
        schedule(callback, delay) { timers.set(++id, { callback, at: time + delay }); return id; },
        cancel(timer) { timers.delete(timer); },
        get count() { return timers.size; },
        async advance(ms) {
            const end = time + ms;
            while (true) {
                const next = [...timers].sort((a, b) => a[1].at - b[1].at)[0];
                if (!next || next[1].at > end) break;
                time = next[1].at;
                timers.delete(next[0]);
                next[1].callback();
                await Promise.resolve();
                await Promise.resolve();
            }
            time = end;
        },
    };
}

for (const interval of [140, 1000, 2000, 3000, 5000, 8000, 10000]) {
    test(`refresh continues every ${interval}ms without websocket messages`, async () => {
        const timer = clock();
        let calls = 0;
        const loop = createMarketRefreshLoop(() => { calls++; }, { ...timer, visible: () => true, interval });
        loop.start();
        await timer.advance(0);
        assert.equal(calls, 1);
        await timer.advance(interval - 1);
        assert.equal(calls, 1);
        await timer.advance(1);
        assert.equal(calls, 2);
        loop.stop();
        assert.equal(timer.count, 0);
    });
}

test('changing speed replaces the previous timer and immediately applies the new cadence', async () => {
    const timer = clock();
    const times = [];
    const loop = createMarketRefreshLoop(() => times.push(timer.now()), { ...timer, visible: () => true });
    loop.start();
    await timer.advance(0);
    await timer.advance(500);
    loop.setSpeed(1000);
    await timer.advance(500);
    assert.deepEqual(times, [0, 1000]);
    loop.setSpeed(5000);
    await timer.advance(4999);
    assert.deepEqual(times, [0, 1000]);
    await timer.advance(1);
    assert.deepEqual(times, [0, 1000, 6000]);
});

test('bursts of websocket notifications cannot bypass the selected speed', async () => {
    const timer = clock();
    let calls = 0;
    const loop = createMarketRefreshLoop(() => { calls++; }, { ...timer, visible: () => true, interval: 1000 });
    loop.start();
    await timer.advance(0);
    for (let index = 0; index < 20; index++) loop.request();
    assert.equal(timer.count, 1);
    await timer.advance(999);
    assert.equal(calls, 1);
    await timer.advance(1);
    assert.equal(calls, 2);
});

test('slow requests never overlap even at Pro speed and resume after completion', async () => {
    const timer = clock();
    let calls = 0;
    let finish;
    const loop = createMarketRefreshLoop(() => {
        calls++;
        if (calls === 1) return new Promise(resolve => { finish = resolve; });
    }, { ...timer, visible: () => true, interval: 140 });
    loop.start();
    await timer.advance(1000);
    loop.request();
    loop.setSpeed(1000);
    assert.equal(calls, 1);
    assert.equal(timer.count, 0);
    finish();
    await Promise.resolve();
    await Promise.resolve();
    await timer.advance(0);
    assert.equal(calls, 2);
});

test('hidden pages pause polling and resume once; stopping prevents late responses from restarting it', async () => {
    const timer = clock();
    let hidden = false;
    let calls = 0;
    let finish;
    const loop = createMarketRefreshLoop(() => {
        calls++;
        if (calls === 2) return new Promise(resolve => { finish = resolve; });
    }, { ...timer, visible: () => !hidden, interval: 1000 });
    loop.start();
    await timer.advance(0);
    hidden = true;
    loop.request();
    await timer.advance(5000);
    assert.equal(calls, 1);
    hidden = false;
    loop.request();
    await timer.advance(0);
    assert.equal(calls, 2);
    loop.stop();
    finish();
    await Promise.resolve();
    await Promise.resolve();
    assert.equal(timer.count, 0);
    loop.start();
    loop.start();
    await timer.advance(0);
    assert.equal(calls, 3);
});

test('a failed refresh retries at the chosen speed without stopping future updates', async () => {
    const timer = clock();
    let calls = 0;
    const failures = [];
    const loop = createMarketRefreshLoop(() => {
        if (++calls === 1) throw new Error('Disconnected');
    }, { ...timer, visible: () => true, interval: 1000, onError: error => failures.push(error.message) });
    loop.start();
    await timer.advance(1000);
    assert.deepEqual(failures, ['Disconnected']);
    assert.equal(calls, 2);
});

test('every real SPEED button controls the shared live loop and updates its active state', () => {
    const source = readFileSync(new URL('../../resources/views/users/sports/Football/marketActivity.blade.php', import.meta.url), 'utf8');
    const start = source.indexOf("document.getElementById('speedBar').addEventListener");
    const end = source.indexOf("eventsBody.addEventListener('click'", start);
    const speeds = [140, 1000, 2000, 3000, 5000, 8000, 10000];
    const buttons = speeds.map(speed => ({ dataset: { speed: String(speed) },
        active: false, pressed: null, classList: { toggle(name, value) { buttons.find(button => button.classList === this).active = value; } },
        setAttribute(name, value) { if (name === 'aria-pressed') this.pressed = value; } }));
    const selected = [];
    let click;
    vm.runInNewContext(source.slice(start, end), { marketRefreshInterval: 3000,
        marketRefreshLoop: { setSpeed: speed => selected.push(speed) },
        document: { getElementById: () => ({ addEventListener(name, callback) { click = callback; } }), querySelectorAll: () => buttons } });
    for (const button of buttons) {
        click({ target: { closest: () => button } });
        assert.equal(button.active, true);
        assert.equal(button.pressed, 'true');
        assert.equal(buttons.filter(item => item.active).length, 1);
    }
    assert.deepEqual(selected, speeds);
});
