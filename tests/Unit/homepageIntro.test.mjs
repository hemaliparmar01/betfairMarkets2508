import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/views/popup.blade.php', import.meta.url), 'utf8');
const script = source.slice(source.indexOf('(() => {', source.indexOf('<div id="subscriptionModal"')), source.lastIndexOf('})();') + 5);

function harness(dismissed = false) {
    const state = { removed: false, listener: null, destination: null, dismissed: dismissed ? '1' : null };
    const classes = { add() {}, remove() {} };
    const modal = {
        classList: classes, inert: false,
        hasAttribute: () => true,
        remove() { state.removed = true; },
        getAnimations: () => [],
        addEventListener(event, listener) { state.listener = listener; },
    };
    const context = vm.createContext({
        document: { getElementById: () => modal, body: { classList: classes }, documentElement: { classList: classes }, querySelector: selector => selector === '#subscriptionModal' ? modal : null },
        sessionStorage: { getItem: () => state.dismissed, setItem: (key, value) => { assert.equal(key, 'bf-home-intro-dismissed'); state.dismissed = value; } },
        window: { location: { assign: href => { state.destination = href; } } },
    });
    vm.runInContext(script, context);
    return state;
}

test('homepage intro close dismisses the overlay and remembers dismissal for this tab session', async () => {
    const state = harness();
    assert.equal(state.removed, false);
    await state.listener({ preventDefault() {}, target: { closest: selector => selector === '[data-close-subscription]' ? {} : null } });
    assert.equal(state.removed, true);
    assert.equal(state.dismissed, '1');
    assert.equal(state.destination, null);
});

test('returning to the landing page after dismissal does not reopen the intro', () => {
    const state = harness(true);
    assert.equal(state.removed, true);
    assert.equal(state.listener, null);
});

test('login and register links dismiss the intro and continue to their authentication page', async () => {
    for (const href of ['/login', '/register']) {
        const state = harness();
        await state.listener({ preventDefault() {}, target: { closest: selector => selector === 'a[href]' ? { href } : null } });
        assert.equal(state.removed, true);
        assert.equal(state.dismissed, '1');
        assert.equal(state.destination, href);
    }
});
