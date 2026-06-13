<?php
session_start();
require_once __DIR__ . '/partials/session_access_guard.php';
require_once __DIR__ . '/../../app/classes/db.php';

$isDepartmentLogin = isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'department';

if (!$isDepartmentLogin) {
    header('Location: my_profile.php');
    exit;
}

$db = Database::connect();
$stmt = $db->prepare('SELECT * FROM departments WHERE department_id = ? LIMIT 1');
$stmt->execute([(int) $_SESSION['department_id']]);
$department = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$user = [
    'full_name' => $department['department_name'] ?? 'Department',
    'department_abbreviation' => $department['department_abbreviation'] ?? '',
    'Email' => $department['department_username'] ?? '',
    'role' => 'Department',
    'photo_url' => $department['department_photo_url'] ?? null,
];

$abbrev = strtoupper(trim((string) ($department['department_abbreviation'] ?? 'D')));
$initials = $abbrev !== '' ? substr($abbrev, 0, 1) : 'D';
$photoUrl = trim((string) ($department['department_photo_url'] ?? ''));
$deptName = trim((string) ($department['department_name'] ?? 'Department'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Department Profile - WLC-SMART</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc34">
  <link rel="stylesheet" href="../assets/css/loading.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/my_profile.css?v=wlc6">
  <style>
    #departmentProfilePage .profile-header-main {
      align-items: center;
      flex-wrap: nowrap;
      justify-content: space-between;
      min-height: 0;
      gap: 16px;
    }

    #departmentProfilePage .profile-header-media {
      flex: 1 1 auto;
      min-width: 0;
      margin-left: 12px;
      max-width: calc(100% - 13.5rem);
    }

    #departmentProfilePage .profile-header-details {
      flex: 1;
      min-width: 0;
      overflow: visible;
    }

    #departmentProfilePage .profile-header-name {
      white-space: normal;
      word-wrap: break-word;
      overflow-wrap: break-word;
      line-height: 1.35;
      max-width: 100%;
    }

    #departmentProfilePage .profile-header-privacy {
      flex: 0 0 auto;
      margin-left: auto;
      align-self: center;
      align-items: center;
      min-width: 10rem;
    }

    #departmentProfilePage .profile-footer-note {
      margin: 0;
      padding: 1rem 1.5rem 1.35rem;
      border-top: 1px solid #e2e8f0;
      color: #64748b;
      font-size: 0.85rem;
      line-height: 1.5;
      text-align: center;
    }

    @media (max-width: 768px) {
      #departmentProfilePage .profile-header-main {
        flex-direction: column;
        align-items: stretch;
        flex-wrap: wrap;
      }

      #departmentProfilePage .profile-header-media {
        max-width: 100%;
        margin-left: 0;
      }

      #departmentProfilePage .profile-header-privacy {
        margin-left: 0;
        align-self: center;
      }
    }
  </style>
</head>
<body id="departmentProfilePage"
      data-login-type="department"
      data-api-url="../../app/api/department_profile.php">

