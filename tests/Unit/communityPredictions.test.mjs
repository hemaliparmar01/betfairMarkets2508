import assert from 'node:assert/strict';
import test from 'node:test';
import { createPredictionPoller, renderPredictionPoll } from '../../resources/users/js/communityPredictions.js';

function pollFixture(values = ['1', 'X', '2'], key = '1x2') {
    const total = { textContent: '' };
    const options = values.map(value => {
        const classes = new Set();
        const nodes = {
            '[data-prediction-count]': { textContent: '' },
            '[data-prediction-percent]': { textContent: '' },
            '[data-prediction-bar]': { style: {}, classList: { toggle(name, enabled) { if (enabled) classes.add(name); else classes.delete(name); } } },
        };
        return { dataset: { value }, attributes: {}, disabled: false, nodes, classes,
            setAttribute(name, value) { this.attributes[name] = value; }, querySelector: selector => nodes[selector] };
    });
    return { dataset: { predictionPoll: key }, options, total,
        querySelectorAll: () => options, querySelector: () => total };
}

function panelFixture(id = '1') {
    const polls = [pollFixture(), pollFixture(['UNDER', 'OVER'], 'uo')];
    return { dataset: { predictionUrl: `/football/matches/${id}/predictions`, predictionPanel: id }, polls,
        querySelectorAll: () => polls };
}

const result = (id = '1', counts = { 1: 3, X: 1, 2: 0 }) => ({
    match_id: id, locked: false, polls: { '1x2': { value: null, counts }, uo: { value: null, counts: { UNDER: 1, OVER: 1 } } },
});
const response = data => ({ ok: true, json: async () => data });

test('all choices display global percentages and counts before voting, including empty options', () => {
    const poll = pollFixture();
    renderPredictionPoll(poll, { value: null, counts: { 1: 3, X: 1 } });
    assert.deepEqual(poll.options.map(option => option.nodes['[data-prediction-percent]'].textContent), ['75%', '25%', '0%']);
    assert.deepEqual(poll.options.map(option => option.nodes['[data-prediction-count]'].textContent), ['3', '1', '0']);
    assert.equal(poll.total.textContent, '4');
    assert.equal(poll.options.every(option => !option.disabled), true);
    assert.equal(poll.options[2].nodes['[data-prediction-bar]'].style.width, '0%');
});

test('saving one market updates its percentages and locks every choice without locking other markets', () => {
    const panel = panelFixture();
    renderPredictionPoll(panel.polls[0], { value: 'X', counts: { 1: 3, X: 2 } });
    renderPredictionPoll(panel.polls[1], { value: null, counts: {} });
    assert.equal(panel.polls[0].options.every(option => option.disabled), true);
    assert.equal(panel.polls[0].options[1].attributes['aria-pressed'], 'true');
    assert.equal(panel.polls[0].options[1].nodes['[data-prediction-percent]'].textContent, '40%');
    assert.equal(panel.polls[1].options.every(option => !option.disabled), true);
    assert.equal(panel.polls[1].total.textContent, '0');
});

test('kickoff locks unvoted choices while percentages stay visible', () => {
    const poll = pollFixture();
    renderPredictionPoll(poll, { value: null, counts: { 1: 1 } }, true);
    assert.equal(poll.options.every(option => option.disabled), true);
    assert.equal(poll.options[0].nodes['[data-prediction-percent]'].textContent, '100%');
});

test('opening the modal loads counts and a subsequent refresh sees another users vote', async () => {
    const requests = [];
    const panel = panelFixture();
    let data = result();
    const poller = createPredictionPoller({ isHidden: () => false, fetcher: async (url, options) => {
        requests.push({ url, options }); return response(data);
    } });
    await poller.open(panel);
    assert.equal(panel.polls[0].total.textContent, '4');
    data = result('1', { 1: 3, X: 1, 2: 1 });
    await poller.refresh();
    assert.equal(panel.polls[0].options[2].nodes['[data-prediction-percent]'].textContent, '20%');
    assert.equal(requests[0].url, '/football/matches/1/predictions');
    assert.equal(requests[0].options.cache, 'no-store');
});

test('hidden tabs and closed modals make no polling requests', async () => {
    let hidden = true;
    let requests = 0;
    const poller = createPredictionPoller({ isHidden: () => hidden, fetcher: async () => { requests++; return response(result()); } });
    await poller.open(panelFixture());
    assert.equal(requests, 0);
    hidden = false;
    await poller.refresh();
    assert.equal(requests, 1);
    poller.close();
    await poller.refresh();
    assert.equal(requests, 1);
});

test('old match responses cannot overwrite a newly opened modal', async () => {
    const resolvers = [];
    const oldPanel = panelFixture('1');
    const nextPanel = panelFixture('2');
    const poller = createPredictionPoller({ isHidden: () => false, fetcher: () => new Promise(resolve => resolvers.push(resolve)) });
    const first = poller.open(oldPanel);
    const second = poller.open(nextPanel);
    resolvers[1](response(result('2')));
    await second;
    resolvers[0](response(result('1', { 1: 50 })));
    await first;
    assert.equal(nextPanel.polls[0].total.textContent, '4');
    assert.equal(oldPanel.polls[0].total.textContent, '');
});

test('a refresh cannot unlock a vote while its submission is pending or overwrite a saved vote', async () => {
    const panel = panelFixture();
    const poll = panel.polls[0];
    poll.dataset.votePending = 'true';
    poll.options.forEach(option => { option.disabled = true; });
    let resolve;
    const poller = createPredictionPoller({ isHidden: () => false, fetcher: () => new Promise(done => { resolve = done; }) });
    const refresh = poller.open(panel);
    resolve(response(result()));
    await refresh;
    assert.equal(poll.options.every(option => option.disabled), true);
    delete poll.dataset.votePending;
    renderPredictionPoll(poll, { value: 'X', counts: { 1: 3, X: 2 } });
    const nextRefresh = poller.refresh();
    panel.dataset.predictionSavedAt = String(Date.now() + 1);
    resolve(response(result()));
    await nextRefresh;
    assert.equal(poll.dataset.voteValue, 'X');
    assert.equal(poll.total.textContent, '5');
});

test('network failure preserves existing counts and later refreshes recover', async () => {
    const panel = panelFixture();
    let fail = true;
    const poller = createPredictionPoller({ isHidden: () => false, fetcher: async () => {
        if (fail) throw new Error('Network unavailable');
        return response(result());
    } });
    renderPredictionPoll(panel.polls[0], { value: null, counts: { 1: 2 } });
    await poller.open(panel);
    assert.equal(panel.polls[0].total.textContent, '2');
    fail = false;
    await poller.refresh();
    assert.equal(panel.polls[0].total.textContent, '4');
});
