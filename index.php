<!-- Save this as index.php (use your original file; unchanged) -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WLC-SMART - Inventory Management, Requisition, and Monitoring System</title>
  <link rel="stylesheet" href="public/assets/css/landing_page.css">
  <link rel="stylesheet" href="public/assets/css/loading.css">
  
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

  <header class="header">
    <div class="container">
      <div class="logo">
        <div class="logo-mark-wrap" aria-hidden="true">
          <img src="public/assets/images/wlc-smart-logo.png" alt="" class="logo-mark-img" width="44" height="44" decoding="async" />
        </div>
        <h2>WLC-SMART</h2>
      </div>
      <button id="loginBtn" class="btn-login">
        <i class="fas fa-sign-in-alt"></i> Login
      </button>
    </div>
  </header>

  <section class="hero">
    <div class="container">
      <h1>Centralized Inventory Management <br><span class="highlight">Made Simple & Powerful</span></h1>
      <p class="subtitle">
        WLC-SMART is your all-in-one solution for real-time inventory tracking, requisition workflows, 
        approval monitoring, and comprehensive reporting across multiple locations.
      </p>
      <div class="hero-actions">
        <a href="#features" class="btn-primary">Explore Features</a>
        <button id="getStartedBtn" class="btn-secondary">Get Started</button>
      </div>
    </div>
  </section>

  <section class="features" id="features">
    <div class="container">
      <h2 class="section-title">Why Choose WLC-SMART?</h2>
      <div class="features-grid">
        <div class="feature-card">
          <i class="fas fa-warehouse icon"></i>
          <h3>Centralized Inventory</h3>
          <p>Manage stock across all warehouses and branches from a single dashboard.</p>
        </div>
        <div class="feature-card">
          <i class="fas fa-clipboard-list icon"></i>
          <h3>Smart Requisitions</h3>
          <p>Create, approve, and track requisition requests with automated workflows.</p>
        </div>
        <div class="feature-card">
          <i class="fas fa-chart-line icon"></i>
          <h3>Real-Time Monitoring</h3>
          <p>Live updates on stock levels, movements, low-stock alerts, and audit trails.</p>
        </div>
        <div class="feature-card">
          <i class="fas fa-users-cog icon"></i>
          <h3>Role-Based Access</h3>
          <p>Secure permissions for admins, managers, staff, and auditors.</p>
        </div>
        <div class="feature-card">
          <i class="fas fa-file-export icon"></i>
          <h3>Advanced Reporting</h3>
          <p>Export detailed reports on inventory valuation, consumption, and trends.</p>
        </div>
        <div class="feature-card">
          <i class="fas fa-bell icon"></i>
          <h3>Automated Alerts</h3>
          <p>Get notified for low stock, pending approvals, and expiring items.</p>
        </div>
      </div>
    </div>
  </section>

  <footer class="footer">
    <div class="container">
      <p>&copy; 2025 WLC-SMART - Inventory Management, Requisition, and Monitoring System. All rights reserved.</p>
      <div class="footer-links">
        <a href="public/pages/privacy_policy.php">Privacy Notice</a>
        <span class="divider">•</span>
        <a href="public/pages/terms_conditions.php">Terms &amp; Conditions</a>
      </div>
    </div>
  </footer>

  <!-- Login Modal -->
  <div id="loginModal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <div class="modal-left">
        <div class="modal-logo">
          <div class="logo-mark-wrap logo-mark-wrap--modal" aria-hidden="true">
            <img src="public/assets/images/wlc-smart-logo.png" alt="" class="logo-mark-img" width="72" height="72" decoding="async" />
          </div>
          <h2>WLC-SMART</h2>
          <p>Centralized Web-Based Inventory<br>& Requisition Management System</p>
        </div>
      </div>

      <div class="modal-right">
        <h3>Welcome Back</h3>

        <div id="modalAlert" class="modal-alert"></div>

        <form id="loginForm">
          <div class="input-group">
            <i class="fas fa-envelope"></i>
            <input type="email" id="email" name="email" placeholder="Email Address" required>
          </div>

          <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" name="password" placeholder="Password" required>
            <span class="toggle-password">
              <i class="fas fa-eye" id="toggleIcon"></i>
            </span>
          </div>

          <button type="submit" id="modalLoginBtn" class="modal-login-btn">
            Login
          </button>

          <div class="modal-footer-text">
            <label class="privacy-agreement">
              <input type="checkbox" id="privacyCheckbox" name="privacy_agreement" aria-describedby="consentHint">
              <span>
                I agree to the
                <a href="#" id="privacyLink" class="privacy-link">Privacy Notice</a>
                and
                <a href="#" id="termsLink" class="privacy-link">Terms &amp; Conditions</a>
              </span>
            </label>
            <div id="consentHint" class="consent-hint" aria-live="polite" role="status"></div>
          </div>
        </form>

      </div>
    </div>
  </div>
  <div id="loginAlert" class="login-alert"></div>


  <script src="public/assets/js/modal.js?v=wlc1"></script>
  <script src="public/assets/js/login.js?v=wlc1"></script>

</body>
</html>
