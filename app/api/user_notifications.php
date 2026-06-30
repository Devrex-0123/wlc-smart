<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/../helpers/user_notifications.php';

function sendJson(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function getJsonPayload(): array
{
    $body = file_get_contents('php://input');
    $payload = json_decode($body, true);

    return is_array($payload) ? $payload : [];
}

try {
    $db = Database::connect();
    ensureUserNotificationsTable($db);

    $userId = null;

    if (isset($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];
    } elseif (
        isset($_SESSION['login_type']) &&
        $_SESSION['login_type'] === 'department' &&
        !empty($_SESSION['department_id'])
    ) {
        require_once __DIR__ . '/../helpers/dean_office_context.php';
        $deptStmt = $db->prepare(
            'SELECT department_abbreviation, department_name FROM departments WHERE department_id = ? LIMIT 1'
        );
        $deptStmt->execute([(int) $_SESSION['department_id']]);
        $dept = $deptStmt->fetch(PDO::FETCH_ASSOC);
        if ($dept) {
            $office = cwirms_resolve_office_for_department($db, $dept);
            if ($office) {
                $actingId = cwirms_find_dean_user_id_for_office($db, (int) $office['office_id']);
                if ($actingId) {
                    $userId = $actingId;
                }
            }
        }
    }

    if (!$userId) {
        sendJson(['success' => false, 'message' => 'Unauthorized']);
    }
    $action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

    if ($action === 'count_unread') {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0'
        );
        $stmt->execute([$userId]);
        sendJson([
            'success' => true,
            'unread_count' => (int) $stmt->fetchColumn(),
        ]);
    }

    if ($action === 'list') {
        $limit = (int) ($_GET['limit'] ?? 15);
        if ($limit <= 0 || $limit > 50) {
            $limit = 15;
        }

        $stmt = $db->prepare(
            'SELECT notification_id, user_id, purchase_order_id, requisition_id, type, meta_json, is_read, created_at
             FROM user_notifications
             WHERE user_id = ?
             ORDER BY created_at DESC, notification_id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $notifications = array_map(static function (array $row): array {
            return cwirmsFormatUserNotificationRow($row);
        }, $rows);

        $countStmt = $db->prepare(
            'SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0'
        );
        $countStmt->execute([$userId]);

        sendJson([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => (int) $countStmt->fetchColumn(),
        ]);
    }

    if ($action === 'mark_read') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJson(['success' => false, 'message' => 'Method not allowed.']);
        }

        $payload = getJsonPayload();
        $notificationId = (int) ($payload['notification_id'] ?? $_POST['notification_id'] ?? 0);
        if ($notificationId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid notification id.']);
        }

        $stmt = $db->prepare(
            'UPDATE user_notifications
             SET is_read = 1, read_at = NOW()
             WHERE notification_id = ? AND user_id = ?'
        );
        $stmt->execute([$notificationId, $userId]);

        $countStmt = $db->prepare(
            'SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0'
        );
        $countStmt->execute([$userId]);

        sendJson([
            'success' => true,
            'unread_count' => (int) $countStmt->fetchColumn(),
        ]);
    }

    sendJson(['success' => false, 'message' => 'Unknown action.']);
} catch (Throwable $exception) {
    sendJson(['success' => false, 'message' => 'Could not process notifications request.']);
}
