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
<link rel="stylesheet" href="../assets/css/facility_management.css?v=wlc1">
<link rel="stylesheet" href="../assets/css/loading.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<?php $imActivePage = 'facility_management.php'; require __DIR__ . '/partials/inventory_manager_sidebar.php'; ?>

<main class="main-content facility-management-container">
    <div class="module-page-header facility-page-header">
        <div class="facility-page-header__top">
            <div class="facility-page-header__text">
                <h1 class="module-page-header__title">Facility Management</h1>
                <p class="module-page-header__subtitle">Manage facilities, monitor locations, and maintain organized campus spaces.</p>
            </div>
            <div class="facility-page-header__actions">
                <div class="search-container facility-page-header__search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="officeSearchInput" placeholder="Search" class="search-input" aria-label="Search offices">
                </div>
                <button class="btn-filter facility-page-header__add-btn" id="addOfficeBtn" type="button"><i class="fas fa-plus"></i> Add Department</button>
            </div>
        </div>
        <section class="facility-summary-stats" aria-label="Facility summary">
            <article class="facility-summary-card facility-summary-card--offices">
                <div class="facility-summary-card__head">
                    <span class="facility-summary-card__badge" aria-hidden="true"><i class="fas fa-building"></i></span>
                    <span class="facility-summary-card__label">Total Departments</span>
                </div>
                <p class="facility-summary-card__value" id="facilitySummaryOffices">0</p>
                <p class="facility-summary-card__meta" id="facilitySummaryOfficesMeta">—</p>
            </article>
            <article class="facility-summary-card facility-summary-card--labs">
                <div class="facility-summary-card__head">
                    <span class="facility-summary-card__badge" aria-hidden="true"><i class="fas fa-flask"></i></span>
                    <span class="facility-summary-card__label">Labs</span>
                </div>
                <p class="facility-summary-card__value" id="facilitySummaryLabs">0</p>
                <p class="facility-summary-card__meta">Across all offices</p>
            </article>
            <article class="facility-summary-card facility-summary-card--rooms">
                <div class="facility-summary-card__head">
                    <span class="facility-summary-card__badge" aria-hidden="true"><i class="fas fa-door-open"></i></span>
                    <span class="facility-summary-card__label">Rooms</span>
                </div>
                <p class="facility-summary-card__value" id="facilitySummaryRooms">0</p>
                <p class="facility-summary-card__meta">Across all offices</p>
            </article>
            <article class="facility-summary-card facility-summary-card--total">
                <div class="facility-summary-card__head">
                    <span class="facility-summary-card__badge" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                    <span class="facility-summary-card__label">Total facilities</span>
                </div>
                <p class="facility-summary-card__value" id="facilitySummaryTotal">0</p>
                <p class="facility-summary-card__meta">Laboratories and Rooms</p>
            </article>
        </section>
    </div>

    <div class="facility-card-swap" id="facilityCardSwap" data-view="list">
    <!-- Offices View -->
    <div id="officesView" class="facility-card-swap__panel facility-card-swap__panel--list">
        <div class="offices-list-card">
            <div class="filter-section offices-filter-bar">
                <nav class="office-type-tabs" id="officeTypeTabs" role="tablist" aria-label="Department type">
                    <button type="button" class="office-type-tab is-active" role="tab" id="officeTabAcademic" data-office-tab="academic" aria-selected="true" aria-controls="officeTablePanel">
                        <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                        <span>Academic Departments</span>
                    </button>
                    <button type="button" class="office-type-tab" role="tab" id="officeTabAdministrative" data-office-tab="administrative" aria-selected="false" aria-controls="officeTablePanel">
                        <i class="fas fa-building" aria-hidden="true"></i>
                        <span>Administrative Offices</span>
                    </button>
                    <button type="button" class="office-type-tab" role="tab" id="officeTabExecutive" data-office-tab="executive" aria-selected="false" aria-controls="officeTablePanel">
                        <i class="fas fa-crown" aria-hidden="true"></i>
                        <span>Executive Offices</span>
                    </button>
                </nav>
            </div>

            <div class="offices-list-card__body">
                <div id="officeGrid" class="office-grid hidden">
                    <div class="office-grid-loading">Loading Offices...</div>
                </div>

                <div id="officeTablePanel" class="table-container office-table-panel">
                    <div class="table-wrapper">
                        <table class="facility-data-table facility-data-table--offices">
                            <colgroup>
                                <col class="facility-col-entry-num" style="width:10%" />
                                <col style="width:24%" />
                                <col style="width:15%" />
                                <col style="width:13%" />
                                <col style="width:13%" />
                                <col style="width:12%" />
                                <col style="width:13%" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th>Department</th>
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

                <footer class="table-panel-footer" id="officePagination" aria-label="Department list pages">
                    <p class="table-panel-footer__info" id="officePageInfo">Showing 0 to 0 of 0 departments</p>
                    <div class="table-panel-footer__pagination">
                        <button type="button" id="officePrevPageBtn" class="table-panel-footer__page-btn" disabled aria-label="Previous page">
                            <i class="fas fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <span class="table-panel-footer__page-num" id="officePageNum">1</span>
                        <button type="button" id="officeNextPageBtn" class="table-panel-footer__page-btn" disabled aria-label="Next page">
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </div>

    <!-- Office Detail (card swap on View) -->
    <div id="facilitiesView" class="facility-card-swap__panel facility-card-swap__panel--detail office-detail-view" aria-hidden="true" hidden>
        <div class="office-detail-card">
            <div class="office-detail-header">
                <div class="office-detail-brand">
                    <div class="office-detail-logo" id="officeDetailLogoWrap">
                        <img id="officeDetailLogoImg" class="office-detail-logo-img" alt="" hidden>
                        <span id="officeDetailLogoInitials" class="office-detail-logo-initials">—</span>
                    </div>
                    <div class="office-detail-meta">
                        <h2 id="facilityViewTitle" class="office-detail-title">Department</h2>
                        <div class="office-detail-tags">
                            <span id="officeDetailTypeBadge"></span>
                        </div>
                    </div>
                </div>
                <button type="button" id="backToOfficesBtn" class="office-detail-back">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    <span>Back to offices</span>
                </button>
            </div>

            <div class="office-detail-stats" aria-label="Facility summary">
                <div class="office-detail-stat office-detail-stat--labs">
                    <span class="office-detail-stat__value" id="officeDetailLabs">0</span>
                    <span class="office-detail-stat__label">Laboratories</span>
                </div>
                <div class="office-detail-stat office-detail-stat--rooms">
                    <span class="office-detail-stat__value" id="officeDetailRooms">0</span>
                    <span class="office-detail-stat__label">Room</span>
                </div>
                <div class="office-detail-stat office-detail-stat--total">
                    <span class="office-detail-stat__value" id="officeDetailTotal">0</span>
                    <span class="office-detail-stat__label">Total</span>
                </div>
            </div>

            <div class="office-detail-facilities">
                <div class="office-detail-facilities-head">
                    <div class="office-detail-facilities-lead">
                        <h3 class="office-detail-facilities-title">Facilities</h3>
                        <p class="office-detail-facilities-subtitle">Rooms and Laboratories in this office</p>
                    </div>
                    <button type="button" class="office-detail-add-btn" id="addFacilityBtn">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add facility
                    </button>
                </div>
                <div class="table-wrapper office-detail-table-wrap">
                        <table class="facility-data-table facility-data-table--facilities">
                            <colgroup>
                                <col class="office-facilities-col-index" />
                                <col class="office-facilities-col-name" />
                                <col class="office-facilities-col-type" />
                                <col class="office-facilities-col-code" />
                                <col class="office-facilities-col-building" />
                                <col class="office-facilities-col-floor" />
                                <col class="office-facilities-col-action" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Code</th>
                                    <th>Building</th>
                                    <th>Floor</th>
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
                <footer class="table-panel-footer" id="facilityPagination" aria-label="Facility list pages">
                    <p class="table-panel-footer__info" id="facilityPageInfo">Showing 0 to 0 of 0 facilities</p>
                    <div class="table-panel-footer__pagination">
                        <button type="button" id="facilityPrevPageBtn" class="table-panel-footer__page-btn" disabled aria-label="Previous page">
                            <i class="fas fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <span class="table-panel-footer__page-num" id="facilityPageNum">1</span>
                        <button type="button" id="facilityNextPageBtn" class="table-panel-footer__page-btn" disabled aria-label="Next page">
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </div>
    </div>

    <!-- Office Modal -->
    <div class="modal" id="officeModal">
        <div class="modal-content office-modal-content">
            <div class="modal-header office-modal-header">
                <div class="office-modal-header-text">
                    <h2 id="modalTitle">Add office</h2>
                    <p id="officeModalSubtitle" class="office-modal-subtitle">Create an office, then add its rooms and labs.</p>
                </div>
                <button type="button" class="close-modal office-modal-close" id="closeOfficeModal" aria-label="Close modal">&times;</button>
            </div>
            <form id="officeForm" class="office-modal-form" enctype="multipart/form-data">
                <input type="hidden" name="office_id" id="office_id">
                <div class="form-group">
                    <label for="office_name">Office name <span class="required">*</span></label>
                    <input type="text" name="office_name" id="office_name" placeholder="e.g. Chemistry Office" required>
                </div>
                <div class="form-group">
                    <label for="office_type">Type <span class="required">*</span></label>
                    <select name="type" id="office_type" required>
                        <option value="">Select office type</option>
                        <option value="Academics">Academic</option>
                        <option value="Administrative">Administrative</option>
                        <option value="Executive">Executive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="office_photo" class="office-logo-label">Office logo <span class="optional-label">(optional)</span></label>
                    <div class="office-logo-upload">
                        <div class="office-logo-preview" id="officeLogoPreview">
                            <img id="officeLogoImage" alt="Office logo preview" hidden>
                            <div id="officeLogoPlaceholder" class="office-logo-placeholder" aria-hidden="true">
                                <i class="fas fa-image"></i>
                            </div>
                        </div>
                        <div class="office-logo-actions">
                            <label for="office_photo" class="btn-choose-file">Choose file</label>
                            <input type="file" name="office_photo" id="office_photo" accept="image/png,image/jpeg,image/gif,image/webp" class="file-input-hidden">
                            <small class="field-hint">Leave empty to keep no image — you can upload or update it later.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer office-modal-footer">
                    <button type="button" class="btn-cancel" id="cancelOfficeBtn">Cancel</button>
                    <button type="submit" class="btn-save" id="saveOfficeBtn">Save office</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Facility Modal -->
    <div class="modal" id="facilityModal">
        <div class="modal-content facility-modal-content">
            <div class="modal-header facility-modal-header">
                <div class="facility-modal-header-text">
                    <h2 id="facilityModalTitle">Add facility</h2>
                    <p id="facilityModalSubtitle" class="facility-modal-subtitle">Add a room or lab to this office.</p>
                </div>
                <button type="button" class="close-modal facility-modal-close" id="closeFacilityModal" aria-label="Close modal">&times;</button>
            </div>
            <form id="facilityForm" class="facility-modal-form">
                <input type="hidden" name="facility_id" id="facility_id">
                <input type="hidden" name="office_id" id="facility_office_id">
                <input type="hidden" name="laboratory" id="facility_laboratory">
                <input type="hidden" name="room" id="facility_room">
                <input type="hidden" name="type" id="facility_type">
                <div class="form-group">
                    <label for="facility_type_kind">Type <span class="required">*</span></label>
                    <select id="facility_type_kind" required>
                        <option value="">Select facility type</option>
                        <option value="Laboratory">Laboratory</option>
                        <option value="Room">Room</option>
                        <option value="__new__">+ Add new type...</option>
                    </select>
                </div>
                <div class="form-group facility-new-type-group hidden" id="facilityNewTypeGroup">
                    <label for="facility_new_type">New type name <span class="required">*</span></label>
                    <input type="text" id="facility_new_type" placeholder="e.g. Lecture Hall, AVR, Storage">
                    <small class="field-hint">This will be added to the type list for next time.</small>
                </div>
                <div class="form-group">
                    <label for="facility_name">Name <span class="required">*</span></label>
                    <input type="text" id="facility_name" placeholder="Select a type first" disabled>
                </div>
                <div class="facility-form-row">
                    <div class="form-group">
                        <label for="facility_building">Building</label>
                        <input type="text" name="building" id="facility_building" placeholder="e.g. Building A">
                    </div>
                    <div class="form-group">
                        <label for="facility_floor">Floor</label>
                        <input type="text" name="floor" id="facility_floor" placeholder="e.g. 2nd Floor">
                    </div>
                </div>
                <div class="form-group">
                    <label for="facility_code">Code</label>
                    <input type="text" name="code" id="facility_code" placeholder="e.g. LAB-001">
                </div>
                <div class="modal-footer facility-modal-footer">
                    <button type="button" class="btn-cancel" id="cancelFacilityBtn">Cancel</button>
                    <button type="submit" class="btn-save" id="saveFacilityBtn">Add facility</button>
                </div>
            </form>
        </div>
    </div>

