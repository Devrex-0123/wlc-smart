<?php
session_start();
require_once __DIR__ . '/partials/session_access_guard.php';

if (isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'department') {
    header('Location: department_profile.php');
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';
require_once __DIR__ . '/../../app/view_models/my_profile_page.php';


$db = Database::connect();
extract(
    my_profile_page_view_model($db, (int)$_SESSION['user_id']),
    EXTR_SKIP
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile - WLC-SMART</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
  <link rel="stylesheet" href="../assets/css/loading.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/my_profile.css?v=wlc6">
</head>
<body id="myProfilePage"
      data-api-url="../../app/api/my_profile.php">
  
  <?php
  $roleLc = strtolower(trim((string)($sidebarUser['role'] ?? '')));
  if (in_array($roleLc, ['inventory manager', 'inventory_manager'], true)) {
      $imActivePage = 'my_profile.php';
      require __DIR__ . '/partials/inventory_manager_sidebar.php';
  } else {
      ?>
  <aside class="sidebar" id="sidebar">
      <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
      <nav>
          <ul class="sidebar-nav">
              <?php foreach ($profileNavItems as $item): ?>
                  <li>
                      <a href="<?php echo htmlspecialchars($item['href']); ?>" class="<?php echo !empty($item['active']) ? 'active' : ''; ?>">
                          <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                          <span><?php echo htmlspecialchars($item['label']); ?></span>
                      </a>
                  </li>
              <?php endforeach; ?>
          </ul>
      </nav>
      <div class="sidebar-footer">
          <div class="user-profile">
              <div class="user-avatar">
                  <?php if (!empty($sidebarUser['photo_url'])): ?>
                      <img src="../<?php echo htmlspecialchars($sidebarUser['photo_url']); ?>" alt="Profile Photo" class="user-avatar-img">
                  <?php else: ?>
                      <div class="user-avatar-initials"><?php echo htmlspecialchars($initials); ?></div>
                  <?php endif; ?>
              </div>
              <div class="user-details">
                  <h4><?php echo htmlspecialchars($displayName); ?></h4>
                  <p><?php echo htmlspecialchars((string)($sidebarUser['role'] ?? 'User')); ?></p>
              </div>
          </div>
      </div>
  </aside>
      <?php
  }
  ?>

  <main class="main-content">
    <div class="profile-wrap">
      <?php
      $headerInitials = $initials;
      $nameParts = preg_split('/\s+/', trim($displayName), -1, PREG_SPLIT_NO_EMPTY);
      if (is_array($nameParts) && count($nameParts) >= 2) {
          $headerInitials = strtoupper(substr((string)$nameParts[0], 0, 1) . substr((string)$nameParts[count($nameParts) - 1], 0, 1));
      }
      ?>
      <div class="profile-card profile-card--unified">
        <header class="profile-card-header">
          <div class="profile-header-main">
            <div class="profile-header-media">
              <div class="photo-preview" id="photoPreviewWrap">
                <img id="profilePhotoImg" alt="Profile Photo" style="<?php echo $initialPhotoUrl !== '' ? '' : 'display:none;'; ?>" src="<?php echo $initialPhotoUrl !== '' ? '../' . htmlspecialchars($initialPhotoUrl) : ''; ?>">
                <span id="profilePhotoInitial" style="<?php echo $initialPhotoUrl !== '' ? 'display:none;' : ''; ?>"><?php echo htmlspecialchars($headerInitials); ?></span>
              </div>
              <div class="profile-header-details">
                <p class="profile-header-label">Profile Photo</p>
                <p class="profile-header-name" id="profileHeaderName"><?php echo htmlspecialchars($displayName); ?></p>
                <p class="profile-header-hint">JPG, PNG or GIF — max 2MB</p>
                <div class="photo-actions">
                  <input id="profilePhotoInput" type="file" accept="image/jpeg,image/png,image/gif" class="profile-photo-input" hidden>
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
                <div class="profile-grid profile-grid--fields">
                  <div class="profile-field">
                    <label class="profile-label" for="full_name">Full Name</label>
                    <input id="full_name" class="profile-input profile-input--display" type="text" value="<?php echo htmlspecialchars((string)($profileFallback['full_name'] ?? '')); ?>" disabled>
                  </div>
                  <div class="profile-field">
                    <label class="profile-label" for="contact_number">Contact Number</label>
                    <input id="contact_number" class="profile-input profile-input--display" type="tel" inputmode="numeric" maxlength="11" value="<?php echo htmlspecialchars((string)($profileFallback['contact_number'] ?? '')); ?>" disabled>
                  </div>
                  <div class="profile-field">
                    <label class="profile-label" for="email">Email</label>
                    <input id="email" class="profile-input profile-input--display" type="email" value="<?php echo htmlspecialchars($email); ?>" disabled placeholder="name@domain.com">
                  </div>
                  <div class="profile-field">
                    <span class="profile-label">Role</span>
                    <span class="badge badge-role" id="role_badge">
                      <i class="fas fa-user-shield" aria-hidden="true"></i>
                      <span id="role_text"><?php echo htmlspecialchars((string)($sidebarUser['role'] ?? '')); ?></span>
                    </span>
                    <input id="role" type="hidden" value="<?php echo htmlspecialchars((string)($sidebarUser['role'] ?? '')); ?>">
                  </div>
                  <div class="profile-field">
                    <label class="profile-label" for="office">Office</label>
                    <input id="office" class="profile-input profile-input--display profile-readonly" readonly value="<?php echo htmlspecialchars((string)($profileFallback['office_name'] ?? '')); ?>">
                  </div>
                  <div class="profile-field">
                    <span class="profile-label">Consent Status</span>
                    <span id="consent_badge" class="badge badge-consent accepted">
                      <i class="fas fa-check" aria-hidden="true"></i>
                      Accepted
                    </span>
                  </div>
                  <div class="profile-field profile-field--full">
                    <label class="profile-label" for="password_updated_at">Password Updated</label>
                    <input id="password_updated_at" class="profile-input profile-input--display profile-readonly" readonly value="<?php echo htmlspecialchars((string)($profileFallback['password_updated_at'] ?? '')); ?>">
                  </div>
                </div>
                <div class="profile-info-card-footer">
                  <div class="section-actions section-actions--full">
                    <button id="editProfileBtn" type="button" class="profile-btn profile-btn-ghost profile-btn-block">
                      <i class="fas fa-pen" aria-hidden="true"></i>
                      Edit profile
                    </button>
                    <button id="saveProfileBtn" type="button" class="profile-btn profile-btn-ghost profile-btn-block is-hidden" disabled>
                      <i class="fas fa-check" aria-hidden="true"></i>
                      Save changes
                    </button>
                    <button id="cancelProfileBtn" type="button" class="profile-btn profile-btn-ghost profile-btn-block is-hidden">
                      <i class="fas fa-times" aria-hidden="true"></i>
                      Cancel
                    </button>
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
      </div>
    </div>
  </main>

  <div id="toastContainer" class="profile-toast-container"></div>

  <!-- Success popup -->
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

  <!-- Save profile confirmation -->
  <div id="profileConfirmModal" class="profile-confirm-modal" aria-hidden="true">
    <div class="profile-confirm-backdrop" id="profileConfirmBackdrop"></div>
    <div class="profile-confirm-card" role="dialog" aria-modal="true" aria-labelledby="profileConfirmTitle" aria-describedby="profileConfirmDesc">
      <div class="profile-confirm-icon profile-confirm-icon--save" aria-hidden="true">
        <i class="fas fa-save"></i>
      </div>
      <h3 id="profileConfirmTitle" class="profile-confirm-title">Save profile changes?</h3>
      <p id="profileConfirmDesc" class="profile-confirm-desc">Are you sure you want to apply these updates?</p>
      <div class="profile-confirm-actions">
        <button type="button" class="profile-confirm-btn profile-confirm-btn-cancel" id="profileConfirmCancel">Cancel</button>
        <button type="button" class="profile-confirm-btn profile-confirm-btn-continue" id="profileConfirmOk">
          <i class="fas fa-check"></i> Continue
        </button>
      </div>
    </div>
  </div>

  <!-- Update password confirmation -->
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

  <script src="../assets/js/logout.js?v=wlc1"></script>
  <script src="../assets/js/my_profile.js?v=wlc6"></script>
  <?php if (in_array($roleLc, ['inventory manager', 'inventory_manager'], true)) {
      require __DIR__ . '/partials/inventory_manager_sidebar_scripts.php';
  } ?>
</body>
</html>

