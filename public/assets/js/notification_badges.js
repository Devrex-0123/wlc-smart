(function () {
    const POLL_INTERVAL_MS = 45000;
    const API_URL = '/CWIRMS/app/api/notification_counts.php';
    const VIEW_API_URL = '/CWIRMS/app/api/notification_views.php';
    const NAV_MAP = {
        'requisition_management.php': 'inventory_review',
        'requisition_status.php': 'requester_attention',
        'dean_requisition_status.php': 'requester_attention',
        'dean_requisition_management.php': 'inventory_review',
        'gsd_request.php': 'gsd_total',
        'canvasser_request.php': 'canvasser_assigned',
        'comptroller_requests.php': 'comptroller_pending',
        'president_request.php': 'president_pending',
        'my_profile.php': 'requester_attention',
    };
    const VIEW_NAV_MAP = {
        'requisition_management.php': 'inventory_review',
        'requisition_status.php': 'requester_attention',
        'dean_requisition_status.php': 'requester_attention',
        'dean_requisition_management.php': 'inventory_review',
        'gsd_request.php': 'gsd_total',
        'canvasser_request.php': 'canvasser_assigned',
        'comptroller_requests.php': 'comptroller_pending',
        'president_request.php': 'president_pending',
    };

    function createBadge(count) {
        const badge = document.createElement('span');
        badge.className = 'sidebar-nav-badge';
        badge.setAttribute('aria-label', `${count} pending items`);
        badge.textContent = count.toString();
        return badge;
    }

    function getNotificationKeyFromHref(href) {
        if (typeof href !== 'string' || href.trim() === '') {
            return null;
        }
        const fileName = href.split('?')[0].split('#')[0].replace(/^.*\//, '').trim().toLowerCase();
        return NAV_MAP[fileName] || null;
    }

    function getViewKeyFromHref(href) {
        if (typeof href !== 'string' || href.trim() === '') {
            return null;
        }
        const fileName = href.split('?')[0].split('#')[0].replace(/^.*\//, '').trim().toLowerCase();
        return VIEW_NAV_MAP[fileName] || null;
    }

    function updateBadgeForLink(link, counts) {
        if (!link) {
            return;
        }
        const customKey = link.dataset.notificationKey;
        const key = typeof customKey === 'string' && customKey.trim() !== ''
            ? customKey.trim()
            : getNotificationKeyFromHref(link.getAttribute('href'));
        if (!key) {
            return;
        }

        const count = Number(counts[key] ?? 0);
        let badge = link.querySelector('.sidebar-nav-badge');
        if (count > 0) {
            if (!badge) {
                badge = createBadge(count);
                link.appendChild(badge);
            } else {
                badge.textContent = count.toString();
            }
        } else if (badge) {
            badge.remove();
        }
    }

    function updateDashboardBadges(counts) {
        const badgeTargets = document.querySelectorAll('.dashboard-card[data-notification-key]');
        if (!badgeTargets.length) {
            return;
        }
        badgeTargets.forEach((element) => {
            const key = element.dataset.notificationKey?.trim();
            if (!key) {
                return;
            }

            const count = Number(counts[key] ?? 0);
            let badge = element.querySelector('.notification-card-badge');
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'notification-card-badge';
                    element.appendChild(badge);
                }
                badge.textContent = count.toString();
            } else if (badge) {
                badge.remove();
            }
        });
    }

    async function markNotificationViewed(key) {
        if (!key) {
            return;
        }

        try {
            await fetch(VIEW_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ notification_key: key }),
            });
        } catch (error) {
            console.error('Notification view marker failed:', error);
        }
    }

    function getNotificationKeyForElement(element) {
        if (!element) {
            return null;
        }

        const customKey = element.dataset.notificationKey;
        if (typeof customKey === 'string' && customKey.trim() !== '') {
            return customKey.trim();
        }

        return getNotificationKeyFromHref(element.getAttribute('href'));
    }

    function getViewKeyForElement(element) {
        if (!element) {
            return null;
        }

        const customKey = element.dataset.notificationViewKey;
        if (typeof customKey === 'string' && customKey.trim() !== '') {
            return customKey.trim();
        }

        return getViewKeyFromHref(element.getAttribute('href'));
    }

    function markViewedForActivePage() {
        const keys = new Set();
        const activeLink = document.querySelector('.sidebar-nav a.active');
        if (activeLink) {
            const activeKey = getViewKeyForElement(activeLink);
            if (activeKey) {
                keys.add(activeKey);
            }
        }

        const explicitTargets = document.querySelectorAll('.dashboard-card[data-notification-key], [data-notification-view-key]');
        explicitTargets.forEach((target) => {
            const key = target.dataset.notificationViewKey?.trim() || target.dataset.notificationKey?.trim();
            if (key) {
                keys.add(key);
            }
        });

        keys.forEach((key) => markNotificationViewed(key));
    }

    function updateNavigationBadges(counts) {
        const links = document.querySelectorAll('.sidebar-nav a');
        if (!links.length) {
            return;
        }
        links.forEach((link) => updateBadgeForLink(link, counts));
    }

    async function fetchNotificationCounts() {
        try {
            const response = await fetch(API_URL, { credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error(`Network response was not ok (${response.status})`);
            }

            const payload = await response.json();
            if (!payload || !payload.success || typeof payload.counts !== 'object') {
                throw new Error(payload?.message || 'Invalid notification payload');
            }

            updateNavigationBadges(payload.counts);
            updateDashboardBadges(payload.counts);
        } catch (error) {
            // Intentional: keep notification badges non-blocking.
            console.error('Notification badges failed to load:', error);
        }
    }

    function startPolling() {
        markViewedForActivePage();
        fetchNotificationCounts();
        setInterval(fetchNotificationCounts, POLL_INTERVAL_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startPolling);
    } else {
        startPolling();
    }
})();

/*
 * Back-navigation guard for authenticated pages.
 * Once logged in, pressing the browser's Back button must never return the
 * user to the login/landing page. We push a history state on load and re-push
 * it whenever the user navigates back, keeping them on the current page.
 * Server-side session guards remain the source of truth for access control.
 */
(function () {
    try {
        history.pushState(null, document.title, location.href);
        window.addEventListener('popstate', function () {
            history.pushState(null, document.title, location.href);
        });
    } catch (e) {
        /* History API unavailable: server-side guards still protect access. */
    }
})();