</main>

<!-- Delete Confirmation Modal -->
<div class="fm-delete-modal" id="fmDeleteModal" aria-hidden="true">
    <div class="fm-delete-backdrop" id="fmDeleteBackdrop"></div>
    <div class="fm-delete-card" role="dialog" aria-modal="true" aria-labelledby="fmDeleteTitle" aria-describedby="fmDeleteDesc">
        <button type="button" class="fm-delete-close" id="fmDeleteClose" aria-label="Close">&times;</button>
        <div class="fm-delete-icon" aria-hidden="true">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3 id="fmDeleteTitle" class="fm-delete-title">Delete this department?</h3>
        <p id="fmDeleteDesc" class="fm-delete-desc">This will permanently remove the department from your list. This action cannot be undone.</p>
        <p class="fm-delete-name" id="fmDeleteName"></p>
        <div class="fm-delete-actions">
            <button type="button" class="fm-delete-btn fm-delete-btn-cancel" id="fmDeleteCancel">Cancel</button>
            <button type="button" class="fm-delete-btn fm-delete-btn-delete" id="fmDeleteConfirm">
                <i class="fas fa-trash-alt"></i> <span id="fmDeleteConfirmLabel">Delete</span>
            </button>
        </div>
    </div>
</div>

<!-- Success Confirmation Modal -->
<div class="fm-success-modal" id="fmSuccessModal" aria-hidden="true">
    <div class="fm-success-backdrop" id="fmSuccessBackdrop"></div>
    <div class="fm-success-card" role="dialog" aria-modal="true" aria-labelledby="fmSuccessTitle" aria-describedby="fmSuccessMessage">
        <div class="fm-success-icon" aria-hidden="true">
            <i class="fas fa-check"></i>
        </div>
        <h3 id="fmSuccessTitle" class="fm-success-title">Deleted</h3>
        <p id="fmSuccessMessage" class="fm-success-desc"></p>
        <div class="fm-success-actions">
            <button type="button" class="fm-success-btn" id="fmSuccessOk">OK</button>
        </div>
    </div>
</div>

<button class="mobile-menu-btn" id="mobileMenuBtn" style="display:none;">
    <i class="fas fa-bars"></i>
</button>

<div id="toastContainer"></div>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/facility_management.js"></script>

<?php require __DIR__ . '/partials/inventory_manager_sidebar_scripts.php'; ?>
</body>
</html>
