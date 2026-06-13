<?php
/**
 * Requester notification bell — include from employee/requester dashboards.
 * Expects $user to be loaded when available (optional, for initials only).
 */
?>
<div class="requester-notifications" id="requesterNotifications">
    <button
        type="button"
        class="requester-notifications-bell"
        id="requesterNotificationsBell"
        aria-label="Notifications"
        aria-expanded="false"
        aria-haspopup="true"
    >
        <i class="fas fa-bell" aria-hidden="true"></i>
        <span class="requester-notifications-badge" id="requesterNotificationsBadge" hidden>0</span>
    </button>
    <div class="requester-notifications-panel" id="requesterNotificationsPanel" hidden>
        <div class="requester-notifications-panel-header">
            <h3>Notifications</h3>
        </div>
        <div class="requester-notifications-list" id="requesterNotificationsList" aria-live="polite">
            <p class="requester-notifications-empty">No notifications yet.</p>
        </div>
        <div class="requester-notifications-panel-footer">
            <a href="notifications.php" class="requester-notifications-view-all">View all notifications</a>
        </div>
    </div>
</div>
