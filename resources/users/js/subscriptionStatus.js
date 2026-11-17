export function createSubscriptionStatusPoller(root, { fetcher = globalThis.fetch, isHidden = () => document.hidden } = {}) {
    let pending = null;
    let stopped = false;
    return {
        stop() {
            stopped = true;
            pending?.abort();
        },
        async refresh() {
            if (stopped || pending || isHidden() || !root?.dataset.statusUrl) return;
            const controller = new AbortController();
            pending = controller;
            try {
                const response = await fetcher(root.dataset.statusUrl, {
                    headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                });
                if ([401, 403, 419].includes(response.status)) {
                    stopped = true;
                    return;
                }
                if (!response.ok) return;
                const data = await response.json();
                if (stopped || typeof data.html !== 'string' || typeof data.version !== 'string' || data.version === root.dataset.statusVersion) return;
                root.innerHTML = data.html;
                root.dataset.statusVersion = data.version;
            } catch {
                // Retain the last confirmed status and retry on the next visible poll.
            } finally {
                pending = null;
            }
        },
    };
}

export function watchSubscriptionStatus(root) {
    if (!root?.dataset.statusUrl) return;
    let poller;
    let timer = null;
    const start = () => {
        if (timer !== null) return;
        poller = createSubscriptionStatusPoller(root);
        timer = window.setInterval(() => poller.refresh(), 5000);
        poller.refresh();
    };
    const onVisible = () => { if (!document.hidden) poller?.refresh(); };
    const stop = () => {
        poller?.stop();
        window.clearInterval(timer);
        timer = null;
    };
    root.addEventListener('submit', stop);
    document.addEventListener('visibilitychange', onVisible);
    window.addEventListener('pagehide', stop);
    window.addEventListener('pageshow', start);
    start();
}
