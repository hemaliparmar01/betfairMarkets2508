export const parseJson = (value, fallback = null) => {
    if (!value) return fallback;

    try {
        return JSON.parse(value);
    } catch {
        return fallback;
    }
};

export const setVisible = (element, visible) => {
    if (!element) return;
    element.hidden = !visible;
};

export const delegate = (root, eventName, selector, handler) => {
    root.addEventListener(eventName, (event) => {
        const target = event.target.closest(selector);
        if (target && root.contains(target)) handler(event, target);
    });
};

export const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

export const escapeHtml = (value) => {
    const node = document.createElement('div');
    node.textContent = String(value ?? '');
    return node.innerHTML;
};

const themeToggle = document.getElementById('themeToggle');
const themeIcon = themeToggle?.querySelector('i, [data-theme-icon]');
const themeIsDark = document.body.classList.contains('dark-mode');
themeToggle?.setAttribute('aria-pressed', String(themeIsDark));
if (themeIcon?.tagName === 'I') themeIcon.className = themeIsDark ? 'fas fa-sun' : 'fas fa-moon';
else if (themeIcon) themeIcon.textContent = themeIsDark ? '☀' : '☾';
themeToggle?.addEventListener('click', function () {
    const isDark = document.body.classList.toggle('dark-mode');
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
    window.updateBrandingAssets?.();
    themeToggle.setAttribute('aria-pressed', String(isDark));
    if (themeIcon?.tagName === 'I') themeIcon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
    else if (themeIcon) themeIcon.textContent = isDark ? '☀' : '☾';
    try {
        localStorage.setItem('bf-markets-theme', isDark ? 'dark' : 'light');
    } catch {
        // Theme switching still works when browser storage is unavailable.
    }
});

export const fixtureVersions = (container) => {
    const versions = {};
    for (const node of container?.childNodes ?? []) {
        if (node.nodeType !== 8) continue;
        const match = node.data.trim().match(/^bf-fixture:([a-f0-9]{16}):([a-f0-9]{16})$/);
        if (match) versions[match[1]] = match[2];
    }
    return versions;
};

export const updateFixtureRows = (container, incoming) => {
    if (!container || !incoming) return false;
    const key = node => node.nodeType === 1 ? node.id || node.getAttribute('data-league-header') || node.getAttribute('data-id') || node.getAttribute('data-match-id') || node.getAttribute('data-group-key') || node.getAttribute('data-alert-key') || node.getAttribute('data-runner-id') || '' : '';
    const patch = (current, next) => {
        if (current.isEqualNode(next)) return current;
        if (current.nodeType !== next.nodeType || current.nodeName !== next.nodeName) return next;
        if (current.nodeType !== 1) { current.nodeValue = next.nodeValue; return current; }
        const modalOpen = current.getAttribute('aria-modal') === 'true' && !current.classList.contains('hidden');
        const modalFlex = modalOpen && current.classList.contains('flex');
        const expanded = current.nodeName === 'DETAILS' && current.open;
        for (const attribute of [...current.attributes]) {
            if (!next.hasAttribute(attribute.name)) current.removeAttribute(attribute.name);
        }
        for (const attribute of next.attributes) {
            if (current.getAttribute(attribute.name) !== attribute.value) current.setAttribute(attribute.name, attribute.value);
        }
        if (modalOpen) { current.classList.remove('hidden'); if (modalFlex) current.classList.add('flex'); }
        if (expanded) current.open = true;
        const children = [...current.childNodes];
        const keyed = new Map(children.filter(child => key(child)).map(child => [key(child), child]));
        const used = new Set();
        const desired = [...next.childNodes].map((child, index) => {
            const previous = key(child) ? keyed.get(key(child)) : children[index];
            if (!previous || used.has(previous) || key(previous) !== key(child)) return child;
            used.add(previous);
            return patch(previous, child);
        });
        desired.forEach((child, index) => { if (current.childNodes[index] !== child) current.insertBefore(child, current.childNodes[index] ?? null); });
        [...current.childNodes].slice(desired.length).forEach(child => child.remove());
        return current;
    };
    const groups = parent => {
        const result = [];
        const occurrences = new Map();
        let fixture = null;
        for (const node of parent.childNodes) {
            const marker = node.nodeType === 8 ? node.data.trim().match(/^bf-fixture:([a-f0-9]{16}):([a-f0-9]{16})$/) : null;
            if (marker) { fixture = { key: `fixture:${marker[1]}`, version: marker[2], nodes: [node], reuse: false }; continue; }
            if (fixture) {
                fixture.nodes.push(node);
                if (node.nodeType === 8 && node.data.trim() === 'bf-fixture-reuse') fixture.reuse = true;
                if (node.nodeType === 8 && node.data.trim() === 'bf-fixture-end') { result.push(fixture); fixture = null; }
                continue;
            }
            if (node.nodeType === 3 && !node.nodeValue.trim()) continue;
            const identity = key(node) || `${node.nodeName}:${result.length}`;
            const occurrence = occurrences.get(identity) ?? 0;
            occurrences.set(identity, occurrence + 1);
            result.push({ key: `${identity}:${occurrence}`, nodes: [node] });
        }
        return result;
    };
    const old = new Map(groups(container).map(group => [group.key, group]));
    let changed = false;
    const desired = groups(incoming).flatMap(group => {
        const previous = old.get(group.key);
        old.delete(group.key);
        if (previous && group.version && (group.reuse || previous.version === group.version)) return previous.nodes;
        changed ||= !previous || previous.nodes.length !== group.nodes.length || group.nodes.some((node, index) => !previous.nodes[index]?.isEqualNode(node));
        const nodes = group.nodes.map((node, index) => previous?.nodes[index] ? patch(previous.nodes[index], node) : node);
        return nodes;
    });
    changed ||= old.size > 0;
    desired.forEach((node, index) => { if (container.childNodes[index] !== node) container.insertBefore(node, container.childNodes[index] ?? null); });
    [...container.childNodes].slice(desired.length).forEach(node => node.remove());
    return changed;
};
