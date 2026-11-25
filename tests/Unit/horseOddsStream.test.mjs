import test from 'node:test';
import assert from 'node:assert/strict';
import { createHorseOddsBook, mergeHorseRunners } from '../../resources/users/js/horseOddsStream.js';
const packet = (version = 1, changes = {}) => ({ id: '456', version, parts: 1, part: 0, status: 'Prematch', runners: [{ runnerId: '11', marketId: '456', marketType: 'WIN', currentBackOdds: 2.34 }], ...changes });
test('a tick applies immediately without an HTTP refresh and old deliveries cannot roll it back', () => {
    const applied = [];
    const book = createHorseOddsBook(id => id === '456', race => applied.push(race));
    book.receive(packet(2)); book.receive(packet(1)); book.receive(packet(2)); book.receive(packet(3, { id: 'other' }));
    assert.equal(applied.length, 1); assert.equal(applied[0].runners[0].currentBackOdds, 2.34);
    book.replay(); assert.equal(applied.at(-1).version, 2);
});
test('fragmented fields commit atomically, ignore mixed revisions and keep status transitions', () => {
    const applied = [];
    const book = createHorseOddsBook(() => true, race => applied.push(race));
    book.receive(packet(1, { parts: 2 })); assert.equal(applied.length, 0);
    book.receive(packet(2, { parts: 2, part: 1 }));
    book.receive(packet(1, { parts: 2, part: 1 })); assert.equal(applied.length, 0);
    book.receive(packet(2, { parts: 2 })); assert.equal(applied.length, 1); assert.equal(applied[0].runners.length, 2);
    book.receive(packet(3, { status: 'Finished', runners: [] })); assert.equal(applied.at(-1).status, 'Finished');
});
test('recovery replays the latest complete state and evicts unloaded races', () => {
    const applied = []; let loaded = true;
    const book = createHorseOddsBook(() => loaded, race => applied.push(race));
    book.receive(packet()); loaded = false; book.replay(); loaded = true; book.replay(); assert.equal(applied.length, 1);
});
test('only changed prices append history and complete snapshots remove withdrawn runners', () => {
    const before = [{ runnerId: '11', marketId: '456', currentBackOdds: 2.4, backOddsHistory: [{ odds: 2.4, timestamp: 1 }] }, { runnerId: '12', marketId: '456' }];
    const next = mergeHorseRunners(before, packet().runners, 10);
    assert.equal(next.length, 1); assert.deepEqual(next[0].backOddsHistory.at(-1), { timestamp: 10, odds: 2.34 });
    assert.equal(mergeHorseRunners(next, packet().runners, 11)[0].backOddsHistory.length, 2);
});

test('only visible races subscribe, and pagination leaves channels that are no longer needed', async () => {
    const { watchHorseOdds } = await import('../../resources/users/js/horseOddsStream.js');
    const subscribed = [], left = [];
    globalThis.window = { Echo: { private(name) { subscribed.push(name); return { listen() {} }; }, leave(name) { left.push(name); } } };
    let ids = ['1.456', '457'];
    const sync = watchHorseOdds({}, () => ids);
    assert.deepEqual(subscribed, ['sports.horseracing.odds.1_456', 'sports.horseracing.odds.457']);
    ids = ['457', '458']; sync();
    assert.deepEqual(left, ['sports.horseracing.odds.1_456']); assert.equal(subscribed.length, 3);
    delete globalThis.window;
});
