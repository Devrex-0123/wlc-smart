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

$roleLc = strtolower(trim((string) ($user['role'] ?? '')));
$isComptroller = ($roleLc === 'comptroller');
$isPresidentVerifier = in_array($roleLc, ['president', 'president verifier', 'verifier president', 'president_verifier'], true);

$poId = (int) ($_GET['id'] ?? 0);
$requestId = (int) ($_GET['request_id'] ?? 0);
$from = trim((string) ($_GET['from'] ?? ''));

$requesterName = '';
if ($requestId > 0) {
    $nameStmt = $db->prepare('SELECT requester_name FROM requisition_item WHERE request_id = ? LIMIT 1');
    $nameStmt->execute([$requestId]);
    $requesterName = trim((string) ($nameStmt->fetchColumn() ?: ''));
}

// 3. SET THE DISPLAY NAME
if ($requesterName !== '') {
    $displayName = $requesterName;
} else {
    $displayName = trim((string) ($user['full_name'] ?? ''));
    if ($displayName === '') {
        $displayName = explode('@', (string) ($user['Email'] ?? 'unknown'))[0] ?? 'unknown';
    }
}

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

            <div class="comptroller-section po-no-print" id="comptroller-section" aria-label="Purchase order charges and tax computation" hidden>
                <div class="comptroller-section-header">
                    <div>
                        <h2 class="comptroller-section-title">Charges &amp; Tax Computation</h2>
                        <p class="comptroller-section-subtitle">Saved for audit record · Not printed on official copy</p>
                    </div>
                    <span class="comptroller-tax-badge comptroller-tax-badge--pending" id="poTaxStatusBadge">Pending computation</span>
                </div>

                <!-- ── Fees & Discounts ─────────────────────────────────── -->
                <div class="po-fees-accordion" id="poFeesAccordion">

                    <!-- Shipping & Delivery -->
                    <div class="po-fees-panel" id="poPanelShipping">
                        <button type="button" class="po-fees-panel-toggle" aria-expanded="false" aria-controls="poPanelShippingBody">
                            <span class="po-fees-panel-icon"><i class="fas fa-truck" aria-hidden="true"></i></span>
                            <span class="po-fees-panel-label">Shipping &amp; Delivery</span>
                            <span class="po-fees-panel-summary" id="poShippingSummary"></span>
                            <i class="fas fa-chevron-down po-fees-chevron" aria-hidden="true"></i>
                        </button>
                        <div class="po-fees-panel-body" id="poPanelShippingBody" hidden>
                            <div class="po-fees-grid">
                                <div class="po-fees-field">
                                    <label for="poShippingFee">Shipping Fee (₱)</label>
                                    <input type="number" id="poShippingFee" class="po-fees-input" min="0" step="0.01" value="0" placeholder="0.00">
                                </div>
                                <div class="po-fees-field">
                                    <label for="poShippingMethod">Shipping Method</label>
                                    <select id="poShippingMethod" class="po-fees-input">
                                        <option value="">— Select method —</option>
                                        <option value="courier">Courier / Door-to-door</option>
                                        <option value="pickup">Store pickup</option>
                                        <option value="freight">Freight / Trucking</option>
                                        <option value="air">Air freight</option>
                                        <option value="sea">Sea freight</option>
                                        <option value="government_vehicle">Government vehicle</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="po-fees-field po-fees-field--wide">
                                    <label for="poShippingAddress">Delivery Address</label>
                                    <textarea id="poShippingAddress" class="po-fees-input" rows="2" placeholder="Street, City, Province…"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Fees -->
                    <div class="po-fees-panel" id="poPanelAdditional">
                        <button type="button" class="po-fees-panel-toggle" aria-expanded="false" aria-controls="poPanelAdditionalBody">
                            <span class="po-fees-panel-icon"><i class="fas fa-plus-circle" aria-hidden="true"></i></span>
                            <span class="po-fees-panel-label">Additional Fees</span>
                            <span class="po-fees-panel-summary" id="poAdditionalSummary"></span>
                            <i class="fas fa-chevron-down po-fees-chevron" aria-hidden="true"></i>
                        </button>
                        <div class="po-fees-panel-body" id="poPanelAdditionalBody" hidden>
                            <div class="po-fees-grid po-fees-grid--4">
                                <div class="po-fees-field">
                                    <label for="poHandlingFee">Handling Fee (₱)</label>
                                    <input type="number" id="poHandlingFee" class="po-fees-input" min="0" step="0.01" value="0" placeholder="0.00">
                                </div>
                                <div class="po-fees-field">
                                    <label for="poInsuranceFee">Insurance Fee (₱)</label>
                                    <input type="number" id="poInsuranceFee" class="po-fees-input" min="0" step="0.01" value="0" placeholder="0.00">
                                </div>
                                <div class="po-fees-field">
                                    <label for="poInstallationFee">Installation Fee (₱)</label>
                                    <input type="number" id="poInstallationFee" class="po-fees-input" min="0" step="0.01" value="0" placeholder="0.00">
                                </div>
                                <div class="po-fees-field">
                                    <label for="poOtherCharges">Other Charges (₱)</label>
                                    <input type="number" id="poOtherCharges" class="po-fees-input" min="0" step="0.01" value="0" placeholder="0.00">
                                </div>
                                <div class="po-fees-field po-fees-field--wide">
                                    <label for="poOtherChargesDesc">Other Charges Description</label>
                                    <input type="text" id="poOtherChargesDesc" class="po-fees-input" maxlength="255" placeholder="e.g. Customs clearance fee">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Discounts -->
                    <div class="po-fees-panel" id="poPanelDiscount">
                        <button type="button" class="po-fees-panel-toggle" aria-expanded="false" aria-controls="poPanelDiscountBody">
                            <span class="po-fees-panel-icon"><i class="fas fa-tag" aria-hidden="true"></i></span>
                            <span class="po-fees-panel-label">Discounts</span>
                            <span class="po-fees-panel-summary" id="poDiscountSummary"></span>
                            <i class="fas fa-chevron-down po-fees-chevron" aria-hidden="true"></i>
                        </button>
                        <div class="po-fees-panel-body" id="poPanelDiscountBody" hidden>
                            <div class="po-fees-discount-type-toggle">
                                <label class="po-fees-radio-label">
                                    <input type="radio" name="poDiscountType" id="poDiscountTypePercent" value="percent" checked>
                                    Percentage (%)
                                </label>
                                <label class="po-fees-radio-label">
                                    <input type="radio" name="poDiscountType" id="poDiscountTypeFixed" value="fixed">
                                    Fixed Amount (₱)
                                </label>
                            </div>
                            <div class="po-fees-grid po-fees-grid--3">
                                <div class="po-fees-field" id="poDiscountPctWrap">
                                    <label for="poDiscountPercentage">Discount Percentage (%)</label>
                                    <input type="number" id="poDiscountPercentage" class="po-fees-input" min="0" max="100" step="0.01" value="0" placeholder="0.00">
                                    <span class="po-fees-input-error" id="poDiscountPctError" hidden>Must be between 0 and 100.</span>
                                </div>
                                <div class="po-fees-field" id="poDiscountAmtWrap" style="display:none;">
                                    <label for="poDiscountAmount">Discount Amount (₱)</label>
                                    <input type="number" id="poDiscountAmount" class="po-fees-input" min="0" step="0.01" value="0" placeholder="0.00">
                                </div>
                                <div class="po-fees-field po-fees-field--wide">
                                    <label for="poDiscountReason">Reason / Justification</label>
                                    <input type="text" id="poDiscountReason" class="po-fees-input" maxlength="255" placeholder="e.g. Negotiated price reduction, volume discount…">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Terms -->
                    <div class="po-fees-panel" id="poPanelPayment">
                        <button type="button" class="po-fees-panel-toggle" aria-expanded="false" aria-controls="poPanelPaymentBody">
                            <span class="po-fees-panel-icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></span>
                            <span class="po-fees-panel-label">Payment Terms</span>
                            <span class="po-fees-panel-summary" id="poPaymentSummary"></span>
                            <i class="fas fa-chevron-down po-fees-chevron" aria-hidden="true"></i>
                        </button>
                        <div class="po-fees-panel-body" id="poPanelPaymentBody" hidden>
                            <div class="po-fees-grid po-fees-grid--2">
                                <div class="po-fees-field">
                                    <label for="poPaymentTerms">Payment Terms</label>
                                    <select id="poPaymentTerms" class="po-fees-input">
                                        <option value="">— Select terms —</option>
                                        <option value="cod">Cash on Delivery (COD)</option>
                                        <option value="net_7">Net 7</option>
                                        <option value="net_15">Net 15</option>
                                        <option value="net_30">Net 30</option>
                                        <option value="net_45">Net 45</option>
                                        <option value="net_60">Net 60</option>
                                        <option value="upon_delivery">Upon delivery</option>
                                        <option value="advance">Full payment in advance</option>
                                        <option value="partial_advance">50% advance, 50% on delivery</option>
                                    </select>
                                </div>
                                <div class="po-fees-field">
                                    <label for="poPaymentDueDate">Payment Due Date</label>
                                    <input type="date" id="poPaymentDueDate" class="po-fees-input">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tax & Withholding (5th panel — open by default) -->
                    <div class="po-fees-panel" id="poPanelTax">
                        <button type="button" class="po-fees-panel-toggle" aria-expanded="true" aria-controls="poPanelTaxBody">
                            <span class="po-fees-panel-icon"><i class="fas fa-receipt" aria-hidden="true"></i></span>
                            <span class="po-fees-panel-label">Tax &amp; Withholding</span>
                            <span class="po-fees-panel-summary" id="poTaxPanelSummary"></span>
                            <i class="fas fa-chevron-down po-fees-chevron" aria-hidden="true"></i>
                        </button>
                        <div class="po-fees-panel-body po-tax-panel-body" id="poPanelTaxBody">

                            <!-- Transaction type selector -->
                            <div class="po-tax-type-row">
                                <div class="po-fees-field">
                                    <label for="poTransactionType">Transaction Type <span class="po-tax-required">*</span></label>
                                    <select id="poTransactionType" class="po-fees-input">
                                        <option value="">— Select transaction type —</option>
                                        <option value="goods">Purchase of Goods / Supplies (1% EWT)</option>
                                        <option value="services">Purchase of Services (2% EWT)</option>
                                        <option value="professional_small">Professional Fees — ≤ ₱3M income (10% EWT)</option>
                                        <option value="professional_large">Professional Fees — Corp / > ₱3M (15% EWT)</option>
                                        <option value="rental">Rental (5% EWT)</option>
                                        <option value="construction">Construction / Contractor (2% EWT)</option>
                                        <option value="media">Media / Talent / Entertainment (15% EWT)</option>
                                        <option value="exempt">Exempt / No withholding (small business, online shop, etc.)</option>
                                    </select>
                                </div>
                                <div class="po-tax-vat-badge-wrap" id="poSupplierVatBadgeWrap" hidden>
                                    <span class="po-tax-vat-badge"><i class="fas fa-circle-check" aria-hidden="true"></i> Supplier is VAT-registered — 5% VAT withholding will be added</span>
                                </div>
                                <button type="button" id="poApplyTransactionTypeBtn" class="btn-secondary po-tax-apply-btn" disabled>
                                    <i class="fas fa-bolt" aria-hidden="true"></i> Apply
                                </button>
                            </div>

                            <!-- Hidden data store — tax rows kept here for JS collection, not rendered -->
                            <table hidden aria-hidden="true"><tbody id="poTaxRowsBody"></tbody></table>

                        </div><!-- /.po-tax-panel-body -->
                    </div><!-- /.poPanelTax -->

                </div><!-- /.po-fees-accordion -->

                <!-- ── Save Fees button ────────────────────────────────── -->
                <div class="po-fees-save-row" id="poFeesSaveRow">
                    <button type="button" id="poFeesSaveBtn" class="btn-secondary po-fees-save-btn">
                        <i class="fas fa-floppy-disk" aria-hidden="true"></i> Save fees &amp; discounts
                    </button>
                    <span class="po-fees-saved-hint" id="poFeesSavedHint" hidden></span>
                </div>

                <!-- ── Live Calculation Panel ──────────────────────────── -->
                <div class="po-calc-panel" id="poCalcPanel" aria-live="polite">
                    <h3 class="po-calc-panel-title">Cost Breakdown</h3>
                    <div class="po-calc-body">
                        <div class="po-calc-row">
                            <span class="po-calc-label">Items Subtotal</span>
                            <span class="po-calc-value" id="poCalcItemsSubtotal">₱ 0.00</span>
                        </div>
                        <div class="po-calc-row po-calc-row--add" id="poCalcShippingRow" hidden>
                            <span class="po-calc-label">+ Shipping Fee</span>
                            <span class="po-calc-value po-calc-value--add" id="poCalcShipping">+ ₱ 0.00</span>
                        </div>
                        <div class="po-calc-row po-calc-row--add" id="poCalcHandlingRow" hidden>
                            <span class="po-calc-label">+ Handling Fee</span>
                            <span class="po-calc-value po-calc-value--add" id="poCalcHandling">+ ₱ 0.00</span>
                        </div>
                        <div class="po-calc-row po-calc-row--add" id="poCalcInsuranceRow" hidden>
                            <span class="po-calc-label">+ Insurance Fee</span>
                            <span class="po-calc-value po-calc-value--add" id="poCalcInsurance">+ ₱ 0.00</span>
                        </div>
                        <div class="po-calc-row po-calc-row--add" id="poCalcInstallationRow" hidden>
                            <span class="po-calc-label">+ Installation Fee</span>
                            <span class="po-calc-value po-calc-value--add" id="poCalcInstallation">+ ₱ 0.00</span>
                        </div>
                        <div class="po-calc-row po-calc-row--add" id="poCalcOtherRow" hidden>
                            <span class="po-calc-label">+ Other Charges</span>
                            <span class="po-calc-value po-calc-value--add" id="poCalcOther">+ ₱ 0.00</span>
                        </div>
                        <div class="po-calc-divider"></div>
                        <div class="po-calc-row po-calc-row--subtotal">
                            <span class="po-calc-label">Gross Total</span>
                            <span class="po-calc-value po-calc-value--strong" id="poCalcGrossTotal">₱ 0.00</span>
                        </div>
                        <div class="po-calc-row po-calc-row--deduct" id="poCalcDiscountRow" hidden>
                            <span class="po-calc-label" id="poCalcDiscountLabel">− Discount</span>
                            <span class="po-calc-value po-calc-value--deduct" id="poCalcDiscount">− ₱ 0.00</span>
                        </div>
                        <div class="po-calc-divider" id="poCalcDiscountDivider" hidden></div>
                        <div class="po-calc-row po-calc-row--taxable">
                            <span class="po-calc-label">Taxable Amount</span>
                            <span class="po-calc-value po-calc-value--taxable" id="poCalcTaxable">₱ 0.00</span>
                        </div>
                        <div id="poCalcTaxDeductions"></div>
                        <div class="po-calc-divider"></div>
                        <div class="po-calc-row po-calc-row--net">
                            <span class="po-calc-label">NET PAYABLE</span>
                            <strong class="po-calc-value po-calc-value--net" id="poCalcNetPayable">₱ 0.00</strong>
                        </div>
                    </div>
                </div><!-- /.po-calc-panel -->

                <!-- ── Comptroller notes & action ────────────────────────── -->
                <label class="comptroller-tax-notes-label" for="poTaxNotes">Comptroller notes</label>
                <textarea id="poTaxNotes" class="comptroller-tax-notes" rows="3" placeholder="e.g. EWT certificate (BIR Form 2307) to be issued to supplier. Reference: …"></textarea>

                <div class="comptroller-tax-action-panel po-no-print" id="poTaxActionPanel">
                    <div class="comptroller-tax-draft-row" id="poTaxDraftRow">
                        <button type="button" class="btn-secondary po-tax-draft-btn" id="poTaxDraftBtn">
                            <i class="fas fa-floppy-disk" aria-hidden="true"></i> Save as draft
                        </button>
                        <button type="button" class="btn-submit po-tax-finalize-btn" id="poTaxFinalizeBtn">
                            <i class="fas fa-lock" aria-hidden="true"></i> Finalize &amp; save
                        </button>
                        <p class="comptroller-tax-draft-saved-hint" id="poTaxDraftSavedHint" hidden aria-live="polite"></p>
                    </div>

                    <div class="comptroller-tax-finalized-panel" id="poTaxFinalizedPanel" hidden>
                        <div class="comptroller-tax-finalized-banner" role="status">
                            <i class="fas fa-circle-check" aria-hidden="true"></i>
                            <div>
                                <strong>Tax computation finalized</strong>
                                <span id="poTaxFinalizedAt">—</span>
                                <p class="comptroller-tax-finalized-note">The requester has been notified that payment is ready for release.</p>
                            </div>
                        </div>
                        <button type="button" class="btn-secondary po-tax-reopen-btn" id="poTaxReopenBtn">
                            <i class="fas fa-lock-open" aria-hidden="true"></i> Reopen for edit
                        </button>
                    </div>
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

<div id="poConfirmModal" class="confirm-modal" style="display:none;">
    <div class="confirm-modal-backdrop"></div>
    <div class="confirm-modal-card" role="dialog" aria-modal="true" aria-labelledby="poConfirmTitle">
        <div class="confirm-modal-header">
            <h3 id="poConfirmTitle">Please Confirm</h3>
        </div>
        <div class="confirm-modal-body" id="poConfirmMessage">Are you sure?</div>
        <div class="confirm-modal-actions">
            <button type="button" id="poConfirmCancelBtn" class="confirm-btn confirm-btn-cancel">Cancel</button>
            <button type="button" id="poConfirmOkBtn" class="confirm-btn confirm-btn-ok">Confirm</button>
        </div>
    </div>
</div>

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
<script src="../assets/js/purchase_order.js?v=wlc23"></script>
</body>
</html>
