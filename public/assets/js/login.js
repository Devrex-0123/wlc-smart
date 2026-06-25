document.addEventListener("DOMContentLoaded", function () {
    const loginForm = document.getElementById("loginForm");
    const modalAlert = document.getElementById("modalAlert");
    const loginBtn = document.getElementById("modalLoginBtn") || document.querySelector(".modal-login-btn");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const privacyCheckbox = document.getElementById("privacyCheckbox");
    const privacyAgreementLabel = document.getElementById("privacyAgreementLabel");
    const privacyLink = document.getElementById("privacyLink");
    const termsLink = document.getElementById("termsLink");
    const consentHint = document.getElementById("consentHint");

    /* =========================================================
       MANDATORY CONSENT WORKFLOW
       - Both policy links start BLUE (attention).
       - Clicking a link turns it GREY (opened/completed).
       - Checkbox stays disabled until BOTH links are grey.
       - Login button stays disabled until the checkbox is ticked.
       ========================================================= */
    const consentState = { privacy: false, terms: false };

    function bothDocumentsOpened() {
        return consentState.privacy && consentState.terms;
    }

    function refreshLoginButton() {
        const ready = bothDocumentsOpened() && privacyCheckbox && privacyCheckbox.checked;
        if (loginBtn) loginBtn.disabled = !ready;
    }

    function refreshConsentUI() {
        const opened = bothDocumentsOpened();

        if (privacyCheckbox) {
            privacyCheckbox.disabled = !opened;
            if (!opened) {
                privacyCheckbox.checked = false;
                delete privacyCheckbox.dataset.locked;
            }
        }

        if (privacyAgreementLabel) {
            privacyAgreementLabel.classList.toggle("is-disabled", !opened);
        }

        if (consentHint) {
            if (!opened) {
                consentHint.textContent = "Please read both documents above before continuing.";
            } else if (!privacyCheckbox.checked) {
                consentHint.textContent = "Now tick the box to confirm your agreement.";
            } else {
                consentHint.textContent = "";
            }
        }

        refreshLoginButton();
    }

    function markConsentLinkOpened(link, key) {
        if (!link) return;
        link.classList.remove("link-attention");
        link.classList.add("link-visited");
        consentState[key] = true;
        refreshConsentUI();
    }

    // Links use target="_blank", so the document opens in a new tab while the
    // login modal stays in place. We just flip the link to its grey state.
    if (privacyLink) {
        privacyLink.addEventListener("click", function () {
            markConsentLinkOpened(privacyLink, "privacy");
        });
    }
    if (termsLink) {
        termsLink.addEventListener("click", function () {
            markConsentLinkOpened(termsLink, "terms");
        });
    }

    if (privacyCheckbox) {
        // Block any interaction while disabled, and lock it in once ticked.
        privacyCheckbox.addEventListener("click", function (e) {
            if (privacyCheckbox.disabled) {
                e.preventDefault();
                return;
            }
            if (privacyCheckbox.dataset.locked === "1") {
                e.preventDefault();
            }
        });

        privacyCheckbox.addEventListener("change", function () {
            if (privacyCheckbox.checked) {
                privacyCheckbox.dataset.locked = "1"; // lock in as checked
                if (consentHint) consentHint.textContent = "";
            }
            refreshLoginButton();
        });
    }

    // Initialize the consent UI on load.
    refreshConsentUI();

    /* ---------------------------------------------------------
       PERSISTENT CONSENT: look up had_consented / has_consented
       by email or department username.
       has_consented = 1  -> skip workflow (box pre-checked/locked)
       has_consented = 0  -> require the open-both-documents workflow
       (The server re-verifies this on authentication.)
       --------------------------------------------------------- */
    const CONSENT_VERSION = "v1.0";
    let consentOnFile = false;
    let consentLookupTimer = null;
    let consentLookupSeq = 0;

    function isLoginIdentifier(value) {
        const trimmed = value.trim().toLowerCase();
        if (!trimmed) return false;
        if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed)) return true;
        return /^[a-z0-9._-]{3,50}$/.test(trimmed);
    }

    async function fetchConsentStatus(identifier) {
        try {
            const body = new URLSearchParams({
                identifier: identifier.trim().toLowerCase(),
                consent_version: CONSENT_VERSION
            });
            const res = await fetch("app/api/consent_status.php", {
                method: "POST",
                body,
                credentials: "include"
            });
            const data = await res.json();
            return Boolean(data && data.success && data.has_consented);
        } catch (_) {
            return false;
        }
    }

    function applyConsentOnFile() {
        consentOnFile = true;
        consentState.privacy = true;
        consentState.terms = true;

        [privacyLink, termsLink].forEach(function (link) {
            if (!link) return;
            link.classList.remove("link-attention");
            link.classList.add("link-visited");
        });

        if (privacyCheckbox) {
            privacyCheckbox.disabled = false;
            privacyCheckbox.checked = true;
            privacyCheckbox.dataset.locked = "1";
        }
        if (privacyAgreementLabel) privacyAgreementLabel.classList.remove("is-disabled");
        if (consentHint) consentHint.textContent = "";

        refreshLoginButton();
    }

    function applyConsentRequired() {
        consentOnFile = false;
        consentState.privacy = false;
        consentState.terms = false;

        [privacyLink, termsLink].forEach(function (link) {
            if (!link) return;
            link.classList.add("link-attention");
            link.classList.remove("link-visited");
        });

        if (privacyCheckbox) {
            delete privacyCheckbox.dataset.locked;
            privacyCheckbox.checked = false;
        }

        refreshConsentUI();
    }

    if (emailInput) {
        emailInput.addEventListener("input", function () {
            const identifier = this.value.trim().toLowerCase();

            if (consentLookupTimer) clearTimeout(consentLookupTimer);

            if (!isLoginIdentifier(identifier)) {
                if (consentOnFile) applyConsentRequired();
                return;
            }

            consentLookupTimer = setTimeout(async () => {
                const seq = ++consentLookupSeq;
                const consented = await fetchConsentStatus(identifier);

                if (seq !== consentLookupSeq) return;
                if (emailInput.value.trim().toLowerCase() !== identifier) return;

                if (consented) {
                    applyConsentOnFile();
                } else if (consentOnFile) {
                    applyConsentRequired();
                }
            }, 300);
        });
    }

    // Clear sensitive inputs on a fresh page load.
    window.addEventListener("load", () => {
        if (emailInput) emailInput.value = "";
        if (passwordInput) passwordInput.value = "";
    });

    /* =========================================================
       LOCKOUT / ATTEMPT HANDLING + AUTHENTICATION
       ========================================================= */
    const APP_BRAND = "WLC-SMART";

    function showLoadingScreen(text = "Loading") {
        const loadingHTML = `
            <div class="loading-screen" id="loadingScreen">
                <div class="loading-logo">${APP_BRAND}</div>
                <div class="loading-spinner"></div>
                <div class="loading-text">
                    ${text}
                    <span class="loading-dots">
                        <span></span><span></span><span></span>
                    </span>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', loadingHTML);
    }

    let lockoutTimeout = null;
    let countdownInterval = null;
    let lastEmail = "";

    function lockInputs() {
        emailInput.disabled = true;
        passwordInput.disabled = true;
        emailInput.style.opacity = "0.6";
        passwordInput.style.opacity = "0.6";
    }

    function unlockInputs() {
        emailInput.disabled = false;
        passwordInput.disabled = false;
        emailInput.style.opacity = "";
        passwordInput.style.opacity = "";
    }

    function resetServerAttempts() {
        const emailValue = emailInput.value.trim().toLowerCase();
        if (emailValue) {
            const resetFormData = new FormData();
            resetFormData.append('email', emailValue);
            fetch("app/api/reset_attempts.php", {
                method: "POST",
                body: resetFormData,
                credentials: "include"
            }).catch(() => {});
        }
    }

    emailInput.addEventListener("input", function () {
        const current = this.value.trim().toLowerCase();
        if (current === "") {
            lastEmail = "";
            return;
        }
        if (current !== lastEmail) {
            if (lastEmail !== "") resetServerAttempts();
            if (loginBtn.disabled && countdownInterval) {
                loginBtn.textContent = "Sign in";
                unlockInputs();
                clearMessage();
                if (countdownInterval) clearInterval(countdownInterval);
                if (lockoutTimeout) clearTimeout(lockoutTimeout);
                countdownInterval = null;
                refreshLoginButton();
            }
            lastEmail = current;
        }
    });

    function showMessage(text, type = "error") {
        modalAlert.textContent = text;
        modalAlert.className = "modal-alert show " + type;
    }

    function clearMessage() {
        modalAlert.textContent = "";
        modalAlert.className = "modal-alert";
    }

    function clearInputs() {
        emailInput.value = "";
        passwordInput.value = "";
        emailInput.focus();
    }

    function startLockout(secondsLeft = 5) {
        clearInputs();
        lockInputs();
        loginBtn.disabled = true;
        loginBtn.textContent = `Locked (${secondsLeft}s)`;
        showMessage(`Too many failed attempts. Please wait ${secondsLeft} seconds.`, "lockout");

        countdownInterval = setInterval(() => {
            secondsLeft--;
            if (secondsLeft > 0) {
                loginBtn.textContent = `Locked (${secondsLeft}s)`;
                modalAlert.textContent = `Too many failed attempts. Please wait ${secondsLeft} seconds.`;
            } else {
                clearInterval(countdownInterval);
                countdownInterval = null;
                loginBtn.textContent = "Sign in";
                unlockInputs();
                clearMessage();
                resetServerAttempts();
                refreshLoginButton();
            }
        }, 1000);

        lockoutTimeout = setTimeout(() => {
            if (countdownInterval) clearInterval(countdownInterval);
            loginBtn.textContent = "Sign in";
            unlockInputs();
            clearMessage();
            resetServerAttempts();
            refreshLoginButton();
        }, secondsLeft * 1000);
    }

    loginForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        if (loginBtn.disabled) return;

        // Enforce the mandatory consent workflow before anything else.
        if (!bothDocumentsOpened()) {
            showMessage("Please open the Privacy Notice and Terms & Conditions first.", "error");
            return;
        }
        if (!privacyCheckbox.checked) {
            showMessage("You must agree to the Privacy Notice and Terms & Conditions to continue.", "error");
            privacyCheckbox.focus();
            return;
        }

        // Basic input validation (friendly UX)
        const loginValue = emailInput.value.trim();
        if (!loginValue) {
            showMessage("Please enter your email or department username.", "error");
            return;
        }

        if (loginValue.includes('@') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(loginValue)) {
            showMessage("Please enter a valid email address.", "error");
            return;
        }

        if (!passwordInput.value || passwordInput.value.trim().length === 0) {
            showMessage("Please enter your password.", "error");
            return;
        }

        clearMessage();
        loginBtn.disabled = true;
        loginBtn.textContent = "Logging in...";

        const normalizedLogin = emailInput.value.trim();
        if (normalizedLogin.includes('@')) {
            emailInput.value = normalizedLogin.toLowerCase();
        } else {
            emailInput.value = normalizedLogin;
        }

        const formData = new FormData(loginForm);
        const loginIdentifier = emailInput.value;

        try {
            const response = await fetch("app/api/login.php", {
                method: "POST",
                body: formData,
                credentials: "include"
            });

            const text = await response.text();
            let result;

            try {
                result = JSON.parse(text);
            } catch (err) {
                console.error("JSON parse error:", err, "Response:", text);
                showMessage("Server error. Please try again.", "error");
                unlockInputs();
                refreshLoginButton();
                return;
            }

            // NON-EXISTENT ACCOUNT
            if (result.account_missing) {
                showMessage("An Account Not exist.", "error");
                unlockInputs();
                refreshLoginButton();
                return;
            }

            if (result.disabled) {
                showMessage(result.message || "Your account has been disabled. Please contact your administrator.", "error");
                unlockInputs();
                refreshLoginButton();
                return;
            }

            if (result.consent_required) {
                showMessage(result.message || "You must agree to the Privacy Notice and Terms & Conditions to continue.", "error");
                applyConsentRequired();
                loginBtn.textContent = "Sign in";
                unlockInputs();
                refreshLoginButton();
                return;
            }

            // =========================================================
            // SUCCESS + TRUST THE SERVER FOR REDIRECT
            // =========================================================
            if (result.success) {
                const role = (result.role || '').toLowerCase().trim();
                const loginType = (result.login_type || 'user').toLowerCase().trim();
                let redirectUrl = result.dashboard_url || '';

                if (!redirectUrl) {
                    redirectUrl = "public/pages/dashboard.php";

                    if (loginType === 'department' || role === 'department') {
                        redirectUrl = "public/pages/dean_dashboard.php";
                    } else if (role === "dean" || role === "user") {
                        redirectUrl = "public/pages/dean_dashboard.php";
                    } else if (
                        role === "employee" ||
                        role === "laboratory manager" ||
                        role === "canvasser"
                    ) {
                        if (result.canvasser_workspace) {
                            redirectUrl = "public/pages/canvasser_dashboard.php";
                        } else {
                            redirectUrl = "public/pages/employee_dashboard.php";
                        }
                    } else if (role === "inventory_manager" || role === "inventory manager") {
                        redirectUrl = "public/pages/dashboard.php";
                    } else if (role === "comptroller") {
                        redirectUrl = "public/pages/comptroller_dashboard.php";
                    } else if (role === "gsd officer") {
                        redirectUrl = "public/pages/gsd_dashboard.php";
                    } else if (
                        role === "president" ||
                        role === "president verifier" ||
                        role === "verifier president" ||
                        role === "president_verifier"
                    ) {
                        redirectUrl = "public/pages/president_dashboard.php";
                    }
                }

                // Hide the login modal so only the loading screen shows.
                const loginModalEl = document.getElementById("loginModal");
                if (loginModalEl) loginModalEl.classList.remove("display-active");

                showLoadingScreen("Logging in");

                // Record time-in for regular users (not department accounts)
                if (result.login_type !== 'department') {
                    fetch("app/api/time_in.php", { method: "POST", credentials: "include" })
                        .then(r => r.json())
                        .then(data => console.log("Time in recorded:", data))
                        .catch(err => console.error("Time in error:", err));
                }

                setTimeout(() => {
                    // Replace the login page in history so Back can't return to it.
                    window.location.replace(redirectUrl);
                }, 1500);
                return;
            }

            // BLOCKED
            if (result.blocked) {
                const secs = result.remaining_seconds !== undefined ? result.remaining_seconds : 5;
                startLockout(secs);
                return;
            }

            // FAILED ATTEMPT
            const remaining = result.remaining !== undefined ? result.remaining : 0;
            if (remaining > 0) {
                const label = loginIdentifier.includes('@') ? 'email or password' : 'username or password';
                showMessage(`Wrong ${label}. ${remaining} attempt(s) left.`, "error");
                loginBtn.textContent = "Sign in";
                unlockInputs();
                refreshLoginButton();
            } else {
                startLockout(5);
            }

        } catch (err) {
            showMessage("Cannot connect to server. Check your internet connection.", "error");
            loginBtn.textContent = "Sign in";
            unlockInputs();
            refreshLoginButton();
        }
    });
});