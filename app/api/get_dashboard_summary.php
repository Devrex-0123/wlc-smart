<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/requisition_detail_payload.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

function dashboardRecentStage(array $row): array
{
    $status = trim((string) ($row['status'] ?? 'Pending'));
    $rq = strtolower(trim((string) ($row['requisition_status'] ?? 'pending')));
    $canvas = strtolower(trim((string) ($row['canvas_status'] ?? 'pending')));
    $canvasSub = strtolower(trim((string) ($row['canvas_submission_status'] ?? 'draft')));
    $prSub = strtolower(trim((string) ($row['pr_submission_status'] ?? 'draft')));
    $prInv = strtolower(trim((string) ($row['pr_inv_status'] ?? 'pending')));
    $prPres = strtolower(trim((string) ($row['pr_pres_status'] ?? 'pending')));
    $comp = strtolower(trim((string) ($row['comp_status'] ?? 'pending')));
    $pres = strtolower(trim((string) ($row['pres_status'] ?? 'pending')));

    if ($status === 'Completed') {
        return ['badge' => 'Completed', 'stage' => 'Delivered', 'tone' => 'completed'];
    }
    if ($rq === 'pending' || $rq === '') {
        return ['badge' => 'Validation', 'stage' => 'Validate Request', 'tone' => 'validation'];
    }
    if ($rq === 'reject') {
        return ['badge' => 'Validation', 'stage' => 'Validate Request', 'tone' => 'validation'];
    }
    if ($canvasSub !== 'submitted' || $canvas === 'pending') {
        return ['badge' => 'Canvass', 'stage' => 'Canvass Form', 'tone' => 'canvass'];
    }
    if ($prSub !== 'submitted' || $prInv === 'pending' || $prPres === 'pending') {
        return ['badge' => 'PR Review', 'stage' => 'PR Validation', 'tone' => 'pr'];
    }
    if ($comp === 'pending' || $pres === 'pending') {
        return ['badge' => 'PO', 'stage' => 'Purchase Order', 'tone' => 'po'];
    }
    if ($status === 'Ongoing') {
        return ['badge' => 'Delivery', 'stage' => 'In Transit', 'tone' => 'delivery'];
    }

    return ['badge' => 'Request', 'stage' => 'Requisition Form', 'tone' => 'request'];
}

$db = Database::connect();

