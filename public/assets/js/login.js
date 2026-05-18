document.addEventListener("DOMContentLoaded", function () {
    const loginForm = document.getElementById("loginForm");
    const modalAlert = document.getElementById("modalAlert");
    const loginBtn = document.getElementById("modalLoginBtn") || document.querySelector(".modal-login-btn");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const privacyCheckbox = document.getElementById("privacyCheckbox");
    const privacyLink = document.getElementById("privacyLink");
    const termsLink = document.getElementById("termsLink");
    const consentHint = document.getElementById("consentHint");
    const CURRENT_CONSENT_VERSION = "v1.0";
    let consentLookupTimer = null;
    let consentLookupRequestSeq = 0;

    // Clear form inputs on page load/refresh unless returning from privacy policy
    window.addEventListener('load', () => {
        const privacySource = sessionStorage.getItem('privacySource');
        
        if (privacySource === 'login') {
            // User is returning from privacy policy - restore form inputs
            const savedEmail = sessionStorage.getItem('loginEmail');
            const savedPassword = sessionStorage.getItem('loginPassword');
            
            if (savedEmail) emailInput.value = savedEmail;
            if (savedPassword) passwordInput.value = savedPassword;
            
            // Clear the saved data after restoring
            sessionStorage.removeItem('privacySource');
            sessionStorage.removeItem('loginEmail');
            sessionStorage.removeItem('loginPassword');
        } else {
            // Normal page load/refresh - clear all form inputs
            emailInput.value = '';
            passwordInput.value = '';
        }

        if (privacyCheckbox) {
            privacyCheckbox.checked = false;
        }

        // Ensure login button remains enabled (consent is validated on submit)
        if (loginBtn) {
            loginBtn.disabled = false;
            loginBtn.style.opacity = "";
            loginBtn.style.cursor = "";
        }
    });

    // Handle checkbox toggle
    if (privacyCheckbox) {
        privacyCheckbox.addEventListener('change', function () {
            if (consentHint) consentHint.textContent = "";
        });
    }

    emailInput.addEventListener("input", function () {
        const email = this.value.trim();
        const normalizedEmail = email.toLowerCase();
        const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail);

        // Default to unchecked for changed/invalid email.
        if (privacyCheckbox) {
            privacyCheckbox.checked = false;
        }

        if (consentLookupTimer) clearTimeout(consentLookupTimer);
        if (!validEmail) return;

        consentLookupTimer = setTimeout(async () => {
            const requestId = ++consentLookupRequestSeq;
            const existingConsent = await hasExistingConsent(normalizedEmail);

            // Ignore stale async responses from previous email values.
            if (requestId !== consentLookupRequestSeq) return;
            if (emailInput.value.trim().toLowerCase() !== normalizedEmail) return;

            if (existingConsent && privacyCheckbox) {
                privacyCheckbox.checked = true;
                if (consentHint) consentHint.textContent = "";
            }
        }, 250);
    });

    // Handle privacy policy link click
    if (privacyLink) {
        privacyLink.addEventListener("click", function (e) {
            e.preventDefault();
            // Save current form inputs only if they have values
            if (emailInput.value.trim()) {
                sessionStorage.setItem('loginEmail', emailInput.value);
                sessionStorage.setItem('loginPassword', passwordInput.value);
            }
            sessionStorage.setItem('privacySource', 'login');
            // Navigate to privacy policy
            window.location.href = "public/pages/privacy_policy.php?return=login";
        });
    }

    if (termsLink) {
        termsLink.addEventListener("click", function (e) {
            e.preventDefault();
            // Save current form inputs only if they have values
            if (emailInput.value.trim()) {
                sessionStorage.setItem('loginEmail', emailInput.value);
                sessionStorage.setItem('loginPassword', passwordInput.value);
            }
            sessionStorage.setItem('privacySource', 'login');
            window.location.href = "public/pages/terms_conditions.php?return=login";
        });
    }

    // Optional prompt when the login modal opens
    const modal = document.getElementById("loginModal");
    if (modal) {
        let consentPromptShown = sessionStorage.getItem("wlcSmartConsentPromptShown") === "1";

        const observer = new MutationObserver(() => {
            if (!modal.classList.contains("show")) return;
            if (consentPromptShown) return;

            if (consentHint) {
                consentHint.textContent = "Please review and accept the Privacy Notice and Terms & Conditions to continue.";
            }
            sessionStorage.setItem("wlcSmartConsentPromptShown", "1");
            consentPromptShown = true;
        });

        observer.observe(modal, { attributes: true, attributeFilter: ["class"] });
    }

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

    function hideLoadingScreen() {
        const loadingScreen = document.getElementById("loadingScreen");
        if (loadingScreen) {
            loadingScreen.classList.add("hide");
            setTimeout(() => loadingScreen.remove(), 500);
        }
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

    async function hasExistingConsent(email) {
        try {
            const body = new URLSearchParams({
                email: email.trim().toLowerCase(),
                consent_version: CURRENT_CONSENT_VERSION
            });
            const res = await fetch("app/api/consent_status.php", {
                method: "POST",
                body,
                credentials: "include"
            });
            const data = await res.json();
            return Boolean(data && data.success && data.consent_current);
        } catch (_) {
            return false;
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
            if (loginBtn.disabled) {
                loginBtn.disabled = false;
                loginBtn.textContent = "Login";
                unlockInputs();
                clearMessage();
                if (countdownInterval) clearInterval(countdownInterval);
                if (lockoutTimeout) clearTimeout(lockoutTimeout);
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
                loginBtn.disabled = false;
                loginBtn.textContent = "Login";
                unlockInputs();
                clearMessage();
                resetServerAttempts();
            }
        }, 1000);

        lockoutTimeout = setTimeout(() => {
            if (countdownInterval) clearInterval(countdownInterval);
            loginBtn.disabled = false;
            loginBtn.textContent = "Login";
            unlockInputs();
            clearMessage();
            resetServerAttempts();
        }, secondsLeft * 1000);
    }

    loginForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        if (loginBtn.disabled) return;

        // Consent checkbox is required only when DB shows no consent yet.
        if (!privacyCheckbox.checked) {
            const existingConsent = await hasExistingConsent(emailInput.value);
            if (!existingConsent) {
                showMessage("You must agree to the Privacy Notice and Terms & Conditions to continue.", "error");
                privacyCheckbox.focus();
                return;
            }
        }

        // Basic input validation (friendly UX)
        const emailValue = emailInput.value.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
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

        const normalizedEmail = emailInput.value.trim().toLowerCase();
        emailInput.value = normalizedEmail;

        const formData = new FormData(loginForm);

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
            } catch (e) {
                console.error("JSON parse error:", e, "Response:", text);
                showMessage("Server error. Please try again.", "error");
                loginBtn.disabled = false;
                loginBtn.textContent = "Login";
                unlockInputs();
                return;
            }

            // NON-EXISTENT ACCOUNT
            if (result.account_missing) {
                showMessage("An Account Not exist.", "error");
                loginBtn.disabled = false;
                loginBtn.textContent = "Login";
                unlockInputs();
                return;
            }

            if (result.disabled) {
                showMessage(result.message || "Your account has been disabled. Please contact your administrator.", "error");
                loginBtn.disabled = false;
                loginBtn.textContent = "Login";
                unlockInputs();
                return;
            }

            // SUCCESS + ROLE-BASED REDIRECT
            if (result.success) {
                // Role-based redirection
                const role = (result.role || '').toLowerCase().trim();
                let redirectUrl = "public/pages/dashboard.php"; // default

                if (role === "dean") {
                    redirectUrl = "public/pages/dean_dashboard.php";
                } else if (
                    role === "employee" ||
                    role === "user" ||
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

                showMessage("Login successful! Redirecting...", "success");
                showLoadingScreen("Logging in");

                // Record time-in
                fetch("app/api/time_in.php", { method: "POST", credentials: "include" })
                    .then(r => r.json())
                    .then(data => console.log("Time in recorded:", data))
                    .catch(err => console.error("Time in error:", err));

                setTimeout(() => {
                    window.location.href = redirectUrl;
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
                showMessage(`Wrong email or password. ${remaining} attempt(s) left.`, "error");
                loginBtn.disabled = false;
                loginBtn.textContent = "Login";
                unlockInputs();
            } else {
                startLockout(5);
            }

        } catch (err) {
            showMessage("Cannot connect to server. Check your internet connection.", "error");
            loginBtn.disabled = false;
            loginBtn.textContent = "Login";
        }
    });
});
