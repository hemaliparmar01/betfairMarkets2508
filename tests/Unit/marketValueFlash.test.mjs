import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { flashMarketValue } from '../../resources/users/js/marketValueFlash.js';

function box() {
    const animations = [];
    return { animations, animate(frames, options) {
        const animation = { frames, options, cancelled: false, cancel() { this.cancelled = true; this.oncancel?.(); } };
        animations.push(animation);
        return animation;
    } };
}

test('changed odds and liquidity flash yellow for 800ms without changing original backgrounds', () => {
    for (const [before, after] of [['2.10', '1.95'], ['66.19', '155.68'], ['0', '40.72']]) {
        const element = box();
        flashMarketValue(element, before, after);
        assert.equal(element.animations.length, 1);
        assert.equal(element.animations[0].options.duration, 800);
        assert.match(element.animations[0].frames[0].boxShadow, /250, 204, 21/);
        assert.equal(element.animations[0].frames[1].boxShadow, 'inset 0 0 0 100vmax rgba(250, 204, 21, 0)');
        assert.equal(element.animations[0].options.fill, undefined);
    }
});

test('unchanged values, initial population, and unavailable or locked values do not flash', () => {
    const element = box();
    for (const [before, after] of [[' 2.10 ', '2.10'], ['2.10\n 100', '2.10 100'], ['', '2.10'], ['—', '2.10'], ['2.10', '—']]) {
        flashMarketValue(element, before, after);
    }
    assert.equal(element.animations.length, 0);
});

test('rapid changes restart the flash only on the changed box', () => {
    const first = box();
    const second = box();
    flashMarketValue(first, '2.10', '1.95');
    flashMarketValue(second, '100', '200');
    flashMarketValue(first, '1.95', '1.90');
    assert.equal(first.animations[0].cancelled, true);
    assert.equal(first.animations.length, 2);
    assert.equal(second.animations[0].cancelled, false);
    first.animations[1].onfinish();
    flashMarketValue(first, '1.90', '1.85');
    assert.equal(first.animations[1].cancelled, false);
});

test('reduced motion and browsers without animation support remain functional', () => {
    const previous = globalThis.matchMedia;
    try {
        globalThis.matchMedia = () => ({ matches: true });
        const element = box();
        flashMarketValue(element, '2.10', '1.95');
        assert.equal(element.animations.length, 0);
        flashMarketValue({}, '2.10', '1.95');
    } finally {
        if (previous === undefined) delete globalThis.matchMedia;
        else globalThis.matchMedia = previous;
    }
});

test('table refresh flashes updated boxes after incoming DOM children are moved', () => {
    const source = readFileSync(new URL('../../resources/views/users/sports/Football/marketActivity.blade.php', import.meta.url), 'utf8');
    const start = source.indexOf('Array.from(incoming.cells).forEach');
    const end = source.indexOf('const selected = row.classList', start);
    const cells = Array.from({ length: 9 }, () => ({ textContent: '2.10', get innerHTML() { return this.textContent; }, childNodes: [] }));
    cells[5].textContent = '1.95';
    cells[5].childNodes = [{ text: '1.95' }];
    const current = cells.map((cell, index) => ({ textContent: '2.10', get innerHTML() { return this.textContent; },
        querySelector: () => null, classList: { contains: () => false },
        replaceChildren(...children) { this.textContent = children.map(child => child.text).join(''); cells[index].textContent = ''; } }));
    const flashes = [];
    vm.runInNewContext(source.slice(start, end), { incoming: { cells }, row: { cells: current }, flashChanges: true, sameSelection: true,
        flashMarketValue: (element, before, after) => flashes.push([before, after]) });
    assert.deepEqual(flashes, [['2.10', '1.95']]);
    assert.equal(current[5].textContent, '1.95');
});
