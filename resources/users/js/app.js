import { watchRacingFeed } from './racingFeed.js';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.watchRacingFeed = watchRacingFeed;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

const realtimeClient = window.Echo.connector.pusher;

window.addEventListener('pagehide', () => {
    realtimeClient.disconnect();
    notificationViewActive = false;
    clearTimeout(connectionExpiry);
    clearInterval(connectionPolling);
    connectionPolling = null;
    connectionStatusRequest?.abort();
    connectionStatusRequest = null;
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        realtimeClient.connect();
        notificationViewActive = true;
        if (notificationPage && latestNotificationState) updateNotificationStatus(latestNotificationState);
    }
});

const themeToggle = document.getElementById('themeToggle');
const themeIcon = themeToggle?.querySelector('i');
if (localStorage.getItem('bf-markets-theme') === 'dark') {
    document.body.classList.add('dark-mode');
    if (themeIcon) themeIcon.className = 'fas fa-sun';
}
themeToggle?.addEventListener('click', function() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    themeIcon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
    localStorage.setItem('bf-markets-theme', isDark ? 'dark' : 'light');
});

// ===== SCROLL TOP =====
// const scrollTop = document.getElementById('scrollTop');
// window.addEventListener('scroll', function() {
//     scrollTop.classList.toggle('visible', window.scrollY > 300);
// });
// scrollTop.addEventListener('click', function() {
//     window.scrollTo({
//         top: 0,
//         behavior: 'smooth'
//     });
// });

const alertContainer = document.getElementById('alert-popup-container');
const notificationPage = document.getElementById('notification-settings');
const notificationFeedback = document.getElementById('notification-feedback');
const notificationState = document.getElementById('notification-state');
let connectionExpiry;
let connectionPolling = null;
let connectionExpiresAt = 0;
let connectionStatusRequest = null;
let telegramAction = null;
let latestNotificationState;
let notificationViewActive = true;

