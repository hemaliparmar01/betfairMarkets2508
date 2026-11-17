export function renderPredictionPoll(poll, result, locked = false) {
    if (poll.dataset.votePending === 'true') return;
    const options = Array.from(poll.querySelectorAll('[data-prediction-option]'));
    const counts = options.map(option => {
        const count = Number(result.counts?.[option.dataset.value] ?? 0);
        return Number.isFinite(count) ? Math.max(0, count) : 0;
    });
    const total = counts.reduce((sum, count) => sum + count, 0);
    const value = result.value == null ? '' : String(result.value);
    poll.dataset.voteValue = value;
    poll.dataset.voteLocked = String(locked);
    options.forEach((option, index) => {
        const percent = total ? Math.round(counts[index] / total * 100) : 0;
        const selected = value === option.dataset.value;
        option.disabled = locked || value !== '';
        option.setAttribute('aria-pressed', String(selected));
        option.querySelector('[data-prediction-count]').textContent = String(counts[index]);
        option.querySelector('[data-prediction-percent]').textContent = `${percent}%`;
        const bar = option.querySelector('[data-prediction-bar]');
        bar.style.width = `${percent}%`;
        bar.classList.toggle('bg-[var(--surface-brand-70)]', selected);
        bar.classList.toggle('bg-[var(--surface-success-50)]', !selected);
    });
    poll.querySelector('[data-prediction-total]').textContent = String(total);
}

export function createPredictionPoller({ fetcher = globalThis.fetch, isHidden = () => document.hidden, onError = () => {} } = {}) {
    let panel = null;
    let pending = null;
    let generation = 0;
    const close = () => {
        generation++;
        panel = null;
        pending?.abort();
        pending = null;
    };
    const refresh = async () => {
        if (!panel?.dataset.predictionUrl || isHidden() || pending) return;
        const target = panel;
        const currentGeneration = generation;
        const startedAt = Date.now();
        const controller = new AbortController();
        pending = controller;
        try {
            const response = await fetcher(target.dataset.predictionUrl, {
                headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
            });
            if (!response.ok) throw new Error(`Community poll refresh failed: ${response.status}`);
            const data = await response.json();
            if (generation !== currentGeneration || panel !== target || String(data.match_id) !== target.dataset.predictionPanel
                || Number(target.dataset.predictionSavedAt ?? 0) >= startedAt) return;
            target.querySelectorAll('[data-prediction-poll]').forEach(poll => {
                const result = data.polls?.[poll.dataset.predictionPoll];
                if (result) renderPredictionPoll(poll, result, data.locked === true);
            });
        } catch (error) {
            if (error.name !== 'AbortError') onError(error);
        } finally {
            if (pending === controller) pending = null;
        }
    };
    return {
        open(nextPanel) {
            if (panel === nextPanel) return;
            close();
            panel = nextPanel;
            return refresh();
        },
        close,
        refresh,
    };
}
