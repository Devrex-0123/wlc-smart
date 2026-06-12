<?php
session_start();
require_once __DIR__ . '/partials/session_access_guard.php';
require_once __DIR__ . '/../../app/classes/db.php';

$db = Database::connect();
$stmt = $db->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$roleLc = strtolower(trim((string)($user['role'] ?? '')));
if ($roleLc === 'dean') {
    header('Location: dean_dashboard.php');
    exit;
}
$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'User';
}
$initialSeed = $username !== '' ? $username : (string)($user['Email'] ?? 'U');
$initials = strtoupper(substr($initialSeed, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit Trail - WLC-SMART</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
  <link rel="stylesheet" href="../assets/css/loading.css">
  <link rel="stylesheet" href="../assets/css/audit_trail.css?v=wlc49">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php
if ($roleLc === 'comptroller') {
    $comptrollerActive = 'audit';
    require __DIR__ . '/partials/comptroller_sidebar.php';
} elseif (in_array($roleLc, ['president', 'president verifier', 'verifier president', 'president_verifier'], true)) {
    $pvActive = 'audit';
    require __DIR__ . '/partials/president_verifier_sidebar.php';
} elseif ($roleLc === 'gsd officer') {
    $gsdActive = 'audit';
    require __DIR__ . '/partials/gsd_sidebar.php';
} elseif ($roleLc === 'canvasser') {
    $cvActive = 'audit';
    require __DIR__ . '/partials/canvasser_sidebar.php';
} elseif (in_array($roleLc, ['employee', 'user', 'laboratory manager'], true)) {
    ?>
<aside class="sidebar" id="sidebar">
  <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
  <nav>
    <ul class="sidebar-nav">
      <li><a href="employee_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="audit_trail.php" class="active"><i class="fas fa-shield-alt"></i> Audit Trail</a></li>
    </ul>
  </nav>
  <div class="sidebar-footer">
    <div class="user-profile">
      <div class="user-avatar">
        <?php if (!empty($user['photo_url'])): ?>
          <img src="../<?php echo htmlspecialchars($user['photo_url']); ?>" alt="Profile Photo" class="user-avatar-img">
        <?php else: ?>
          <div class="user-avatar-initials"><?php echo htmlspecialchars($initials); ?></div>
        <?php endif; ?>
      </div>
      <div class="user-details">
        <h4><?php echo htmlspecialchars($username); ?></h4>
        <p><?php echo htmlspecialchars((string)($user['role'] ?? 'User')); ?></p>
      </div>
    </div>
  </div>
</aside>
    <?php
} else {
    ?>
<?php $imActivePage = 'audit_trail.php'; require __DIR__ . '/partials/inventory_manager_sidebar.php'; ?>
    <?php
}
?>

<main class="main-content audit-trail-container">
  <div class="module-page-header audit-trail-page-header">
    <div class="audit-trail-page-header__top">
      <div class="audit-trail-page-header__text">
        <h1 class="module-page-header__title">Audit Trail</h1>
        <p class="module-page-header__subtitle">Combined view of logged sessions and activity history.</p>
      </div>
      <div class="audit-trail-page-header__actions">
        <div class="search-container audit-trail-page-header__search">
          <i class="fas fa-search"></i>
          <input type="text" id="globalSearch" placeholder="Search" class="search-input" aria-label="Search audit trail">
        </div>
      </div>
    </div>
    <section class="dashboard-stats" aria-label="Audit summary">
      <article class="dashboard-stat-card dashboard-stat-card--assets">
        <div class="dashboard-stat-card__head">
          <span class="dashboard-stat-card__icon" aria-hidden="true"><i class="fas fa-chart-line"></i></span>
          <span class="dashboard-stat-card__label">Today's Activities</span>
        </div>
        <p class="dashboard-stat-card__value" id="todaysActivitiesCount">0</p>
        <p class="dashboard-stat-card__meta">Number of actions performed today</p>
      </article>

      <article class="dashboard-stat-card dashboard-stat-card--requests">
        <div class="dashboard-stat-card__head">
          <span class="dashboard-stat-card__icon" aria-hidden="true"><i class="fas fa-users"></i></span>
          <span class="dashboard-stat-card__label">Today's Active Users</span>
        </div>
        <p class="dashboard-stat-card__value" id="activeUsersCount">0</p>
        <p class="dashboard-stat-card__meta">Users who logged in today</p>
      </article>

      <article class="dashboard-stat-card dashboard-stat-card--failed">
        <div class="dashboard-stat-card__head">
          <span class="dashboard-stat-card__icon" aria-hidden="true"><i class="fas fa-shield-halved"></i></span>
          <span class="dashboard-stat-card__label">Failed Login Attempts</span>
        </div>
        <p class="dashboard-stat-card__value" id="failedLoginCount">0</p>
        <p class="dashboard-stat-card__meta">Possible unauthorized access attempts</p>
      </article>

      <article class="dashboard-stat-card dashboard-stat-card--total">
        <div class="dashboard-stat-card__head">
          <span class="dashboard-stat-card__icon" aria-hidden="true"><i class="fas fa-database"></i></span>
          <span class="dashboard-stat-card__label">Total Audit Records</span>
        </div>
        <p class="dashboard-stat-card__value" id="totalAuditRecordsCount">0</p>
        <p class="dashboard-stat-card__meta">All logged sessions and activities</p>
      </article>
    </section>
  </div>

  <div class="audit-trail-data-card">
    <div class="filter-section">
      <div class="filter-controls audit-trail-filter-bar">
          <div class="view-toggle-stack">
            <button type="button" id="showActivityBtn" class="view-toggle active"><i class="fas fa-list"></i> Activity History</button>
            <button type="button" id="showLoggedBtn" class="view-toggle"><i class="fas fa-history"></i> Logged History</button>
          </div>
          <div class="audit-trail-filter-bar__dates">
          <div class="date-filter-row">
            <label class="date-filter-label" for="dateFrom">From</label>
            <div class="date-input-wrap">
              <input type="date" id="dateFrom" class="date-filter-input">
              <i class="fas fa-calendar-alt date-input-icon" aria-hidden="true"></i>
            </div>
          </div>
          <div class="date-filter-row">
            <label class="date-filter-label" for="dateTo">To</label>
            <div class="date-input-wrap">
              <input type="date" id="dateTo" class="date-filter-input">
              <i class="fas fa-calendar-alt date-input-icon" aria-hidden="true"></i>
            </div>
          </div>
          <button type="button" id="clearDateBtn" class="clear-filter-btn"><i class="fas fa-rotate-right"></i> Clear Dates</button>
          </div>
      </div>
    </div>

    <div class="audit-trail-table-body">
        <div class="audit-trail-table-panel" id="loggedPanel" style="display:none;">
          <div class="table-container">
            <div class="table-wrapper">
              <table class="audit-trail-table audit-trail-table--logged">
            <thead>
              <tr>
                <th>#</th>
                <th>Log ID</th>
                <th>User Email</th>
                <th>Role</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Duration</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="loggedBody">
              <tr><td colspan="8" style="text-align:center;padding:36px;color:#64748b;">Loading logged history...</td></tr>
            </tbody>
              </table>
            </div>
          </div>
          <footer class="table-panel-footer" id="loggedPagination" aria-label="Logged history pages">
            <p class="table-panel-footer__info" id="loggedPageInfo">Showing 0 to 0 of 0 sessions</p>
            <div class="table-panel-footer__pagination">
              <button type="button" class="table-panel-footer__page-btn" id="prevLoggedBtn" disabled aria-label="Previous page">
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
              </button>
              <span class="table-panel-footer__page-num" id="loggedPageNum">1</span>
              <button type="button" class="table-panel-footer__page-btn" id="nextLoggedBtn" disabled aria-label="Next page">
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
              </button>
            </div>
          </footer>
        </div>

        <div class="audit-trail-table-panel" id="activityPanel">
          <div class="table-container">
            <div class="table-wrapper">
              <table class="audit-trail-table audit-trail-table--activity">
            <thead>
              <tr>
                <th>#</th>
                <th>Activity ID</th>
                <th>User</th>
                <th>Role</th>
                <th>Type</th>
                <th>Description</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody id="activityBody">
              <tr><td colspan="7" style="text-align:center;padding:36px;color:#64748b;">Loading activity history...</td></tr>
            </tbody>
              </table>
            </div>
          </div>
          <footer class="table-panel-footer" id="activityPagination" aria-label="Activity history pages">
            <p class="table-panel-footer__info" id="activityPageInfo">Showing 0 to 0 of 0 entries</p>
            <div class="table-panel-footer__pagination">
              <button type="button" class="table-panel-footer__page-btn" id="prevActivityBtn" disabled aria-label="Previous page">
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
              </button>
              <span class="table-panel-footer__page-num" id="activityPageNum">1</span>
              <button type="button" class="table-panel-footer__page-btn" id="nextActivityBtn" disabled aria-label="Next page">
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
              </button>
            </div>
          </footer>
        </div>
    </div>
  </div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" style="display:none;"><i class="fas fa-bars"></i></button>
<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/audit_trail.js?v=wlc14"></script>
</body>
</html>