try {
    $totalAssets = (int) $db->query('SELECT COUNT(*) FROM inventory')->fetchColumn();

    $totalDepartments = (int) $db->query("
        SELECT COUNT(DISTINCT f.office_id)
        FROM inventory i
        INNER JOIN facilities f ON f.facility_id = i.facility_id
    ")->fetchColumn();

    $activeRequests = (int) $db->query("
        SELECT COUNT(*)
        FROM requisition_item r
        WHERE r.submission_status = 'submitted'
          AND r.status IN ('Pending', 'Ongoing')
    ")->fetchColumn();

    $awaitingValidation = (int) $db->query("
        SELECT COUNT(*)
        FROM requisition_item r
        LEFT JOIN requisition_form_approval rfa ON rfa.request_id = r.request_id
        WHERE r.submission_status = 'submitted'
          AND r.status = 'Pending'
          AND LOWER(TRIM(COALESCE(rfa.requisition_status, 'pending'))) = 'pending'
    ")->fetchColumn();

    $pendingDelivery = (int) $db->query("
        SELECT COUNT(*)
        FROM requisition_item r
        WHERE r.submission_status = 'submitted'
          AND r.status = 'Ongoing'
    ")->fetchColumn();

    $arrivingThisWeek = (int) $db->query("
        SELECT COUNT(*)
        FROM requisition_item r
        WHERE r.submission_status = 'submitted'
          AND r.status = 'Ongoing'
          AND YEARWEEK(r.created_at, 1) = YEARWEEK(CURDATE(), 1)
    ")->fetchColumn();

    $deptsWithActiveRequests = (int) $db->query("
        SELECT COUNT(DISTINCT r.office_id)
        FROM requisition_item r
        WHERE r.submission_status = 'submitted'
          AND r.status IN ('Pending', 'Ongoing')
    ")->fetchColumn();

    $requestSubmitted = (int) $db->query("
        SELECT COUNT(*)
        FROM requisition_item
        WHERE submission_status = 'submitted'
    ")->fetchColumn();

    $requestAwaiting = (int) $db->query("
        SELECT COUNT(*)
        FROM requisition_item r
        LEFT JOIN requisition_form_approval rfa ON rfa.request_id = r.request_id
        WHERE r.submission_status = 'submitted'
          AND LOWER(TRIM(COALESCE(rfa.requisition_status, 'pending'))) = 'pending'
    ")->fetchColumn();

    $canvassSubmitted = (int) $db->query("
        SELECT COUNT(*)
        FROM canvass_verification_approval
        WHERE LOWER(TRIM(COALESCE(canvas_submission_status, 'draft'))) = 'submitted'
    ")->fetchColumn();

    $canvassAwaiting = (int) $db->query("
        SELECT COUNT(*)
        FROM canvass_verification_approval cva
        WHERE LOWER(TRIM(COALESCE(cva.canvas_submission_status, 'draft'))) = 'submitted'
          AND (
            LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) = 'pending'
            OR LOWER(TRIM(COALESCE(cva.gsd_status, 'pending'))) = 'pending'
            OR LOWER(TRIM(COALESCE(cva.comp_status, 'pending'))) = 'pending'
            OR LOWER(TRIM(COALESCE(cva.pres_status, 'pending'))) = 'pending'
          )
    ")->fetchColumn();

    $prSubmitted = (int) $db->query("
        SELECT COUNT(*)
        FROM purchase_requisition_approval
        WHERE LOWER(TRIM(COALESCE(pr_submission_status, 'draft'))) = 'submitted'
    ")->fetchColumn();

    $prAwaiting = (int) $db->query("
        SELECT COUNT(*)
        FROM purchase_requisition_approval pra
        WHERE LOWER(TRIM(COALESCE(pra.pr_submission_status, 'draft'))) = 'submitted'
          AND (
            LOWER(TRIM(COALESCE(pra.pr_inv_status, 'pending'))) = 'pending'
            OR LOWER(TRIM(COALESCE(pra.pr_pres_status, 'pending'))) = 'pending'
          )
    ")->fetchColumn();

    $poSubmitted = (int) $db->query("
        SELECT COUNT(*)
        FROM requisition_item r
        INNER JOIN purchase_requisition_approval pra ON pra.request_id = r.request_id
        WHERE r.submission_status = 'submitted'
          AND r.status IN ('Pending', 'Ongoing')
          AND LOWER(TRIM(pra.pr_inv_status)) = 'accept'
          AND LOWER(TRIM(pra.pr_pres_status)) = 'accept'
    ")->fetchColumn();

    $poAwaiting = (int) $db->query("
        SELECT COUNT(*)
        FROM requisition_item r
        INNER JOIN purchase_requisition_approval pra ON pra.request_id = r.request_id
        INNER JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
        WHERE r.submission_status = 'submitted'
          AND r.status IN ('Pending', 'Ongoing')
          AND LOWER(TRIM(pra.pr_inv_status)) = 'accept'
          AND LOWER(TRIM(pra.pr_pres_status)) = 'accept'
          AND (
            LOWER(TRIM(COALESCE(cva.comp_status, 'pending'))) = 'pending'
            OR LOWER(TRIM(COALESCE(cva.pres_status, 'pending'))) = 'pending'
          )
    ")->fetchColumn();

    $deliveryInTransit = $pendingDelivery;

    $deliveryPendingReceiving = (int) $db->query("
        SELECT COUNT(*)
        FROM requisition_item r
        INNER JOIN purchase_requisition_approval pra ON pra.request_id = r.request_id
        INNER JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
        WHERE r.submission_status = 'submitted'
          AND r.status = 'Ongoing'
          AND LOWER(TRIM(pra.pr_inv_status)) = 'accept'
          AND LOWER(TRIM(pra.pr_pres_status)) = 'accept'
          AND LOWER(TRIM(COALESCE(cva.pres_status, 'pending'))) = 'accept'
    ")->fetchColumn();

    $agg = requisitionSqlSelectListAggregates();
    $recentStmt = $db->query("
        SELECT r.request_id, r.created_at, r.status,
               d.office_name,
               f.building, f.room, f.laboratory,
               rfa.requisition_status,
               cva.canvas_status, cva.canvas_submission_status,
               cva.comp_status, cva.pres_status,
               COALESCE(pra.pr_inv_status, 'pending') AS pr_inv_status,
               COALESCE(pra.pr_pres_status, 'pending') AS pr_pres_status,
               COALESCE(pra.pr_submission_status, 'draft') AS pr_submission_status,
               {$agg}
        FROM requisition_item r
        LEFT JOIN offices d ON d.office_id = r.office_id
        LEFT JOIN facilities f ON f.facility_id = r.facility_id
        LEFT JOIN requisition_form_approval rfa ON rfa.request_id = r.request_id
        LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
        LEFT JOIN purchase_requisition_approval pra ON pra.request_id = r.request_id
        WHERE r.submission_status = 'submitted'
        ORDER BY r.created_at DESC, r.request_id DESC
        LIMIT 5
    ");
    $recentRows = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    $recentRequisitions = array_map(static function (array $row) {
        $items = requisitionExplodePipeOrDefault($row['items_concat'] ?? null, 'Requisition');
        $itemLabel = $items[0] ?? 'Requisition';
        $facilityParts = array_values(array_filter([
            trim((string) ($row['building'] ?? '')),
            trim((string) ($row['room'] ?? '')),
            trim((string) ($row['laboratory'] ?? '')),
        ], static fn ($part) => $part !== ''));
        $location = $facilityParts !== []
            ? implode(' ', $facilityParts)
            : trim((string) ($row['office_name'] ?? 'General'));
        if ($location === '') {
            $location = 'General';
        }
        $year = date('Y', strtotime((string) ($row['created_at'] ?? 'now')));
        $requestId = (int) ($row['request_id'] ?? 0);
        $stage = dashboardRecentStage($row);

        return [
            'request_id' => $requestId,
            'reference' => sprintf('REQ-%s-%03d', $year, $requestId),
            'title' => $itemLabel . ' — ' . $location,
            'badge' => $stage['badge'],
            'stage' => $stage['stage'],
            'tone' => $stage['tone'],
        ];
    }, $recentRows);

    echo json_encode([
        'success' => true,
        'summary' => [
            'total_assets' => $totalAssets,
            'total_departments' => $totalDepartments,
            'active_requests' => $activeRequests,
            'awaiting_validation' => $awaitingValidation,
            'pending_delivery' => $pendingDelivery,
            'arriving_this_week' => $arrivingThisWeek,
            'depts_with_active_requests' => $deptsWithActiveRequests,
        ],
        'pipeline' => [
            'request' => ['submitted' => $requestSubmitted, 'awaiting' => $requestAwaiting],
            'canvass' => ['submitted' => $canvassSubmitted, 'awaiting' => $canvassAwaiting],
            'pr' => ['submitted' => $prSubmitted, 'awaiting' => $prAwaiting],
            'po' => ['submitted' => $poSubmitted, 'awaiting' => $poAwaiting],
            'delivery' => ['in_transit' => $deliveryInTransit, 'pending_receiving' => $deliveryPendingReceiving],
        ],
        'recent_requisitions' => $recentRequisitions,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

exit;
