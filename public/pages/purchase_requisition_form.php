<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';
require_once __DIR__ . '/../../app/api/requisition_detail_payload.php';

$db = Database::connect();
$stmt = $db->prepare('SELECT u.Email, u.role FROM user u WHERE u.user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$displayName = explode('@', (string) ($user['Email'] ?? 'unknown'))[0] ?? 'unknown';
$roleLc = strtolower(trim((string) ($user['role'] ?? '')));
$isInventoryVerifier = ($roleLc === 'inventory manager' || $roleLc === 'inventory_manager');
$isPresidentVerifier = in_array($roleLc, ['president', 'president verifier', 'verifier president', 'president_verifier'], true);
$requestId = (int) ($_GET['request_id'] ?? 0);
$from = trim((string) ($_GET['from'] ?? ''));

$progressQs = $requestId > 0 ? ('?rid=' . $requestId) : '';
$backHref = 'dean_requisition_management.php';
if ($from === 'gsd') {
    $backHref = 'gsd_request.php';
} elseif ($from === 'comptroller') {
    $backHref = 'requisition_status_progress.php' . $progressQs;
} elseif ($from === 'president') {
    $backHref = 'president_requisition_status_progress.php' . $progressQs;
} elseif ($from === 'inventory') {
    $backHref = 'requisition_status_progress.php' . $progressQs;
} elseif ($from === 'progress' || $from === 'requisition') {
    $backHref = 'dean_requisition_status_progress.php' . $progressQs;
} elseif ($from === 'history') {
    $backHref = 'audit_trail.php';
}

if ($requestId > 0 && !requisitionCanvassFormAcceptedForRequest($db, $requestId)) {
    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(403);
    if (in_array($roleLc, ['inventory manager', 'inventory_manager', 'comptroller'], true)) {
        $progressFallback = 'requisition_status_progress.php?rid=' . (int) $requestId;
    } elseif ($isPresidentVerifier) {
        $progressFallback = 'president_requisition_status_progress.php?rid=' . (int) $requestId;
    } else {
        $progressFallback = 'dean_requisition_status_progress.php?rid=' . (int) $requestId;
    }
    $safeBack = htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8');
    $safeProgress = htmlspecialchars($progressFallback, ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase requisition · Not available</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/requisition_form.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<main class="requisition-main">
    <div class="requisition-card" style="max-width: 520px; margin: 2rem auto;">
        <h1 style="font-size: 1.1rem; color: #1e293b; margin: 0 0 0.75rem 0;">Purchase requisition not available yet</h1>
        <p style="color: #64748b; margin: 0 0 1.25rem 0; line-height: 1.5;">This form opens only after <strong>G.S.D., Comptroller, and President</strong> have verified the canvass form (abstract of quotation).</p>
        <p style="margin:0 0 0.5rem 0;"><a class="req-flow-context-link" href="<?php echo $safeBack; ?>">Back to previous page</a></p>
        <p style="margin:0;"><a class="req-flow-context-link" href="<?php echo $safeProgress; ?>">Request status / progress</a></p>
    </div>
</main>
</body>
</html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase requisition · WLC-SMART</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/requisition_form.css">
    <link rel="stylesheet" href="../assets/css/purchase_requisition_form.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="page-purchase-requisition-form">
<main class="requisition-main">
    <div class="requisition-card purchase-requisition-card">
        <a href="<?php echo htmlspecialchars($backHref); ?>" class="requisition-close-btn" id="purchaseReqBackBtn" data-fallback-href="<?php echo htmlspecialchars($backHref); ?>" aria-label="Back" data-tooltip="Back">
            <i class="fas fa-times"></i>
        </a>

        <div class="requisition-top">
            <div class="logo-left">
                <div class="requisition-logo-wlc-wrap">
                    <img src="../assets/images/wlc-smart-logo.png" alt="WLC-SMART Inventory Office" class="requisition-logo-wlc" decoding="async" />
                </div>
            </div>
            <div class="requisition-title">
                <h1>Western Leyte College of Ormoc City Inc.</h1>
                <div class="requisition-subtitle">
                    <p>A. Bonifacio St., Ormoc City, Leyte, Philippines</p>
                    <p>Tel Nos.: (053) 561 - 5310 / 255 8549</p>
                    <p>E-mail Address: westernleytecollege@yahoo.com</p>
                </div>
                <p class="requisition-section">PURCHASE REQUISITION</p>
            </div>
            <div class="logo-right">
                <img src="../assets/images/western-letye-logo.jpg" alt="College Logo" class="requisition-logo" />
            </div>
        </div>

        <div class="requisition-info pr-meta-grid">
            <div class="field-group">
                <label for="prRequestNo">Request #</label>
                <input type="text" id="prRequestNo" value="—" disabled>
            </div>
            <div class="field-group">
                <label for="prRequester">Requester Name</label>
                <input type="text" id="prRequester" value="<?php echo htmlspecialchars($displayName); ?>" disabled>
            </div>
            <div class="field-group">
                <label for="prRequestedAt">Requested Date</label>
                <input type="text" id="prRequestedAt" value="—" disabled>
            </div>
            <div class="field-group">
                <label for="prLocation">Location / Facility</label>
                <input type="text" id="prLocation" value="—" disabled>
            </div>
            <div class="field-group pr-purpose-field">
                <label for="prPurpose">Purpose of Request</label>
                <input type="text" id="prPurpose" value="—" disabled>
            </div>
        </div>

        <section class="rf-section rf-section-pr-lines">
            <h2 class="rf-section-heading">Purchase Requisition Lines</h2>
            <div class="table-section">
            <div class="supplier-table-wrapper pr-lines-table-wrap">
                <table class="supplier-table purchase-req-table" id="purchaseReqTable">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Qty</th>
                            <th>Supplier</th>
                            <th>Unit Price</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody id="purchaseReqBody">
                        <tr>
                            <td colspan="5" class="empty-state">Loading purchase requisition lines...</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="purchase-req-total-row">
                            <td colspan="4" class="purchase-req-total-label">Total Amount</td>
                            <td class="purchase-req-total-value"><strong id="purchaseReqGrandTotal">PHP 0.00</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            </div>
        </section>

        <div class="approval-section purchase-approval-section pr-verifier-summary" aria-label="Purchase requisition verifier summary">
            <h2 class="rf-section-heading">Verifier Summary</h2>
            <div class="approval-card approval-card-canvass-verifiers pr-verifier-row">
                <div class="approval-role">
                    <div class="circle-icon inactive"><i class="fas fa-check"></i></div>
                    <div class="approval-role-body">
                        <div class="approval-name">Verified by</div>
                        <div class="approval-sub cv-appr-kind">Inventory officer</div>
                        <div class="cv-appr-detail" id="prInvVerifiedStatus">Pending</div>
                    </div>
                </div>
                <div class="approval-role">
                    <div class="circle-icon inactive"><i class="fas fa-check"></i></div>
                    <div class="approval-role-body">
                        <div class="approval-name">Approved by</div>
                        <div class="approval-sub cv-appr-kind">President</div>
                        <div class="cv-appr-detail" id="prPresApprovedStatus">Pending</div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($isInventoryVerifier || $isPresidentVerifier): ?>
        <div class="comptroller-approve-wrapper purchase-approval-actions verifier-decision-bar rf-form-actions">
            <button type="button" id="prApproveBtn" class="btn-submit"><i class="fas fa-check" aria-hidden="true"></i> Accept</button>
            <button type="button" id="prRejectBtn" class="btn-secondary comptroller-reject-btn"><i class="fas fa-xmark" aria-hidden="true"></i> Reject</button>
            <button type="button" id="prUndoBtn" class="btn-secondary comptroller-undo-btn" style="display:none;"><i class="fas fa-rotate-left" aria-hidden="true"></i> Undo decision</button>
        </div>
        <?php if ($isInventoryVerifier): ?>
        <div class="pr-rejection-panel pr-rejection-panel--hidden note-group" aria-hidden="true">
            <label for="prRejectReason" class="note-label pr-rejection-label"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Rejection note <span class="pr-rejection-hint">(click Reject to enter)</span></label>
            <textarea id="prRejectReason" class="pr-rejection-textarea" rows="2" placeholder="Briefly state why this purchase requisition is rejected…"></textarea>
        </div>
        <?php endif; ?>
        <?php if ($isPresidentVerifier): ?>
        <div class="pr-rejection-panel pr-rejection-panel--hidden note-group" aria-hidden="true">
            <label for="prPresidentRejectReason" class="note-label pr-rejection-label"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Rejection note <span class="pr-rejection-hint">(click Reject to enter)</span></label>
            <textarea id="prPresidentRejectReason" class="pr-rejection-textarea" rows="2" placeholder="Briefly state why this purchase requisition is rejected…"></textarea>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<div id="purchaseReqToast" class="toast error" style="display:none;"></div>
<div id="purchaseConfirmModal" class="confirm-modal" style="display:none;">
    <div class="confirm-modal-backdrop"></div>
    <div class="confirm-modal-card" role="dialog" aria-modal="true" aria-labelledby="purchaseConfirmTitle">
        <div class="confirm-modal-header">
            <h3 id="purchaseConfirmTitle">Please Confirm</h3>
        </div>
        <div class="confirm-modal-body" id="purchaseConfirmMessage">Are you sure?</div>
        <div class="confirm-modal-actions">
            <button type="button" id="purchaseConfirmCancelBtn" class="confirm-btn confirm-btn-cancel">Cancel</button>
            <button type="button" id="purchaseConfirmOkBtn" class="confirm-btn confirm-btn-ok">Confirm</button>
        </div>
    </div>
</div>

<script>
window.IMRMS_PURCHASE_REQUISITION_CONFIG = <?php echo json_encode([
    'requestId' => $requestId,
    'api' => '../../app/api/purchase_requisition.php',
    'isInventoryVerifier' => $isInventoryVerifier,
    'isPresidentVerifier' => $isPresidentVerifier,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
</script>
<script src="../assets/js/purchase_requisition_form.js"></script>
</body>
</html>

