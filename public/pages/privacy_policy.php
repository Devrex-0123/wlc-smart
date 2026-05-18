<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Privacy Notice – WLC-SMART</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #16a34a 0%, #22c55e 50%, #4ade80 100%);
      min-height: 100vh;
      padding: 2rem;
    }

    .header {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      background: rgba(255, 255, 255, 0.97);
      backdrop-filter: blur(12px);
      z-index: 1000;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      height: 70px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 2rem;
    }

    .header .container {
      display: none;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 0.65rem;
    }

    .logo-mark-wrap {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      overflow: hidden;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(165deg, #ecfdf5 0%, #d1fae5 55%, #bbf7d0 100%);
      border: 2px solid rgba(22, 163, 74, 0.35);
      box-shadow: 0 2px 10px rgba(22, 163, 74, 0.18);
    }

    .logo-mark-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      transform: scale(1.45);
      display: block;
    }

    .logo h2 {
      color: #16a34a;
      font-weight: 800;
      font-size: 1.8rem;
      margin: 0;
      letter-spacing: -0.5px;
    }

    .back-btn {
      background: #16a34a;
      color: white;
      padding: 0.7rem 1.4rem;
      border-radius: 10px;
      font-weight: 600;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 6px 20px rgba(22, 163, 74, 0.3);
      border: none;
      outline: none;
      cursor: pointer;
      text-decoration: none;
      margin: 0;
    }

    .back-btn:hover {
      background: #15803d;
      transform: translateY(-3px);
      box-shadow: 0 12px 28px rgba(22, 163, 74, 0.4);
    }

    .container {
      max-width: 1400px;
      margin: 80px auto 0;
      padding: 0 2rem;
    }

    .header-section {
      text-align: center;
      margin-bottom: 3rem;
      color: white;
    }

    .header-section h1 {
      font-size: 3.2rem;
      font-weight: 800;
      margin-bottom: 0.8rem;
    }

    .header-section p {
      font-size: 1.2rem;
      opacity: 0.95;
      max-width: 700px;
      margin: 0 auto;
    }

    .policy-content {
      background: white;
      border-radius: 20px;
      padding: 3rem;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
      line-height: 1.8;
      color: #334155;
      margin-bottom: 2rem;
    }

    .policy-content::-webkit-scrollbar {
      width: 0;
    }

    .policy-content::-webkit-scrollbar-track {
      background: transparent;
    }

    .policy-content::-webkit-scrollbar-thumb {
      background: transparent;
    }

    .policy-content h2 {
      color: #16a34a;
      font-size: 1.55rem;
      margin-top: 2.2rem;
      margin-bottom: 1rem;
      font-weight: 700;
      border-bottom: 1px solid #dcfce7;
      padding-bottom: 0.45rem;
    }

    .policy-content h2:first-child {
      margin-top: 0;
    }

    .policy-content h3 {
      color: #1e293b;
      font-size: 1.2rem;
      margin-top: 1.5rem;
      margin-bottom: 0.8rem;
      font-weight: 600;
    }

    .policy-content p {
      margin-bottom: 1rem;
    }

    .policy-content ul {
      margin-left: 1.2rem;
      margin-bottom: 1.2rem;
    }

    .policy-content li {
      margin-bottom: 0.5rem;
    }

    .policy-content strong {
      color: #1e293b;
      font-weight: 600;
    }

    .updated-date {
      color: #16a34a;
      font-weight: 600;
      margin-bottom: 0.65rem;
      display: inline-block;
      background: #ecfdf5;
      padding: 0.4rem 0.75rem;
      border-radius: 999px;
      border: 1px solid #bbf7d0;
      font-size: 0.92rem;
    }

    .intro-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 1rem 1.2rem;
      margin: 1rem 0 1.4rem;
    }

    .topics-box {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      border-radius: 12px;
      padding: 1rem 1.2rem;
      margin-bottom: 1.3rem;
    }

    .topics-box h3 {
      margin-top: 0;
      color: #166534;
      font-size: 1rem;
    }

    .topics-box ul {
      margin-bottom: 0;
    }

    .contact-block {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 1rem 1.2rem;
      margin-top: 0.75rem;
    }


    @media (max-width: 1024px) {
      .policy-content { padding: 2.3rem; }
    }

    @media (max-width: 768px) {
      .header-section h1 {
        font-size: 2.2rem;
      }

      .header-section p {
        font-size: 1rem;
      }

      .policy-content { padding: 1.6rem; margin: 0 1rem; }

    }

    @media (max-width: 480px) {
      .container {
        padding: 0 1rem;
      }

      .header {
        padding: 0 1rem;
      }

      .logo-mark-wrap {
        width: 38px;
        height: 38px;
      }

      .logo h2 {
        font-size: 1.45rem;
      }

      .header-section h1 {
        font-size: 1.8rem;
      }

      .policy-content { padding: 1.2rem; margin: 0; border-radius: 15px; }

      .policy-content h2 {
        font-size: 1.5rem;
      }

      .policy-content h3 {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>

  <header class="header">
    <div class="logo">
      <div class="logo-mark-wrap" aria-hidden="true">
        <img src="../assets/images/wlc-smart-logo.png" alt="" class="logo-mark-img" width="44" height="44" decoding="async" />
      </div>
      <h2>WLC-SMART</h2>
    </div>
    <a href="javascript:goBackToLogin()" class="back-btn">
      <i class="fas fa-arrow-left"></i> Back to Login
    </a>
  </header>

  <div class="container">
    <div class="header-section">
      <h1>Privacy Notice</h1>
      <p>WLC-SMART — Inventory Management, Requisition, and Monitoring System</p>
    </div>

    <div class="policy-content">
      <p class="updated-date">Last Updated: April 29, 2026</p>
      <div class="intro-card">
        <p><strong>WLC-SMART Data Privacy Notice</strong></p>
        <p>Integrated Supplier Management, School Assets, Requisition, and Monitoring System<br>Western Leyte College Inventory Office</p>
        <p>Western Leyte College is committed to protecting your personal data in accordance with Republic Act No. 10173, also known as the Data Privacy Act of 2012. This Data Privacy Notice explains how your personal data is collected, used, stored, retained, and protected when you use the WLC-SMART system.</p>
      </div>

      <div class="topics-box">
        <h3>Topics Covered</h3>
        <ul>
          <li>What data do we collect?</li>
          <li>How do we collect your data?</li>
          <li>How will we use your data?</li>
          <li>How do we store your data?</li>
          <li>How long do we keep your data? (Retention Policy)</li>
          <li>What are your data protection rights?</li>
          <li>Changes to our privacy policy</li>
          <li>How to contact us</li>
          <li>How to contact the appropriate authorities</li>
        </ul>
      </div>

      <h2>What Data Do We Collect?</h2>
      <p>WLC-SMART collects the following data:</p>
      <ul>
        <li>Personal identification information (Full Name, Email Address, Contact Number)</li>
        <li>User role/position (e.g., Inventory Officer, Staff, Administrator)</li>
        <li>Login credentials (username and encrypted password)</li>
        <li>System activity data (logs, requisition records)</li>
      </ul>

      <h2>How Do We Collect Your Data?</h2>
      <p>You directly provide WLC-SMART with most of the data we collect. We collect data when you:</p>
      <ul>
        <li>Register or are registered as an authorized system user</li>
        <li>Log in and use the system features</li>
        <li>Submit requisition requests or update inventory records</li>
        <li>Provide feedback or communicate with system administrators</li>
      </ul>

      <h2>How Will We Use Your Data?</h2>
      <p>WLC-SMART collects your data so that we can:</p>
      <ul>
        <li>Authenticate users and manage accounts</li>
        <li>Process and track requisition requests</li>
        <li>Manage inventory and supplier records</li>
        <li>Generate reports for monitoring, planning, and decision-making</li>
        <li>Ensure system security and prevent unauthorized access</li>
      </ul>
      <p>Your data will not be used for marketing or unrelated purposes.</p>

      <h2>How Do We Store Your Data?</h2>
      <p>WLC-SMART securely stores your data in a centralized database system managed by Western Leyte College.</p>
      <p>Security measures include:</p>
      <ul>
        <li>Password encryption</li>
        <li>Role-based access control</li>
        <li>Secure login authentication</li>
        <li>Restricted system access to authorized personnel only</li>
      </ul>

      <h2>How Long Do We Keep Your Data? (Retention Policy)</h2>
      <p>WLC-SMART retains personal and system data only for as long as necessary to fulfill its operational, legal, and security purposes.</p>
      <p><strong>User Account Data:</strong></p>
      <p>
        User accounts are retained while active. When a user becomes inactive, the account is disabled but retained for at least 1–2 years for accountability and audit purposes. After this period, personal data may be anonymized while maintaining system records.
      </p>
      <p><strong>Requisition and Inventory Records:</strong></p>
      <p>
        Requisition, inventory, and related transaction records are retained for a minimum of 5 years to support audit, reporting, and institutional requirements. After this period, records may be archived in a read-only format.
      </p>
      <p><strong>System Activity Logs:</strong></p>
      <p>
        Logs (such as login history and user actions) are retained for 6–12 months for security monitoring and system maintenance, after which they may be automatically deleted or archived.
      </p>
      <p>WLC-SMART ensures that data is not retained longer than necessary and that disposal or anonymization is conducted securely and appropriately.</p>

      <h2>What Are Your Data Protection Rights?</h2>
      <p>Under the Data Privacy Act of 2012, you are entitled to the following rights:</p>
      <ul>
        <li>Right to be informed - Know how your data is collected and used</li>
        <li>Right to access - Request copies of your personal data</li>
        <li>Right to rectification - Request correction of inaccurate data</li>
        <li>Right to erasure or blocking - Request deletion of your data (when applicable)</li>
        <li>Right to object - Object to certain data processing activities</li>
        <li>Right to data portability - Request transfer of your data (if applicable)</li>
      </ul>
      <p>If you make a request, we have one month to respond to you. If you would like to exercise any of these rights, please contact us at <strong>wlcsmart0@gmail.com</strong>.</p>

      <h2>Changes to Our Privacy Policy</h2>
      <p>WLC-SMART keeps this Privacy Notice under regular review and places any updates on this web page. This privacy policy was last updated on April 29, 2026.</p>

      <h2>How to Contact Us</h2>
      <div class="contact-block">
        <p><strong>Western Leyte College</strong></p>
        <p>Ormoc City, Leyte</p>
        <p>Email: <strong>wlcsmart0@gmail.com</strong></p>
      </div>

      <h2>How to Contact the Appropriate Authority</h2>
      <div class="contact-block">
        <p><strong>National Privacy Commission</strong></p>
        <p>Email: <strong>info@privacy.gov.ph</strong></p>
        <p>Website: <strong>privacy.gov.ph</strong></p>
      </div>
    </div>

  </div>

  <script>
    function getQuery(name) {
      const params = new URLSearchParams(window.location.search);
      return params.get(name) || '';
    }

    function resolveReturnUrl() {
      const from = getQuery('return');
      if (from === 'consent') return '/CWIRMS/index.php?openLogin=1';
      return '/CWIRMS/index.php?openLogin=1';
    }

    function agreeAndReturn() {
      window.location.href = resolveReturnUrl();
    }

    function goBackToLogin() {
      window.location.href = resolveReturnUrl();
    }
  </script>
</body>
</html>
