(function () {
  const root = document.getElementById("myProfilePage");
  if (!root) return;

  const msg = document.getElementById("msg");
  const fullNameEl = document.getElementById("full_name");
  const contactEl = document.getElementById("contact_number");
  const saveBtn = document.getElementById("saveProfileBtn");
  const editBtn = document.getElementById("editProfileBtn");
  const cancelBtn = document.getElementById("cancelProfileBtn");
  const profilePhotoImg = document.getElementById("profilePhotoImg");
  const profilePhotoInitial = document.getElementById("profilePhotoInitial");
  const profilePhotoInput = document.getElementById("profilePhotoInput");
  const uploadPhotoBtn = document.getElementById("uploadPhotoBtn");
  const changePasswordBtn = document.getElementById("changePasswordBtn");
  const passwordInlineError = document.getElementById("passwordInlineError");
  const currentPasswordEl = document.getElementById("current_password");
  const newPasswordEl = document.getElementById("new_password");
  const confirmPasswordEl = document.getElementById("confirm_password");

  const apiUrl = root.dataset.apiUrl || "../../app/api/my_profile.php";

  let originalProfile = {
    full_name: fullNameEl.value || "",
    contact_number: contactEl.value || ""
  };

  function notify(text, ok) {
    msg.textContent = text || "";
    msg.style.color = ok ? "#166534" : "#991b1b";
  }

  async function parseApiResponse(res) {
    const raw = await res.text();
    try {
      return JSON.parse(raw);
    } catch (_) {
      return { success: false, message: "Server returned invalid response." };
    }
  }

  function setPasswordError(text) {
    passwordInlineError.textContent = text || "";
  }

  function formatDate(value) {
    if (!value) return "—";
    const dt = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(dt.getTime())) return value;
    return dt.toLocaleString();
  }

  function setEditMode(enabled) {
    fullNameEl.disabled = !enabled;
    contactEl.disabled = !enabled;
    editBtn.classList.toggle("is-hidden", enabled);
    saveBtn.classList.toggle("is-hidden", !enabled);
    cancelBtn.classList.toggle("is-hidden", !enabled);
    if (!enabled) saveBtn.disabled = true;
  }

  function refreshSaveButtonState() {
    const changed =
      fullNameEl.value.trim() !== (originalProfile.full_name || "") ||
      contactEl.value.trim() !== (originalProfile.contact_number || "");
    saveBtn.disabled = !changed;
  }

  function applyPhoto(url, initials) {
    if (url) {
      profilePhotoImg.src = `../${url}`;
      profilePhotoImg.style.display = "block";
      profilePhotoInitial.style.display = "none";
      return;
    }
    profilePhotoImg.src = "";
    profilePhotoImg.style.display = "none";
    profilePhotoInitial.textContent = initials || "U";
    profilePhotoInitial.style.display = "inline";
  }

  function evaluatePassword(pwd) {
    const tests = {
      length: pwd.length >= 8,
      uppercase: /[A-Z]/.test(pwd),
      lowercase: /[a-z]/.test(pwd),
      number: /\d/.test(pwd),
      special: /[@$!%*?&#\-_.]/.test(pwd)
    };

    ["length", "uppercase", "lowercase", "number", "special"].forEach((key) => {
      const el = document.getElementById(`pwd-${key}`);
      if (el) el.classList.toggle("requirement-met", tests[key]);
    });

    const met = Object.values(tests).filter(Boolean).length;
    const strength = document.getElementById("passwordStrength");
    if (strength) {
      strength.textContent = met === 5 ? "Strong password" : met >= 3 ? "Medium password" : "Weak password";
      strength.style.color = met === 5 ? "#10b981" : met >= 3 ? "#f59e0b" : "#ef4444";
    }

    return Object.values(tests).every(Boolean);
  }

  function setProfileFromResponse(u) {
    const displayName = (u.full_name || u.email || "U").trim();
    originalProfile = { full_name: u.full_name || "", contact_number: u.contact_number || "" };
    fullNameEl.value = u.full_name || "";
    contactEl.value = u.contact_number || "";
    document.getElementById("email").value = u.email || "";
    document.getElementById("role").value = u.role || "";
    document.getElementById("office").value = u.office_name || "";
    document.getElementById("password_updated_at").value = formatDate(u.password_updated_at);
    applyPhoto(u.photo_url || "", displayName.charAt(0).toUpperCase());
    const sidebarName = document.querySelector(".sidebar-footer .user-details h4");
    if (sidebarName) {
      sidebarName.textContent = displayName;
    }
    const consentBadge = document.getElementById("consent_badge");
    consentBadge.textContent = Number(u.has_consented) === 1 ? "Accepted" : "Pending";
    consentBadge.classList.toggle("accepted", Number(u.has_consented) === 1);
    document.getElementById("viewMyData").innerHTML =
      `<strong>My Data Snapshot</strong><br>
      Full Name: ${u.full_name || "—"}<br>
      Email: ${u.email || "—"}<br>
      Role: ${u.role || "—"}<br>
      Office: ${u.office_name || "—"}<br>
      Contact Number: ${u.contact_number || "—"}<br>
      Consent: ${Number(u.has_consented) === 1 ? `Accepted (${u.consent_version || "n/a"})` : "Not yet accepted"}`;
    setEditMode(false);
  }

  async function loadProfile() {
    const res = await fetch(`${apiUrl}?action=get`, { credentials: "include" });
    const data = await parseApiResponse(res);
    if (!data.success) {
      notify(data.message || "Failed to load profile");
      return;
    }
    setProfileFromResponse(data.user || {});
  }

  editBtn.addEventListener("click", function () {
    setEditMode(true);
    refreshSaveButtonState();
  });

  cancelBtn.addEventListener("click", function () {
    fullNameEl.value = originalProfile.full_name || "";
    contactEl.value = originalProfile.contact_number || "";
    setEditMode(false);
    notify("");
  });

  fullNameEl.addEventListener("input", refreshSaveButtonState);
  contactEl.addEventListener("input", refreshSaveButtonState);

  saveBtn.addEventListener("click", async function () {
    saveBtn.disabled = true;
    saveBtn.textContent = "Saving...";
    try {
      const body = new URLSearchParams({
        action: "update_profile",
        full_name: fullNameEl.value.trim(),
        contact_number: contactEl.value.trim()
      });
      const res = await fetch(apiUrl, { method: "POST", credentials: "include", body });
      const data = await parseApiResponse(res);
      notify(data.message || "Updated", !!data.success);
      if (data.success) {
        await loadProfile();
      } else {
        refreshSaveButtonState();
      }
    } catch (_) {
      notify("Unable to save profile right now.");
      refreshSaveButtonState();
    } finally {
      saveBtn.disabled = false;
      saveBtn.textContent = "Save Changes";
    }
  });

  newPasswordEl.addEventListener("input", function () {
    evaluatePassword(newPasswordEl.value);
    setPasswordError("");
  });

  changePasswordBtn.addEventListener("click", async function () {
    setPasswordError("");
    const validPassword = evaluatePassword(newPasswordEl.value);
    if (!validPassword) {
      setPasswordError("Password does not meet all requirements.");
      return;
    }
    if (newPasswordEl.value !== confirmPasswordEl.value) {
      setPasswordError("New password and confirm password do not match.");
      return;
    }

    changePasswordBtn.disabled = true;
    changePasswordBtn.textContent = "Updating...";
    try {
      const body = new URLSearchParams({
        action: "change_password",
        current_password: currentPasswordEl.value,
        new_password: newPasswordEl.value,
        confirm_password: confirmPasswordEl.value
      });
      const res = await fetch(apiUrl, { method: "POST", credentials: "include", body });
      const data = await parseApiResponse(res);
      if (!data.success) {
        setPasswordError(data.message || "Failed to update password.");
      } else {
        notify(data.message || "Password updated.", true);
        currentPasswordEl.value = "";
        newPasswordEl.value = "";
        confirmPasswordEl.value = "";
        evaluatePassword("");
        await loadProfile();
      }
    } finally {
      changePasswordBtn.disabled = false;
      changePasswordBtn.textContent = "Update Password";
    }
  });

  profilePhotoInput.addEventListener("change", function () {
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

  uploadPhotoBtn.addEventListener("click", async function () {
    const file = profilePhotoInput.files && profilePhotoInput.files[0];
    if (!file) {
      notify("Please choose an image file first.");
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
      notify(data.message || "Upload complete", !!data.success);
      if (data.success) {
        profilePhotoInput.value = "";
        await loadProfile();
      }
    } finally {
      uploadPhotoBtn.disabled = false;
      uploadPhotoBtn.textContent = "Upload Photo";
    }
  });

  evaluatePassword("");
  loadProfile();

  (function setupLegalModals() {
    const privacyModal = document.getElementById("privacyModal");
    const termsModal = document.getElementById("termsModal");
    const openPrivacyBtn = document.getElementById("openPrivacyNoticeBtn");
    const openTermsBtn = document.getElementById("openTermsModalBtn");
    const privacyClose = document.getElementById("privacyModalClose");
    const termsClose = document.getElementById("termsModalClose");

    if (!privacyModal || !termsModal || !openPrivacyBtn || !openTermsBtn) return;

    let lastFocus = null;

    function openModal(modal) {
      lastFocus = document.activeElement;
      modal.removeAttribute("hidden");
      const closeEl = modal.querySelector(".profile-modal-close");
      if (closeEl) closeEl.focus();
    }

    function closeModal(modal) {
      modal.setAttribute("hidden", "");
      if (lastFocus && typeof lastFocus.focus === "function") {
        lastFocus.focus();
      }
      lastFocus = null;
    }

    openPrivacyBtn.addEventListener("click", function () {
      openModal(privacyModal);
    });
    openTermsBtn.addEventListener("click", function () {
      openModal(termsModal);
    });

    if (privacyClose) {
      privacyClose.addEventListener("click", function () {
        closeModal(privacyModal);
      });
    }
    if (termsClose) {
      termsClose.addEventListener("click", function () {
        closeModal(termsModal);
      });
    }

    privacyModal.addEventListener("click", function (e) {
      if (e.target === privacyModal) closeModal(privacyModal);
    });
    termsModal.addEventListener("click", function (e) {
      if (e.target === termsModal) closeModal(termsModal);
    });

    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      if (!privacyModal.hasAttribute("hidden")) closeModal(privacyModal);
      else if (!termsModal.hasAttribute("hidden")) closeModal(termsModal);
    });
  })();
})();
