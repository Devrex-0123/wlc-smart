<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';
$db = Database::connect();
$stmt = $db->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$initials = strtoupper(substr($user['Email'], 0, 1));
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Facility Management - IMRMS</title>
<link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
<link rel="stylesheet" href="../assets/css/facility_management.css">
<link rel="stylesheet" href="../assets/css/loading.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<?php $imActivePage = 'facility_management.php'; require __DIR__ . '/partials/inventory_manager_sidebar.php'; ?>

<main class="main-content facility-management-container">
    <div class="page-header">
        <h1>Facility Management</h1>
        <p>Manage offices and their facilities (rooms, labs). The office list is paginated five per page.</p>
    </div>

    <!-- Breadcrumb Navigation -->
    <div class="breadcrumb-nav" id="breadcrumb">
        <div class="breadcrumb-item active" id="breadcrumb-home">
            <i class="fas fa-home"></i> Offices
        </div>
        <div class="breadcrumb-item hidden" id="breadcrumb-facility">
            <i class="fas fa-chevron-right"></i> <span id="breadcrumb-facility-text">Facilities</span>
        </div>
    </div>

    <!-- Offices View -->
    <div id="officesView">
        <div class="filter-section">
            <div class="filter-section-lead">
                <h3>Offices</h3>
                <p class="office-list-hint">Five offices per page; use Previous and Next below the list.</p>
            </div>
            <div class="filter-controls">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="officeSearchInput" placeholder="Search offices..." class="search-input">
                </div>
                <select id="officeSortDropdown" class="sort-dropdown">
                    <option value="">Sort By</option>
                    <option value="name-asc">Name (A-Z)</option>
                    <option value="name-desc">Name (Z-A)</option>
                    <option value="labs-asc">Labs (Low to High)</option>
                    <option value="labs-desc">Labs (High to Low)</option>
                    <option value="rooms-asc">Rooms (Low to High)</option>
                    <option value="rooms-desc">Rooms (High to Low)</option>
                    <option value="total-asc">Total (Low to High)</option>
                    <option value="total-desc">Total (High to Low)</option>
                </select>
                <button type="button" id="deptLayoutToggleBtn" class="dept-layout-toggle" title="Show offices as card grid" aria-label="Switch to grid layout" data-tooltip="Show offices as card grid">
                    <i class="fas fa-th-large dept-layout-toggle-icon" aria-hidden="true"></i>
                </button>
                <button class="btn-filter" id="addOfficeBtn"><i class="fas fa-plus"></i> Add Office</button>
            </div>
        </div>

        <div id="officeGrid" class="office-grid hidden">
            <div class="office-grid-loading">Loading Offices...</div>
        </div>

        <div id="officeTablePanel" class="table-container office-table-panel">
            <div class="table-wrapper">
                <table class="facility-data-table facility-data-table--offices">
                    <colgroup>
                        <col class="facility-col-entry-num" style="width:1.75%" />
                        <col style="width:10%" />
                        <col style="width:14%" />
                        <col style="width:5%" />
                        <col style="width:5%" />
                        <col style="width:5%" />
                        <col style="width:10%" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th>Office</th>
                            <th>Type</th>
                            <th>Total Labs</th>
                            <th>Total Rooms</th>
                            <th>TOTAL</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="officeTableBody">
                        <tr>
                            <td colspan="7" class="loading-cell">Loading Offices...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pagination-controls office-pagination" id="officePagination" aria-label="Office list pages">
            <button type="button" id="officePrevPageBtn" class="pagination-btn" disabled>
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            <span id="officePageInfo" class="page-info">Page 1</span>
            <button type="button" id="officeNextPageBtn" class="pagination-btn" disabled>
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

    <!-- Facilities View -->
    <div id="facilitiesView" class="hidden">
        <div class="filter-section">
            <h3 id="facilityViewTitle">Facilities</h3>
            <div class="filter-controls">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="facilitySearchInput" placeholder="Search by building, code, lab, or room..." class="search-input">
                </div>
                <select id="facilitySortDropdown" class="sort-dropdown">
                    <option value="">Sort By</option>
                    <option value="building-asc">Building (A-Z)</option>
                    <option value="building-desc">Building (Z-A)</option>
                    <option value="code-asc">Code (A-Z)</option>
                    <option value="code-desc">Code (Z-A)</option>
                    <option value="lab-asc">Laboratory (A-Z)</option>
                    <option value="lab-desc">Laboratory (Z-A)</option>
                    <option value="room-asc">Room (A-Z)</option>
                    <option value="room-desc">Room (Z-A)</option>
                    <option value="type-asc">Type (A-Z)</option>
                    <option value="type-desc">Type (Z-A)</option>
                </select>
                <button class="btn-filter" id="addFacilityBtn"><i class="fas fa-plus"></i> Add Facility</button>
            </div>
        </div>
        <div class="table-container">
            <div class="table-wrapper">
                <table class="facility-data-table facility-data-table--facilities">
                    <colgroup>
                        <col class="facility-col-entry-num" style="width:1.75%" />
                        <col style="width:16%" />
                        <col style="width:10%" />
                        <col style="width:20%" />
                        <col style="width:20%" />
                        <col style="width:14%" />
                        <col style="width:18.25%" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th>Building</th>
                            <th>Code</th>
                            <th>Laboratory</th>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="facilityTableBody">
                        <tr>
                            <td colspan="7" class="loading-cell">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Office Modal -->
    <div class="modal" id="officeModal">
        <div class="modal-content">
            <span class="close-modal" id="closeOfficeModal">&times;</span>
            <h2 id="modalTitle">Add Office</h2>
            <form id="officeForm" enctype="multipart/form-data">
                <input type="hidden" name="office_id" id="office_id">
                <div class="form-group">
                    <label>Office Name</label>
                    <input type="text" name="office_name" id="office_name" placeholder="e.g. Chemistry" required>
                </div>
                <div class="form-group">
                    <label for="office_type">Office Type</label>
                    <select name="type" id="office_type" required>
                        <option value="">Select office type</option>
                        <option value="academic">Academic</option>
                        <option value="administrative">Administrative</option>
                        <option value="executive">Executive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="office_photo">Office Logo (Optional)</label>
                    <input type="file" name="office_photo" id="office_photo" accept="image/png,image/jpeg,image/gif,image/webp">
                    <small class="field-hint">Leave empty to keep no image. You can upload/update later.</small>
                </div>

                <button type="submit" class="btn-save">Save Office</button>
            </form>
        </div>
    </div>

    <!-- Facility Modal -->
    <div class="modal" id="facilityModal">
        <div class="modal-content">
            <span class="close-modal" id="closeFacilityModal">&times;</span>
            <h2 id="facilityModalTitle">Add Facility</h2>
            <form id="facilityForm">
                <input type="hidden" name="facility_id" id="facility_id">
                <input type="hidden" name="office_id" id="facility_office_id">
                <div class="form-group">
                    <label>Building</label>
                    <input type="text" name="building" id="facility_building" placeholder="e.g. Building A">
                </div>
                <div class="form-group">
                    <label>Code</label>
                    <input type="text" name="code" id="facility_code" placeholder="e.g. LAB-001" required>
                </div>
                <div class="form-group">
                    <label>Floor</label>
                    <input type="text" name="floor" id="facility_floor" placeholder="e.g. 2nd Floor">
                </div>
                <div class="form-group">
                    <label>Laboratory</label>
                    <input type="text" name="laboratory" id="facility_laboratory" placeholder="e.g. Lab 1">
                </div>
                <div class="form-group">
                    <label>Room</label>
                    <input type="text" name="room" id="facility_room" placeholder="e.g. Room 101">
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" id="facility_type" required>
                        <option value="">Select facility type</option>
                        <option value="Computer Lab">Computer Lab</option>
                        <option value="Laboratory">Laboratory</option>
                        <option value="Classroom">Classroom</option>
                        <option value="Office">Office</option>
                        <option value="Storage Room">Storage Room</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <button type="submit" class="btn-save">Save Facility</button>
            </form>
        </div>
    </div>

</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" style="display:none;">
    <i class="fas fa-bars"></i>
</button>

<div id="toastContainer"></div>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/facility_management.js"></script>

<?php require __DIR__ . '/partials/inventory_manager_sidebar_scripts.php'; ?>
</body>
</html>
