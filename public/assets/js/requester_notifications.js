(function () {
    const root = document.getElementById('requesterNotifications');
    if (!root) {
        return;
    }

    const API = '../../app/api/user_notifications.php';
    const bell = document.getElementById('requesterNotificationsBell');
    const badge = document.getElementById('requesterNotificationsBadge');
    const panel = document.getElementById('requesterNotificationsPanel');
    const list = document.getElementById('requesterNotificationsList');

    let panelOpen = false;
    let notifications = [];

    function setBadgeCount(count) {
        if (!badge) {
            return;
        }
        const safe = Math.max(0, Number(count) || 0);
        if (safe <= 0) {
            badge.hidden = true;
            badge.textContent = '0';
            return;
        }
        badge.hidden = false;
        badge.textContent = safe > 99 ? '99+' : String(safe);
    }

    function iconForType(type) {
        if (type === 'payment_ready') {
            return 'fa-money-bill-wave';
        }
        return 'fa-bell';
    }

    function renderList() {
        if (!list) {
            return;
        }
        if (!notifications.length) {
            list.innerHTML = '<p class="requester-notifications-empty">No notifications yet.</p>';
            return;
        }

        list.innerHTML = notifications
            .map((item) => {
                const unreadClass = item.is_read ? 'is-read' : 'is-unread';
                return `
                    <button
                        type="button"
                        class="requester-notification-item ${unreadClass}"
                        data-notification-id="${item.notification_id}"
                    >
                        <span class="requester-notification-icon" aria-hidden="true">
                            <i class="fas ${iconForType(item.type)}"></i>
                        </span>
                        <span class="requester-notification-body">
                            <span class="requester-notification-message">${item.message_html || ''}</span>
                            <span class="requester-notification-secondary">${item.secondary || ''}</span>
                        </span>
                    </button>
                `;
            })
            .join('');

        list.querySelectorAll('.requester-notification-item').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = Number(btn.getAttribute('data-notification-id') || 0);
                if (id > 0) {
                    void markRead(id);
                }
            });
        });
    }

    async function fetchNotifications() {
        try {
            const res = await fetch(`${API}?action=list&limit=15`, { credentials: 'same-origin' });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Could not load notifications.');
            }
            notifications = Array.isArray(data.notifications) ? data.notifications : [];
            setBadgeCount(data.unread_count);
            if (panelOpen) {
                renderList();
            }
        } catch (err) {
            console.error('Failed to load notifications:', err);
        }
    }

    async function markRead(notificationId) {
        try {
            const res = await fetch(`${API}?action=mark_read`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Could not mark notification as read.');
            }
            notifications = notifications.map((item) =>
                item.notification_id === notificationId ? { ...item, is_read: true } : item
            );
            setBadgeCount(data.unread_count);
            renderList();
        } catch (err) {
            console.error('Failed to mark notification read:', err);
        }
    }

    function closePanel() {
        panelOpen = false;
        if (panel) {
            panel.hidden = true;
        }
        if (bell) {
            bell.setAttribute('aria-expanded', 'false');
        }
    }

    function openPanel() {
        panelOpen = true;
        if (panel) {
            panel.hidden = false;
        }
        if (bell) {
            bell.setAttribute('aria-expanded', 'true');
        }
        renderList();
    }

    if (bell) {
        bell.addEventListener('click', (event) => {
            event.stopPropagation();
            if (panelOpen) {
                closePanel();
            } else {
                openPanel();
                void fetchNotifications();
            }
        });
    }

    document.addEventListener('click', (event) => {
        if (!panelOpen || !root.contains(event.target)) {
            closePanel();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePanel();
        }
    });

    void fetchNotifications();
    window.setInterval(() => {
        void fetchNotifications();
    }, 60000);
})();
