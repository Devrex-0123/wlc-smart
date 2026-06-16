(function () {
    const list = document.getElementById('notificationsPageList');
    if (!list) {
        return;
    }

    const API = '../../app/api/user_notifications.php';

    function iconForType(type) {
        return type === 'payment_ready' ? 'fa-money-bill-wave' : 'fa-bell';
    }

    async function loadAll() {
        try {
            const res = await fetch(`${API}?action=list&limit=50`, { credentials: 'same-origin' });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Could not load notifications.');
            }
            const items = Array.isArray(data.notifications) ? data.notifications : [];
            if (!items.length) {
                list.innerHTML = '<p class="requester-notifications-empty">No notifications yet.</p>';
                return;
            }
            list.innerHTML = items
                .map((item) => {
                    const unreadClass = item.is_read ? 'is-read' : 'is-unread';
                    return `
                        <article class="notifications-page-item ${unreadClass}" data-notification-id="${item.notification_id}">
                            <span class="requester-notification-icon"><i class="fas ${iconForType(item.type)}"></i></span>
                            <div class="requester-notification-body">
                                <p class="requester-notification-message">${item.message_html || ''}</p>
                                <p class="requester-notification-secondary">${item.secondary || ''}</p>
                            </div>
                        </article>
                    `;
                })
                .join('');

            list.querySelectorAll('.notifications-page-item.is-unread').forEach((el) => {
                el.addEventListener('click', async () => {
                    const id = Number(el.getAttribute('data-notification-id') || 0);
                    if (id <= 0) {
                        return;
                    }
                    await fetch(`${API}?action=mark_read`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ notification_id: id }),
                    });
                    el.classList.remove('is-unread');
                    el.classList.add('is-read');
                });
            });
        } catch (err) {
            console.error(err);
            list.innerHTML = '<p class="requester-notifications-empty">Unable to load notifications.</p>';
        }
    }

    void loadAll();
})();
