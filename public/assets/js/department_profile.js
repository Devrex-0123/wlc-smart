(function () {
  const root = document.getElementById("departmentProfilePage");
  if (!root) return;

  const apiUrl = root.dataset.apiUrl || "../../app/api/department_profile.php";
  const profilePhotoImg = document.getElementById("profilePhotoImg");
  const profilePhotoInitial = document.getElementById("profilePhotoInitial");
  const profilePhotoInput = document.getElementById("profilePhotoInput");
  const uploadPhotoBtn = document.getElementById("uploadPhotoBtn");
  const changePasswordBtn = document.getElementById("changePasswordBtn");
  const passwordInlineError = document.getElementById("passwordInlineError");
  const currentPasswordEl = document.getElementById("current_password");
  const newPasswordEl = document.getElementById("new_password");
  const confirmPasswordEl = document.getElementById("confirm_password");
  const toastContainer = document.getElementById("toastContainer");

  const passwordConfirmModal = document.getElementById("passwordConfirmModal");
  const passwordConfirmBackdrop = document.getElementById("passwordConfirmBackdrop");
  const passwordConfirmCancel = document.getElementById("passwordConfirmCancel");
  const passwordConfirmOk = document.getElementById("passwordConfirmOk");

  const profileSuccessModal = document.getElementById("profileSuccessModal");
  const profileSuccessBackdrop = document.getElementById("profileSuccessBackdrop");
  const profileSuccessMessage = document.getElementById("profileSuccessMessage");
  const profileSuccessOk = document.getElementById("profileSuccessOk");

  let successModalOnClose = null;

  function showToast(text) {
    if (!toastContainer || !text) return;
    const div = document.createElement("div");
    div.className = "profile-toast";
    div.textContent = text;
    toastContainer.appendChild(div);
    setTimeout(() => div.remove(), 4000);
  }

  async function parseApiResponse(res) {
    const raw = await res.text();
    try {
      return JSON.parse(raw);
    } catch (_) {
      return { success: false, message: "Server returned invalid response." };
    }
  }

  function getAbbrevInitials(abbrev) {
    const value = String(abbrev || "D").trim();
    return value ? value.charAt(0).toUpperCase() : "D";
  }

  function applyPhoto(url, initials) {
    if (url) {
      profilePhotoImg.src = `../${url}`;
      profilePhotoImg.style.display = "block";
      profilePhotoInitial.style.display = "none";
      const sidebarImg = document.querySelector(".sidebar-footer .user-avatar-img");
      if (sidebarImg) {
        sidebarImg.src = `../${url}`;
        sidebarImg.style.display = "block";
        const sidebarInitial = document.querySelector(".sidebar-footer .user-avatar-initials");
        if (sidebarInitial) sidebarInitial.style.display = "none";
      }
      return;
    }
    profilePhotoImg.src = "";
    profilePhotoImg.style.display = "none";
    profilePhotoInitial.textContent = initials || "D";
    profilePhotoInitial.style.display = "inline";
  }

  function setPasswordError(text) {
    passwordInlineError.textContent = text || "";
  }

  function openConfirmModal(modal) {
    if (!modal) return;
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
  }

  function closeConfirmModal(modal) {
    if (!modal) return;
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
  }

  function openSuccessModal(message, onClose) {
    if (!profileSuccessModal || !profileSuccessMessage) return;
    successModalOnClose = typeof onClose === "function" ? onClose : null;
    profileSuccessMessage.textContent = message;
    profileSuccessModal.classList.add("is-open");
    profileSuccessModal.setAttribute("aria-hidden", "false");
    profileSuccessOk?.focus();
  }

  function closeSuccessModal() {
    if (!profileSuccessModal) return;
    profileSuccessModal.classList.remove("is-open");
    profileSuccessModal.setAttribute("aria-hidden", "true");
    const callback = successModalOnClose;
    successModalOnClose = null;
    if (callback) callback();
  }

  function evaluatePassword(pwd) {
    const tests = {
      length: pwd.length >= 8,
      uppercase: /[A-Z]/.test(pwd),
      lowercase: /[a-z]/.test(pwd),
      number: /\d/.test(pwd),
      special: /[@$!%*?&#\-_.]/.test(pwd),
    };

    ["length", "uppercase", "lowercase", "number", "special"].forEach((key) => {
      const el = document.getElementById(`pwd-${key}`);
      if (el) el.classList.toggle("requirement-met", tests[key]);
    });

    const met = Object.values(tests).filter(Boolean).length;
    const strength = document.getElementById("passwordStrength");
    if (strength) {
      strength.textContent = met === 5 ? "Strong password" : met >= 3 ? "Medium password" : "Weak password";
      strength.style.color = met === 5 ? "#16a34a" : met >= 3 ? "#d97706" : "#dc2626";
    }

    return Object.values(tests).every(Boolean);
  }

  function setProfileFromResponse(dept) {
    const name = dept.department_name || "Department";
    const abbrev = dept.department_abbreviation || "D";

    document.getElementById("department_abbreviation").value = abbrev;
    document.getElementById("department_type").value = dept.department_type || "";
    document.getElementById("department_username").value = dept.department_username || "";

    const statusText = document.getElementById("status_text");
    if (statusText) statusText.textContent = dept.department_status || "Active";

    const headerName = document.getElementById("profileHeaderName");
    if (headerName) headerName.textContent = name;

    const sidebarName = document.querySelector(".sidebar-footer .user-details h4");
    if (sidebarName) sidebarName.textContent = String(abbrev).toUpperCase();

    applyPhoto(dept.department_photo_url || "", getAbbrevInitials(abbrev));
  }

  async function loadProfile() {
    const res = await fetch(`${apiUrl}?action=get`, { credentials: "include" });
    const data = await parseApiResponse(res);
    if (!data.success) {
      showToast(data.message || "Failed to load department profile");
      return;
    }
    setProfileFromResponse(data.department || {});
  }

  function redirectToLogin() {
    window.location.href = "../../index.php";
  }

  const uploadPhotoBtnDefaultHtml =
    '<i class="fas fa-upload" aria-hidden="true"></i> Upload photo';
  const changePasswordBtnDefaultHtml =
    '<i class="fas fa-lock" aria-hidden="true"></i> Update password';

  profilePhotoInput?.addEventListener("change", function () {
    const file = profilePhotoInput.files && profilePhotoInput.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (event) {
      profilePhotoImg.src = event.target.result;
      profilePhotoImg.style.display = "block";
      profilePhotoInitial.style.display = "none";
    };
    reader.readAsDataURL(file);
  });

  uploadPhotoBtn?.addEventListener("click", async function () {
    const file = profilePhotoInput.files && profilePhotoInput.files[0];
    if (!file) {
      profilePhotoInput.click();
      return;
    }
    uploadPhotoBtn.disabled = true;
    uploadPhotoBtn.textContent = "Uploading...";
    try {
      const form = new FormData();
      form.append("action", "update_photo");
      form.append("photo", file);
      const res = await fetch(apiUrl, { method: "POST", credentials: "include", body: form });
      const data = await parseApiResponse(res);
      if (data.success) {
        profilePhotoInput.value = "";
        await loadProfile();
        showToast("Department photo updated.");
      } else {
        showToast(data.message || "Upload failed.");
      }
    } finally {
      uploadPhotoBtn.disabled = false;
      uploadPhotoBtn.innerHTML = uploadPhotoBtnDefaultHtml;
    }
  });

  newPasswordEl?.addEventListener("input", function () {
    evaluatePassword(newPasswordEl.value);
    setPasswordError("");
  });

  async function submitPasswordChange() {
    changePasswordBtn.disabled = true;
    changePasswordBtn.textContent = "Updating...";
    try {
      const body = new URLSearchParams({
        action: "change_password",
        current_password: currentPasswordEl.value,
        new_password: newPasswordEl.value,
        confirm_password: confirmPasswordEl.value,
      });
      const res = await fetch(apiUrl, { method: "POST", credentials: "include", body });
      const data = await parseApiResponse(res);
      if (!data.success) {
        setPasswordError(data.message || "Failed to update password.");
        return;
      }
      if (data.logout_required) {
        openSuccessModal("Your password has been changed successfully.", redirectToLogin);
        return;
      }
      openSuccessModal("Your password has been changed successfully.");
      clearPasswordFields();
      await loadProfile();
    } finally {
      changePasswordBtn.disabled = false;
      changePasswordBtn.innerHTML = changePasswordBtnDefaultHtml;
    }
  }

  function clearPasswordFields() {
    if (currentPasswordEl) currentPasswordEl.value = "";
    if (newPasswordEl) newPasswordEl.value = "";
    if (confirmPasswordEl) confirmPasswordEl.value = "";
    setPasswordError("");
    evaluatePassword("");
  }

  function closePasswordConfirmModal() {
    closeConfirmModal(passwordConfirmModal);
    clearPasswordFields();
  }

  changePasswordBtn?.addEventListener("click", function () {
    setPasswordError("");
    if (!evaluatePassword(newPasswordEl.value)) {
      setPasswordError("Password does not meet all requirements.");
      return;
    }
    if (newPasswordEl.value !== confirmPasswordEl.value) {
      setPasswordError("New password and confirm password do not match.");
      return;
    }
    if (!currentPasswordEl.value) {
      setPasswordError("Current password is required.");
      return;
    }
    openConfirmModal(passwordConfirmModal);
  });

  passwordConfirmBackdrop?.addEventListener("click", closePasswordConfirmModal);
  passwordConfirmCancel?.addEventListener("click", closePasswordConfirmModal);
  passwordConfirmOk?.addEventListener("click", () => {
    closeConfirmModal(passwordConfirmModal);
    submitPasswordChange();
  });

  profileSuccessBackdrop?.addEventListener("click", closeSuccessModal);
  profileSuccessOk?.addEventListener("click", closeSuccessModal);

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    if (profileSuccessModal?.classList.contains("is-open")) {
      closeSuccessModal();
      return;
    }
    if (passwordConfirmModal?.classList.contains("is-open")) {
      closePasswordConfirmModal();
    }
  });

  document.querySelectorAll(".password-toggle").forEach(function (toggleBtn) {
    toggleBtn.addEventListener("click", function () {
      const targetId = toggleBtn.getAttribute("data-target");
      const input = targetId ? document.getElementById(targetId) : null;
      if (!input) return;
      const icon = toggleBtn.querySelector("i");
      const showPassword = input.type === "password";
      input.type = showPassword ? "text" : "password";
      if (icon) {
        icon.classList.toggle("fa-eye", !showPassword);
        icon.classList.toggle("fa-eye-slash", showPassword);
      }
    });
  });

  evaluatePassword("");
  loadProfile();
})();
