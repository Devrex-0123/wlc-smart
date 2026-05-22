<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/db.php';

function sendJson(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function ensureNotificationViewsTable(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS notification_views (
            notification_view_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            request_id INT NOT NULL,
            notification_key VARCHAR(64) NOT NULL,
            viewed_at DATETIME NOT NULL,
            UNIQUE KEY idx_user_request_key (user_id, request_id, notification_key),
            KEY idx_user_key (user_id, notification_key),
            KEY idx_request (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function getAllowedNotificationKeys(): array
{
    return [
        'inventory_review',
        'gsd_assignment',
        'gsd_verification',
        'gsd_total',
        'canvasser_assigned',
        'comptroller_pending',
        'president_pending',
        'requester_attention',
    ];
}

function getJsonPayload(): array
{
    $body = file_get_contents('php://input');
    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        return [];
    }
    return $payload;
}

function markNotificationsViewed(PDO $db, int $userId, string $notificationKey, int $requestId = 0): bool
{
    if ($requestId > 0) {
        $stmt = $db->prepare(
            'INSERT INTO notification_views (user_id, request_id, notification_key, viewed_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE viewed_at = VALUES(viewed_at)'
        );
        return $stmt->execute([$userId, $requestId, $notificationKey]);
    }

    switch ($notificationKey) {
        case 'inventory_review':
            $sql = "INSERT INTO notification_views (user_id, request_id, notification_key, viewed_at)
                SELECT ?, r.request_id, 'inventory_review', NOW()
                FROM requisition_item r
                LEFT JOIN requisition_form_approval rfa ON rfa.request_id = r.request_id
                WHERE LOWER(TRIM(COALESCE(rfa.requisition_status, 'pending'))) = 'pending'";
            break;
        case 'gsd_assignment':
            $sql = "INSERT INTO notification_views (user_id, request_id, notification_key, viewed_at)
                SELECT ?, r.request_id, 'gsd_assignment', NOW()
                FROM requisition_item r
                LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
                WHERE cva.canvas_assignee_user_id = ?
                  AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) IN ('', 'pending')
                  AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) != 'reject'";
            break;
        case 'gsd_verification':
            $sql = "INSERT INTO notification_views (user_id, request_id, notification_key, viewed_at)
                SELECT ?, r.request_id, 'gsd_verification', NOW()
                FROM requisition_item r
                LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
                WHERE LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) = 'accept'
                  AND LOWER(TRIM(COALESCE(cva.gsd_status, 'pending'))) = 'pending'";
            break;
        case 'canvasser_assigned':
            $sql = "INSERT INTO notification_views (user_id, request_id, notification_key, viewed_at)
                SELECT ?, r.request_id, 'canvasser_assigned', NOW()
                FROM requisition_item r
                LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
                WHERE cva.canvas_assignee_user_id = ?
                  AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) IN ('', 'pending')
                  AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) != 'reject'";
            break;
        case 'comptroller_pending':
            $sql = "INSERT INTO notification_views (user_id, request_id, notification_key, viewed_at)
                SELECT ?, r.request_id, 'comptroller_pending', NOW()
                FROM requisition_item r
                LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
                WHERE LOWER(TRIM(COALESCE(cva.comp_status, 'pending'))) = 'pending'
                  AND LOWER(TRIM(COALESCE(cva.gsd_status, 'pending'))) = 'accept'";
            break;
        case 'president_pending':
            $sql = "INSERT INTO notification_views (user_id, request_id, notification_key, viewed_at)
                SELECT ?, r.request_id, 'president_pending', NOW()
                FROM requisition_item r
                LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
                WHERE LOWER(TRIM(COALESCE(cva.pres_status, 'pending'))) = 'pending'
                  AND LOWER(TRIM(COALESCE(cva.comp_status, 'pending'))) = 'accept'";
            break;
        case 'requester_attention':
            $sql = "INSERT INTO notification_views (user_id, request_id, notification_key, viewed_at)
                SELECT ?, r.request_id, 'requester_attention', NOW()
                FROM requisition_item r
                WHERE r.user_id = ?";
            break;
        case 'gsd_total':
            $sql = "INSERT INTO notification_views (user_id, request_id, notification_key, viewed_at)
                SELECT ?, r.request_id, 'gsd_assignment', NOW()
                FROM requisition_item r
                LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
                WHERE cva.canvas_assignee_user_id = ?
                  AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) IN ('', 'pending')
                  AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) != 'reject'";
            $extraSql = "INSERT INTO notification_views (user_id, request_id, notification_key, viewed_at)
                SELECT ?, r.request_id, 'gsd_verification', NOW()
                FROM requisition_item r
                LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
                WHERE LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) = 'accept'
                  AND LOWER(TRIM(COALESCE(cva.gsd_status, 'pending'))) = 'pending'";
            break;
        default:
            return false;
    }

    $stmt = $db->prepare($sql . ' ON DUPLICATE KEY UPDATE viewed_at = VALUES(viewed_at)');
    if (strpos($sql, '?') !== false) {
        if (in_array($notificationKey, ['gsd_assignment', 'gsd_total', 'canvasser_assigned', 'requester_attention'], true)) {
            $result = $stmt->execute([$userId, $userId]);
        } else {
            $result = $stmt->execute([$userId]);
        }
    } else {
        $result = $stmt->execute();
    }

    if ($notificationKey === 'gsd_total' && $result) {
        $extraStmt = $db->prepare($extraSql . ' ON DUPLICATE KEY UPDATE viewed_at = VALUES(viewed_at)');
        $result = $extraStmt->execute([$userId]);
    }

    return (bool) $result;

    return false;
}

try {
    $db = Database::connect();
    ensureNotificationViewsTable($db);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['success' => false, 'message' => 'Method not allowed.']);
    }

    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Unauthorized']);
    }

    $payload = getJsonPayload();
    $notificationKey = trim((string)($payload['notification_key'] ?? ''));
    $allowedKeys = getAllowedNotificationKeys();
    if ($notificationKey === '' || !in_array($notificationKey, $allowedKeys, true)) {
        sendJson(['success' => false, 'message' => 'Invalid notification key.']);
    }

    $requestId = isset($payload['request_id']) ? (int)$payload['request_id'] : 0;

    if (!markNotificationsViewed($db, (int)$_SESSION['user_id'], $notificationKey, $requestId)) {
        sendJson(['success' => false, 'message' => 'Could not mark notifications as viewed.']);
    }

    sendJson(['success' => true, 'message' => 'Notifications marked as viewed.']);
} catch (Throwable $exception) {
    sendJson(['success' => false, 'message' => 'Could not update notification view state.']);
}
