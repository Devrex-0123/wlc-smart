<?php
session_start();
require_once __DIR__ . '/partials/session_access_guard.php';
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
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/loading.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/my_profile.css">
</head>
<body id="myProfilePage"
      data-api-url="../../app/api/my_profile.php">
  
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

  <main class="main-content">
    <div class="page-header">
      <h1>My Profile</h1>
      <p>Manage your personal account details and privacy-related controls.</p>
    </div>
    <div class="profile-wrap">
      <div class="profile-card">
        <h2>Profile Information</h2>
        <div class="photo-panel">
          <div class="photo-preview" id="photoPreviewWrap">
            <img id="profilePhotoImg" alt="Profile Photo" style="<?php echo $initialPhotoUrl !== '' ? '' : 'display:none;'; ?>" src="<?php echo $initialPhotoUrl !== '' ? '../' . htmlspecialchars($initialPhotoUrl) : ''; ?>">
            <span id="profilePhotoInitial" style="<?php echo $initialPhotoUrl !== '' ? 'display:none;' : ''; ?>"><?php echo htmlspecialchars($initials); ?></span>
          </div>
          <div class="photo-actions">
            <input id="profilePhotoInput" type="file" accept="image/*">
            <button id="uploadPhotoBtn" class="profile-btn muted">Upload Photo</button>
          </div>
        </div>
        <div class="profile-grid">
          <div><label class="profile-label">Full Name</label><input id="full_name" class="profile-input" type="text" value="<?php echo htmlspecialchars((string)($profileFallback['full_name'] ?? '')); ?>" disabled></div>
          <div><label class="profile-label">Contact Number</label><input id="contact_number" class="profile-input" type="text" value="<?php echo htmlspecialchars((string)($profileFallback['contact_number'] ?? '')); ?>" disabled></div>
          <div><label class="profile-label">Email</label><input id="email" class="profile-input profile-readonly" readonly value="<?php echo htmlspecialchars($email); ?>"></div>
          <div><label class="profile-label">Role</label><input id="role" class="profile-input profile-readonly" readonly value="<?php echo htmlspecialchars((string)($sidebarUser['role'] ?? '')); ?>"></div>
          <div><label class="profile-label">Office</label><input id="office" class="profile-input profile-readonly" readonly value="<?php echo htmlspecialchars((string)($profileFallback['office_name'] ?? '')); ?>"></div>
          <div><label>Consent Status</label><div><span id="consent_badge" class="badge accepted">Accepted</span></div></div>
          <div><label class="profile-label">Password Updated</label><input id="password_updated_at" class="profile-input profile-readonly" readonly value="<?php echo htmlspecialchars((string)($profileFallback['password_updated_at'] ?? '')); ?>"></div>
        </div>
        <div class="section-actions">
          <button id="editProfileBtn" class="profile-btn muted">Edit Profile</button>
          <button id="saveProfileBtn" class="profile-btn save is-hidden" disabled>Save Changes</button>
          <button id="cancelProfileBtn" class="profile-btn muted is-hidden">Cancel</button>
        </div>
        <div class="inline-help">For major account changes, please contact the system administrator.</div>
      </div>

      <div class="profile-card">
        <h2>Change Password</h2>
        <div class="profile-grid">
          <div><label class="profile-label">Current Password</label><input id="current_password" class="profile-input" type="password"></div>
          <div>
            <label class="profile-label">New Password</label>
            <input id="new_password" class="profile-input" type="password" placeholder="e.g. SecureP@ssw0rd2025">
            <ul class="password-requirements">
              <li id="pwd-length">At least 8 characters</li>
              <li id="pwd-uppercase">One uppercase letter</li>
              <li id="pwd-lowercase">One lowercase letter</li>
              <li id="pwd-number">One number</li>
              <li id="pwd-special">One special char (@$!%*?&#-_. )</li>
            </ul>
            <div id="passwordStrength" class="password-strength">Weak password</div>
          </div>
          <div><label class="profile-label">Confirm New Password</label><input id="confirm_password" class="profile-input" type="password"></div>
        </div>
        <div id="passwordInlineError" class="password-inline-error" aria-live="polite"></div>
        <div class="section-actions">
          <button id="changePasswordBtn" class="profile-btn save">Update Password</button>
        </div>
      </div>

      <div class="profile-card">
        <h2>Data Privacy Controls</h2>
        <div class="section-actions">
          <button type="button" id="openPrivacyNoticeBtn" class="profile-btn muted">View Privacy Notice</button>
          <button type="button" id="openTermsModalBtn" class="profile-btn muted">Terms &amp; Conditions</button>
        </div>
        <div class="view-my-data" id="viewMyData"></div>
      </div>
      <div id="msg" class="profile-msg" aria-live="polite"></div>
    </div>
  </main>

  <div id="privacyModal" class="profile-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="privacyModalTitle" hidden>
    <div class="profile-modal-panel" role="document">
      <div class="profile-modal-header">
        <h3 id="privacyModalTitle" class="profile-modal-title">Privacy Notice</h3>
        <button type="button" class="profile-modal-close" id="privacyModalClose" aria-label="Close Privacy Notice">&times;</button>
      </div>
      <div class="profile-modal-body" tabindex="-1">
        <?php require __DIR__ . '/partials/modal_privacy_notice_content.php'; ?>
      </div>
    </div>
  </div>

  <div id="termsModal" class="profile-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="termsModalTitle" hidden>
    <div class="profile-modal-panel" role="document">
      <div class="profile-modal-header">
        <h3 id="termsModalTitle" class="profile-modal-title">Terms &amp; Conditions</h3>
        <button type="button" class="profile-modal-close" id="termsModalClose" aria-label="Close Terms and Conditions">&times;</button>
      </div>
      <div class="profile-modal-body" tabindex="-1">
        <?php require __DIR__ . '/partials/modal_terms_conditions_content.php'; ?>
      </div>
    </div>
  </div>

  <script src="../assets/js/logout.js?v=wlc1"></script>
  <script src="../assets/js/my_profile.js"></script>
</body>
</html>

