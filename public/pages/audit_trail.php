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
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/loading.css">
  <link rel="stylesheet" href="../assets/css/audit_trail.css">
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
<aside class="sidebar" id="sidebar">
  <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
  <nav>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
      <li><a href="requisition_management.php" class="internal-link"><i class="fas fa-file-signature"></i> <span>Requisition Management</span></a></li>
      <li><a href="requisition_status.php" class="internal-link"><i class="fas fa-bars-progress"></i> <span>Status</span></a></li>
      <li><a href="audit_trail.php" class="internal-link active"><i class="fas fa-shield-alt"></i> <span>Audit Trail</span></a></li>
      <li><a href="my_profile.php" class="internal-link"><i class="fas fa-user"></i><span>My Profile</span></a></li>
      <li><a href="account_management.php" class="internal-link"><i class="fas fa-users-cog"></i><span>Account Management</span></a></li>
      <li><a href="facility_management.php"><i class="fas fa-building"></i> Facility Management</a></li>
      <li><a href="item_management.php"><i class="fas fa-box"></i> Item Management</a></li>
      <li><a href="inventory_management.php"><i class="fas fa-cubes"></i> Inventory Management</a></li>
      <li><a href="supplier_management.php"><i class="fas fa-truck"></i> Supplier Management</a></li>
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
}
?>

<main class="main-content audit-trail-container">
  <div class="page-header">
    <h1>Audit Trail</h1>
    <p>Combined view of logged sessions and activity history with filters.</p>
  </div>

  <div class="stats-row">
    <div class="stat-card">
      <h4>Total Logged Records</h4>
      <div class="stat-value" id="loggedCount">0</div>
    </div>
    <div class="stat-card">
      <h4>Total Activity Records</h4>
      <div class="stat-value" id="activityCount">0</div>
    </div>
    <div class="stat-card">
      <h4>Total Combined</h4>
      <div class="stat-value" id="combinedCount">0</div>
    </div>
  </div>

  <div class="filter-section">
    <div class="filter-controls">
      <button type="button" id="showLoggedBtn" class="view-toggle active"><i class="fas fa-history"></i> Logged History</button>
      <button type="button" id="showActivityBtn" class="view-toggle"><i class="fas fa-list"></i> Activity History</button>
      <input type="text" id="globalSearch" placeholder="Search current view...">
      <label class="action-filter-label">Action
        <select id="actionTypeFilter">
          <option value="all">All Actions</option>
          <option value="add">Add</option>
          <option value="edit">Edit</option>
          <option value="update">Update</option>
          <option value="delete">Delete</option>
          <option value="login">Login</option>
          <option value="logout">Logout</option>
          <option value="approve">Approve</option>
          <option value="reject">Reject</option>
          <option value="other">Other</option>
        </select>
      </label>
      <label class="date-filter-label">From <input type="date" id="dateFrom"></label>
      <label class="date-filter-label">To <input type="date" id="dateTo"></label>
      <button type="button" id="clearDateBtn" class="clear-filter-btn">Clear Dates</button>
    </div>
  </div>

  <div class="table-container" id="loggedPanel">
    <div class="table-header">
      <h2><i class="fas fa-history"></i> Logged History</h2>
    </div>
    <div class="table-wrapper">
      <table>
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
    <div class="pagination-controls">
      <button class="pagination-btn" id="prevLoggedBtn" disabled><i class="fas fa-chevron-left"></i> Previous</button>
      <span id="loggedPageInfo" class="page-info">Page 1</span>
      <button class="pagination-btn" id="nextLoggedBtn">Next <i class="fas fa-chevron-right"></i></button>
    </div>
  </div>

  <div class="table-container" id="activityPanel" style="display:none;">
    <div class="table-header">
      <h2><i class="fas fa-list"></i> Activity History</h2>
    </div>
    <div class="table-wrapper">
      <table>
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
    <div class="pagination-controls">
      <button class="pagination-btn" id="prevActivityBtn" disabled><i class="fas fa-chevron-left"></i> Previous</button>
      <span id="activityPageInfo" class="page-info">Page 1</span>
      <button class="pagination-btn" id="nextActivityBtn">Next <i class="fas fa-chevron-right"></i></button>
    </div>
  </div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" style="display:none;"><i class="fas fa-bars"></i></button>
<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/audit_trail.js"></script>
</body>
</html>