const notificationRequest = async (url, method, body, signal) => {
    const response = await fetch(url, {
        method, credentials: 'same-origin', signal,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    const data = await response.json();
    if (!response.ok) throw Object.assign(new Error(data.message || 'Notification request failed.'), { status: response.status });
    return data;
};

const updateNotificationStatus = (state, preferences = false) => {
    if (alertContainer) {
        alertContainer.dataset.popupEnabled = state.popup_enabled ? '1' : '0';
        if (!state.popup_enabled) alertContainer.replaceChildren();
    }
    if (!notificationPage) return;
    if (telegramAction === 'connect' && connectionExpiresAt && Date.now() >= connectionExpiresAt && !state.connected) {
        state = { ...state, error: 'Telegram connection token expired. Please connect again.' };
    }
    latestNotificationState = state;
    if (state.connected || state.error) {
        if (telegramAction === 'connect') {
            notificationFeedback.textContent = state.connected ? 'Telegram connected successfully.' : state.error;
            telegramAction = null;
        }
        connectionExpiresAt = 0;
    }
    if (telegramAction !== 'connect' || !notificationViewActive || !notificationPage.isConnected) {
        clearTimeout(connectionExpiry);
        clearInterval(connectionPolling);
        connectionPolling = null;
        connectionStatusRequest?.abort();
        connectionStatusRequest = null;
    } else if (connectionExpiresAt && connectionPolling === null) {
        connectionPolling = setInterval(async () => {
            if (!notificationPage.isConnected) {
                updateNotificationStatus(latestNotificationState);
                return;
            }
            if (connectionStatusRequest) return;
            const controller = new AbortController();
            connectionStatusRequest = controller;
            try {
                const status = await notificationRequest(notificationPage.dataset.statusUrl, 'GET', undefined, controller.signal);
                if (!controller.signal.aborted) updateNotificationStatus(status);
            } catch (error) {
                if (error.name === 'AbortError') return;
                notificationFeedback.textContent = error.message;
                if (error.status >= 400 && error.status < 500 && ![408, 429].includes(error.status)) {
                    updateNotificationStatus({ ...latestNotificationState, error: error.message });
                }
            } finally {
                if (connectionStatusRequest === controller) connectionStatusRequest = null;
            }
        }, 3000);
        connectionExpiry = setTimeout(() => {
            updateNotificationStatus({ ...latestNotificationState, error: 'Telegram connection token expired. Please connect again.' });
        }, Math.max(0, connectionExpiresAt - Date.now()));
    }
    if (!notificationPage.isConnected) return;
    document.getElementById('tgStatusConnected').style.display = state.connected ? 'flex' : 'none';
    document.getElementById('tgStatusDisconnected').style.display = !state.connected && !state.error ? 'flex' : 'none';
    document.getElementById('tgStatusError').style.display = !state.connected && state.error ? 'flex' : 'none';
    document.getElementById('tgUsername').textContent = state.username ? `@${state.username}` : 'Telegram';
    document.getElementById('tgErrorText').textContent = state.error || '';
    document.getElementById('btnTestNotification').disabled = !state.connected;
    const button = document.getElementById('btnConnectTelegram');
    const actions = document.getElementById(state.connected ? 'tgStatusConnected' : state.error ? 'tgStatusError' : 'tgStatusDisconnected').querySelector('.tg-status-actions');
    if (button.parentElement !== actions) actions.append(button);
    button.className = state.connected ? button.dataset.disconnectClass : button.dataset.connectClass;
    button.disabled = telegramAction !== null;
    button.querySelector('i').className = state.connected ? 'fas fa-unlink' : 'fas fa-link';
    button.querySelector('[data-telegram-action-label]').textContent = telegramAction === 'connect' ? 'Connecting...' : telegramAction === 'disconnect' ? 'Disconnecting...' : state.connected ? 'Disconnect Telegram' : 'Connect Telegram';
    if (preferences) {
        notificationPage.querySelectorAll('[data-notification-filter]').forEach(input => { input.checked = Boolean(state.preferences[input.dataset.notificationFilter]); });
        document.getElementById('popup-enabled').checked = state.popup_enabled;
    }
};

if (notificationPage && notificationState) {
    updateNotificationStatus(JSON.parse(notificationState.textContent), true);
    notificationPage.addEventListener('click', async event => {
        const item = event.target.closest('[data-filter]');
        if (item && event.target.tagName !== 'INPUT') {
            const input = item.querySelector('input');
            input.checked = !input.checked;
        }
        const button = event.target.closest('button');
        if (!button || button.disabled) return;
        event.preventDefault();
        button.disabled = true;
        const label = button.innerHTML;
        button.closest('.save-bar, .tg-card')?.append(notificationFeedback);
        notificationFeedback.textContent = '';
        notificationFeedback.setAttribute('role', 'status');
        let telegramWindow;
        try {
            if (button.id === 'btnConnectTelegram' && !latestNotificationState.connected) {
                telegramAction = 'connect';
                updateNotificationStatus({ ...latestNotificationState, error: null });
                telegramWindow = window.open('about:blank', '_blank');
                if (telegramWindow) telegramWindow.opener = null;
                const data = await notificationRequest(notificationPage.dataset.connectUrl, 'POST');
                const destination = data.url;
                if (telegramWindow) telegramWindow.location.replace(destination);
                else window.location.assign(destination);
                notificationFeedback.textContent = 'Open @BFMarketsAlertsBot and press Start to finish connecting.';
                if (!latestNotificationState.connected && telegramAction === 'connect') {
                    connectionExpiresAt = Date.now() + data.expires_in * 1000;
                    updateNotificationStatus(latestNotificationState);
                }
            } else if (button.id === 'btnConnectTelegram') {
                telegramAction = 'disconnect';
                updateNotificationStatus(latestNotificationState);
                updateNotificationStatus(await notificationRequest(notificationPage.dataset.disconnectUrl, 'DELETE'));
                notificationFeedback.textContent = 'Telegram disconnected. Your preferences are saved.';
            } else if (button.id === 'btnSavePreferences') {
                button.textContent = 'Saving...';
                const preferences = Object.fromEntries(Array.from(notificationPage.querySelectorAll('[data-notification-filter]'), input => [input.dataset.notificationFilter, input.checked]));
                updateNotificationStatus(await notificationRequest(alertContainer.dataset.preferencesUrl, 'PATCH', { preferences, popup_enabled: document.getElementById('popup-enabled').checked }));
                notificationFeedback.textContent = 'Notification preferences saved successfully.';
            } else if (button.id === 'btnTestNotification') {
                const data = await notificationRequest(notificationPage.dataset.testUrl, 'POST', { request_id: crypto.randomUUID() });
                notificationFeedback.textContent = data.message;
            }
        } catch (error) {
            if (telegramWindow) telegramWindow.close();
            if (button.id === 'btnConnectTelegram' && telegramAction === 'connect') updateNotificationStatus({ ...latestNotificationState, error: error.message });
            notificationFeedback.textContent = error.message;
            notificationFeedback.setAttribute('role', 'alert');
        } finally {
            button.disabled = false;
            if (button.id === 'btnSavePreferences') button.innerHTML = label;
            if (button.id === 'btnConnectTelegram' && (telegramAction === 'disconnect' || !connectionExpiresAt)) telegramAction = null;
            updateNotificationStatus(latestNotificationState);
        }
    });
}

if (alertContainer) {
    const receivedAlerts = new Set();
    window.Echo.private(`customers.${alertContainer.dataset.customerId}.alerts`)
        .listen('.notifications.changed', state => updateNotificationStatus(state))
        .listen('.notification.tested', state => { if (notificationFeedback) notificationFeedback.textContent = state.message; })
        .listen('.alert.received', alert => {
            if (alertContainer.dataset.popupEnabled !== '1' || !['ltd', 'goal'].includes(alert.alert_type) || receivedAlerts.has(alert.alert_id)) return;
            receivedAlerts.add(alert.alert_id);
            if (receivedAlerts.size > 200) receivedAlerts.delete(receivedAlerts.values().next().value);
            const language = document.documentElement.lang.toLowerCase().split('-')[0];
            const t = alert.templates[language] || alert.templates.en;
            const popup = document.createElement('article');
            popup.className = 'pointer-events-auto translate-x-0 rounded-2xl border border-[#c9e5f5] bg-white p-5 text-sm text-[#0b2a40] shadow-xl transition-transform duration-300 dark:border-[#365165] dark:bg-[#1f3444] dark:text-white';
            popup.setAttribute('role', 'status');
            popup.innerHTML = '<header class="flex items-start justify-between gap-3 border-b border-[#c9e5f5] pb-3"><strong data-title></strong><button type="button" data-close class="text-xl" aria-label="Close">×</button></header><p data-live class="mt-3 font-bold text-red-600"></p><p data-match class="mt-2 font-bold"></p><p data-league class="mt-1 text-[#5a7d99] dark:text-[#8aaccc]"></p><p data-time class="mt-3"></p><p data-odds class="mt-2 font-bold"></p><p data-strategy class="mt-2 font-semibold"></p><footer class="mt-4 flex flex-wrap gap-3 border-t border-[#c9e5f5] pt-3"><a data-view class="rounded-full bg-[#1a6b9c] px-4 py-2 font-bold text-white"></a><button type="button" data-mute class="rounded-full border border-[#c9e5f5] px-4 py-2"></button></footer>';
            popup.querySelector('[data-title]').textContent = `${alert.alert_type === 'goal' ? '⚽' : '🔔'} ${t.title}`;
            popup.querySelector('[data-live]').textContent = alert.alert_type === 'goal' ? '' : t.status_label;
            popup.querySelector('[data-match]').textContent = `⚽ ${alert.match}`;
            popup.querySelector('[data-league]').textContent = `🏆 ${alert.league}`;
            popup.querySelector('[data-time]').textContent = alert.alert_type === 'goal' ? `${t.score_label}: ${alert.score} · ${alert.minute}' ${t.minute_label} (${t.half_label})` : `${alert.minute}' ${t.minute_label} | ${t.score_label}: ${alert.score}`;
            popup.querySelector('[data-odds]').textContent = alert.alert_type === 'ltd' ? `📈 ${t.odds_label}: ${Number(alert.draw_lay).toFixed(2)}` : '';
            popup.querySelector('[data-strategy]').textContent = alert.alert_type === 'goal' ? `✅ ${t.status_label}` : `🎯 ${t.strategy_label}`;
            const view = popup.querySelector('[data-view]');
            view.textContent = t.view_btn;
            const target = new URL(alert.url, window.location.origin);
            view.href = target.origin === window.location.origin ? target.href : '/';
            const mute = popup.querySelector('[data-mute]');
            mute.textContent = `🔕 ${t.mute_btn || (language === 'ka' ? 'აღარ ჩამაჩვენო' : "Don't show again")}`;
            mute.addEventListener('click', async () => {
                if (!window.confirm(language === 'ka' ? 'გამორთოთ პლატფორმის შეტყობინებები? Telegram უცვლელი დარჩება.' : 'Disable platform pop-up alerts? Telegram preferences will stay unchanged.')) return;
                mute.disabled = true;
                try {
                    updateNotificationStatus(await notificationRequest(alertContainer.dataset.preferencesUrl, 'PATCH', { popup_enabled: false }), true);
                } catch (error) {
                    mute.textContent = error.message;
                    mute.disabled = false;
                }
            });
            let remaining = 8000;
            let started = performance.now();
            let dismiss = setTimeout(() => popup.remove(), remaining);
            popup.querySelector('[data-close]').addEventListener('click', () => { clearTimeout(dismiss); popup.remove(); });
            popup.addEventListener('mouseenter', () => { clearTimeout(dismiss); remaining = Math.max(0, remaining - (performance.now() - started)); });
            popup.addEventListener('mouseleave', () => { started = performance.now(); dismiss = setTimeout(() => popup.remove(), remaining); });
            if (alertContainer.children.length >= 3) alertContainer.firstElementChild.remove();
            alertContainer.append(popup);
            popup.animate([{ transform: 'translateX(110%)', opacity: 0 }, { transform: 'translateX(0)', opacity: 1 }], { duration: 300 });
        });
}
