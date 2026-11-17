export function createMarketRefreshLoop(refresh, {
    interval = 3000,
    now = () => performance.now(),
    visible = () => !document.hidden,
    schedule = (callback, delay) => setTimeout(callback, delay),
    cancel = timer => clearTimeout(timer),
    onError = error => console.error('Market refresh failed:', error),
} = {}) {
    let started = false;
    let running = false;
    let timer = null;
    let lastStarted = null;

    const clear = () => {
        if (timer !== null) cancel(timer);
        timer = null;
    };
    const queue = () => {
        clear();
        if (!started || running || !visible()) return;
        const delay = lastStarted === null ? 0 : Math.max(0, interval - (now() - lastStarted));
        timer = schedule(run, delay);
    };
    const run = async () => {
        timer = null;
        if (!started || running || !visible()) return;
        running = true;
        lastStarted = now();
        try {
            await refresh();
        } catch (error) {
            onError(error);
        } finally {
            running = false;
            queue();
        }
    };

    return {
        start() {
            if (started) return;
            started = true;
            lastStarted = null;
            queue();
        },
        stop() {
            started = false;
            clear();
        },
        request: queue,
        setSpeed(value) {
            const speed = Number(value);
            if (![140, 1000, 2000, 3000, 5000, 8000, 10000].includes(speed)) return;
            interval = speed;
            queue();
        },
    };
}
