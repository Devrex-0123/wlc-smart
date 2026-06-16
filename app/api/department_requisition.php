<?php
/**
 * Department requisition API — office-scoped lists for department portal.
 */
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/../helpers/dean_office_context.php';
require_once __DIR__ . '/requisition_detail_payload.php';

function sendJson(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function mapDepartmentRequestRow(array $row): array
{
    $email = (string) ($row['Email'] ?? '');
    $requester = $email !== '' ? (explode('@', $email)[0] ?? 'Unknown') : 'Department';

    return [
        'id' => 'REQ-' . str_pad((string) $row['request_id'], 6, '0', STR_PAD_LEFT),
        'request_id' => (int) $row['request_id'],
        'date' => $row['created_at'],
        'updated_at' => $row['updated_at'] ?? $row['created_at'],
        'items' => requisitionExplodePipeOrDefault($row['items_concat'] ?? null, '—'),
        'suppliers' => requisitionExplodePipeOrDefault($row['suppliers_concat'] ?? null, 'N/A'),
        'status' => $row['status'] ?? 'Pending',
        'message' => $row['message'] ?? '',
        'requisition_status' => (string) ($row['requisition_status'] ?? 'pending'),
        'requisition_note' => (string) ($row['requisition_note'] ?? ''),
        'canvas_status' => (string) ($row['canvas_status'] ?? 'pending'),
        'gsd_status' => (string) ($row['gsd_status'] ?? 'pending'),
        'comp_status' => (string) ($row['comp_status'] ?? 'pending'),
        'pres_status' => (string) ($row['pres_status'] ?? 'pending'),
        'pr_inv_status' => (string) ($row['pr_inv_status'] ?? 'pending'),
        'pr_pres_status' => (string) ($row['pr_pres_status'] ?? 'pending'),
        'purchase_order_id' => !empty($row['purchase_order_id']) ? (int) $row['purchase_order_id'] : null,
        'purchase_order_number' => (string) ($row['purchase_order_number'] ?? ''),
        'purchase_order_status' => (string) ($row['purchase_order_status'] ?? ''),
        'requester' => $requester,
        'office' => $row['office_name'] ?? '—',
    ];
}

try {
    $db = Database::connect();
    $ctx = cwirms_dean_api_require_context($db);

    if (empty($ctx['is_department_login'])) {
        sendJson(['success' => false, 'message' => 'Department access only.']);
    }

    $officeId = (int) ($ctx['office_id'] ?? 0);
    if ($officeId <= 0) {
        sendJson(['success' => false, 'message' => 'Department office is not linked.']);
    }

    $action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

    if ($action === 'list_requests') {
        $agg = requisitionSqlSelectListAggregates();
        $stmt = $db->prepare("
            SELECT r.request_id, r.created_at, r.status, r.message,
                   u.Email, d.`office_name` AS office_name, rfa.requisition_status, rfa.requisition_note,
                   cva.canvas_status, cva.gsd_status,
                   COALESCE(cva.comp_status, 'pending') AS comp_status,
                   COALESCE(cva.pres_status, 'pending') AS pres_status,
                   COALESCE(pra.pr_inv_status, 'pending') AS pr_inv_status,
                   COALESCE(pra.pr_pres_status, 'pending') AS pr_pres_status,
                   po.id AS purchase_order_id,
                   po.po_number AS purchase_order_number,
                   po.status AS purchase_order_status,
                   {$agg}
            FROM requisition_item r
            LEFT JOIN user u ON u.user_id = r.user_id
            LEFT JOIN offices d ON d.office_id = r.office_id
            LEFT JOIN requisition_form_approval rfa ON rfa.request_id = r.request_id
            LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
            LEFT JOIN purchase_requisition_approval pra ON pra.request_id = r.request_id
            LEFT JOIN purchase_orders po ON po.requisition_id = r.request_id AND po.deleted_at IS NULL
            WHERE r.office_id = ?
              AND r.submission_status = 'submitted'
            ORDER BY r.created_at DESC, r.request_id DESC
        ");
        $stmt->execute([$officeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $requests = array_map('mapDepartmentRequestRow', $rows);

        sendJson([
            'success' => true,
            'requests' => $requests,
            'office_name' => (string) ($ctx['office_name'] ?? ''),
        ]);
    }

    sendJson(['success' => false, 'message' => 'Invalid action or not implemented yet.']);
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => 'Error loading department requisitions.']);
}
