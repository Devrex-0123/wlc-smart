<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';
require_once __DIR__ . '/../../app/api/requisition_detail_payload.php';
$db = Database::connect();
$stmt = $db->prepare('SELECT u.*, d.`office_name` AS office_name FROM user u LEFT JOIN offices d ON d.office_id = u.office_id WHERE u.user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$displayName = trim((string)($user['full_name'] ?? ''));
if ($displayName === '') {
    $displayName = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'Unknown';
}
$userEmail = trim((string)($user['Email'] ?? ''));
$userContact = trim((string)($user['contact_number'] ?? ''));
$from = $_GET['from'] ?? '';
$progressFrom = trim((string) ($_GET['progress_from'] ?? ''));
$viewOnly = isset($_GET['view']) && (string)$_GET['view'] === '1';
$viewRequestIdRaw = trim((string) ($_GET['request_id'] ?? ''));
$viewRequestId = (int) $viewRequestIdRaw;
if ($viewRequestId <= 0 && $viewRequestIdRaw !== '' && preg_match('/^REQ-0*(\d+)$/i', $viewRequestIdRaw, $m)) {
    $viewRequestId = (int) $m[1];
}
$roleLc = strtolower(trim((string)($user['role'] ?? '')));
$isInventoryManager = ($roleLc === 'inventory manager' || $roleLc === 'inventory_manager');
$isComptroller = ($roleLc === 'comptroller');
$isGsdOfficer = ($roleLc === 'gsd officer');
$isPresident = in_array($roleLc, ['president', 'president verifier', 'verifier president', 'president_verifier'], true);
$isCanvasserWorkspace = in_array($roleLc, ['employee', 'user', 'laboratory manager', 'canvasser'], true);
$viewingRequest = $viewRequestId > 0 && $viewOnly;
$prAfterCanvassAccepted = $viewRequestId > 0 && requisitionCanvassFormAcceptedForRequest($db, $viewRequestId);
$isInventoryManagerActiveReview = $viewingRequest && $isInventoryManager && $from !== 'history';
$isCanvasserActiveReview = $viewingRequest && $isCanvasserWorkspace && $from === 'canvasser';
$isComptrollerActiveReview = $viewingRequest && $isComptroller && $from !== 'history';
$isGsdActiveReview = $viewingRequest && $isGsdOfficer && $from !== 'history';
$isPresidentActiveReview = $viewingRequest && $isPresident && $from !== 'history';
/** GSD officer review (non-history): assign office staff for canvassing — not shown to dean/canvasser/etc. */
$isGsdCanvasAssigneeUi = (bool) $isGsdActiveReview;
$applyViewOnlyChrome = $viewingRequest;
$canvassBannerEligible = !$isInventoryManager && !$isComptroller && !$isGsdOfficer && !$isPresident && !$isCanvasserActiveReview;
$canShowPurchaseRequisitionLink = $viewingRequest
    && $viewRequestId > 0
    && $prAfterCanvassAccepted
    && (
        $isInventoryManager
        || $isPresidentActiveReview
        || ($isPresident && $from === 'history')
        || (
            !$isInventoryManager
            && !$isComptroller
            && !$isGsdOfficer
            && !$isPresident
            && !$isCanvasserWorkspace
        )
    );

$backUrl = 'dean_requisition_management.php';
if ($from === 'progress' && $viewRequestId > 0) {
    $progressQs = 'rid=' . $viewRequestId . ($progressFrom === 'status' ? '&from=status' : '');
    if ($isInventoryManager || $isComptroller) {
        $backUrl = 'requisition_status_progress.php?' . $progressQs;
    } else {
        $backUrl = 'dean_requisition_status_progress.php?' . $progressQs;
    }
} elseif ($from === 'dashboard') {
    $backUrl = 'dean_dashboard.php';
} elseif ($from === 'requisition') {
    $backUrl = 'dean_requisition_management.php';
} elseif ($from === 'history') {
    $backUrl = $isInventoryManager ? 'audit_trail.php' : ($roleLc === 'dean' ? 'dean_requisition_status.php' : 'audit_trail.php');
} elseif ($from === 'inventory' && $viewRequestId > 0) {
    $progressQs = 'rid=' . $viewRequestId . ($progressFrom === 'status' ? '&from=status' : '');
    $backUrl = 'requisition_status_progress.php?' . $progressQs;
} elseif ($from === 'comptroller' && $viewRequestId > 0) {
    $progressQs = 'rid=' . $viewRequestId . ($progressFrom === 'status' ? '&from=status' : '');
    $backUrl = 'requisition_status_progress.php?' . $progressQs;
} elseif ($from === 'gsd' && $viewRequestId > 0) {
    $backUrl = 'gsd_request.php';
} elseif ($from === 'president' && $viewRequestId > 0) {
    $backUrl = 'president_requisition_status_progress.php?rid=' . $viewRequestId;
} elseif ($from === 'canvasser' && $viewRequestId > 0) {
    $backUrl = 'canvasser_request.php';
}

$rfRequestId = 0;
$rfStepLine = '';
$rfHint = '';
$rfLinkUrl = '';
$rfLinkText = '';
if ($viewingRequest && $viewRequestId > 0) {
    $rfRequestId = $viewRequestId;
    if ($isCanvasserActiveReview) {
        $rfStepLine = 'Supplier matrix · canvasser workspace';
        $rfHint = 'Use this page for per-line suppliers and prices. The abstract of quotation lists formal quote lines.';
        $rfLinkUrl = 'dean_canvass_form.php?request_id=' . $viewRequestId . '&from=canvasser';
        $rfLinkText = 'Open abstract of quotation';
    } elseif ($isInventoryManagerActiveReview) {
        $rfStepLine = 'Inventory · accept or reject requisition';
        $rfHint = 'Requesters open the canvass sheet only after you accept.';
        $rfLinkUrl = 'dean_canvass_form.php?request_id=' . $viewRequestId . '&from=inventory' . ($progressFrom === 'status' ? '&progress_from=status' : '');
        $rfLinkText = 'Open abstract of quotation';
    } elseif ($isComptrollerActiveReview) {
        $rfStepLine = 'Comptroller · requisition view';
        $rfLinkUrl = 'dean_canvass_form.php?request_id=' . $viewRequestId . '&from=comptroller';
        $rfLinkText = 'Open abstract of quotation';
    } elseif ($isPresidentActiveReview) {
        $rfStepLine = 'President verifier · requisition view';
        $rfLinkUrl = 'dean_canvass_form.php?request_id=' . $viewRequestId . '&from=president';
        $rfLinkText = 'Open abstract of quotation';
    } elseif ($isGsdActiveReview) {
        $rfStepLine = 'G.S.D. · assign canvasser & review';
        $rfLinkUrl = 'dean_canvass_form.php?request_id=' . $viewRequestId . '&from=gsd';
        $rfLinkText = 'Open abstract of quotation';
    } elseif ($isComptroller && $from === 'history') {
        $rfStepLine = 'Comptroller · history (read-only)';
        $rfLinkUrl = 'dean_canvass_form.php?request_id=' . $viewRequestId . '&from=history';
        $rfLinkText = 'View abstract of quotation';
    } elseif ($isPresident && $from === 'history') {
        $rfStepLine = 'President verifier · history (read-only)';
        $rfLinkUrl = 'dean_canvass_form.php?request_id=' . $viewRequestId . '&from=history';
        $rfLinkText = 'View abstract of quotation';
    } else {
        $rfStepLine = 'Step 1 of 2 · Requisition';
        $rfHint = 'Step 2 is the abstract of quotation after inventory accepts.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $rfRequestId > 0 ? 'Requisition · Request #' . $rfRequestId . ' · WLC-SMART' : 'Requisition form · WLC-SMART'; ?></title>
<link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
<link rel="stylesheet" href="../assets/css/requisition_form.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="page-requisition-form">

<main class="requisition-main">
    <div class="requisition-card<?php echo $applyViewOnlyChrome ? ' view-only' : ''; ?><?php echo ($isInventoryManagerActiveReview || $isCanvasserActiveReview || $isComptrollerActiveReview || $isGsdActiveReview || $isPresidentActiveReview) ? ' comptroller-request-review' : ''; ?><?php echo $isCanvasserActiveReview ? ' canvasser-matrix-edit' : ''; ?>">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="requisition-close-btn" aria-label="Back" data-tooltip="Back">
            <i class="fas fa-times"></i>
        </a>
        <div class="requisition-top">
            <div class="logo-left">
                <div class="requisition-logo-wlc-wrap">
                    <img src="../assets/images/wlc-smart-logo.png" alt="WLC-SMART Inventory Office" class="requisition-logo-wlc" decoding="async" />
                </div>
            </div>
            <div class="requisition-title">
                <h1>WESTERN LEYTE COLLEGE OF ORMOC CITY INC.</h1>
                <div class="requisition-subtitle">
                    <p>A. Bonifacio St., Ormoc City, Leyte, Philippines</p>
                    <p>E-mail Address: westernleytecollege@yahoo.com</p>
                    <p>Tel Nos.: (053) 561 - 5310 / 255 8549</p>
                </div>
            </div>
            <div class="logo-right">
                <img src="../assets/images/western-letye-logo.jpg" alt="College Logo" class="requisition-logo" />
            </div>
        </div>
        <?php if ($rfRequestId > 0): ?>
        <nav class="req-breadcrumb" aria-label="Request context">
            <div class="req-breadcrumb-track">
                <span class="req-breadcrumb-pill req-breadcrumb-pill--id">Request #<?php echo (int) $rfRequestId; ?></span>
                <?php if ($rfStepLine !== ''): ?>
                <span class="req-breadcrumb-sep" aria-hidden="true">›</span>
                <span class="req-breadcrumb-pill req-breadcrumb-pill--stage"><?php echo htmlspecialchars($rfStepLine); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($rfLinkUrl !== '' && $rfLinkText !== ''): ?>
            <a class="req-breadcrumb-link" href="<?php echo htmlspecialchars($rfLinkUrl); ?>"><?php echo htmlspecialchars($rfLinkText); ?></a>
            <?php endif; ?>
            <?php if ($rfHint !== ''): ?>
            <p class="req-breadcrumb-hint"><?php echo htmlspecialchars($rfHint); ?></p>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
        <?php if ($canShowPurchaseRequisitionLink): ?>
        <div class="req-flow-context">
            <div class="req-flow-context-top">
                <div class="req-flow-context-main">
                    <span class="req-flow-step">Purchase requisition is now available for review.</span>
                </div>
                <a class="req-flow-context-link" href="purchase_requisition_form.php?request_id=<?php echo (int) $rfRequestId; ?>&from=<?php echo htmlspecialchars($from !== '' ? $from : 'requisition'); ?>">Open purchase requisition</a>
            </div>
        </div>
        <?php endif; ?>
        <section class="rf-form-card rf-form-card--meta" aria-label="Request details">
            <p class="rf-form-title" id="requisitionFormTitle">REQUISITION FORM</p>
            <h2 class="rf-form-card__title">Requester Information</h2>
            <div class="requisition-info requisition-meta-grid">
                <div class="field-group">
                    <label for="requesterName">Requester name</label>
                    <input type="text" id="requesterName" value="<?php echo htmlspecialchars($displayName); ?>" disabled>
                </div>
                <div class="field-group">
                    <label for="facultyRole">Faculty / staff role</label>
                    <input type="text" id="facultyRole" value="<?php echo htmlspecialchars($user['role'] ?? ''); ?>" disabled>
                </div>
                <div class="field-group field-group--facility">
                    <label for="facilitySelect">Office / facility location</label>
                    <select id="facilitySelect">
                        <option value="">Select location</option>
                    </select>
                </div>
                <div class="field-group field-group--department">
                    <label for="officeSelect">Department</label>
                    <select id="officeSelect">
                        <option value="">Select department</option>
                    </select>
                </div>
                <div class="field-group">
                    <label for="requesterEmail">Email address</label>
                    <input type="email" id="requesterEmail" value="<?php echo htmlspecialchars($userEmail); ?>" disabled>
                </div>
                <div class="field-group">
                    <label for="requesterContact">Contact number</label>
                    <input type="text" id="requesterContact" value="<?php echo htmlspecialchars($userContact); ?>" placeholder="0917 123 4567" disabled>
                </div>
                <div class="field-group field-group--date">
                    <label for="requestDate">Date needed</label>
                    <input type="date" id="requestDate" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="field-group field-group--purpose">
                    <label for="requestPurpose">Purpose / description</label>
                    <textarea id="requestPurpose" rows="2" placeholder=""></textarea>
                </div>
            </div>
        </section>

        <?php if ($canvassBannerEligible): ?>
        <div id="canvassContinueBanner" class="canvass-continue-banner" role="status" aria-live="polite" hidden>
            <div class="canvass-continue-banner-inner">
                <span class="canvass-continue-banner-icon" aria-hidden="true"><i class="fas fa-circle-exclamation"></i></span>
                <p class="canvass-continue-banner-msg"><strong>Step 2 of 2:</strong> Open the abstract of quotation (canvass sheet).</p>
                <a href="#" id="canvassContinueLink" class="canvass-continue-action">Open canvass form</a>
            </div>
        </div>
        <?php endif; ?>

        <section class="rf-form-card rf-section rf-section-items" aria-label="Requested items and note">
            <h2 class="rf-section-heading">Requested Items</h2>
            <div class="table-section rf-items-list-wrap">
                <datalist id="itemNameSuggestions"></datalist>
                <div class="rf-items-list" id="requestedItemsTable" aria-label="Requested items">
                    <div class="rf-items-list__header">
                        <span class="rf-items-list__head-index">#</span>
                        <span class="rf-items-list__head-desc">Item description</span>
                        <div class="rf-items-row__controls rf-items-list__head-controls">
                            <span class="rf-items-list__head-unit">Unit</span>
                            <span class="rf-items-list__head-qty">Quantity</span>
                            <span class="rf-items-list__head-action">Action</span>
                        </div>
                    </div>
                    <div id="requestedItemsBody" class="rf-items-list__body"></div>
                </div>
                <div class="rf-items-list-footer">
                    <button type="button" id="rfAddItemBtn" class="rf-items-add-btn">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add item
                    </button>
                </div>

                <?php if ($isCanvasserActiveReview): ?>
                <div class="note-group rf-items-canvasser-note">
                    <p>Supplier and quoted price entries are handled on the <strong>Abstract of quotation</strong> page only.
                    This requisition form is read-only for canvassers to avoid duplicate supplier entries.</p>
                </div>
                <?php endif; ?>

                <div class="rf-section-note-block">
                    <div class="field-group field-group--note">
                        <label for="requestMessage">Note / Message</label>
                        <textarea id="requestMessage" rows="3" placeholder="Add note or message here... (use this for urgent/immediate requests)"></textarea>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($isGsdCanvasAssigneeUi || $isCanvasserActiveReview): ?>
        <div class="approval-section rf-verifier-summary">
            <div class="approval-card">
              <div class="approval-role<?php echo $isGsdCanvasAssigneeUi ? ' approval-role-gsd-assignee' : ''; ?>">
                    <div class="circle-icon inactive"><i class="fas fa-check"></i></div>
                    <div class="approval-role-body">
                        <div class="approval-name">REVIEWED BY</div>
                        <?php if ($isGsdCanvasAssigneeUi): ?>
                        <div class="gsd-canvas-assignee-field">
                            <input type="hidden" id="gsdCanvasAssigneeUserId" value="">
                            <label class="sr-only" for="gsdCanvasAssigneeInput">Assign staff to canvass</label>
                            <input type="text" id="gsdCanvasAssigneeInput" class="gsd-canvas-assignee-input" autocomplete="off" placeholder="Search name or email…">
                            <ul id="gsdCanvasAssigneeSuggestions" class="gsd-canvas-assignee-suggestions" role="listbox" hidden></ul>
                            <p class="gsd-canvas-assignee-hint">Suggestions are limited to your office. Required before <strong>Verify</strong>.</p>
                        </div>
                        <?php else: ?>
                        <div class="approval-sub" id="canvasAssigneeNameDisplay">—</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$isCanvasserActiveReview): ?>
        <div class="rf-form-footer-bar" id="rfFormFooterBar">
            <div class="rf-form-footer-bar__message">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                <span>Please review all details before submitting your requisition request.</span>
            </div>
            <div class="rf-form-footer-bar__actions">
                <a href="<?php echo htmlspecialchars($backUrl); ?>" id="rfFormCancelBtn" class="rf-form-footer-bar__btn rf-form-footer-bar__btn--cancel rf-form-cancel-link">Cancel</a>
                <button type="button" id="submitRequisitionBtn" class="rf-form-footer-bar__btn rf-form-footer-bar__btn--submit">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i> Submit Request
                </button>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($isInventoryManagerActiveReview || $isComptrollerActiveReview || $isGsdActiveReview || $isPresidentActiveReview): ?>
        <div class="comptroller-approve-wrapper verifier-decision-bar rf-form-actions">
            <button type="button" id="comptrollerApproveBtn" class="btn-submit"><i class="fas fa-check" aria-hidden="true"></i> <?php echo $isInventoryManagerActiveReview ? 'Accept requisition' : 'Approve'; ?></button>
            <button type="button" id="comptrollerRejectBtn" class="btn-secondary comptroller-reject-btn"><i class="fas fa-xmark" aria-hidden="true"></i> Reject</button>
            <button type="button" id="comptrollerUndoBtn" class="btn-secondary comptroller-undo-btn" style="display: none;"><i class="fas fa-rotate-left" aria-hidden="true"></i> Undo decision</button>
        </div>
        <?php if ($isInventoryManagerActiveReview): ?>
        <div class="note-group">
            <label for="inventoryRejectReason" class="note-label">Rejection note (required when rejecting)</label>
            <textarea id="inventoryRejectReason" rows="2" placeholder="Add reason if requisition is rejected..."></textarea>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</main>
<div id="formToast" class="toast success" style="display:none;" role="status" aria-live="polite" aria-atomic="true"></div>
<?php if ($isCanvasserActiveReview): ?>
<div id="canvasserNewSupplierModal" class="canvasser-supplier-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="canvasserNewSupplierTitle">
    <div class="canvasser-supplier-modal-backdrop" data-close-canvasser-supplier-modal></div>
    <div class="canvasser-supplier-modal-card">
        <div class="canvasser-supplier-modal-header">
            <h3 id="canvasserNewSupplierTitle">Register a new supplier</h3>
            <button type="button" class="canvasser-supplier-modal-close" data-close-canvasser-supplier-modal aria-label="Close">&times;</button>
        </div>
        <p class="canvasser-supplier-modal-intro">Use this when no suitable supplier exists in the list (for example the requester left suppliers blank). Inventory can add photos later in Supplier Management.</p>
        <form id="canvasserNewSupplierForm" class="canvasser-supplier-form">
            <label class="canvasser-supplier-field"><span>Supplier name <em>*</em></span><input type="text" id="canvasserNewSupplierName" name="supplier_name" required maxlength="100" autocomplete="organization"></label>
            <label class="canvasser-supplier-field"><span>Contact person</span><input type="text" id="canvasserNewSupplierContact" name="contact_person" maxlength="100" autocomplete="name"></label>
            <label class="canvasser-supplier-field"><span>Phone</span><input type="text" id="canvasserNewSupplierPhone" name="phone_number" maxlength="30" autocomplete="tel"></label>
            <label class="canvasser-supplier-field"><span>Email</span><input type="email" id="canvasserNewSupplierEmail" name="email" maxlength="100" autocomplete="email"></label>
            <label class="canvasser-supplier-field"><span>Address</span><input type="text" id="canvasserNewSupplierAddress" name="address" maxlength="255"></label>
            <div class="canvasser-supplier-form-grid">
                <label class="canvasser-supplier-field"><span>City</span><input type="text" id="canvasserNewSupplierCity" name="city" maxlength="50"></label>
                <label class="canvasser-supplier-field"><span>Country</span><input type="text" id="canvasserNewSupplierCountry" name="country" maxlength="50"></label>
                <label class="canvasser-supplier-field"><span>Postal code</span><input type="text" id="canvasserNewSupplierPostal" name="postal_code" maxlength="20"></label>
            </div>
        </form>
        <div class="canvasser-supplier-modal-actions">
            <button type="button" class="btn-secondary" data-close-canvasser-supplier-modal>Cancel</button>
            <button type="button" id="canvasserNewSupplierSubmit" class="btn-submit">Save supplier</button>
        </div>
    </div>
</div>
<?php endif; ?>
<div id="confirmModal" class="confirm-modal" style="display:none;">
    <div class="confirm-modal-backdrop"></div>
    <div class="confirm-modal-card" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
        <div class="confirm-modal-header">
            <h3 id="confirmTitle">Please Confirm</h3>
        </div>
        <div class="confirm-modal-body" id="confirmMessage">Are you sure?</div>
        <div class="confirm-modal-actions">
            <button type="button" id="confirmCancelBtn" class="confirm-btn confirm-btn-cancel">Cancel</button>
            <button type="button" id="confirmOkBtn" class="confirm-btn confirm-btn-ok">Confirm</button>
        </div>
    </div>
</div>

<script>
window.IMRMS_REQ_FORM_CONFIG = <?php echo json_encode([
    'viewOnly' => $applyViewOnlyChrome,
    'requestId' => $viewingRequest ? $viewRequestId : 0,
    'detailApi' => ($viewingRequest && ($isInventoryManager || $isCanvasserActiveReview || $isComptroller || $isGsdOfficer || $isPresident)) ? 'admin' : 'dean',
    'isCanvasserView' => (bool) $isCanvasserActiveReview,
    'isInventoryManagerView' => (bool) $isInventoryManagerActiveReview,
    'inventoryApproveApi' => $isInventoryManagerActiveReview ? '../../app/api/admin_requisition.php' : null,
    'canvasserApproveApi' => $isCanvasserActiveReview ? '../../app/api/canvasser_requests.php' : null,
    'isComptrollerView' => (bool) $isComptrollerActiveReview,
    'comptrollerApproveApi' => $isComptrollerActiveReview ? '../../app/api/comptroller.php' : null,
    'isGsdView' => (bool) $isGsdActiveReview,
    'gsdApproveApi' => $isGsdActiveReview ? '../../app/api/gsd/requests.php' : null,
    'isGsdCanvasAssigneeUi' => (bool) $isGsdCanvasAssigneeUi,
    'isPresidentView' => (bool) $isPresidentActiveReview,
    'presidentApproveApi' => $isPresidentActiveReview ? '../../app/api/president/requests.php' : null,
    'canvassBannerEligible' => (bool) $canvassBannerEligible,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
</script>
<script src="../assets/js/requisition_form.js"></script>
</body>
</html>