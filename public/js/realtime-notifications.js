(() => {
    'use strict';

    const config = window.FarmReachRealtime;

    if (!config) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const knownIds = new Set();
    const state = {
        unreadCount: Number(config.unreadCount) || 0,
        actionSequence: 0,
        syncInFlight: false,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    function boot() {
        collectKnownIds();
        updateUnreadUI();
        bindNotificationForms();
        bindSyncTriggers();
        initializeEcho();
        syncFeed();
    }

    function collectKnownIds() {
        document.querySelectorAll('[data-notification-id]').forEach((element) => {
            if (element.dataset.notificationId) {
                knownIds.add(element.dataset.notificationId);
            }
        });
    }

    function initializeEcho() {
        if (!config.key || !config.channel || typeof Echo === 'undefined' || typeof Pusher === 'undefined') {
            return;
        }

        window.Pusher = Pusher;

        const secure = config.scheme === 'https';
        const echo = new Echo({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: config.wsHost,
            wsPort: Number(config.wsPort),
            wssPort: Number(config.wsPort),
            forceTLS: secure,
            enabledTransports: [secure ? 'wss' : 'ws'],
            disableStats: true,
            authEndpoint: config.authEndpoint,
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        });

        window.FarmReachEcho = echo;

        echo.private(config.channel)
            .listen('.notification.created', handleLiveNotification);

        const connection = echo.connector?.pusher?.connection;

        if (connection) {
            connection.bind('connected', syncFeed);
            connection.bind('state_change', (states) => {
                if (states.current === 'connected') {
                    syncFeed();
                }
            });
        }
    }

    function handleLiveNotification(notification) {
        if (!notification?.id || knownIds.has(notification.id)) {
            return;
        }

        knownIds.add(notification.id);
        state.actionSequence += 1;

        if (!notification.read_at) {
            state.unreadCount += 1;
        }

        renderDropdownItem(notification);
        renderHistoryItem(notification);

        if (notification.read_at) {
            applyReadState(notification.id, true);
        }

        trimDropdown();
        updateUnreadUI();
        pulseBadges();
    }

    async function syncFeed() {
        if (state.syncInFlight || !config.feedUrl) {
            return;
        }

        state.syncInFlight = true;
        const sequenceAtStart = state.actionSequence;

        try {
            const response = await fetch(config.feedUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (!Array.isArray(data.notifications)) {
                return;
            }

            data.notifications
                .slice()
                .reverse()
                .forEach((notification) => {
                    if (!notification?.id) {
                        return;
                    }

                    if (!knownIds.has(notification.id)) {
                        knownIds.add(notification.id);
                        renderDropdownItem(notification);
                        renderHistoryItem(notification);
                    }

                    if (notification.read_at) {
                        applyReadState(notification.id, true);
                    }
                });

            trimDropdown();

            if (sequenceAtStart === state.actionSequence) {
                state.unreadCount = Number(data.unread_count) || 0;
            }

            updateUnreadUI();
        } catch (error) {
            console.warn('Unable to synchronize notifications.', error);
        } finally {
            state.syncInFlight = false;
        }
    }

    function bindSyncTriggers() {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                syncFeed();
            }
        });

        window.addEventListener('pageshow', syncFeed);
        window.addEventListener('online', syncFeed);
        window.addEventListener('focus', syncFeed);
    }

    function bindNotificationForms() {
        document.addEventListener('submit', (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if (form.matches('[data-notification-read-form]')) {
                event.preventDefault();
                markAsRead(form);
            }

            if (form.matches('[data-notification-mark-all]')) {
                event.preventDefault();
                markAllAsRead(form);
            }
        });
    }

    async function markAsRead(form) {
        if (form.dataset.submitting === 'true') {
            return;
        }

        setFormSubmitting(form, true);

        try {
            const response = await postForm(form);

            if (!response.ok) {
                throw new Error('Notification request failed.');
            }

            const data = await response.json();
            state.actionSequence += 1;
            state.unreadCount = Number(data.unread_count) || 0;

            applyReadState(form.dataset.notificationId, true);
            updateUnreadUI();

            if (isSafeUrl(data.redirect_url)) {
                window.location.assign(data.redirect_url);

                return;
            }
        } catch (error) {
            form.submit();

            return;
        } finally {
            setFormSubmitting(form, false);
        }
    }

    async function markAllAsRead(form) {
        if (form.dataset.submitting === 'true') {
            return;
        }

        setFormSubmitting(form, true);

        try {
            const response = await postForm(form);

            if (!response.ok) {
                throw new Error('Notification request failed.');
            }

            const data = await response.json();
            state.actionSequence += 1;
            state.unreadCount = Number(data.unread_count) || 0;

            markEveryItemRead();
            updateUnreadUI();
            pulseBadges();
        } catch (error) {
            form.submit();

            return;
        } finally {
            setFormSubmitting(form, false);
        }
    }

    function postForm(form) {
        return fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
            credentials: 'same-origin',
        });
    }

    function setFormSubmitting(form, submitting) {
        form.dataset.submitting = submitting ? 'true' : 'false';

        form.querySelectorAll('button').forEach((button) => {
            button.disabled = submitting;
        });
    }

    function applyReadState(notificationId, isRead) {
        if (!notificationId) {
            return;
        }

        document.querySelectorAll('[data-notification-id]').forEach((element) => {
            if (element.dataset.notificationId !== notificationId) {
                return;
            }

            element.querySelectorAll('.is-unread').forEach((unreadElement) => {
                unreadElement.classList.remove('is-unread');
            });

            if (isRead) {
                element.classList.remove('is-unread');

                element.querySelectorAll('[data-notification-unread-dot]').forEach((dot) => {
                    dot.remove();
                });

                element.querySelectorAll('[data-notification-read-badge]').forEach((badge) => {
                    badge.classList.remove('d-none');
                });

                element.querySelectorAll('[data-notification-mark-read]').forEach((markReadForm) => {
                    markReadForm.classList.add('d-none');
                });
            }
        });
    }

    function markEveryItemRead() {
        document.querySelectorAll('[data-notification-id]').forEach((element) => {
            element.classList.remove('is-unread');

            element.querySelectorAll('.is-unread').forEach((unreadElement) => {
                unreadElement.classList.remove('is-unread');
            });

            element.querySelectorAll('[data-notification-unread-dot]').forEach((dot) => {
                dot.remove();
            });

            element.querySelectorAll('[data-notification-read-badge]').forEach((badge) => {
                badge.classList.remove('d-none');
            });

            element.querySelectorAll('[data-notification-mark-read]').forEach((markReadForm) => {
                markReadForm.classList.add('d-none');
            });
        });
    }

    function updateUnreadUI() {
        const count = Math.max(0, Number(state.unreadCount) || 0);

        document.querySelectorAll('[data-notification-unread-badge]').forEach((badge) => {
            badge.textContent = count > 99 ? '99+' : String(count);
            setVisible(badge, count > 0);
        });

        document.querySelectorAll('[data-notification-count-pill]').forEach((pill) => {
            pill.textContent = String(count);
            setVisible(pill, count > 0);
        });

        document.querySelectorAll('[data-notification-unread-label]').forEach((label) => {
            label.textContent = count > 0 ? `${count} unread` : "You're all caught up";
        });

        document.querySelectorAll('[data-notification-mark-all]').forEach((form) => {
            setVisible(form, count > 0);
        });

        const summary = document.querySelector('[data-notification-history-summary]');

        if (summary) {
            summary.textContent = count > 0
                ? `You have ${count} unread notification${count === 1 ? '' : 's'}.`
                : "You're all caught up.";
        }
    }

    function setVisible(element, visible) {
        element.hidden = !visible;
        element.classList.toggle('d-none', !visible);
    }

    function pulseBadges() {
        const badges = document.querySelectorAll('[data-notification-unread-badge], [data-notification-count-pill]');

        badges.forEach((badge) => {
            badge.classList.remove('badge-bounce');
            void badge.offsetWidth;
            badge.classList.add('badge-bounce');
        });

        window.setTimeout(() => {
            badges.forEach((badge) => badge.classList.remove('badge-bounce'));
        }, 600);
    }

    function renderDropdownItem(notification) {
        const list = document.querySelector('[data-notification-list]');

        if (!list) {
            return;
        }

        list.querySelector('[data-notification-empty]')?.classList.add('d-none');

        const form = createReadForm(notification, 'dropdown');
        const button = document.createElement('button');

        button.type = 'submit';
        button.className = `notification-dropdown-item${notification.read_at ? '' : ' is-unread'}`;

        const iconWrapper = document.createElement('span');
        iconWrapper.className = 'notification-item-icon';

        const icon = document.createElement('i');
        icon.className = `bi ${safeIcon(notification.icon)}`;
        iconWrapper.appendChild(icon);

        const content = document.createElement('span');
        content.className = 'notification-item-content';

        const title = document.createElement('span');
        title.className = 'notification-item-title';
        title.textContent = notification.title || 'Notification';

        const message = document.createElement('span');
        message.className = 'notification-item-message';
        message.textContent = notification.message || '';

        const meta = document.createElement('span');
        meta.className = 'notification-item-meta';
        meta.textContent = formatRelative(notification.created_at);

        if (notification.reference) {
            meta.textContent += ` • ${notification.reference}`;
        }

        content.append(title, message, meta);
        button.append(iconWrapper, content);

        if (!notification.read_at) {
            const dot = document.createElement('span');
            dot.className = 'notification-unread-dot';
            dot.dataset.notificationUnreadDot = '';
            dot.setAttribute('aria-label', 'Unread');
            button.appendChild(dot);
        }

        form.appendChild(button);
        list.prepend(form);
    }

    function renderHistoryItem(notification) {
        const list = document.querySelector('[data-notification-history-list]');

        if (!list) {
            return;
        }

        list.classList.remove('d-none');
        document.querySelector('[data-notification-history-empty]')?.classList.add('d-none');

        const article = document.createElement('article');
        article.className = `notification-list-item${notification.read_at ? '' : ' is-unread'}`;
        article.dataset.notificationId = notification.id;
        article.dataset.notificationHistoryItem = '';

        const iconWrapper = document.createElement('span');
        iconWrapper.className = 'notification-list-icon';

        const icon = document.createElement('i');
        icon.className = `bi ${safeIcon(notification.icon)}`;
        iconWrapper.appendChild(icon);

        const content = document.createElement('div');
        content.className = 'notification-list-content';

        const heading = document.createElement('div');
        heading.className = 'd-flex flex-wrap justify-content-between gap-1';

        const title = document.createElement('h2');
        title.className = 'h6 fw-bold mb-1';
        title.textContent = notification.title || 'Notification';

        const relativeTime = document.createElement('time');
        relativeTime.className = 'notification-time';
        relativeTime.dateTime = notification.created_at || '';
        relativeTime.title = formatAbsolute(notification.created_at);
        relativeTime.textContent = formatRelative(notification.created_at);

        heading.append(title, relativeTime);

        const message = document.createElement('p');
        message.className = 'text-muted mb-2';
        message.textContent = notification.message || '';

        const meta = document.createElement('div');
        meta.className = 'd-flex flex-wrap align-items-center gap-2';

        if (notification.reference) {
            const reference = document.createElement('span');
            reference.className = 'badge bg-light text-dark border';
            reference.textContent = notification.reference;
            meta.appendChild(reference);
        }

        const absoluteTime = document.createElement('span');
        absoluteTime.className = 'notification-time';
        absoluteTime.textContent = formatAbsolute(notification.created_at);
        meta.appendChild(absoluteTime);

        const readBadge = document.createElement('span');
        readBadge.className = `badge bg-success-subtle text-success-emphasis${notification.read_at ? '' : ' d-none'}`;
        readBadge.dataset.notificationReadBadge = '';
        readBadge.textContent = 'Read';
        meta.appendChild(readBadge);

        content.append(heading, message, meta);

        const actions = document.createElement('div');
        actions.className = 'notification-list-actions';

        if (isSafeUrl(notification.url)) {
            actions.appendChild(createReadForm(notification, 'view'));
        }

        if (!notification.read_at) {
            actions.appendChild(createReadForm(notification, 'read'));
        }

        article.append(iconWrapper, content, actions);
        list.prepend(article);
    }

    function createReadForm(notification, mode) {
        const form = document.createElement('form');

        form.method = 'POST';
        form.action = readUrl(notification.id);
        form.className = 'm-0';
        form.dataset.notificationReadForm = '';
        form.dataset.notificationId = notification.id;

        appendHiddenInput(form, '_token', csrfToken);

        if (mode !== 'read' && isSafeUrl(notification.url)) {
            appendHiddenInput(form, 'redirect_to', notification.url);
        }

        if (mode === 'view') {
            const button = document.createElement('button');
            button.type = 'submit';
            button.className = 'btn btn-sm btn-farm-primary';
            button.textContent = 'View';
            form.appendChild(button);
        }

        if (mode === 'read') {
            form.dataset.notificationMarkRead = '';

            const button = document.createElement('button');
            button.type = 'submit';
            button.className = 'btn btn-sm btn-outline-secondary';
            button.textContent = 'Mark as read';
            form.appendChild(button);
        }

        return form;
    }

    function appendHiddenInput(form, name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }

    function trimDropdown() {
        const list = document.querySelector('[data-notification-list]');

        if (!list) {
            return;
        }

        const items = Array.from(list.children).filter((child) => child.matches('form[data-notification-read-form]'));

        items.slice(8).forEach((item) => item.remove());
    }

    function readUrl(notificationId) {
        const template = config.readUrlTemplate || '/notifications/__NOTIFICATION__/read';

        return template.replace('__NOTIFICATION__', encodeURIComponent(notificationId));
    }

    function isSafeUrl(url) {
        return typeof url === 'string'
            && url.startsWith('/')
            && !url.startsWith('//')
            && !url.includes('\\')
            && !url.includes('\r')
            && !url.includes('\n');
    }

    function safeIcon(icon) {
        return typeof icon === 'string' && /^bi-[a-z0-9-]+$/.test(icon) ? icon : 'bi-bell';
    }

    function formatRelative(value) {
        if (!value) {
            return 'Just now';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return 'Just now';
        }

        const seconds = Math.round((date.getTime() - Date.now()) / 1000);
        const absoluteSeconds = Math.abs(seconds);
        const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

        if (absoluteSeconds < 60) {
            return formatter.format(seconds, 'second');
        }

        if (absoluteSeconds < 3600) {
            return formatter.format(Math.round(seconds / 60), 'minute');
        }

        if (absoluteSeconds < 86400) {
            return formatter.format(Math.round(seconds / 3600), 'hour');
        }

        return formatter.format(Math.round(seconds / 86400), 'day');
    }

    function formatAbsolute(value) {
        if (!value) {
            return '';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return '';
        }

        return date.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    }
})();
