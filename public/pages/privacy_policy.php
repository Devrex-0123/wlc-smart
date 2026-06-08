<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Privacy Notice – WLC-SMART</title>
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

        /* Container ensuring logo aspect-ratio remains distortion-free */
        .navbar-logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Perfectly scales your customized circle logo banner inline with the system name */
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

        /* --- Content Layout Structure --- */
        .policy-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 40px;
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

        .contact-box {
            background: #f9f9f9;
            border: 1px solid var(--border-gray);
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
        }

        .contact-box p {
            font-family: 'Inter', sans-serif !important;
            font-size: 0.95rem !important;
            text-align: left !important;
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
                text-align: left; /* Fallback to standard left alignment on mobile viewports */
            }
        }

        /* --- Direct Print Styles --- */
        @media print {
            .top-navbar, .policy-sidebar, .download-btn {
                display: none !important;
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
            <h1>Privacy Policy</h1>
            <h2>We're committed to protecting your privacy and ensuring transparency about how we collect, use, and safeguard your personal information on our platform.</h2>
        </div>
    </header>

    <div class="policy-container">
        
        <main class="policy-content">
            <div class="meta-info">Effective April 29, 2026</div>
            <div class="meta-info" style="margin-bottom: 20px;">Our Privacy Policy has been updated.</div>

            <h2 class="system-title-sub">Your Privacy Matters</h2>
            <p class="intro-text">
                <strong>WLC-SMART</strong> (Integrated Supplier Management, School Assets, Requisition, and Monitoring System) values transparency. 
                Western Leyte College Inventory Office is committed to protecting your personal data in accordance with 
                <strong>Republic Act No. 10173</strong>, also known as the <strong>Data Privacy Act of 2012</strong>. This notice explains how your 
                personal data is collected, used, stored, and protected when you use the system.
            </p>
            
            <section id="collect" class="policy-section" style="border-top: 2px solid #333; padding-top: 20px;">
                <h2>What data do we collect?</h2>
                <p>WLC-SMART collects the following personal data parameters:</p>
                <ul>
                    <li><strong>Personal identification information:</strong> Full Name, Email Address, and Contact Number</li>
                    <li><strong>User role/position:</strong> e.g., Inventory Officer, Staff, Administrator</li>
                    <li><strong>Login credentials:</strong> Username and securely encrypted password</li>
                    <li><strong>System activity data:</strong> Transaction logs and requisition records</li>
                </ul>
            </section>

            <section id="how-collect" class="policy-section">
                <h2>How do we collect your data?</h2>
                <p>You directly provide WLC-SMART with most of the data we collect. We record configuration details whenever you perform task updates like:</p>
                <ul>
                    <li>Register or are registered as an authorized system user</li>
                    <li>Log in and actively use the system dashboard features</li>
                    <li>Submit explicit requisition requests or update current live inventory records</li>
                    <li>Provide direct operational feedback or communicate explicitly with system administrators</li>
                </ul>
            </section>

            <section id="use-data" class="policy-section">
                <h2>How will we use your data?</h2>
                <p>WLC-SMART collects your data strictly to manage and enable the following internal platform systems:</p>
                <ul>
                    <li>Authenticate active users and safely manage active internal profiles</li>
                    <li>Process and monitor lifecycle logs tracking requisition requests</li>
                    <li>Manage unified centralized inventory databases and explicit supplier accounts</li>
                    <li>Generate clean structural reports for direct system monitoring, architectural planning, and active decision-making</li>
                    <li>Ensure total operational system security protections and actively prevent malicious unauthorized access threats</li>
                </ul>
                <p><em>Note: Your data will not be used for marketing or unrelated secondary purposes under any circumstances.</em></p>
            </section>

            <section id="store-data" class="policy-section">
                <h2>How do we store your data?</h2>
                <p>WLC-SMART securely stores your records within a highly protected, centralized database environment managed directly by Western Leyte College framework divisions.</p>
                <p>Active core security protective structural protocols include:</p>
                <ul>
                    <li>Robust architectural password encryption standards</li>
                    <li>Granular Role-Based Access Control (RBAC)</li>
                    <li>Secure login pipeline processing and multi-tiered authentication</li>
                    <li>Restricted system file interface visibility accessible solely to verified authorized personnel</li>
                </ul>
            </section>

            <section id="rights" class="policy-section">
                <h2>What are your data protection rights?</h2>
                <p>Under the Data Privacy Act of 2012, system entities are entirely entitled to exercise the following individual compliance rights:</p>
                <ul>
                    <li><strong>Right to be informed:</strong> Know precisely how your personal metrics data is compiled, tracked, and used</li>
                    <li><strong>Right to access:</strong> Formally request comprehensive electronic system copies of your structural personal data</li>
                    <li><strong>Right to rectification:</strong> Request swift correction adjustments to inaccurate or incomplete record balances</li>
                    <li><strong>Right to erasure or blocking:</strong> Request deletion context extraction maps of your specific data files where applicable</li>
                    <li><strong>Right to object:</strong> Object to secondary or specific structural file data processing actions</li>
                    <li><strong>Right to data portability:</strong> Request manual exports transferring your data profile sheets to other setups if applicable</li>
                </ul>
                <p>If you choose to file an official request, we maintain an explicit <strong>one month</strong> window timeline to generate an active response back to you. To exercise any of these items, please contact our team via email at: <a href="mailto:wlcsmart0@gmail.com" style="color: var(--link-color); font-family: sans-serif; font-size: 0.95rem;">wlcsmart0@gmail.com</a>.</p>
            </section>

            <section id="changes" class="policy-section">
                <h2>Changes to our privacy policy</h2>
                <p>WLC-SMART keeps this Privacy Notice under regular ongoing review structures. Any verified document updates will be instantly posted directly onto this web interface path environment. This privacy file configuration policy was last modified on <strong>April 29, 2026</strong>.</p>
            </section>

            <section id="contact" class="policy-section">
                <h2>How to contact us</h2>
                <p>If you have questions regarding this notice, structural operations, or user privacy file handling tracking modules, reach out directly via:</p>
                <div class="contact-box">
                    <p><strong>Institution:</strong> Western Leyte College</p>
                    <p><strong>Address:</strong> Ormoc City, Leyte</p>
                    <p><strong>Support Desk Email:</strong> <a href="mailto:wlcsmart0@gmail.com" style="color: var(--link-color);">wlcsmart0@gmail.com</a></p>
                </div>
            </section>

            <section id="authority" class="policy-section">
                <h2>How to contact the appropriate authority</h2>
                <p>If you believe that your personal data privacy rights have been handled poorly or directly violated under systemic modules, you are encouraged to connect with:</p>
                <div class="contact-box">
                    <p><strong>Agency:</strong> National Privacy Commission (NPC)</p>
                    <p><strong>Official Email:</strong> <a href="mailto:info@privacy.gov.ph" style="color: var(--link-color);">info@privacy.gov.ph</a></p>
                    <p><strong>Web Portal:</strong> <a href="https://privacy.gov.ph" target="_blank" style="color: var(--link-color);">privacy.gov.ph</a></p>
                </div>
            </section>

        </main>

        <aside class="policy-sidebar">
            <div class="toc-card">
                <h3>Table of Contents:</h3>
                <ul class="toc-list">
                    <li><a href="#collect" class="active">What data do we collect?</a></li>
                    <li><a href="#how-collect">How do we collect your data?</a></li>
                    <li><a href="#use-data">How will we use your data?</a></li>
                    <li><a href="#store-data">How do we store your data?</a></li>
                    <li><a href="#rights">What are your data protection rights?</a></li>
                    <li><a href="#changes">Changes to our privacy policy</a></li>
                    <li><a href="#contact">How to contact us</a></li>
                    <li><a href="#authority">How to contact authorities</a></li>
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
                <a href="../../index.php" class="logo">
                    <img src="../assets/images/wlc-smart-logo.png" alt="WLC-SMART Logo" class="logo-img">
                    WLC-SMART
                </a>
                <p>Empowering Western Leyte College with real-time asset monitoring and seamless workflows—so you can spend less time handling logs and more time shaping futures.</p>
            </div>

            <div class="footer-links">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="../../index.php">Home</a></li>
                    <li><a href="../../index.php#features">Features</a></li>
                    <li><a href="../../index.php#about">About Us</a></li>
                    <li><a href="../../index.php" id="footerLaunchLink">Launch System</a></li>
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
