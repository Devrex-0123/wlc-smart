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
                    // Derive link: prefer server-provided link_url, fall back to building from requisition_id
                    let resolvedLink = item.link_url || '';
                    if (!resolvedLink && item.type === 'payment_ready' && item.requisition_id) {
                        resolvedLink = `dean_requisition_status_progress.php?rid=${item.requisition_id}`;
                    }
                    const linkAttr = resolvedLink ? ` data-link-url="${resolvedLink}"` : '';
                    const clickable = resolvedLink ? ' notifications-page-item--clickable' : '';
                    return `
                        <article class="notifications-page-item ${unreadClass}${clickable}" data-notification-id="${item.notification_id}"${linkAttr}>
                            <span class="requester-notification-icon"><i class="fas ${iconForType(item.type)}"></i></span>
                            <div class="requester-notification-body">
                                <p class="requester-notification-message">${item.message_html || ''}</p>
                                <p class="requester-notification-secondary">${item.secondary || ''}</p>
                            </div>
                        </article>
                    `;
                })
                .join('');

            list.querySelectorAll('.notifications-page-item').forEach((el) => {
                el.addEventListener('click', async () => {
                    const id = Number(el.getAttribute('data-notification-id') || 0);
                    const linkUrl = el.getAttribute('data-link-url') || '';
                    if (id > 0 && el.classList.contains('is-unread')) {
                        try {
                            await fetch(`${API}?action=mark_read`, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ notification_id: id }),
                            });
                        } catch (_) { /* non-blocking */ }
                        el.classList.remove('is-unread');
                        el.classList.add('is-read');
                    }
                    if (linkUrl) {
                        window.location.href = linkUrl;
                    }
                });
            });
        } catch (err) {
            console.error(err);
            list.innerHTML = '<p class="requester-notifications-empty">Unable to load notifications.</p>';
        }
    }

    void loadAll();
})();
