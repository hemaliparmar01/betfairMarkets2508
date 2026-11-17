import assert from 'node:assert/strict';
import test from 'node:test';
import { bindCheckoutConsent } from '../../resources/users/js/checkoutConsent.js';

function card(checked = false, recurring = true) {
    const listeners = {};
    const checkbox = { checked, reports: 0, reportValidity() { this.reports++; },
        addEventListener(type, callback) { listeners[type] = callback; } };
    const button = { disabled: recurring };
    const form = { ownerDocument: { defaultView: { addEventListener(type, callback) { listeners[`window:${type}`] = callback; } } },
        querySelector(selector) { return selector.startsWith('input') ? (recurring ? checkbox : null) : button; },
        addEventListener(type, callback) { listeners[`form:${type}`] = callback; } };
    bindCheckoutConsent(form);
    return { checkbox, button, listeners };
}

test('each gateway enables only its own button after consent and disables again on uncheck', () => {
    const cards = [card(), card(), card()];
    for (const current of cards) assert.equal(current.button.disabled, true);
    cards[1].checkbox.checked = true;
    cards[1].listeners.change();
    assert.deepEqual(cards.map(current => current.button.disabled), [true, false, true]);
    cards[1].checkbox.checked = false;
    cards[1].listeners.change();
    assert.equal(cards[1].button.disabled, true);
});

test('submit without consent is blocked even if the button was manually enabled', () => {
    const current = card();
    current.button.disabled = false;
    let prevented = false;
    current.listeners['form:submit']({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(current.button.disabled, true);
    assert.equal(current.checkbox.reports, 1);
});

test('checked consent allows submission and browser-restored state is synchronized', () => {
    const current = card(true);
    assert.equal(current.button.disabled, false);
    current.listeners['form:submit']({ preventDefault() { assert.fail('Consented payment must be allowed'); } });
    current.checkbox.checked = false;
    current.listeners['window:pageshow']();
    assert.equal(current.button.disabled, true);
});

test('reset disables the button after the checkbox resets', async () => {
    const current = card(true);
    current.listeners['form:reset']();
    current.checkbox.checked = false;
    await Promise.resolve();
    assert.equal(current.button.disabled, true);
});

test('one-time payment cards without a consent checkbox remain enabled', () => {
    const current = card(false, false);
    assert.equal(current.button.disabled, false);
});
