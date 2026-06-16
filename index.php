<?php
// Login page guard: an authenticated user must never see the login screen.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent the browser (and Back button) from showing a cached login page.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// If already logged in, bounce straight to the user's dashboard.
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
  <title>WLC-SMART - Inventory Management, Requisition, and Monitoring System</title>
  <link rel="stylesheet" href="public/assets/css/landing_page.css">
  <link rel="stylesheet" href="public/assets/css/about_preview.css?v=wlc13">
  <link rel="stylesheet" href="public/assets/css/loading.css">

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <script>
    // If this page is restored from the browser's back-forward cache, force a
    // reload so the server-side login guard runs and can redirect if logged in.
    window.addEventListener('pageshow', function (event) {
      if (event.persisted) {
        window.location.reload();
      }
    });
  </script>
</head>
<body>

  <header class="site-header">
    <div class="container navbar">
      <a href="#" class="logo">
        <img src="public/assets/images/wlc-smart-logo.png" alt="WLC-SMART Logo" class="logo-img">
        WLC-SMART
      </a>
      <ul class="nav-links">
        <li><a href="#">Home</a></li>
        <li><a href="#features">Features</a></li>
        <li><a href="#about">About</a></li>
      </ul>
      <button type="button" id="getStartedBtn" class="nav-btn">Get Started</button>
    </div>
  </header>

  <section class="hero">
    <div class="container hero-grid">
      <div class="hero-content">
        <h1>
          <span class="hero-title-line">Ditch the Spreadsheets</span>
          <span class="hero-title-line hero-title-subline">Upgrade to WLC-SMART</span>
        </h1>
        <p class="hero-description">WLC-SMART brings inventory, requisitions, suppliers, and asset monitoring together into one powerful and easy-to-use platform.</p>
        <div class="hero-cta">
          <a href="#" id="heroLaunchBtn" class="btn-primary">Launch System</a>
          <a href="#features" class="btn-secondary">Explore Features</a>
        </div>
      </div>
      
    </div>
  </section>

  <section id="features" class="features">
    <div class="container">
      <div class="features-header">
        <h2>Core Platform Features</h2>
        <p>Discover the smart capabilities designed to streamline asset management, track internal requisitions, and maintain total operational accountability.</p>
      </div>

      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon"><i class="fa-solid fa-cubes"></i></div>
          <div class="feature-info">
            <h3>Unified Asset Tracking</h3>
            <p>Monitor school equipment, supplies, and resources in one organized platform.</p>
          </div>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fa-solid fa-file-signature"></i></div>
          <div class="feature-info">
            <h3>Digital Request Processing</h3>
            <p>Submit and manage requisition requests electronically with faster approval handling.</p>
          </div>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fa-solid fa-eye"></i></div>
          <div class="feature-info">
            <h3>Live Inventory Visibility</h3>
            <p>Instantly check available stocks, issued items, returned assets, and inventory activity.</p>
          </div>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fa-solid fa-chart-pie"></i></div>
          <div class="feature-info">
            <h3>Analytics &amp; Insights</h3>
            <p>Generate summaries, usage reports, stock history, and procurement records anytime.</p>
          </div>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fa-solid fa-user-shield"></i></div>
          <div class="feature-info">
            <h3>Multi-Level User Control</h3>
            <p>Secure and assign different access levels for administrators, department heads, staff, and suppliers.</p>
          </div>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fa-solid fa-clipboard-list"></i></div>
          <div class="feature-info">
            <h3>Activity Logs &amp; Audit Trails</h3>
            <p>Record all user actions and inventory changes for transparency and accountability.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="about" class="about">
    <div class="container about-grid">
      <div class="about-content">
        <span class="about-tag">Who We Are</span>
        <h2>One Smart System for Western Leyte College</h2>
        <p>WLC-SMART replaces manual tracking and slow approval chains with one seamless system. WLC-SMART was engineered specifically to handle the complex inventory demands of educational institutions.</p>
        <p>We give administrators real-time visibility over every asset. Cut down your paperwork, safeguard your department budgets, and redirect your focus where it belongs&mdash;on your students.</p>

        <div class="about-stats">
          <div class="stat-item">
            <h3>99%</h3>
            <p>Security</p>
          </div>
          <div class="stat-item">
            <h3>24/7</h3>
            <p>Live Visibility</p>
          </div>
          <div class="stat-item">
            <h3>0</h3>
            <p>Paper Waste</p>
          </div>
        </div>
      </div>

      <div class="about-graphic">
        <div class="preview-shell" id="aboutPreview" aria-label="Interactive WLC-SMART inventory dashboard preview">
          <aside class="preview-sidebar">
            <div class="preview-sidebar-header">
              <div class="preview-sidebar-brand">
                <div class="preview-sidebar-brand-logo-wrap">
                  <img src="public/assets/images/wlc-smart-logo.png" alt="WLC-SMART" class="preview-sidebar-brand-logo" decoding="async">
                </div>
                <p class="preview-sidebar-brand-title">WLC-SMART</p>
                <p class="preview-sidebar-brand-tag">Inventory System</p>
              </div>
            </div>
            <nav class="preview-nav" aria-label="Preview navigation">
              <p class="preview-nav-section">Main</p>
              <span class="preview-nav-btn is-active" aria-current="page">
                <i class="fa-solid fa-grip"></i><span>Dashboard</span>
              </span>
              <span class="preview-nav-btn is-decorative">
                <i class="fa-solid fa-box"></i><span>Inventory</span>
              </span>
              <span class="preview-nav-btn is-decorative">
                <i class="fa-solid fa-file-lines"></i><span>Requisitions</span>
                <span class="preview-nav-badge">3</span>
              </span>
              <p class="preview-nav-section">Analytics</p>
              <span class="preview-nav-btn is-decorative">
                <i class="fa-solid fa-chart-column"></i><span>Reports</span>
              </span>
              <p class="preview-nav-section">Settings</p>
              <span class="preview-nav-btn is-decorative">
                <i class="fa-solid fa-gear"></i><span>Settings</span>
              </span>
            </nav>
            <div class="preview-user">
              <div class="preview-user-avatar">IM</div>
              <div class="preview-user-meta">
                <strong>Inventory Manager</strong>
                
              </div>
            </div>
          </aside>

          <main class="preview-main">
            <section class="preview-view is-active">
              <header class="preview-topbar">
                <div>
                  <h3 class="preview-page-title">Dashboard</h3>
                  <p class="preview-page-sub">Welcome back, Inventory Manager! Here's what's happening today.</p>
                </div>
                <button type="button" class="preview-btn-outline" data-preview-action="add-item">
                  <i class="fa-solid fa-plus"></i> Add Item
                </button>
              </header>
              <div class="preview-kpi-row">
                <button type="button" class="preview-kpi" data-preview-action="kpi" data-kpi="items">
                  <span class="preview-kpi-label">Total items</span>
                  <strong class="preview-kpi-value">0</strong>
                  <span class="preview-kpi-foot preview-kpi-foot--blue">All categories</span>
                </button>
                <button type="button" class="preview-kpi" data-preview-action="kpi" data-kpi="low-stock">
                  <span class="preview-kpi-label">Low stock alerts</span>
                  <strong class="preview-kpi-value">0</strong>
                  <span class="preview-kpi-foot preview-kpi-foot--warn">Needs restocking</span>
                </button>
                <button type="button" class="preview-kpi is-pulse" data-preview-action="kpi" data-kpi="pending">
                  <span class="preview-kpi-label">Pending requests</span>
                  <strong class="preview-kpi-value">3</strong>
                  <span class="preview-kpi-foot preview-kpi-foot--warn">Awaiting approval</span>
                </button>
                <button type="button" class="preview-kpi" data-preview-action="kpi" data-kpi="month">
                  <span class="preview-kpi-label">Items this month</span>
                  <strong class="preview-kpi-value">12</strong>
                  <span class="preview-kpi-foot preview-kpi-foot--ok"><i class="fa-solid fa-arrow-up"></i> 4 added</span>
                </button>
              </div>
              <div class="preview-split">
                <article class="preview-card">
                  <header class="preview-card-head">
                    <h4><i class="fa-solid fa-triangle-exclamation preview-icon-warn"></i> Low stock items</h4>
                    <button type="button" class="preview-link-btn" data-preview-action="view-low-stock">View all</button>
                  </header>
                  <div class="preview-card-body preview-empty" id="previewLowStockEmpty">
                    <p>No low-stock items right now.</p>
                  </div>
                  <ul class="preview-stock-list" id="previewLowStockList" hidden>
                    <li><span>Ballpen (blue)</span><em>12 pcs left</em></li>
                    <li><span>Whiteboard marker</span><em>5 pcs left</em></li>
                  </ul>
                </article>
                <article class="preview-card">
                  <header class="preview-card-head">
                    <h4><i class="fa-solid fa-file-lines preview-icon-ok"></i> Recent requisitions</h4>
                    <button type="button" class="preview-link-btn" data-preview-action="view-requisitions">View all</button>
                  </header>
                  <ul class="preview-req-list">
                    <li>
                      <button type="button" class="preview-req-item" data-preview-req="Printer paper (A4) x 10 reams">
                        <span class="preview-req-icon"><i class="fa-solid fa-print"></i></span>
                        <span class="preview-req-text"><strong>Printer paper (A4) x 10 reams</strong><em>Grade 7 — Jun 2, 2026</em></span>
                        <span class="preview-badge preview-badge--pending">Pending</span>
                      </button>
                    </li>
                    <li>
                      <button type="button" class="preview-req-item" data-preview-req="Ballpen (blue) x 50 pcs">
                        <span class="preview-req-icon"><i class="fa-solid fa-pen"></i></span>
                        <span class="preview-req-text"><strong>Ballpen (blue) x 50 pcs</strong><em>Admin Office — Jun 1, 2026</em></span>
                        <span class="preview-badge preview-badge--approved">Approved</span>
                      </button>
                    </li>
                    <li>
                      <button type="button" class="preview-req-item" data-preview-req="Notebooks x 30 pcs">
                        <span class="preview-req-icon"><i class="fa-solid fa-book"></i></span>
                        <span class="preview-req-text"><strong>Notebooks x 30 pcs</strong><em>Grade 9 — May 30, 2026</em></span>
                        <span class="preview-badge preview-badge--approved">Approved</span>
                      </button>
                    </li>
                    <li>
                      <button type="button" class="preview-req-item" data-preview-req="Whiteboard marker x 20 pcs">
                        <span class="preview-req-icon"><i class="fa-solid fa-wrench"></i></span>
                        <span class="preview-req-text"><strong>Whiteboard marker x 20 pcs</strong><em>Science Lab — May 29, 2026</em></span>
                        <span class="preview-badge preview-badge--rejected">Rejected</span>
                      </button>
                    </li>
                  </ul>
                </article>
              </div>
            </section>
          </main>
        </div>
      </div>
    </div>
  </section>

  <footer class="footer">
    <div class="container footer-grid">
      <div class="footer-brand">
        <a href="#" class="logo">
          <img src="public/assets/images/wlc-smart-logo.png" alt="WLC-SMART Logo" class="logo-img">
          WLC-SMART
        </a>
        <p>Empowering Western Leyte College with real-time asset monitoring and seamless workflows&mdash;so you can spend less time handling logs and more time shaping futures.</p>
      </div>

      <div class="footer-links">
        <h3>Quick Links</h3>
        <ul>
          <li><a href="index.php">Home</a></li>
          <li><a href="#features">Features</a></li>
          <li><a href="#about">About Us</a></li>
          <li><a href="#" id="footerLaunchLink">Launch System</a></li>
        </ul>
      </div>

      <div class="footer-contact">
        <h3>Contact &amp; Support</h3>
        <ul>
          <li><i class="fa-solid fa-location-dot"></i> Western Leyte College, Ormoc City, Leyte</li>
          <li><i class="fa-solid fa-envelope"></i> support@wlc.edu.ph</li>
          <li><i class="fa-solid fa-phone"></i> (053) XXX-XXXX</li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <div class="container footer-bottom-flex">
        <div class="footer-legal">
          <a href="public/pages/privacy_policy.php">Privacy Notice</a>
          <span class="separator">&bull;</span>
          <a href="public/pages/terms_conditions.php">Terms &amp; Conditions</a>
        </div>
        <p class="copyright">&copy; 2026 WLC-SMART Development Team &amp; Western Leyte College. All rights reserved. <br>
        Developed as a Capstone Project by Melo &amp; Francisco.</p>
      </div>
    </div>
  </footer>

  <!-- Login Modal -->
  <div id="loginModal" class="modal-overlay">
    <div class="modal-box-split">
      <button type="button" class="modal-close-x close" aria-label="Close">&times;</button>

      <div class="modal-left-brand">
        <img src="public/assets/images/wlc-smart-logo.png" alt="WLC-SMART Logo" class="m-logo">
        <h2>WLC-SMART</h2>
        <p>Smart inventory and requisition management for Western Leyte College</p>
      </div>

      <div class="modal-right">
        <h3>Welcome Back</h3>

        <div id="modalAlert" class="modal-alert"></div>

        <form id="loginForm">
          <div class="input-group">
            <i class="fa-solid fa-user input-icon"></i>
            <input type="text" id="email" name="email" placeholder="Email or Department Username" required autocomplete="username">
          </div>

          <div class="input-group">
            <i class="fa-solid fa-lock input-icon"></i>
            <input type="password" id="password" name="password" placeholder="Password" required>
            <span class="toggle-password">
              <i class="fa-solid fa-eye" id="toggleIcon"></i>
            </span>
          </div>

          <button type="submit" id="modalLoginBtn" class="modal-login-btn" disabled>Login</button>

          <div class="consent-box">
            <div id="consentHint" class="consent-hint" aria-live="polite" role="status">Open both documents to continue.</div>
            <label class="privacy-agreement is-disabled" id="privacyAgreementLabel">
              <input type="checkbox" id="privacyCheckbox" name="privacy_agreement" aria-describedby="consentHint" disabled>
              <span>I agree to the
                <a href="public/pages/terms_conditions.php" id="termsLink" class="consent-link link-attention" target="_blank" rel="noopener">Terms and Conditions</a>
                &amp;
                <a href="public/pages/privacy_policy.php" id="privacyLink" class="consent-link link-attention" target="_blank" rel="noopener">Privacy Policy</a>
              </span>
            </label>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div id="loginAlert" class="login-alert"></div>

  <script src="public/assets/js/landing_nav.js?v=wlc1"></script>
  <script src="public/assets/js/modal.js?v=wlc6"></script>
  <script src="public/assets/js/login.js?v=wlc9"></script>
  <script src="public/assets/js/about_preview.js?v=wlc8"></script>

</body>
</html>
