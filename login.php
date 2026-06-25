<?php
// Login page guard: an authenticated user must never see the login screen.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$isLoggedIn = !empty($_SESSION['user_id'])
    || (isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'department' && !empty($_SESSION['department_id']));

if ($isLoggedIn) {
    $dashboardUrl = $_SESSION['dashboard_url'] ?? 'public/pages/dashboard.php';
    header("Location: " . $dashboardUrl);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In – WLC-SMART</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="public/assets/css/loading.css">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #0a3d24;
      color: #0f1f17;
      overflow-x: hidden;
      min-height: 100vh;
    }

    @keyframes float1  { 0%,100%{transform:translate(0,0) scale(1);}       50%{transform:translate(30px,-20px) scale(1.05);} }
    @keyframes float2  { 0%,100%{transform:translate(0,0) scale(1);}       50%{transform:translate(-25px,25px) scale(1.08);} }
    @keyframes auroraA { 0%,100%{transform:translate(0,0) scale(1);opacity:0.55;} 50%{transform:translate(60px,40px) scale(1.18);opacity:0.8;} }
    @keyframes auroraB { 0%,100%{transform:translate(0,0) scale(1.1);opacity:0.45;} 50%{transform:translate(-50px,-30px) scale(0.95);opacity:0.7;} }
    @keyframes auroraC { 0%,100%{transform:translate(0,0) scale(1);opacity:0.4;} 50%{transform:translate(30px,-50px) scale(1.25);opacity:0.65;} }
    @keyframes spinSlow { from{transform:rotate(0deg);}   to{transform:rotate(360deg);} }
    @keyframes spinRev  { from{transform:rotate(360deg);} to{transform:rotate(0deg);} }
    @keyframes twinkle  { 0%,100%{opacity:0.15;transform:scale(0.8);} 50%{opacity:0.7;transform:scale(1.2);} }

    .lp-wrap {
      min-height: 100vh;
      width: 100%;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 24px;
      background: linear-gradient(135deg, #0f6b40 0%, #0a4e2f 45%, #063a23 100%);
    }

    /* ---- Background layers ---- */
    .lp-bg { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }

    .lp-orb-a { position:absolute; width:560px; height:560px; border-radius:50%; background:radial-gradient(circle,rgba(120,245,175,0.55),rgba(120,245,175,0) 70%); top:-180px; right:-120px; filter:blur(60px); animation:auroraA 16s ease-in-out infinite; }
    .lp-orb-b { position:absolute; width:480px; height:480px; border-radius:50%; background:radial-gradient(circle,rgba(34,170,100,0.6),rgba(34,170,100,0) 70%); bottom:-200px; left:-140px; filter:blur(70px); animation:auroraB 20s ease-in-out infinite; }
    .lp-orb-c { position:absolute; width:340px; height:340px; border-radius:50%; background:radial-gradient(circle,rgba(180,255,210,0.4),rgba(180,255,210,0) 70%); top:40%; left:30%; filter:blur(80px); animation:auroraC 18s ease-in-out infinite; }

    .lp-shape-a { position:absolute; width:420px; height:420px; border-radius:47% 53% 62% 38% / 42% 56% 44% 58%; background:linear-gradient(135deg,rgba(150,250,190,0.10),rgba(31,170,90,0.02)); top:-120px; left:-80px; animation:float1 14s ease-in-out infinite; filter:blur(1px); }
    .lp-shape-b { position:absolute; width:360px; height:360px; border-radius:58% 42% 38% 62% / 55% 48% 52% 45%; background:linear-gradient(135deg,rgba(150,250,190,0.08),rgba(31,170,90,0.02)); bottom:-120px; right:-80px; animation:float2 18s ease-in-out infinite; filter:blur(1px); }

    .lp-rings-a { position:absolute; top:8%; right:10%; width:260px; height:260px; animation:spinSlow 60s linear infinite; }
    .lp-rings-a span { position:absolute; border-radius:50%; }
    .lp-rings-a span:nth-child(1) { inset:0;    border:1px solid rgba(255,255,255,0.10); }
    .lp-rings-a span:nth-child(2) { inset:34px; border:1px dashed rgba(255,255,255,0.10); }
    .lp-rings-a span:nth-child(3) { inset:80px; border:1px solid rgba(255,255,255,0.08); }

    .lp-rings-b { position:absolute; bottom:6%; left:6%; width:180px; height:180px; animation:spinRev 80s linear infinite; }
    .lp-rings-b span { position:absolute; border-radius:50%; }
    .lp-rings-b span:nth-child(1) { inset:0;    border:1px dashed rgba(255,255,255,0.09); }
    .lp-rings-b span:nth-child(2) { inset:44px; border:1px solid rgba(255,255,255,0.07); }

    .lp-star { position:absolute; border-radius:50%; background:rgba(180,255,210,0.9); }
    .lp-star:nth-child(1) { width:6px; height:6px; top:22%; left:20%; animation:twinkle 4.0s ease-in-out infinite; }
    .lp-star:nth-child(2) { width:4px; height:4px; top:70%; left:60%; animation:twinkle 5.5s ease-in-out infinite 1.0s; }
    .lp-star:nth-child(3) { width:5px; height:5px; top:35%; left:78%; animation:twinkle 4.8s ease-in-out infinite 0.5s; }
    .lp-star:nth-child(4) { width:4px; height:4px; top:85%; left:25%; animation:twinkle 6.0s ease-in-out infinite 2.0s; }

    .lp-dots { position:absolute; inset:0; background-image:radial-gradient(rgba(255,255,255,0.045) 1px,transparent 1px); background-size:26px 26px; }

    /* ---- Card ---- */
    .lp-card {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 980px;
      display: grid;
      grid-template-columns: 0.85fr 1.15fr;
      background: rgba(255,255,255,0.10);
      backdrop-filter: blur(22px);
      -webkit-backdrop-filter: blur(22px);
      border: 1px solid rgba(255,255,255,0.22);
      border-radius: 28px;
      overflow: hidden;
      box-shadow: 0 40px 90px -30px rgba(0,0,0,0.6);
    }

    .lp-accent-bar {
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 6px;
      background: linear-gradient(90deg, #f5c518, #ffd84d, #f5c518);
      z-index: 5;
    }

    /* ---- Left brand panel ---- */
    .lp-brand {
      padding: 56px 40px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
      text-align: center;
      background: linear-gradient(180deg, rgba(7,53,31,0.55), rgba(7,53,31,0.25));
      color: #ffffff;
      position: relative;
    }
    .lp-brand-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .lp-seal-wrap {
      width: 150px;
      height: 150px;
      background: #ffffff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 20px 44px -14px rgba(0,0,0,0.5);
      margin-bottom: 28px;
      padding: 8px;
      overflow: hidden;
    }
    .lp-seal-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      border-radius: 50%;
      transform: scale(1.38);
    }
    .lp-brand-title {
      font-family: 'Sora', sans-serif;
      font-weight: 800;
      font-size: 34px;
      line-height: 1.05;
      letter-spacing: 0.5px;
    }
    .lp-brand-footer { width: 100%; }
    .lp-brand-rule  { height:1px; background:rgba(255,255,255,0.22); margin-bottom:16px; }
    .lp-brand-school { font-size:14px; font-weight:700; color:#ffffff; }

    /* ---- Right form panel ---- */
    .lp-form-panel {
      background: #ffffff;
      padding: 52px 50px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .lp-form-head { margin-bottom: 28px; }

    .lp-eyebrow-badge {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: #e9f9f0;
      color: #0c5e35;
      font-weight: 600;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      padding: 6px 12px;
      border-radius: 999px;
      margin-bottom: 18px;
    }
    .lp-eyebrow-dot { width:7px; height:7px; border-radius:50%; background:#1faa5a; }

    .lp-title {
      font-family: 'Sora', sans-serif;
      font-weight: 700;
      font-size: 30px;
      color: #0f1f17;
      letter-spacing: -0.5px;
      margin-bottom: 6px;
    }
    .lp-subtitle { font-size:14px; color:#6b7770; }

    /* Alert */
    .lp-alert {
      display: none;
      margin-bottom: 16px;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 13px;
      text-align: center;
    }
    .lp-alert.show    { display: block; }
    .lp-alert.error   { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
    .lp-alert.success { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
    .lp-alert.lockout { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }

    /* Fields */
    .lp-field  { margin-bottom: 18px; }
    .lp-label  { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.7px; color:#6b7770; margin-bottom:8px; }
    .lp-input-wrap { position: relative; }
    .lp-input-icon { position:absolute; left:15px; top:50%; transform:translateY(-50%); color:#9aa6a0; font-size:15px; }
    .lp-input {
      width: 100%;
      padding: 14px 16px 14px 44px;
      border: 1.5px solid #e4e9e6;
      border-radius: 12px;
      font-size: 14px;
      background: #f6f9f7;
      color: #0f1f17;
      outline: none;
      transition: all 0.2s;
      font-family: inherit;
    }
    .lp-input:focus { border-color:#1faa5a; background:#ffffff; box-shadow:0 0 0 4px rgba(31,170,90,0.12); }
    .lp-input::placeholder { color:#9aa6a0; }
    .lp-pw-input { padding-right: 48px; }

    .lp-toggle-pw {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: #9aa6a0;
      padding: 6px;
      display: flex;
      transition: opacity 0.2s;
    }
    .lp-toggle-pw:hover { opacity: 0.75; }

    /* Consent */
    .lp-consent-box {
      margin-bottom: 0;
      background: #f6f9f7;
      border: 1px solid #e4e9e6;
      border-radius: 12px;
      padding: 13px 14px;
    }
    .lp-consent-label {
      display: flex;
      gap: 10px;
      align-items: flex-start;
      cursor: pointer;
    }
    .lp-consent-label input[type="checkbox"] {
      margin-top: 2px;
      width: 16px;
      height: 16px;
      accent-color: #1faa5a;
      cursor: pointer;
      flex-shrink: 0;
    }
    .lp-consent-label span { font-size:12px; color:#374151; line-height:1.5; }
    .lp-consent-label.is-disabled { cursor: not-allowed; }
    .lp-consent-label input[type="checkbox"]:disabled { cursor:not-allowed; opacity:0.5; }

    .consent-link { font-weight:600; text-decoration:none; transition:color 0.2s; }
    .consent-link.link-attention { color:#1faa5a; }
    .consent-link.link-attention:hover { text-decoration:underline; }
    .consent-link.link-visited { color:#9ca3af; }
    .consent-link.link-visited:hover { text-decoration:underline; }

    .consent-hint { margin-top:6px; margin-left:26px; font-size:12px; color:#c2410c; }
    .consent-hint:empty { margin-top:0; }
    .consent-hint:not(:empty)::before { content:"\f05a"; font-family:"Font Awesome 6 Free"; font-weight:900; margin-right:5px; }

    /* Submit */
    .lp-submit-btn {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, #1faa5a, #0c7a42);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 15px;
      font-weight: 600;
      font-family: 'Sora', sans-serif;
      cursor: pointer;
      margin-top: 18px;
      box-shadow: 0 10px 30px -10px rgba(31,170,90,0.5);
      transition: all 0.25s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 9px;
    }
    .lp-submit-btn:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 16px 40px -8px rgba(31,170,90,0.55); }
    .lp-submit-btn:disabled { background:#f3f4f6; color:#9ca3af; box-shadow:none; cursor:not-allowed; }

    /* Footer */
    .lp-form-footer {
      text-align: center;
      margin-top: 22px;
      padding-top: 20px;
      border-top: 1px solid #eef2f0;
      font-size: 13px;
      color: #6b7770;
    }
    .lp-form-footer a { color:#1faa5a; font-weight:600; text-decoration:none; }
    .lp-form-footer a:hover { text-decoration:underline; }

    /* Responsive */
    @media (max-width: 768px) {
      .lp-card { grid-template-columns: 1fr; }
      .lp-brand { display: none; }
      .lp-form-panel { padding: 40px 28px; }
    }
    @media (max-width: 480px) {
      .lp-form-panel { padding: 32px 20px; }
    }
  </style>
  <script>
    window.addEventListener('pageshow', function(e) {
      if (e.persisted) window.location.reload();
    });
  </script>
</head>
<body>

<div class="lp-wrap">

  <!-- Animated background -->
  <div class="lp-bg">
    <div style="position:absolute;inset:0;background:radial-gradient(60% 55% at 78% 18%,rgba(108,235,160,0.32) 0%,rgba(108,235,160,0) 60%),radial-gradient(55% 50% at 12% 82%,rgba(46,196,118,0.30) 0%,rgba(46,196,118,0) 62%),radial-gradient(70% 70% at 50% 50%,rgba(8,58,34,0.0) 0%,rgba(5,40,24,0.55) 100%);"></div>
    <div class="lp-orb-a"></div>
    <div class="lp-orb-b"></div>
    <div class="lp-orb-c"></div>
    <div class="lp-shape-a"></div>
    <div class="lp-shape-b"></div>
    <div class="lp-rings-a"><span></span><span></span><span></span></div>
    <div class="lp-rings-b"><span></span><span></span></div>
    <div class="lp-star"></div>
    <div class="lp-star"></div>
    <div class="lp-star"></div>
    <div class="lp-star"></div>
    <div class="lp-dots"></div>
  </div>

  <!-- Login card -->
  <div class="lp-card">
    <div class="lp-accent-bar"></div>

    <!-- Left: brand panel -->
    <div class="lp-brand">
      <div class="lp-brand-main">
        <div class="lp-seal-wrap">
          <img src="public/assets/images/wlc-smart-logo.png" alt="WLC-SMART seal">
        </div>
        <div class="lp-brand-title">WLC-SMART</div>
      </div>
      <div class="lp-brand-footer">
        <div class="lp-brand-rule"></div>
        <p class="lp-brand-school">Western Leyte College</p>
      </div>
    </div>

    <!-- Right: form panel -->
    <div class="lp-form-panel">
      <div class="lp-form-head">
        <div class="lp-eyebrow-badge">
          <span class="lp-eyebrow-dot"></span> Sign in
        </div>
        <h1 class="lp-title">Welcome back</h1>
        <p class="lp-subtitle">Enter your credentials to access the portal.</p>
      </div>

      <div id="modalAlert" class="lp-alert"></div>

      <form id="loginForm">
        <!-- Username -->
        <div class="lp-field">
          <label for="email" class="lp-label">Username</label>
          <div class="lp-input-wrap">
            <i class="fa-solid fa-user lp-input-icon"></i>
            <input class="lp-input" type="text" id="email" name="email"
              placeholder="Email or department username" required autocomplete="username">
          </div>
        </div>

        <!-- Password -->
        <div class="lp-field">
          <label for="password" class="lp-label">Password</label>
          <div class="lp-input-wrap">
            <i class="fa-solid fa-lock lp-input-icon"></i>
            <input class="lp-input lp-pw-input" type="password" id="password" name="password"
              placeholder="Enter your password" required>
            <button type="button" class="toggle-password lp-toggle-pw" aria-label="Toggle password visibility">
              <i class="fa-solid fa-eye" id="toggleIcon"></i>
            </button>
          </div>
        </div>

        <!-- Consent -->
        <div class="lp-consent-box">
          <label class="privacy-agreement is-disabled lp-consent-label" id="privacyAgreementLabel">
            <input type="checkbox" id="privacyCheckbox" name="privacy_agreement"
              aria-describedby="consentHint" disabled>
            <span>I agree to the
              <a href="public/pages/terms_conditions.php" id="termsLink"
                class="consent-link link-attention" target="_blank" rel="noopener">Terms &amp; Conditions</a>
              and
              <a href="public/pages/privacy_policy.php" id="privacyLink"
                class="consent-link link-attention" target="_blank" rel="noopener">Privacy Policy</a>.
            </span>
          </label>
          <div id="consentHint" class="consent-hint" aria-live="polite" role="status">Please read both documents above before continuing.</div>
        </div>

        <!-- Submit -->
        <button type="submit" id="modalLoginBtn" class="lp-submit-btn" disabled>
          Sign in
          <i class="fa-solid fa-arrow-right"></i>
        </button>
      </form>

      <div class="lp-form-footer">
        <i class="fa-regular fa-circle-question" style="color:#9ca3af;margin-right:5px;"></i>
        Having trouble? <a href="mailto:support@wlc.edu.ph">Contact support</a>
      </div>
    </div>
  </div>

</div>

<script src="public/assets/js/login.js?v=wlc10"></script>
<script>
  // Password visibility toggle (handled by modal.js on the landing page; inline here)
  (function () {
    const toggleBtn  = document.querySelector('.toggle-password');
    const pwField    = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    if (toggleBtn && pwField && toggleIcon) {
      toggleBtn.addEventListener('click', function () {
        const isPassword = pwField.getAttribute('type') === 'password';
        pwField.setAttribute('type', isPassword ? 'text' : 'password');
        toggleIcon.classList.toggle('fa-eye',       !isPassword);
        toggleIcon.classList.toggle('fa-eye-slash',  isPassword);
      });
    }
  })();
</script>

</body>
</html>
