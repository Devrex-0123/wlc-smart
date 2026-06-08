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
$isComptroller = ($roleLc === 'comptroller');
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
<body class="page-purchase-order-form">
<main class="requisition-main">
    <div class="requisition-card purchase-order-card purchase-order-document">
        <a href="<?php echo htmlspecialchars($backHref); ?>" class="requisition-close-btn po-no-print" id="poBackBtn" aria-label="Back" data-tooltip="Back">
            <i class="fas fa-times"></i>
        </a>

        <div class="requisition-top po-header">
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
                <p class="requisition-section po-form-title">PURCHASE ORDER</p>
            </div>
            <div class="logo-right">
                <img src="../assets/images/western-letye-logo.jpg" alt="College Logo" class="requisition-logo" />
            </div>
        </div>

        <div id="purchaseOrderForm" class="po-form">
            <div class="requisition-info po-meta-grid">
                <div class="field-group">
                    <label for="poNumber">PO Number</label>
                    <input type="text" id="poNumber" class="po-readonly-field" value="—" readonly>
                </div>
                <div class="field-group">
                    <label for="poRequestedBy">Requested By</label>
                    <input type="text" id="poRequestedBy" class="po-readonly-field" value="<?php echo htmlspecialchars($displayName); ?>" readonly>
                </div>
                <div class="field-group">
                    <label for="poDateIssued">Date Issued</label>
                    <input type="text" id="poDateIssued" class="po-readonly-field" value="<?php echo htmlspecialchars($todayLabel); ?>" readonly>
                </div>
                <div class="field-group">
                    <label for="poModeOfPayment">Mode of Payment</label>
                    <input type="text" id="poModeOfPayment" class="po-readonly-field" value="—" readonly>
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
                <div class="field-group po-purpose-field">
                    <label for="poPurpose">Purpose of Request</label>
                    <input type="text" id="poPurpose" class="po-readonly-field" value="—" readonly>
                </div>
            </div>

            <section class="rf-section rf-section-po-lines">
                <h2 class="rf-section-heading">Purchase Order Lines</h2>
                <div class="table-section">
                <div class="supplier-table-wrapper po-lines-table-wrap">
                    <table class="supplier-table po-lines-table" id="poLinesTable">
                        <thead>
                            <tr class="po-thead-main">
                                <th>Description</th>
                                <th>Sub-description</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody id="poLinesBody">
                            <tr class="po-line-row po-line-loading">
                                <td colspan="5">Loading purchase order lines…</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="po-total-row">
                                <td colspan="4" class="po-total-label">Total Amount</td>
                                <td class="po-total-value"><strong id="poGrandTotal">PHP 0.00</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                </div>
            </section>

            <div class="approval-section po-verifier-section" aria-label="Verifier summary">
                <h2 class="rf-section-heading">Verifier Summary</h2>
                <div class="approval-card po-verifier-card po-verifier-row">
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

            <?php if ($isComptroller): ?>
            <p id="poTaxPendingNotice" class="comptroller-tax-pending-notice po-no-print" hidden>
                <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                Tax &amp; deduction computation unlocks after the President approves this purchase order.
            </p>

            <div class="comptroller-divider po-no-print" id="comptroller-divider" role="separator" aria-label="Comptroller section" hidden>
                <i class="fas fa-lock" aria-hidden="true"></i>
                <span>Comptroller section — visible to comptroller only</span>
            </div>

            <div class="comptroller-section po-no-print" id="comptroller-section" aria-label="Tax and deduction computation" hidden>
                <div class="comptroller-section-header">
                    <div>
                        <h2 class="comptroller-section-title">Tax &amp; deduction computation</h2>
                        <p class="comptroller-section-subtitle">Saved for audit record · Not printed on official copy</p>
                    </div>
                    <span class="comptroller-tax-badge comptroller-tax-badge--pending" id="poTaxStatusBadge">Pending computation</span>
                </div>

                <div class="comptroller-tax-table-wrap">
                    <table class="comptroller-tax-table" id="poTaxTable" aria-label="Tax deductions">
                        <thead>
                            <tr>
                                <th class="tax-table-header">Tax type</th>
                                <th class="tax-table-header">Rate</th>
                                <th class="tax-table-header">Amount deducted</th>
                                <th class="tax-table-header tax-table-header--action">Remove</th>
                            </tr>
                        </thead>
                        <tbody id="poTaxRowsBody"></tbody>
                    </table>
                </div>

                <div class="comptroller-tax-quick-add">
                    <button type="button" class="btn-tax-ewt" id="poTaxAddEwtBtn"><i class="fas fa-plus" aria-hidden="true"></i> EWT</button>
                    <button type="button" class="btn-tax-vat" id="poTaxAddVatBtn"><i class="fas fa-plus" aria-hidden="true"></i> VAT Withholding</button>
                    <button type="button" class="btn-tax-other" id="poTaxAddOtherBtn"><i class="fas fa-plus" aria-hidden="true"></i> Other</button>
                    <button type="button" class="btn-tax-add" id="poTaxAddDeductionBtn"><i class="fas fa-plus" aria-hidden="true"></i> Add deduction</button>
                </div>

                <div class="comptroller-tax-breakdown" id="poTaxBreakdown" aria-live="polite">
                    <div class="breakdown-row">
                        <span>Gross amount</span>
                        <strong id="poTaxGrossAmount">PHP 0.00</strong>
                    </div>
                    <div id="poTaxBreakdownDeductions"></div>
                    <div class="breakdown-total">
                        <span>Net payable</span>
                        <strong id="poTaxNetPayable">PHP 0.00</strong>
                    </div>
                </div>

                <label class="comptroller-tax-notes-label" for="poTaxNotes">Comptroller notes</label>
                <textarea id="poTaxNotes" class="comptroller-tax-notes" rows="3" placeholder="e.g. EWT certificate (BIR Form 2307) to be issued to supplier. Reference: …"></textarea>

                <div class="comptroller-tax-save-row">
                    <button type="button" class="btn-submit" id="poTaxSaveBtn"><i class="fas fa-floppy-disk" aria-hidden="true"></i> Save tax record</button>
                </div>
            </div>
            <?php endif; ?>

            <div class="comptroller-approve-wrapper po-action-bar verifier-decision-bar rf-form-actions po-no-print">
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
    'isComptroller' => $isComptroller,
    'isPresidentVerifier' => $isPresidentVerifier,
    'defaultRequestedBy' => $displayName,
    'todayLabel' => $todayLabel,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
</script>
<script src="../assets/js/purchase_order.js"></script>
</body>
</html>
