<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/db.php';

function sendJson(array $payload): void
{
    echo json_encode($payload);
    exit;
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
        "SELECT COUNT(DISTINCT rfa.request_id) FROM requisition_form_approval rfa
            WHERE LOWER(TRIM(COALESCE(rfa.requisition_status, 'pending'))) = 'pending'"
    );
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function countGsdAssignment(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(DISTINCT cva.request_id) FROM requisition_form_approval rfa
            LEFT JOIN canvass_verification_approval cva ON cva.request_id = rfa.request_id
            WHERE LOWER(TRIM(COALESCE(rfa.requisition_status, 'pending'))) = 'accept'
              AND (cva.canvas_assignee_user_id IS NULL OR cva.canvas_assignee_user_id = 0)
              AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) IN ('', 'pending')
              AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) != 'reject'"
    );
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function countGsdVerification(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(DISTINCT cva.request_id) FROM canvass_verification_approval cva
            WHERE LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) = 'accept'
              AND LOWER(TRIM(COALESCE(cva.gsd_status, 'pending'))) = 'pending'"
    );
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function countCanvasserAssigned(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(DISTINCT cva.request_id) FROM canvass_verification_approval cva
            WHERE cva.canvas_assignee_user_id = ?
              AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) IN ('', 'pending')
              AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) != 'reject'"
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function countComptrollerPending(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(DISTINCT cva.request_id) FROM canvass_verification_approval cva
            WHERE LOWER(TRIM(COALESCE(cva.comp_status, 'pending'))) = 'pending'
              AND LOWER(TRIM(COALESCE(cva.gsd_status, 'pending'))) = 'accept'"
    );
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function countPresidentPending(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(DISTINCT cva.request_id) FROM canvass_verification_approval cva
            WHERE LOWER(TRIM(COALESCE(cva.pres_status, 'pending'))) = 'pending'
              AND LOWER(TRIM(COALESCE(cva.comp_status, 'pending'))) = 'accept'"
    );
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function countRequesterAttention(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(DISTINCT r.request_id) FROM requisition_item r
            LEFT JOIN requisition_form_approval rfa ON rfa.request_id = r.request_id
            LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
            WHERE r.user_id = ?
              AND (
                  LOWER(TRIM(COALESCE(rfa.requisition_status, 'pending'))) = 'reject'
                  OR LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) = 'reject'
                  OR LOWER(TRIM(COALESCE(cva.gsd_status, 'pending'))) = 'reject'
              )"
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

try {
    $db = Database::connect();

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
