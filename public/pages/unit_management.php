<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';
$db   = Database::connect();
$stmt = $db->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$role = strtolower(trim($user['role'] ?? ''));
if ($role !== 'inventory manager' && $role !== 'inventory_manager') {
    header("Location: ../../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit Management - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
    <link rel="stylesheet" href="../assets/css/unit_management.css?v=wlc3">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<?php $imActivePage = 'unit_management.php'; require __DIR__ . '/partials/inventory_manager_sidebar.php'; ?>

<main class="main-content unit-management-container">

    <!-- Page header -->
    <div class="page-header">
        <h1>Unit Management</h1>
        <p>Manage measurement units used across requisitions and canvassing.</p>
    </div>

    <!-- Toolbar -->
    <div class="filter-section">
        <h3>Units List</h3>
        <div class="filter-controls">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="unitSearchInput" placeholder="Search units..." class="search-input" aria-label="Search units">
            </div>
            <button class="btn-filter" id="addUnitBtn" type="button">
                <i class="fas fa-plus"></i> Add Unit
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="table-container">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Unit Name</th>
                        <th>Abbreviation</th>
                        <th>Description</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="unitTableBody">
                    <tr>
                        <td colspan="6" style="text-align:center;padding:50px;color:#64748b;">Loading units...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <footer class="table-panel-footer" id="unitPagination" aria-label="Unit list pages">
            <p class="table-panel-footer__info" id="unitPageInfo">Showing 0 to 0 of 0 units</p>
            <div class="table-panel-footer__pagination">
                <button type="button" class="table-panel-footer__page-btn" id="prevUnitBtn" disabled aria-label="Previous page">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                </button>
                <span class="table-panel-footer__page-num" id="unitPageNum">1</span>
                <button type="button" class="table-panel-footer__page-btn" id="nextUnitBtn" disabled aria-label="Next page">
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
        </footer>
    </div>

    <!-- ── Add / Edit Modal ── -->
    <div class="modal" id="unitModal">
        <div class="modal-content">
            <span class="close-modal" id="closeUnitModal">&times;</span>
            <h2 id="unitModalTitle">Add Unit</h2>

            <form id="unitForm">
                <input type="hidden" id="unit_id" name="unit_id">

                <div class="form-group">
                    <label for="unit_name">Unit Name <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="unit_name" id="unit_name" placeholder="e.g. Piece" maxlength="50" autocomplete="off" required>
                </div>

                <div class="form-group">
                    <label for="unit_abbreviation">Abbreviation <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="unit_abbreviation" id="unit_abbreviation" placeholder="e.g. pc" maxlength="20" autocomplete="off" required>
                    <small style="font-size:12px;color:#64748b;display:block;margin-top:4px;">Stored in lowercase (e.g. pc, set, box).</small>
                </div>

                <div class="form-group">
                    <label for="unit_description">Description <span style="color:#94a3b8;font-size:0.85em;">(optional)</span></label>
                    <input type="text" name="unit_description" id="unit_description" placeholder="e.g. Individual item count" maxlength="50" autocomplete="off">
                </div>

                <button type="submit" class="btn-save">Save Unit</button>
            </form>
        </div>
    </div>

    <!-- ── Delete Confirmation Modal ── -->
    <div class="unit-delete-modal" id="unitDeleteModal" aria-hidden="true">
        <div class="unit-delete-backdrop" id="unitDeleteBackdrop"></div>
        <div class="unit-delete-card" role="dialog" aria-modal="true"
             aria-labelledby="unitDeleteTitle" aria-describedby="unitDeleteDesc">
            <button type="button" class="unit-delete-close" id="closeUnitDeleteModal" aria-label="Close">&times;</button>
            <div class="unit-delete-icon" aria-hidden="true">
                <i class="fas fa-trash-alt"></i>
            </div>
            <h3 id="unitDeleteTitle" class="unit-delete-title">Delete this unit?</h3>
            <p id="unitDeleteDesc" class="unit-delete-desc">
                This will permanently remove the unit. This action cannot be undone.
            </p>
            <p class="unit-delete-name" id="deleteUnitName"></p>
            <div class="unit-delete-actions">
                <button type="button" class="unit-delete-btn unit-delete-btn-cancel" id="cancelUnitDeleteBtn">Cancel</button>
                <button type="button" class="unit-delete-btn unit-delete-btn-delete" id="confirmUnitDeleteBtn">
                    <i class="fas fa-trash-alt"></i> Delete unit
                </button>
            </div>
        </div>
    </div>

    <!-- ── Success Modal ── -->
    <div class="unit-success-modal" id="unitSuccessModal" aria-hidden="true">
        <div class="unit-success-backdrop" id="unitSuccessBackdrop"></div>
        <div class="unit-success-card" role="dialog" aria-modal="true"
             aria-labelledby="unitSuccessTitle" aria-describedby="unitSuccessMessage">
            <div class="unit-success-icon" aria-hidden="true">
                <i class="fas fa-check"></i>
            </div>
            <h3 id="unitSuccessTitle" class="unit-success-title">Success</h3>
            <p id="unitSuccessMessage" class="unit-success-desc"></p>
            <div class="unit-success-actions">
                <button type="button" class="unit-success-btn" id="unitSuccessOk">OK</button>
            </div>
        </div>
    </div>

</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" style="display:none;">
    <i class="fas fa-bars"></i>
</button>

<div id="toastContainer"></div>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/unit_management.js?v=wlc2"></script>

<?php require __DIR__ . '/partials/inventory_manager_sidebar_scripts.php'; ?>
</body>
</html>
