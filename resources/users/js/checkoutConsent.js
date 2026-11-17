export function bindCheckoutConsent(form) {
    const checkbox = form.querySelector('input[name="auto_renew"]');
    const button = form.querySelector('button[type="submit"]');
    if (!checkbox || !button) return;

    const sync = () => {
        button.disabled = !checkbox.checked;
    };

    sync();
    checkbox.addEventListener('change', sync);
    form.addEventListener('submit', (event) => {
        sync();
        if (!checkbox.checked) {
            event.preventDefault();
            checkbox.reportValidity();
        }
    });
    form.addEventListener('reset', () => queueMicrotask(sync));
    form.ownerDocument.defaultView.addEventListener('pageshow', sync);
}
