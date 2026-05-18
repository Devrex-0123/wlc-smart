<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Terms &amp; Conditions – WLC-SMART</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #16a34a 0%, #22c55e 50%, #4ade80 100%);
      min-height: 100vh;
      padding: 2rem;
      color: #334155;
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

    .terms-content {
      background: white;
      border-radius: 20px;
      padding: 3rem;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
      overflow-y: visible;
      line-height: 1.8;
      color: #334155;
      max-height: none;
    }

    .terms-content h2 {
      color: #16a34a;
      font-size: 1.5rem;
      margin-top: 2.2rem;
      margin-bottom: 1rem;
      font-weight: 700;
      border-bottom: 1px solid #dcfce7;
      padding-bottom: 0.45rem;
    }

    .terms-content h2:first-child { margin-top: 0; }

    .terms-content h3 {
      color: #1e293b;
      font-size: 1.2rem;
      margin-top: 1.5rem;
      margin-bottom: 0.8rem;
      font-weight: 600;
    }

    .terms-content p { margin-bottom: 1rem; }
    .terms-content ul { margin-left: 1.5rem; margin-bottom: 1rem; }
    .terms-content li { margin-bottom: 0.5rem; }
    .terms-content strong { color: #1e293b; font-weight: 600; }

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

    @media (max-width: 768px) {
      .header-section h1 { font-size: 2.2rem; }
      .header-section p { font-size: 1rem; }
      .terms-content { padding: 1.6rem; margin: 0 1rem; border-radius: 15px; }
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
      <h1>Terms &amp; Conditions</h1>
      <p>WLC-SMART — Inventory Management, Requisition, and Monitoring System</p>
    </div>

    <div class="terms-content">
      <p class="updated-date">Last Updated: April 29, 2026</p>
      <div class="intro-card">
        <p><strong>Terms and Conditions</strong></p>
        <p>
          Western Leyte College Integrated Supplier Management, School Assets, Requisition, and Monitoring System
          (WLC-SMART) is intended for authorized institutional use only.
        </p>
      </div>
      <p>
        This system is developed to support the operations of the Western Leyte College Inventory Office by providing a platform
        that integrates supplier management, school asset inventory, requisition processing, and monitoring.
      </p>
      <p>
        The system is intended for authorized personnel only, including the Inventory Officer, administrative staff, and other
        designated users involved in inventory-related processes. It is designed solely for official institutional use to improve
        efficiency, accuracy, transparency, and accountability in managing school resources.
      </p>

      <h2>Scope and Purpose of System Use</h2>

      <h2>User Responsibilities and Acceptable Use</h2>
      <p>All users of the system are expected to:</p>
      <ul>
        <li>Use the system only for official and authorized purposes</li>
        <li>Provide accurate, complete, and truthful information when entering inventory, requisition, or supplier data</li>
        <li>Maintain the confidentiality of login credentials and prevent unauthorized access</li>
        <li>Ensure that all system activities comply with institutional policies and procedures</li>
        <li>Log out properly after each session</li>
      </ul>
      <p>Users are responsible for all actions performed under their assigned accounts.</p>

      <h2>Prohibited Activities</h2>
      <p>Users are strictly prohibited from:</p>
      <ul>
        <li>Accessing or attempting to access accounts, data, or system features without authorization</li>
        <li>Entering false, misleading, or fraudulent information</li>
        <li>Modifying, deleting, or tampering with records without proper authority</li>
        <li>Sharing login credentials or allowing others to use their account</li>
        <li>Using the system for non-academic, personal, or malicious purposes</li>
        <li>Attempting to disrupt system operations, including introducing viruses, malware, or harmful code</li>
      </ul>
      <p>
        Any violation may result in account suspension, disciplinary action, or administrative sanctions in accordance with
        institutional policies.
      </p>

      <h2>Data Ownership and Handling Responsibilities</h2>
      <p>
        All data stored in the system, including inventory records, requisition transactions, and supplier information, are the
        property of Western Leyte College.
      </p>
      <p>Users are responsible for:</p>
      <ul>
        <li>Ensuring proper and ethical handling of data</li>
        <li>Maintaining the confidentiality and integrity of system information</li>
        <li>Accessing only the data necessary for their assigned roles</li>
      </ul>
      <p>
        The system implements role-based access control to restrict unauthorized access and ensure that users can only view or
        modify data relevant to their responsibilities.
      </p>

      <h2>Limitation of Liability</h2>
      <p>
        This system is developed as part of an academic capstone project and is intended to assist in improving inventory and
        requisition processes.
      </p>
      <p>While efforts have been made to ensure system accuracy and reliability:</p>
      <ul>
        <li>The system administrators and developers do not guarantee that the system is free from errors or interruptions</li>
        <li>
          The institution and developers shall not be held liable for any losses, delays, or damages resulting from incorrect data
          entry, system misuse, or technical issues
        </li>
        <li>Users are responsible for verifying the accuracy of data before making decisions based on system outputs</li>
      </ul>

      <h2>Compliance with Data Privacy Laws</h2>
      <p>
        This system complies with the Data Privacy Act of 2012, which governs the collection, processing, storage, and
        protection of personal data. All personal information processed within the system is handled in accordance with
        applicable data privacy regulations. For more details, users are encouraged to review the system’s Data Privacy Notice.
      </p>

      <h2>Acceptance of Terms</h2>
      <p>
        By accessing and using the system, users acknowledge that they have read, understood, and agreed to abide by these
        Terms and Conditions. Continued use of the system constitutes full acceptance of these terms. If a user does not agree
        with any part of these Terms and Conditions, they must discontinue use of the system immediately.
      </p>

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

