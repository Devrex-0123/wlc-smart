<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';

$db = Database::connect();
$stmt = $db->prepare('SELECT * FROM user WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (strtolower($currentUser['role'] ?? '') !== 'dean') {
    header('Location: dashboard.php');
    exit;
}

$deanOfficeId = $currentUser['office_id'] ?? null;
if (!$deanOfficeId) {
    echo 'Dean is not assigned to any office.';
    exit;
}

$stmt = $db->prepare('SELECT `office_name` FROM offices WHERE office_id = ?');
$stmt->execute([$deanOfficeId]);
$dept = $stmt->fetch(PDO::FETCH_ASSOC);
$deptName = $dept['office_name'] ?? 'Unknown Office';

$initialFacilityId = isset($_GET['facility_id']) ? (int) $_GET['facility_id'] : 0;
if ($initialFacilityId < 0) {
    $initialFacilityId = 0;
}

$username = trim((string)($currentUser['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($currentUser['Email'] ?? ''))[0] ?? 'Dean';
}
$initials = strtoupper(substr($currentUser['Email'] ?? 'D', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory — Dean</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
    <link rel="stylesheet" href="../assets/css/dean_requisition_management.css">
    <link rel="stylesheet" href="../assets/css/dean_inventory.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li><a href="dean_dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a href="dean_requisition_management.php"><i class="fas fa-file-signature"></i> <span>Requisition Management</span></a></li>
            <li><a href="dean_requisition_status.php"><i class="fas fa-bars-progress"></i> <span>Status</span></a></li>
            <li><a href="dean_inventory.php" class="active"><i class="fas fa-cubes"></i> <span>Inventory</span></a></li>
            <li><a href="dean_account_management.php"><i class="fas fa-users-cog"></i> <span>Account Management</span></a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <?php if (!empty($currentUser['photo_url'])): ?>
                    <img src="../<?php echo htmlspecialchars($currentUser['photo_url']); ?>" alt="Profile Photo" class="user-avatar-img">
                <?php else: ?>
                    <div class="user-avatar-initials"><?php echo htmlspecialchars($initials); ?></div>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($username); ?></h4>
                <p>Dean</p>
            </div>
        </div>
        <button id="logoutBtn" class="btn-logout-sidebar" type="button">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </div>
</aside>

<main class="main-content dean-inventory-page">
    <div class="page-header management-header">
        <div>
            <h1>Office inventory</h1>
            <p>Facilities and assets for <strong><?php echo htmlspecialchars($deptName); ?></strong> only. Browse by facility, then view equipment registered there (read-only).</p>
        </div>
    </div>

    <section class="dean-inv-panel dean-lab-manager-panel" id="deanLabManagerPanel" aria-labelledby="deanLabManagerHeading">
        <h2 id="deanLabManagerHeading" class="dean-lab-manager-heading">Default assignee</h2>
        <div class="dean-lab-manager-row">
            <label class="dean-lab-manager-label" for="deanLabManagerSelect">Office staff</label>
            <select id="deanLabManagerSelect" class="sort-dropdown dean-lab-manager-select">
                <option value="">Loading…</option>
            </select>
            <button type="button" class="btn-primary dean-lab-manager-save" id="deanLabManagerSave">Save</button>
        </div>
        <p class="dean-lab-manager-status" id="deanLabManagerStatus" hidden></p>
    </section>

    <nav class="dean-inv-breadcrumb" id="deanInvBreadcrumb" aria-label="Breadcrumb"></nav>

    <p class="inventory-hint" role="note">
        <i class="fas fa-info-circle" style="margin-right:0.35rem;"></i>
        Only locations linked to your office appear here. Open a facility to see inventory items assigned to it.
    </p>

    <section class="dean-inv-panel" id="deanFacilitiesSection"<?php echo $initialFacilityId > 0 ? ' hidden' : ''; ?>>
        <div class="filter-section">
            <h3>Facilities (labs &amp; rooms)</h3>
            <div class="search-container dean-inv-search">
                <i class="fas fa-search"></i>
                <input type="search" id="deanFacilitySearch" class="search-input" placeholder="Search by lab, room, or building…" autocomplete="off">
            </div>
        </div>
        <div class="summary-grid dean-facilities-summary" id="deanFacilitiesSummary">
            <div class="summary-card">
                <p>Facilities (labs &amp; rooms)</p>
                <h3 id="deanFacilitiesStatCount">0</h3>
            </div>
            <div class="summary-card">
                <p>Total part quantities (current list)</p>
                <h3 id="deanFacilitiesStatParts">0</h3>
            </div>
            <div class="summary-card">
                <p>Locations with stock on hand</p>
                <h3 id="deanFacilitiesStatStocked">0</h3>
            </div>
        </div>
        <div class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Building</th>
                            <th>Code</th>
                            <th>Floor</th>
                            <th>Laboratory</th>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Inventory (parts qty)</th>
                            <th class="col-action" style="text-align:center;min-width:3.25rem">Action</th>
                        </tr>
                    </thead>
                    <tbody id="deanFacilitiesBody">
                        <tr>
                            <td colspan="9" class="dean-inv-loading">Loading facilities…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="dean-inv-panel" id="deanInventorySection"<?php echo $initialFacilityId > 0 ? '' : ' hidden'; ?>>
        <h3 class="dean-inv-facility-title" id="deanInvFacilityHead">Inventory in facility</h3>
        <div class="summary-grid" id="deanInvStatsWrap">
        <div class="summary-card">
            <p>Total assemblies</p>
            <h3 id="deanInvStatTotal">0</h3>
        </div>
        <div class="summary-card">
            <p>Good (first part)</p>
            <h3 id="deanInvStatGood">0</h3>
        </div>
        <div class="summary-card">
            <p>Needs attention</p>
            <h3 id="deanInvStatAttention">0</h3>
        </div>
        </div>

    <div class="filter-section" style="margin-top:0;">
        <h3>Items in this facility</h3>
        <div class="filter-controls">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="search" id="deanInventorySearch" class="search-input" placeholder="Search name, code, or part…" autocomplete="off">
            </div>
            <select id="deanInventoryCondition" class="sort-dropdown" aria-label="Filter by condition">
                <option value="all">All conditions</option>
                <option value="good">Good</option>
                <option value="fair">Fair</option>
            </select>
            <select id="deanInventoryStatus" class="sort-dropdown" aria-label="Filter by status">
                <option value="all">All statuses</option>
                <option value="available">Available</option>
                <option value="in use">In use</option>
                <option value="stored">Stored</option>
                <option value="maintenance">Maintenance</option>
            </select>
        </div>
    </div>

    <div class="table-container">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Assembly / set name</th>
                        <th>Item code</th>
                        <th>Primary part</th>
                        <th>Qty</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th class="col-action" style="text-align:center;min-width:3.25rem">Action</th>
                    </tr>
                </thead>
                <tbody id="deanInventoryBody">
                    <tr>
                        <td colspan="8" class="dean-inv-loading">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    </section>

    <div class="modal" id="deanInventoryDetailModal" aria-hidden="true">
        <div class="modal-content" role="dialog" aria-labelledby="deanInvDetailTitle">
            <button type="button" class="close-modal" id="deanInventoryCloseModal" aria-label="Close">&times;</button>
            <h2 id="deanInvDetailTitle">Item details</h2>
            <dl class="dean-inventory-detail">
                <div class="detail-row">
                    <dt>Assembly / set</dt>
                    <dd id="detailName">—</dd>
                </div>
                <div class="detail-row">
                    <dt>Item code</dt>
                    <dd id="detailCode">—</dd>
                </div>
                <div class="detail-row">
                    <dt>Location</dt>
                    <dd id="detailFacility">—</dd>
                </div>
                <div class="detail-row">
                    <dt>First part (summary)</dt>
                    <dd id="detailQty">—</dd>
                </div>
                <div class="detail-row">
                    <dt>Condition (first part)</dt>
                    <dd id="detailCondition">—</dd>
                </div>
                <div class="detail-row">
                    <dt>Status (first part)</dt>
                    <dd id="detailStatus">—</dd>
                </div>
            </dl>
            <div class="dean-inv-parts-wrap" id="deanInvPartsWrap" hidden>
                <h3 class="dean-inv-parts-h">Catalog parts</h3>
                <div class="table-container" style="margin:0;">
                    <table class="dean-inv-parts-table">
                        <thead>
                            <tr>
                                <th>Part</th>
                                <th>Code</th>
                                <th>Qty</th>
                                <th>Condition</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="deanInvPartsBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-muted" id="deanInventoryModalDismiss">Close</button>
            </div>
        </div>
    </div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" type="button"><i class="fas fa-bars"></i></button>

<script>
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
mobileMenuBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    sidebar?.classList.toggle('open');
});
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 && sidebar?.classList.contains('open') &&
        !sidebar.contains(e.target) && !mobileMenuBtn?.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});
</script>
<script>
window.DEAN_INVENTORY_CONFIG = <?php echo json_encode([
    'api' => '../../app/api/dean_inventory.php',
    'officeName' => $deptName,
    'initialFacilityId' => $initialFacilityId,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/dean_inventory.js"></script>
</body>
</html>
