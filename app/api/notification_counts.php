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

function getCurrentUserRole(PDO $db): string
{
    if (!isset($_SESSION['user_id'])) {
        return '';
    }

    $stmt = $db->prepare('SELECT role FROM user WHERE user_id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return strtolower(trim((string) ($row['role'] ?? '')));
}

function countInventoryReview(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM requisition_item r
            LEFT JOIN requisition_form_approval rfa ON rfa.request_id = r.request_id
            LEFT JOIN notification_views nv ON nv.request_id = r.request_id AND nv.user_id = ? AND nv.notification_key = 'inventory_review'
            WHERE LOWER(TRIM(COALESCE(rfa.requisition_status, 'pending'))) = 'pending'
              AND (
                  nv.viewed_at IS NULL
                  OR nv.viewed_at < GREATEST(COALESCE(r.created_at, '1970-01-01'), COALESCE(rfa.requisition_reviewed_at, '1970-01-01'))
              )"
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function countGsdAssignment(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM requisition_item r
            LEFT JOIN requisition_form_approval rfa ON rfa.request_id = r.request_id
            LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
            LEFT JOIN notification_views nv ON nv.request_id = r.request_id AND nv.user_id = ? AND nv.notification_key = 'gsd_assignment'
            WHERE LOWER(TRIM(COALESCE(rfa.requisition_status, 'pending'))) = 'accept'
              AND (cva.canvas_assignee_user_id IS NULL OR cva.canvas_assignee_user_id = 0)
              AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) IN ('', 'pending')
              AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) != 'reject'
              AND (
                  nv.viewed_at IS NULL
                  OR nv.viewed_at < GREATEST(COALESCE(r.created_at, '1970-01-01'), COALESCE(cva.canvassed_at, '1970-01-01'))
              )"
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function countGsdVerification(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM requisition_item r
            LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
            LEFT JOIN notification_views nv ON nv.request_id = r.request_id AND nv.user_id = ? AND nv.notification_key = 'gsd_verification'
            WHERE LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) = 'accept'
              AND LOWER(TRIM(COALESCE(cva.gsd_status, 'pending'))) = 'pending'
              AND (
                  nv.viewed_at IS NULL
                  OR nv.viewed_at < GREATEST(COALESCE(r.created_at, '1970-01-01'), COALESCE(cva.canvassed_at, '1970-01-01'), COALESCE(cva.verified_at, '1970-01-01'))
              )"
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function countCanvasserAssigned(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM requisition_item r
            LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
            LEFT JOIN notification_views nv ON nv.request_id = r.request_id AND nv.user_id = ? AND nv.notification_key = 'canvasser_assigned'
            WHERE cva.canvas_assignee_user_id = ?
              AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) IN ('', 'pending')
              AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) != 'reject'
              AND (
                  nv.viewed_at IS NULL
                  OR nv.viewed_at < GREATEST(COALESCE(r.created_at, '1970-01-01'), COALESCE(cva.canvassed_at, '1970-01-01'))
              )"
    );
    $stmt->execute([$userId, $userId]);

    return (int) $stmt->fetchColumn();
}

function countComptrollerPending(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM requisition_item r
            LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
            LEFT JOIN notification_views nv ON nv.request_id = r.request_id AND nv.user_id = ? AND nv.notification_key = 'comptroller_pending'
            WHERE LOWER(TRIM(COALESCE(cva.comp_status, 'pending'))) = 'pending'
              AND LOWER(TRIM(COALESCE(cva.gsd_status, 'pending'))) = 'accept'
              AND (
                  nv.viewed_at IS NULL
                  OR nv.viewed_at < GREATEST(COALESCE(r.created_at, '1970-01-01'), COALESCE(cva.verified_at, '1970-01-01'))
              )"
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function countPresidentPending(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM requisition_item r
            LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
            LEFT JOIN notification_views nv ON nv.request_id = r.request_id AND nv.user_id = ? AND nv.notification_key = 'president_pending'
            WHERE LOWER(TRIM(COALESCE(cva.pres_status, 'pending'))) = 'pending'
              AND LOWER(TRIM(COALESCE(cva.comp_status, 'pending'))) = 'accept'
              AND (
                  nv.viewed_at IS NULL
                  OR nv.viewed_at < GREATEST(COALESCE(r.created_at, '1970-01-01'), COALESCE(cva.verified_at, '1970-01-01'), COALESCE(cva.approved_at, '1970-01-01'))
              )"
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function countRequesterAttention(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM requisition_item r
            LEFT JOIN requisition_form_approval rfa ON rfa.request_id = r.request_id
            LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
            LEFT JOIN purchase_requisition_approval pra ON pra.request_id = r.request_id
            LEFT JOIN notification_views nv ON nv.request_id = r.request_id AND nv.user_id = ? AND nv.notification_key = 'requester_attention'
            WHERE r.user_id = ?
              AND (
                  nv.viewed_at IS NULL
                  OR nv.viewed_at < GREATEST(
                        COALESCE(r.created_at, '1970-01-01'),
                        COALESCE(rfa.requisition_reviewed_at, '1970-01-01'),
                        COALESCE(cva.canvassed_at, '1970-01-01'),
                        COALESCE(cva.checked_at, '1970-01-01'),
                        COALESCE(cva.verified_at, '1970-01-01'),
                        COALESCE(cva.approved_at, '1970-01-01'),
                        COALESCE(pra.pr_inv_at, '1970-01-01'),
                        COALESCE(pra.pr_pres_at, '1970-01-01')
                    )
              )"
    );
    $stmt->execute([$userId, $userId]);

    return (int) $stmt->fetchColumn();
}

try {
    $db = Database::connect();
    ensureNotificationViewsTable($db);

    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Unauthorized']);
    }

    $role = getCurrentUserRole($db);
    $userId = (int) $_SESSION['user_id'];

    $counts = [
        'inventory_review' => countInventoryReview($db, $userId),
        'gsd_assignment' => countGsdAssignment($db, $userId),
        'gsd_verification' => countGsdVerification($db, $userId),
        'canvasser_assigned' => countCanvasserAssigned($db, $userId),
        'comptroller_pending' => countComptrollerPending($db, $userId),
        'president_pending' => countPresidentPending($db, $userId),
        'requester_attention' => countRequesterAttention($db, $userId),
    ];

    $counts['gsd_total'] = $counts['gsd_assignment'] + $counts['gsd_verification'];

    sendJson([
        'success' => true,
        'role' => $role,
        'counts' => $counts,
    ]);
} catch (Throwable $exception) {
    sendJson(['success' => false, 'message' => 'Could not fetch notification counts.']);
}
