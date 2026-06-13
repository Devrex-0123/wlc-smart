<?php
require_once __DIR__ . '/../../app/config/consent.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Terms &amp; Conditions – WLC-SMART</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
            --banner-bg: #009640; /* Corporate green header color */
            --link-color: #009640;
            --text-main: #2b2b2b; /* Softened gray-black for digital reading readability */
            --text-secondary: #555555;
            --border-gray: #cdcdcd;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            color: var(--text-main);
            background-color: #ffffff;
            line-height: 1.6;
        }

        /* --- Top Navbar Branding --- */
        .top-navbar {
            background-color: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 20px;
        }

        .navbar-container {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
        }

        .navbar-logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .navbar-logo-img {
            height: 40px; 
            width: auto;
            object-fit: contain;
            display: block;
        }

        .navbar-brand {
            font-family: 'Inter', sans-serif;
            font-size: 1.35rem;
            font-weight: 700;
            color: #0b2545; /* Dark corporate blue tone */
            letter-spacing: -0.02em;
        }

        /* --- Full Width Header Banner --- */
        .policy-header {
            background-color: var(--banner-bg);
            color: #ffffff;
            padding: 35px 20px;
            text-align: center;
        }

        .header-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .policy-header h1 {
            font-size: 2.2rem;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .policy-header h2 {
            font-size: 1.1rem;
            font-weight: 400;
            opacity: 0.95;
            max-width: 800px;
            margin: 0 auto;
        }

        .policy-header-meta {
            font-size: 0.95rem;
            font-weight: 500;
            opacity: 0.92;
            margin: 14px auto 0;
            letter-spacing: 0.02em;
        }

        /* --- Content Layout Structure --- */
        .policy-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 40px;
        }

        /* --- Meta and Subtitles --- */
        .meta-info {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-style: italic;
            margin-bottom: 5px;
        }

        .system-title-sub {
            font-size: 1.6rem;
            font-weight: 600;
            color: #111111;
            margin-bottom: 20px;
        }

        /* --- Professional Legal Document Justification Styles --- */
        .intro-text, 
        .policy-section p, 
        .policy-section ul li {
            font-family: 'Georgia', serif; /* Corporate standard font for formal documentation */
            font-size: 1.05rem;
            text-align: justify;
            text-justify: inter-character; /* Distributes whitespace evenly across words like MS Word */
        }

        .intro-text {
            margin-bottom: 25px;
        }

        /* --- Content Layout Sections --- */
        .policy-content {
            padding-right: 10px;
        }

        .policy-section {
            padding-top: 30px;
            margin-top: 20px;
            border-top: 1px solid #e0e0e0;
            scroll-margin-top: 20px;
        }

        .policy-section h2 {
            font-family: 'Inter', sans-serif;
            font-size: 1.5rem;
            font-weight: 500;
            color: #000000;
            margin-bottom: 15px;
        }

        .policy-section p {
            margin-bottom: 15px;
        }

        .policy-section ul {
            list-style-type: disc;
            margin-left: 24px;
            margin-bottom: 20px;
        }

        .policy-section ul li {
            margin-bottom: 10px;
            padding-left: 4px;
        }

        /* --- Action Controls --- */
        .action-container {
            margin-top: 35px;
            padding-top: 25px;
            border-top: 2px solid #2b2b2b;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .agreement-label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
        }

        .agreement-label input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--link-color);
            cursor: pointer;
            margin-top: 2px;
        }

        .button-group {
            display: flex;
            gap: 15px;
        }

        .btn {
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background-color: var(--banner-bg);
            color: #ffffff;
            border: none;
        }

        .btn-primary:hover {
            background-color: #007a33;
        }

        .btn-primary:disabled {
            background-color: #cccccc;
            color: #666666;
            cursor: not-allowed;
        }

        .btn-secondary {
            background-color: #ffffff;
            color: #555555;
            border: 1px solid var(--border-gray);
        }

        .btn-secondary:hover {
            background-color: #f5f5f5;
            color: #2b2b2b;
        }

        /* --- Right Floating Table of Contents --- */
        .policy-sidebar {
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        .toc-card {
            background: #ffffff;
            border: 1px solid var(--border-gray);
            padding: 25px;
            border-radius: 2px;
        }

        .toc-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .toc-list {
            list-style: none;
        }

        .toc-list li {
            margin-bottom: 15px;
        }

        .toc-list a {
            color: var(--link-color);
            text-decoration: none;
            font-size: 0.95rem;
            display: block;
            transition: text-decoration 0.2s ease;
        }

        .toc-list a:hover {
            text-decoration: underline;
        }

        .toc-list a.active {
            font-weight: bold;
        }

        .download-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            background-color: transparent;
            color: var(--link-color);
            border: 1px solid var(--link-color);
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            margin-top: 25px;
            transition: all 0.2s ease;
        }

        .download-btn:hover {
            background-color: rgba(0, 150, 64, 0.05);
        }

        /* --- Shared Main Container Layout --- */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* --- Footer Section Styles --- */
        .footer {
            background-color: #1f2937; 
            color: #f3f4f6; 
            padding: 24px 0 0 0;
            font-size: 0.95rem;
            margin-top: 60px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 20px;
            padding-bottom: 16px;
        }

        .footer-brand .logo {
            color: #ffffff;
            font-size: 1.5rem;
            margin-bottom: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        .footer-brand .logo-img {
            height: 30px;
            width: auto;
        }

        .footer-brand p {
            color: #9ca3af;
            line-height: 1.5;
        }

        .footer h3 {
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer ul li {
            margin-bottom: 8px;
        }

        .footer ul li a {
            color: #9ca3af;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer ul li a:hover {
            color: #ffffff; 
        }

        .footer-contact li {
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-contact li i {
            color: #10b981; 
        }

        /* --- Centered Bottom Footer Styling --- */
        .footer-bottom {
            border-top: 1px solid #374151;
            padding: 12px 0;
        }

        .footer-bottom-flex {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-align: center;
        }

        .footer-legal {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }

        .footer-legal a {
            color: #9ca3af;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-legal a:hover {
            color: #ffffff;
        }

        .footer-legal .separator {
            color: #6b7280;
        }

        .footer-bottom p.copyright {
            color: #6b7280;
            margin: 0;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        /* --- Responsive Styles --- */
        @media (max-width: 768px) {
            .policy-container {
                grid-template-columns: 1fr;
            }
            .policy-sidebar {
                grid-row: 1;
                position: relative;
                top: 0;
            }
            .intro-text, .policy-section p, .policy-section ul li {
                text-align: left;
            }
            .button-group {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        /* --- Direct Print Styles --- */
        @media print {
            .top-navbar, .policy-sidebar, .download-btn, .action-container, .footer {
                display: none !important; /* Hides footer smoothly in printed PDFs */
            }
            .policy-container {
                grid-template-columns: 1fr;
            }
            .policy-header {
                background-color: #ffffff !important;
                color: #000000 !important;
                border-bottom: 2px solid #000000;
            }
        }
        
  </style>
</head>
<body>

    <nav class="top-navbar">
        <div class="navbar-container">
            <div class="navbar-logo-container">
                <img src="../assets/images/wlc-smart-logo.png" alt="WLC-SMART Logo" class="navbar-logo-img">
            </div>
            <span class="navbar-brand">WLC-SMART</span>
        </div>
    </nav>

    <header class="policy-header">
        <div class="header-container">
            <h1>Terms and Conditions</h1>
            <h2>Please read our terms and conditions carefully to understand the policies, acceptable uses, and liabilities associated with our institutional framework operations.</h2>
            <p class="policy-header-meta">Version <?php echo htmlspecialchars(CONSENT_VERSION); ?></p>
        </div>
    </header>

    <div class="policy-container">
        
        <main class="policy-content">
            <div class="meta-info" style="margin-bottom: 20px;"><?php echo htmlspecialchars(CONSENT_EFFECTIVE_DATE); ?></div>

            <h2 class="system-title-sub">System Terms of Service</h2>
            <p class="intro-text">
                This system is developed to support the operations of the <strong>Western Leyte College Inventory Office</strong> by providing a platform that integrates supplier management, school asset inventory, requisition processing, and monitoring.
            </p>
            <p class="intro-text">
                The system is intended for authorized personnel only, including the Inventory Officer, administrative staff, and other designated users involved in inventory-related processes. It is designed solely for official institutional use to improve efficiency, accuracy, transparency, and accountability in managing school resources.
            </p>
            
            <section id="scope" class="policy-section" style="border-top: 2px solid #333; padding-top: 20px;">
                <h2>Scope and Purpose of System Use</h2>
                <p>WLC-SMART handles operational core architectures engineered explicitly to oversee processing pipelines. Authorized access maps scale parameters monitoring internal physical materials, structural allocations, and tracking assets managed natively by administrative operations inside the organization ecosystem layouts.</p>
            </section>

            <section id="responsibilities" class="policy-section">
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
            </section>

            <section id="prohibited" class="policy-section">
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
                <p>Any violation may result in account suspension, disciplinary action, or administrative sanctions in accordance with institutional policies.</p>
            </section>

            <section id="ownership" class="policy-section">
                <h2>Data Ownership and Handling Responsibilities</h2>
                <p>All data stored in the system, including inventory records, requisition transactions, and supplier information, are the property of Western Leyte College.</p>
                <p>Users are responsible for:</p>
                <ul>
                    <li>Ensuring proper and ethical handling of data</li>
                    <li>Maintaining the confidentiality and integrity of system information</li>
                    <li>Accessing only the data necessary for their assigned roles</li>
                </ul>
                <p>The system implements role-based access control to restrict unauthorized access and ensure that users can only view or modify data relevant to their responsibilities.</p>
            </section>

            <section id="liability" class="policy-section">
                <h2>Limitation of Liability</h2>
                <p>This system is developed as part of an academic capstone project and is intended to assist in improving inventory and requisition processes.</p>
                <p>While efforts have been made to ensure system accuracy and reliability:</p>
                <ul>
                    <li>The system administrators and developers do not guarantee that the system is free from errors or interruptions</li>
                    <li>The institution and developers shall not be held liable for any losses, delays, or damages resulting from incorrect data entry, system misuse, or technical issues</li>
                    <li>Users are responsible for verifying the accuracy of data before making decisions based on system outputs</li>
                </ul>
            </section>

            <section id="privacy" class="policy-section">
                <h2>Compliance with Data Privacy Laws</h2>
                <p>This system complies with the Data Privacy Act of 2012, which governs the collection, processing, storage, and protection of personal data. All personal information processed within the system is handled in accordance with applicable data privacy regulations. For more details, users are encouraged to review the system’s Data Privacy Notice.</p>
            </section>

            <section id="acceptance" class="policy-section">
                <h2>Acceptance of Terms</h2>
                <p>By accessing and using the system, users acknowledge that they have read, understood, and agreed to abide by these Terms and Conditions. Continued use of the system constitutes full acceptance of these terms. If a user does not agree with any part of these Terms and Conditions, they must discontinue use of the system immediately.</p>
                <p>These Terms and Conditions are Version <strong><?php echo htmlspecialchars(CONSENT_VERSION); ?></strong>, last updated on <strong><?php echo htmlspecialchars(CONSENT_EFFECTIVE_DATE); ?></strong>.</p>
            </section>

        </main>

        <aside class="policy-sidebar">
            <div class="toc-card">
                <h3>Table of Contents:</h3>
                <ul class="toc-list">
                    <li><a href="#scope" class="active">Scope and Purpose</a></li>
                    <li><a href="#responsibilities">User Responsibilities</a></li>
                    <li><a href="#prohibited">Prohibited Activities</a></li>
                    <li><a href="#ownership">Data Ownership</a></li>
                    <li><a href="#liability">Limitation of Liability</a></li>
                    <li><a href="#privacy">Privacy Compliance</a></li>
                    <li><a href="#acceptance">Acceptance of Terms</a></li>
                </ul>
                <button class="download-btn" onclick="window.print()">
                    <i class="fas fa-file-pdf"></i> Save Document as PDF
                </button>
            </div>
        </aside>

    </div>

    <footer class="footer">
        <div class="container footer-grid">
            <div class="footer-brand">
                <a href="../mainpage.html" class="logo">
                    <img src="../assets/images/wlc-smart-logo.png" alt="WLC-SMART Logo" class="logo-img">
                    WLC-SMART
                </a>
                <p>Empowering Western Leyte College with real-time asset monitoring and seamless workflows—so you can spend less time handling logs and more time shaping futures.</p>
            </div>

            <div class="footer-links">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="/wlc-smart/">Home</a></li>
                    <li><a href="/wlc-smart/#features">Features</a></li>
                    <li><a href="/wlc-smart/#about">About Us</a></li>
                    <li><a href="/wlc-smart/" id="footerLaunchLink">Launch System</a></li>
                </ul>
            </div>

            <div class="footer-contact">
                <h3>Contact & Support</h3>
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
                    <a href="../pages/privacy_policy.php">Privacy Notice</a>
                    <span class="separator">•</span>
                    <a href="../pages/terms_conditions.php">Terms & Conditions</a>
                </div>
                <p class="copyright">&copy; 2026 WLC-SMART Development Team & Western Leyte College. All rights reserved. <br>
                Developed as a Capstone Project by Melo & Francisco.</p>
            </div>
        </div>
    </footer>

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

