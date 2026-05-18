<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}
$next = $_GET['next'] ?? 'dashboard.php';
if (!is_string($next) || strpos($next, 'public/pages/') !== 0) {
    $next = 'public/pages/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Consent Required - WLC-SMART</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: Arial, sans-serif; background:#f8fafc; margin:0; }
    .wrap { max-width: 780px; margin: 48px auto; padding: 24px; background:#fff; border-radius:12px; box-shadow:0 10px 28px rgba(0,0,0,.08); }
    .title { color:#166534; margin-top:0; }
    .links a { color:#15803d; font-weight:600; text-decoration:none; margin-right:16px; }
    .notice { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; padding:10px 12px; border-radius:8px; margin:14px 0; }
    .actions { display:flex; gap:12px; margin-top:18px; }
    button { border:0; border-radius:8px; padding:10px 14px; cursor:pointer; font-weight:600; }
    .agree { background:#16a34a; color:#fff; }
    .logout { background:#e2e8f0; color:#0f172a; }
    #msg { margin-top:12px; min-height:20px; color:#991b1b; }
  </style>
</head>
<body>
  <div class="wrap">
    <h2 class="title">Consent Required Before Access</h2>
    <p>
      To continue using WLC-SMART, please review and accept the Privacy Notice and Terms & Conditions.
      This consent is stored in your account for compliance with RA 10173.
    </p>
    <div class="links">
      <a href="privacy_policy.php?return=consent&next=<?php echo urlencode($next); ?>">Privacy Notice</a>
      <a href="terms_conditions.php?return=consent&next=<?php echo urlencode($next); ?>">Terms & Conditions</a>
    </div>
    <div class="notice">
      Current consent version: <strong>v1.0</strong>
    </div>
    <div class="actions">
      <button id="agreeBtn" class="agree"><i class="fas fa-check"></i> I Agree</button>
      <button id="logoutBtn" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</button>
    </div>
    <div id="msg" aria-live="polite"></div>
  </div>

  <script>
    const msg = document.getElementById('msg');
    document.getElementById('agreeBtn').addEventListener('click', async () => {
      msg.textContent = 'Saving consent...';
      try {
        const res = await fetch('../../app/api/consent.php', {
          method: 'POST',
          credentials: 'include',
          body: new URLSearchParams({ consent_version: 'v1.0' })
        });
        const data = await res.json();
        if (!data.success) {
          msg.textContent = data.message || 'Unable to save consent.';
          return;
        }
        msg.style.color = '#166534';
        msg.textContent = 'Consent saved. Redirecting...';
        const nextUrl = <?php echo json_encode($next, JSON_UNESCAPED_SLASHES); ?>;
        const normalized = nextUrl.startsWith('public/pages/') ? `/CWIRMS/${nextUrl}` : '/CWIRMS/public/pages/dashboard.php';
        setTimeout(() => { window.location.href = normalized; }, 800);
      } catch (_) {
        msg.textContent = 'Network error while saving consent.';
      }
    });

    document.getElementById('logoutBtn').addEventListener('click', async () => {
      try {
        await fetch('../../app/api/logout.php', { method: 'POST', credentials: 'include' });
      } catch (_) {
        // Continue redirect even if API returns network error.
      }
      window.location.href = '/CWIRMS/index.php';
    });
  </script>
</body>
</html>

