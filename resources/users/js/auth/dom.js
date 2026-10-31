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
    themeToggle.setAttribute('aria-pressed', String(isDark));
    if (themeIcon?.tagName === 'I') themeIcon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
    else if (themeIcon) themeIcon.textContent = isDark ? '☀' : '☾';
    try {
        localStorage.setItem('bf-markets-theme', isDark ? 'dark' : 'light');
    } catch {
        // Theme switching still works when browser storage is unavailable.
    }
});
