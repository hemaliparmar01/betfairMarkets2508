export function createMarketChartLoader({
    root = document,
    Observer = globalThis.IntersectionObserver,
    now = Date.now,
    every = globalThis.setInterval,
    stopEvery = globalThis.clearInterval,
    later = globalThis.setTimeout,
    stopLater = globalThis.clearTimeout,
    maxConcurrent = 3,
    refreshMs = 20_000,
} = {}) {
    const states = new Map();
    let active = 0;
    let stopped = false;
    const isPageVisible = () => (root.ownerDocument ?? root).visibilityState !== 'hidden';

    function pump() {
        if (stopped || !isPageVisible()) return;
        for (const [image, state] of states) {
            if (active >= maxConcurrent) break;
            if (!image.isConnected || !state.visible || state.loading || now() - state.updatedAt < refreshMs) continue;
            state.loading = true;
            active++;
            let deadline;
            let finished = false;
            const complete = (failed = false) => {
                if (finished) return;
                finished = true;
                image.removeEventListener('load', loaded);
                image.removeEventListener('error', errored);
                stopLater(deadline);
                state.updatedAt = now();
                state.loading = false;
                state.cancel = null;
                active--;
                if (failed) image.src = state.placeholder;
                pump();
            };
            const loaded = () => complete();
            const errored = () => complete(true);
            state.cancel = errored;
            image.addEventListener('load', loaded);
            image.addEventListener('error', errored);
            deadline = later(errored, 10_000);
            const url = new URL(image.dataset.chartUrl, (root.ownerDocument ?? root).baseURI);
            url.searchParams.set('_chart', String(Math.floor(now() / refreshMs)));
            image.src = url.href;
        }
    }

    const observer = Observer ? new Observer(entries => {
        for (const entry of entries) {
            const state = states.get(entry.target);
            if (state) state.visible = entry.isIntersecting;
        }
        pump();
    }, { rootMargin: '150px 0px' }) : null;

    function scan(container = root) {
        for (const [image] of states) {
            if (!image.isConnected) {
                states.get(image).cancel?.();
                observer?.unobserve(image);
                states.delete(image);
            }
        }
        for (const image of container.querySelectorAll('[data-market-chart]')) {
            if (states.has(image)) continue;
            states.set(image, {
                visible: !observer, loading: false, updatedAt: -Infinity,
                placeholder: image.getAttribute('src'),
            });
            observer?.observe(image);
        }
        pump();
    }

    function replaceContent(current, incoming) {
        const existing = new Map(Array.from(current.querySelectorAll('[data-market-chart]'), image => [image.dataset.chartKey, image]));
        for (const image of incoming.querySelectorAll('[data-market-chart]')) {
            const previous = existing.get(image.dataset.chartKey);
            if (previous?.dataset.chartUrl === image.dataset.chartUrl) {
                image.replaceWith(previous);
                existing.delete(image.dataset.chartKey);
            }
        }
        current.replaceChildren(...incoming.childNodes);
        scan(current);
    }

    const visibilityChanged = () => pump();
    (root.ownerDocument ?? root).addEventListener('visibilitychange', visibilityChanged);
    const interval = every(pump, refreshMs);
    scan();

    return {
        scan,
        replaceContent,
        destroy() {
            stopped = true;
            stopEvery(interval);
            observer?.disconnect();
            for (const state of states.values()) state.cancel?.();
            states.clear();
            (root.ownerDocument ?? root).removeEventListener('visibilitychange', visibilityChanged);
        },
    };
}
