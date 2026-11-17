const activeFlashes = new WeakMap();
const normalized = value => String(value ?? '').trim().replace(/\s+/g, ' ');

export function flashMarketValue(element, before, after) {
    const previous = normalized(before);
    const current = normalized(after);
    if (!element || previous === current || !/\d/.test(previous) || !/\d/.test(current)
        || !element.animate || globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;

    activeFlashes.get(element)?.cancel();
    const animation = element.animate([
        { boxShadow: 'inset 0 0 0 100vmax rgba(250, 204, 21, 0.85)' },
        { boxShadow: 'inset 0 0 0 100vmax rgba(250, 204, 21, 0)' },
    ], { duration: 800, easing: 'ease-out' });
    activeFlashes.set(element, animation);
    const clear = () => {
        if (activeFlashes.get(element) === animation) activeFlashes.delete(element);
    };
    animation.onfinish = clear;
    animation.oncancel = clear;
}
