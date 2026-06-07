<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';

$db = Database::connect();
$stmt = $db->prepare('SELECT u.Email, u.role, u.full_name FROM user u WHERE u.user_id = ? AND u.deleted_at IS NULL');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$displayName = trim((string) ($user['full_name'] ?? ''));
if ($displayName === '') {
    $displayName = explode('@', (string) ($user['Email'] ?? 'unknown'))[0] ?? 'unknown';
}

$roleLc = strtolower(trim((string) ($user['role'] ?? '')));
$isPresidentVerifier = in_array($roleLc, ['president', 'president verifier', 'verifier president', 'president_verifier'], true);
$poId = (int) ($_GET['id'] ?? 0);
$requestId = (int) ($_GET['request_id'] ?? 0);
$from = trim((string) ($_GET['from'] ?? ''));

$progressQs = $requestId > 0 ? ('?rid=' . $requestId) : '';
$backHref = 'president_dashboard.php';
if ($from === 'progress' || $from === 'requisition') {
    $backHref = 'dean_requisition_status_progress.php' . $progressQs;
} elseif ($from === 'inventory') {
    $backHref = 'requisition_status_progress.php' . $progressQs;
} elseif ($from === 'comptroller') {
    $backHref = 'requisition_status_progress.php' . $progressQs;
} elseif ($from === 'president') {
    $backHref = 'president_requisition_status_progress.php' . $progressQs;
} elseif ($from === 'dean') {
    $backHref = 'dean_requisition_management.php';
} elseif ($roleLc === 'inventory manager' || $roleLc === 'inventory_manager') {
    $backHref = 'requisition_management.php';
} elseif ($roleLc === 'comptroller') {
    $backHref = 'comptroller_requests.php';
} elseif (!$isPresidentVerifier) {
    $backHref = 'dean_requisition_status_progress.php' . $progressQs;
}

$todayLabel = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase order · WLC-SMART</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/requisition_form.css">
    <link rel="stylesheet" href="../assets/css/purchase_order.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<main class="requisition-main">
    <div class="requisition-card purchase-order-card purchase-order-document">
        <a href="<?php echo htmlspecialchars($backHref); ?>" class="requisition-close-btn po-no-print" id="poBackBtn" aria-label="Back" data-tooltip="Back">
            <i class="fas fa-times"></i>
        </a>

        <div class="requisition-top po-header">
            <div class="logo-left">
                <div class="requisition-logo-wlc-wrap po-seal-placeholder" aria-hidden="true">
                    <img src="../assets/images/western-letye-logo.jpg" alt="" class="requisition-logo po-seal-img" decoding="async" />
                </div>
            </div>
            <div class="requisition-title">
                <h1>Western Leyte College of Ormoc City Inc.</h1>
                <div class="requisition-subtitle">
                    <p>A. Bonifacio St., Ormoc City, Leyte, Philippines</p>
                    <p>Tel Nos.: (053) 561 - 5310 / 255 8549</p>
                    <p>E-mail Address: westernleytecollege@yahoo.com</p>
                </div>
                <p class="requisition-section po-form-title">PURCHASE ORDER</p>
            </div>
            <div class="logo-right">
                <div class="requisition-logo-wlc-wrap po-seal-placeholder" aria-hidden="true">
                    <img src="../assets/images/wlc-smart-logo.png" alt="" class="requisition-logo-wlc po-seal-img" decoding="async" />
                </div>
            </div>
        </div>

        <div id="purchaseOrderForm" class="po-form">
            <div class="requisition-info po-info-grid">
                <div class="info-left info-grid">
                    <div class="field-group">
                        <label for="poNumber">PO Number</label>
                        <input type="text" id="poNumber" class="po-readonly-field" value="—" readonly>
                    </div>
                    <div class="field-group">
                        <label for="poRequestedBy">Requested By</label>
                        <input type="text" id="poRequestedBy" class="po-readonly-field" value="<?php echo htmlspecialchars($displayName); ?>" readonly>
                    </div>
                    <div class="field-group">
                        <label for="poLocation">Location / Facility</label>
                        <input type="text" id="poLocation" class="po-readonly-field" value="—" readonly>
                    </div>
                    <div class="field-group">
                        <label for="poSupplierName">Supplier Name</label>
                        <input type="text" id="poSupplierName" class="po-readonly-field" value="—" readonly>
                    </div>
                    <div class="field-group">
                        <label for="poSupplierTin">Supplier TIN Number</label>
                        <input type="text" id="poSupplierTin" class="po-readonly-field" value="" placeholder="000-000-000-000" readonly>
                    </div>
                </div>
                <div class="info-right">
                    <div class="field-group">
                        <label for="poDateIssued">Date Issued</label>
                        <input type="text" id="poDateIssued" class="po-readonly-field" value="<?php echo htmlspecialchars($todayLabel); ?>" readonly>
                    </div>
                    <div class="field-group">
                        <label for="poModeOfPayment">Mode of Payment</label>
                        <input type="text" id="poModeOfPayment" class="po-readonly-field" value="—" readonly>
                    </div>
                    <div class="field-group">
                        <label for="poPurpose">Purpose of Request</label>
                        <input type="text" id="poPurpose" class="po-readonly-field" value="—" readonly>
                    </div>
                </div>
            </div>

            <div class="table-section">
                <div class="section-label">Purchase order lines</div>
                <div class="supplier-table-wrapper">
                    <table class="supplier-table po-lines-table" id="poLinesTable">
                        <thead>
                            <tr class="po-thead-main">
                                <th>Description</th>
                                <th>Sub-description</th>
                                <th>QTY</th>
                                <th>Unit Price</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody id="poLinesBody">
                            <tr class="po-line-row po-line-loading">
                                <td colspan="5">Loading purchase order lines…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="po-total-row">
                    <span>Total Amount</span>
                    <strong id="poGrandTotal">PHP 0.00</strong>
                </div>
            </div>

            <div class="approval-section po-verifier-section" aria-label="Verifier summary">
                <div class="section-label">Verifier summary</div>
                <div class="approval-card po-verifier-card">
                    <div class="approval-role" id="poPresidentVerifier">
                        <div class="circle-icon inactive"><i class="fas fa-check"></i></div>
                        <div class="approval-role-body">
                            <div class="approval-name">Approved by</div>
                            <div class="approval-sub cv-appr-kind">President</div>
                            <div class="cv-appr-detail" id="poPresidentStatus">Pending</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="comptroller-approve-wrapper po-action-bar verifier-decision-bar po-no-print">
                <?php if ($isPresidentVerifier): ?>
                <button type="button" id="poApproveBtn" class="btn-submit" style="display:none;"><i class="fas fa-check" aria-hidden="true"></i> Approve</button>
                <button type="button" id="poRejectBtn" class="btn-secondary comptroller-reject-btn" style="display:none;"><i class="fas fa-xmark" aria-hidden="true"></i> Reject</button>
                <button type="button" id="poUndoBtn" class="btn-secondary comptroller-undo-btn" style="display:none;"><i class="fas fa-rotate-left" aria-hidden="true"></i> Undo decision</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<div id="poToast" class="toast error" style="display:none;"></div>

<script>
window.IMRMS_PURCHASE_ORDER_CONFIG = <?php echo json_encode([
    'poId' => $poId,
    'requestId' => $requestId,
    'api' => '../../app/api/purchase_order.php',
    'isPresidentVerifier' => $isPresidentVerifier,
    'defaultRequestedBy' => $displayName,
    'todayLabel' => $todayLabel,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
</script>
<script src="../assets/js/purchase_order.js"></script>
</body>
</html>
