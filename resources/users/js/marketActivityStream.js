export function marketHasActiveLiquidity(event, now = Date.now() / 1000, limits = {}) {
    if (event.stale !== false || event.removed || event.streamRemoved || event.active === false
        || event.liquidityValid === false || !Number.isFinite(event.freshUntil) || event.freshUntil <= now
        || !Array.isArray(event.runners) || !event.runners.length) return false;
    let favorite = null;
    for (const runner of event.runners) {
        if (![runner.back, runner.lay, runner.backSize, runner.laySize].every(Number.isFinite)
            || runner.back <= 1 || runner.lay <= 1 || runner.lay < runner.back
            || runner.backSize <= 0 || runner.laySize <= 0) return false;
        if (!favorite || runner.back < favorite.back) favorite = runner;
    }
    const minimum = Math.min(favorite.backSize, favorite.laySize);
    return minimum >= (limits.minimum_size ?? 25)
        && Math.max(favorite.backSize, favorite.laySize) / minimum > (limits.minimum_size_ratio ?? 5);
}

const speeds = [140, 1000, 2000, 3000, 5000, 8000, 10000];
export const marketStreamKey = market => JSON.stringify([String(market.streamMatchKey ?? market.matchKey), String(market.streamMarketKey ?? market.marketKey)]);

// The exchange's available-to-lay money is Back demand, and vice versa.
export function mergeMarketQuote(event, quote) {
    if (event.locked || event.available === false || marketStreamKey(event) !== marketStreamKey(quote)
        || Number(quote.lastMarketUpdate ?? 0) < Number(event.lastMarketUpdate ?? 0)) return event;
    if (quote.removed) return { ...event, streamRemoved: true };
    if (!Array.isArray(quote.runners) || !quote.runners.length) return event;
    const old = new Map(event.runners.map(runner => [String(runner.id ?? runner.name), runner]));
    const runners = quote.runners.map(runner => ({ ...old.get(String(runner.id ?? runner.name)), ...runner }));
    let favIdx = 0;
    let price = Infinity;
    runners.forEach((runner, index) => {
        if (runner.back > 0 && runner.back < price) { favIdx = index; price = runner.back; }
    });
    const favorite = runners[favIdx];
    const valid = quote.liquidityValid !== false && [favorite.backSize, favorite.laySize].every(value => typeof value === 'number' && Number.isFinite(value) && value >= 0);
    const total = valid ? favorite.backSize + favorite.laySize : 0;
    const backPercent = total > 0 ? favorite.laySize / total * 100 : null;
    return { ...event, ...quote, runners, favIdx, streamRemoved: false,
        totalBackSize: runners.reduce((sum, runner) => sum + runner.backSize, 0),
        totalLaySize: runners.reduce((sum, runner) => sum + runner.laySize, 0),
        backPercent, layPercent: backPercent === null ? null : 100 - backPercent,
        delta: quote.deltaValue == null ? '—' : `${quote.deltaValue > 0 ? '+' : ''}${quote.deltaValue.toFixed(1)} pp`,
    };
}

export function createMarketQuoteStream(apply, {
    interval = 3000, accepts = () => true,
    now = () => performance.now(), visible = () => !document.hidden,
    schedule = (callback, delay) => setTimeout(callback, delay), cancel = timer => clearTimeout(timer),
} = {}) {
    const pending = new Map();
    const latest = new Map();
    const applied = new Map();
    const fragments = new Map();
    let started = false;
    let timer = null;
    let renderedAt = -Infinity;
    const clear = () => { if (timer !== null) cancel(timer); timer = null; };
    const request = () => {
        clear();
        if (!started || !pending.size || !visible()) return;
        timer = schedule(() => {
            timer = null;
            if (!started || !visible()) return;
            const rows = [...pending.values()];
            pending.clear();
            for (const row of rows) applied.set(marketStreamKey(row), row);
            renderedAt = now();
            apply(rows);
        }, Math.max(0, interval - (now() - renderedAt)));
    };
    return {
        receive(payload) {
            for (let quote of payload.markets ?? []) {
                if (!accepts(quote)) continue;
                const key = marketStreamKey(quote);
                if (quote.parts > 1) {
                    let book = fragments.get(key);
                    if (book && book.revision !== quote.revision && payload.sent_at < book.sentAt) continue;
                    if (!book || book.revision !== quote.revision) {
                        book = { revision: quote.revision, sentAt: payload.sent_at, parts: new Map() };
                        fragments.set(key, book);
                    }
                    book.parts.set(quote.part, quote.runners);
                    if (book.parts.size !== quote.parts) continue;
                    quote = { ...quote, runners: [...book.parts].sort((a, b) => a[0] - b[0]).flatMap(part => part[1]) };
                    fragments.delete(key);
                }
                const previous = latest.get(key);
                if (previous && (Number(quote.lastMarketUpdate ?? 0) < Number(previous.quote.lastMarketUpdate ?? 0)
                    || (Number(quote.lastMarketUpdate ?? 0) === Number(previous.quote.lastMarketUpdate ?? 0) && payload.sent_at <= previous.sentAt))) continue;
                latest.set(key, { quote, sentAt: payload.sent_at });
                pending.set(key, quote);
            }
            request();
        },
        // Reconcile an HTTP response without letting an older snapshot overwrite newer WS prices.
        reconcile(events) {
            return events.map(event => {
                const quote = applied.get(marketStreamKey(event));
                return quote ? mergeMarketQuote(event, quote) : event;
            });
        },
        retain(events) {
            const keys = new Set(events.map(marketStreamKey));
            for (const store of [latest, applied, pending, fragments]) for (const key of store.keys()) if (!keys.has(key)) store.delete(key);
        },
        start() { started = true; request(); },
        stop() { started = false; clear(); },
        request,
        setSpeed(value) { if (speeds.includes(Number(value))) { interval = Number(value); request(); } },
    };
}