<?php
$deanActivePage = 'department_profile.php';
require __DIR__ . '/partials/dean_sidebar.php';
?>

  <main class="main-content">
    <div class="profile-wrap">
      <div class="profile-card profile-card--unified">
        <header class="profile-card-header">
          <div class="profile-header-main">
            <div class="profile-header-media">
              <div class="photo-preview" id="photoPreviewWrap">
                <img id="profilePhotoImg" alt="Department Photo" style="<?php echo $photoUrl !== '' ? '' : 'display:none;'; ?>" src="<?php echo $photoUrl !== '' ? '../' . htmlspecialchars($photoUrl) : ''; ?>">
                <span id="profilePhotoInitial" style="<?php echo $photoUrl !== '' ? 'display:none;' : ''; ?>"><?php echo htmlspecialchars($initials); ?></span>
              </div>
              <div class="profile-header-details">
                <p class="profile-header-name" id="profileHeaderName"><?php echo htmlspecialchars($deptName); ?></p>
                <p class="profile-header-hint">JPG, PNG, GIF or WEBP — max 2MB</p>
                <div class="photo-actions">
                  <input id="profilePhotoInput" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="profile-photo-input" hidden>
                  <button id="uploadPhotoBtn" type="button" class="profile-btn profile-btn-outline">
                    <i class="fas fa-upload" aria-hidden="true"></i>
                    Upload photo
                  </button>
                </div>
              </div>
            </div>
            <div class="profile-header-privacy">
              <h2 class="profile-section-title profile-section-title--header profile-privacy-title">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                Data privacy controls
              </h2>
              <div class="profile-header-privacy-actions">
                <a href="privacy_policy.php" class="profile-btn profile-btn-ghost">
                  <i class="fas fa-file-alt" aria-hidden="true"></i>
                  Privacy notice
                </a>
                <a href="terms_conditions.php" class="profile-btn profile-btn-ghost">
                  <i class="fas fa-file-contract" aria-hidden="true"></i>
                  Terms &amp; conditions
                </a>
              </div>
            </div>
          </div>
        </header>

        <section class="profile-section profile-section--info">
          <div class="profile-info-split">
            <div class="profile-info-card profile-info-card--details">
              <h2 class="profile-section-title">
                <i class="fas fa-building" aria-hidden="true"></i>
                Department information
              </h2>
              <div class="profile-grid profile-grid--fields">
                <div class="profile-field">
                  <label class="profile-label" for="department_abbreviation">Abbreviation</label>
                  <input id="department_abbreviation" class="profile-input profile-input--display profile-readonly" readonly value="<?php echo htmlspecialchars((string) ($department['department_abbreviation'] ?? '')); ?>">
                </div>
                <div class="profile-field">
                  <label class="profile-label" for="department_type">Department Type</label>
                  <input id="department_type" class="profile-input profile-input--display profile-readonly" readonly value="<?php echo htmlspecialchars((string) ($department['department_type'] ?? '')); ?>">
                </div>
                <div class="profile-field">
                  <label class="profile-label" for="department_username">Username</label>
                  <input id="department_username" class="profile-input profile-input--display profile-readonly" readonly value="<?php echo htmlspecialchars((string) ($department['department_username'] ?? '')); ?>">
                </div>
                <div class="profile-field">
                  <span class="profile-label">Account Status</span>
                  <span id="status_badge" class="badge badge-role">
                    <i class="fas fa-circle" aria-hidden="true"></i>
                    <span id="status_text"><?php echo htmlspecialchars((string) ($department['department_status'] ?? 'Active')); ?></span>
                  </span>
                </div>
              </div>
            </div>

            <div class="profile-info-card profile-info-card--password">
              <h2 class="profile-section-title">
                <i class="fas fa-lock" aria-hidden="true"></i>
                Change password
              </h2>
              <div class="profile-stack">
                <div class="profile-field">
                  <label class="profile-label" for="current_password">Current Password</label>
                  <div class="password-field-wrap">
                    <input id="current_password" class="profile-input" type="password" autocomplete="current-password">
                    <button type="button" class="password-toggle" data-target="current_password" aria-label="Show current password">
                      <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                  </div>
                </div>
                <div class="profile-field">
                  <label class="profile-label" for="new_password">New Password</label>
                  <div class="password-field-wrap">
                    <input id="new_password" class="profile-input" type="password" autocomplete="new-password" placeholder="e.g. SecureP@ssw0rd2025">
                    <button type="button" class="password-toggle" data-target="new_password" aria-label="Show new password">
                      <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                  </div>
                  <ul class="password-requirements">
                    <li id="pwd-length"><span class="req-icon" aria-hidden="true"></span> At least 8 characters</li>
                    <li id="pwd-uppercase"><span class="req-icon" aria-hidden="true"></span> One uppercase letter</li>
                    <li id="pwd-lowercase"><span class="req-icon" aria-hidden="true"></span> One lowercase letter</li>
                    <li id="pwd-number"><span class="req-icon" aria-hidden="true"></span> One number</li>
                    <li id="pwd-special"><span class="req-icon" aria-hidden="true"></span> One special char (@$!%*?&#-_. )</li>
                  </ul>
                  <div id="passwordStrength" class="password-strength">Weak password</div>
                </div>
                <div class="profile-field">
                  <label class="profile-label" for="confirm_password">Confirm New Password</label>
                  <div class="password-field-wrap">
                    <input id="confirm_password" class="profile-input" type="password" autocomplete="new-password">
                    <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show confirm password">
                      <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                  </div>
                </div>
              </div>
              <div id="passwordInlineError" class="password-inline-error" aria-live="polite"></div>
              <div class="profile-info-card-footer profile-info-card-footer--password">
                <div class="section-actions section-actions--full">
                  <button id="changePasswordBtn" type="button" class="profile-btn profile-btn-ghost profile-btn-block">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    Update password
                  </button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <p class="profile-footer-note">Department details are managed by the inventory administrator. You can update your photo and password below.</p>
      </div>
    </div>
  </main>

  <div id="toastContainer" class="profile-toast-container"></div>

  <div id="profileSuccessModal" class="profile-confirm-modal" aria-hidden="true">
    <div class="profile-confirm-backdrop" id="profileSuccessBackdrop"></div>
    <div class="profile-confirm-card" role="dialog" aria-modal="true" aria-labelledby="profileSuccessTitle" aria-describedby="profileSuccessMessage">
      <div class="profile-confirm-icon profile-confirm-icon--success" aria-hidden="true">
        <i class="fas fa-check"></i>
      </div>
      <h3 id="profileSuccessTitle" class="profile-confirm-title">Success</h3>
      <p id="profileSuccessMessage" class="profile-confirm-desc"></p>
      <div class="profile-confirm-actions profile-confirm-actions--single">
        <button type="button" class="profile-confirm-btn profile-confirm-btn-continue" id="profileSuccessOk">OK</button>
      </div>
    </div>
  </div>

  <div id="passwordConfirmModal" class="profile-confirm-modal" aria-hidden="true">
    <div class="profile-confirm-backdrop" id="passwordConfirmBackdrop"></div>
    <div class="profile-confirm-card" role="dialog" aria-modal="true" aria-labelledby="passwordConfirmTitle" aria-describedby="passwordConfirmDesc">
      <div class="profile-confirm-icon profile-confirm-icon--password" aria-hidden="true">
        <i class="fas fa-lock"></i>
      </div>
      <h3 id="passwordConfirmTitle" class="profile-confirm-title">Update password?</h3>
      <p id="passwordConfirmDesc" class="profile-confirm-desc">Are you sure you want to apply these updates?</p>
      <p class="profile-confirm-warning">Saving these changes will log you out of your current session. You will need to log back in using your new password.</p>
      <div class="profile-confirm-actions">
        <button type="button" class="profile-confirm-btn profile-confirm-btn-cancel" id="passwordConfirmCancel">Cancel</button>
        <button type="button" class="profile-confirm-btn profile-confirm-btn-continue" id="passwordConfirmOk">
          <i class="fas fa-check"></i> Continue
        </button>
      </div>
    </div>
  </div>

  <script src="../assets/js/logout.js?v=wlc2"></script>
  <script src="../assets/js/department_profile.js?v=wlc4"></script>
  <?php require __DIR__ . '/partials/dean_sidebar_scripts.php'; ?>
</body>
</html>
