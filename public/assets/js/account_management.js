// ---------------- Sidebar & Toast (unchanged) ----------------
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
if (window.innerWidth <= 768) mobileMenuBtn.style.display = 'block';
mobileMenuBtn?.addEventListener('click', () => sidebar.classList.toggle('open'));
document.addEventListener('click', e => {
    if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target) && sidebar.classList.contains('open')) 
        sidebar.classList.remove('open');
});
window.addEventListener('resize', () => {
    mobileMenuBtn.style.display = window.innerWidth > 768 ? 'none' : 'block';
    if (window.innerWidth > 768) sidebar.classList.remove('open');
});

function showToast(msg, type='success') {
    const container = document.getElementById('toastContainer');
    const div = document.createElement('div');
    div.className = `toast ${type}`;
    div.textContent = msg;
    container.appendChild(div);
    setTimeout(() => { div.remove(); }, 4000);
}

// ---------------- Elements ----------------
const modal = document.getElementById('userModal');
const closeModal = document.getElementById('closeModal');
const addUserBtn = document.getElementById('addUserBtn');
const addDepartmentBtn = document.getElementById('addDepartmentBtn');
const modalTitle = document.getElementById('modalTitle');
const userForm = document.getElementById('userForm');
const departmentModal = document.getElementById('departmentModal');
const departmentForm = document.getElementById('departmentForm');
const closeDepartmentModal = document.getElementById('closeDepartmentModal');
const saveDepartmentBtn = document.getElementById('saveDepartmentBtn');
const departmentPasswordInput = document.getElementById('department_password');
const departmentConfirmPasswordInput = document.getElementById('department_confirm_password');
const departmentPhotoInput = document.getElementById('department_photo');
const departmentPhotoPreview = document.getElementById('departmentPhotoPreview');
const departmentPhotoPlaceholder = document.getElementById('departmentPhotoPlaceholder');
const adminUsersTableBody = document.getElementById('adminUsersTableBody');
const deptUsersTableBody = document.getElementById('deptUsersTableBody');
const accountManagementRoot = document.getElementById('accountManagementRoot');
const adminUsersCountBadge = document.getElementById('adminUsersCountBadge');
const deptUsersCountBadge = document.getElementById('deptUsersCountBadge');
const adminPageInfo = document.getElementById('adminPageInfo');
const deptPageInfo = document.getElementById('deptPageInfo');
const adminPageNum = document.getElementById('adminPageNum');
const deptPageNum = document.getElementById('deptPageNum');
const adminPrevBtn = document.getElementById('adminPrevBtn');
const adminNextBtn = document.getElementById('adminNextBtn');
const deptPrevBtn = document.getElementById('deptPrevBtn');
const deptNextBtn = document.getElementById('deptNextBtn');

const USERS_PER_PAGE = 5;
let adminCurrentPage = 1;
let deptCurrentPage = 1;
let filteredAdminUsers = [];
const photoInput = document.getElementById('photo');
const photoPreview = document.getElementById('photoPreview');
const photoPlaceholder = document.getElementById('photoPlaceholder');
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm_password');
const saveBtn = document.getElementById('saveBtn');
// Photo lightbox elements
const photoLightbox = document.getElementById('photoLightbox');
const photoLightboxImg = document.getElementById('photoLightboxImg');
const photoLightboxMeta = document.getElementById('photoLightboxMeta');
const photoLightboxClose = document.querySelector('.photo-lightbox-close');

// ---------------- Delete confirmation modal ----------------
const deleteConfirmModal = document.getElementById('deleteConfirmModal');
const deleteConfirmBackdrop = document.getElementById('deleteConfirmBackdrop');
const deleteConfirmCancel = document.getElementById('deleteConfirmCancel');
const deleteConfirmOk = document.getElementById('deleteConfirmOk');
const deleteConfirmEmail = document.getElementById('deleteConfirmEmail');
const deleteConfirmTitle = document.getElementById('deleteConfirmTitle');
const deleteConfirmDesc = document.getElementById('deleteConfirmDesc');
const deleteConfirmIcon = document.querySelector('#deleteConfirmModal .delete-confirm-icon i');
let pendingDeleteUserId = null;
let pendingDeleteDepartmentId = null;

// ---------------- Disable account confirmation (same UX as delete modal) ----------------
const disableConfirmModal = document.getElementById('disableConfirmModal');
const disableConfirmBackdrop = document.getElementById('disableConfirmBackdrop');
const disableConfirmCancel = document.getElementById('disableConfirmCancel');
const disableConfirmOk = document.getElementById('disableConfirmOk');
const disableConfirmEmail = document.getElementById('disableConfirmEmail');
let pendingDisableUserId = null;

// ---------------- Enable account confirmation ----------------
const enableConfirmModal = document.getElementById('enableConfirmModal');
const enableConfirmBackdrop = document.getElementById('enableConfirmBackdrop');
const enableConfirmCancel = document.getElementById('enableConfirmCancel');
const enableConfirmOk = document.getElementById('enableConfirmOk');
const enableConfirmEmail = document.getElementById('enableConfirmEmail');
let pendingEnableUserId = null;

// ---------------- Save edit confirmation ----------------
const saveConfirmModal = document.getElementById('saveConfirmModal');
const saveConfirmBackdrop = document.getElementById('saveConfirmBackdrop');
const saveConfirmCancel = document.getElementById('saveConfirmCancel');
const saveConfirmOk = document.getElementById('saveConfirmOk');
let pendingDepartmentSave = false;

const accountSuccessModal = document.getElementById('accountSuccessModal');
const accountSuccessBackdrop = document.getElementById('accountSuccessBackdrop');
const accountSuccessMessage = document.getElementById('accountSuccessMessage');
const accountSuccessOk = document.getElementById('accountSuccessOk');

function openAccountSuccessModal(message) {
    if (accountSuccessMessage) {
        accountSuccessMessage.textContent = message;
    }
    if (accountSuccessModal) {
        accountSuccessModal.classList.add('is-open');
        accountSuccessModal.setAttribute('aria-hidden', 'false');
        accountSuccessOk?.focus();
    }
}

function closeAccountSuccessModal() {
    if (accountSuccessModal) {
        accountSuccessModal.classList.remove('is-open');
        accountSuccessModal.setAttribute('aria-hidden', 'true');
    }
}

function openSaveConfirmModal() {
    if (saveConfirmModal) {
        saveConfirmModal.classList.add('is-open');
        saveConfirmModal.setAttribute('aria-hidden', 'false');
        saveConfirmOk?.focus();
    }
}

function closeSaveConfirmModal() {
    pendingDepartmentSave = false;
    if (saveConfirmModal) {
        saveConfirmModal.classList.remove('is-open');
        saveConfirmModal.setAttribute('aria-hidden', 'true');
    }
}

const viewUserModal = document.getElementById('viewUserModal');
const viewUserBackdrop = document.getElementById('viewUserBackdrop');
const viewUserCloseBtn = document.getElementById('viewUserCloseBtn');
const viewDepartmentModal = document.getElementById('viewDepartmentModal');
const viewDepartmentBackdrop = document.getElementById('viewDepartmentBackdrop');
const viewDepartmentCloseBtn = document.getElementById('viewDepartmentCloseBtn');
const departmentModalTitle = document.getElementById('departmentModalTitle');

function formatDateTime(value) {
    if (!value) return '—';
    const dt = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(dt.getTime())) return String(value);
    return dt.toLocaleString();
}

function openViewUserModal(user) {
    if (!viewUserModal || !user) return;

    const fullName = user.full_name || '—';
    const email = user.email || '—';
    const roleLabel = isCanvasserAssignee(user) ? 'Canvasser' : (user.role || '—');
    const status = (user.account_status || 'active').toLowerCase();
    const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
    const consentText = user.has_consented == 1 ? `Accepted (${user.consent_version || 'n/a'})` : 'Not yet accepted';

    const heroName = document.getElementById('view_hero_name');
    const heroEmail = document.getElementById('view_hero_email');
    const heroRole = document.getElementById('view_hero_role');
    const viewPhoto = document.getElementById('view_photo');
    const viewPhotoPlaceholder = document.getElementById('view_photo_placeholder');
    const statusEl = document.getElementById('view_status');

    if (heroName) heroName.textContent = fullName;
    if (heroEmail) heroEmail.textContent = email;
    if (heroRole) heroRole.textContent = roleLabel;

    if (viewPhoto && viewPhotoPlaceholder) {
        if (user.photo_url) {
            viewPhoto.src = `../${user.photo_url}`;
            viewPhoto.alt = `Photo of ${email}`;
            viewPhoto.hidden = false;
            viewPhotoPlaceholder.style.display = 'none';
        } else {
            viewPhoto.hidden = true;
            viewPhoto.src = '';
            viewPhotoPlaceholder.textContent = (email !== '—' ? email : 'U').charAt(0).toUpperCase();
            viewPhotoPlaceholder.style.display = 'flex';
        }
    }

    const contactEl = document.getElementById('view_contact_number');
    const officeEl = document.getElementById('view_office');
    const contactLabel = document.getElementById('view_contact_label');
    const officeLabel = document.getElementById('view_office_label');
    const isDeptAccount = normalizeUserRole(user.role) === 'user';

    if (isDeptAccount) {
        if (contactLabel) contactLabel.innerHTML = '<i class="fas fa-tag" aria-hidden="true"></i> Abbreviation';
        if (officeLabel) officeLabel.innerHTML = '<i class="fas fa-layer-group" aria-hidden="true"></i> Department Type';
        if (contactEl) contactEl.textContent = user.abbreviation || '—';
        if (officeEl) officeEl.textContent = user.department_type || '—';
    } else {
        if (contactLabel) contactLabel.innerHTML = '<i class="fas fa-phone" aria-hidden="true"></i> Contact';
        if (officeLabel) officeLabel.innerHTML = '<i class="fas fa-map-marker-alt" aria-hidden="true"></i> Office';
        if (contactEl) contactEl.textContent = user.contact_number || '—';
        if (officeEl) officeEl.textContent = user.office_name || '—';
    }
    const consentEl = document.getElementById('view_consent');
    if (consentEl) consentEl.textContent = consentText;
    if (statusEl) {
        statusEl.innerHTML = `<span class="status-pill status-${status}">${statusLabel}</span>`;
    }
    const lastLoginEl = document.getElementById('view_last_login');
    if (lastLoginEl) lastLoginEl.textContent = formatDateTime(user.last_login);
    const createdEl = document.getElementById('view_created_at');
    if (createdEl) createdEl.textContent = formatDateTime(user.created_at);

    viewUserModal.classList.add('is-open');
    viewUserModal.setAttribute('aria-hidden', 'false');
}

function closeViewUserModal() {
    if (!viewUserModal) return;
    viewUserModal.classList.remove('is-open');
    viewUserModal.setAttribute('aria-hidden', 'true');
}

function openDeleteConfirmModal(userId, email) {
    pendingDeleteUserId = userId;
    pendingDeleteDepartmentId = null;
    if (deleteConfirmTitle) deleteConfirmTitle.textContent = 'Delete this user?';
    if (deleteConfirmDesc) {
        deleteConfirmDesc.textContent = 'This will soft-delete the account (can be restored from database records if needed).';
    }
    if (deleteConfirmIcon) deleteConfirmIcon.className = 'fas fa-user-slash';
    if (deleteConfirmOk) deleteConfirmOk.innerHTML = '<i class="fas fa-trash-alt"></i> Delete user';
    if (deleteConfirmEmail && email) {
        deleteConfirmEmail.textContent = email;
        deleteConfirmEmail.hidden = false;
    } else if (deleteConfirmEmail) {
        deleteConfirmEmail.textContent = '';
        deleteConfirmEmail.hidden = true;
    }
    if (deleteConfirmModal) {
        deleteConfirmModal.classList.add('is-open');
        deleteConfirmModal.setAttribute('aria-hidden', 'false');
        deleteConfirmOk?.focus();
    }
}

function openDeleteDepartmentConfirmModal(departmentId, label) {
    pendingDeleteDepartmentId = departmentId;
    pendingDeleteUserId = null;
    if (deleteConfirmTitle) deleteConfirmTitle.textContent = 'Delete this department?';
    if (deleteConfirmDesc) {
        deleteConfirmDesc.textContent = 'This will permanently remove the department from the system.';
    }
    if (deleteConfirmIcon) deleteConfirmIcon.className = 'fas fa-building';
    if (deleteConfirmOk) deleteConfirmOk.innerHTML = '<i class="fas fa-trash-alt"></i> Delete department';
    if (deleteConfirmEmail && label) {
        deleteConfirmEmail.textContent = label;
        deleteConfirmEmail.hidden = false;
    } else if (deleteConfirmEmail) {
        deleteConfirmEmail.textContent = '';
        deleteConfirmEmail.hidden = true;
    }
    if (deleteConfirmModal) {
        deleteConfirmModal.classList.add('is-open');
        deleteConfirmModal.setAttribute('aria-hidden', 'false');
        deleteConfirmOk?.focus();
    }
}

function closeDeleteConfirmModal() {
    pendingDeleteUserId = null;
    pendingDeleteDepartmentId = null;
    if (deleteConfirmModal) {
        deleteConfirmModal.classList.remove('is-open');
        deleteConfirmModal.setAttribute('aria-hidden', 'true');
    }
}

function openDisableConfirmModal(userId, email) {
    pendingDisableUserId = userId;
    if (disableConfirmEmail && email) {
        disableConfirmEmail.textContent = email;
        disableConfirmEmail.hidden = false;
    } else if (disableConfirmEmail) {
        disableConfirmEmail.textContent = '';
        disableConfirmEmail.hidden = true;
    }
    if (disableConfirmModal) {
        disableConfirmModal.classList.add('is-open');
        disableConfirmModal.setAttribute('aria-hidden', 'false');
        disableConfirmOk?.focus();
    }
}

function closeDisableConfirmModal() {
    pendingDisableUserId = null;
    if (disableConfirmModal) {
        disableConfirmModal.classList.remove('is-open');
        disableConfirmModal.setAttribute('aria-hidden', 'true');
    }
}

function openEnableConfirmModal(userId, email) {
    pendingEnableUserId = userId;
    if (enableConfirmEmail && email) {
        enableConfirmEmail.textContent = email;
        enableConfirmEmail.hidden = false;
    } else if (enableConfirmEmail) {
        enableConfirmEmail.textContent = '';
        enableConfirmEmail.hidden = true;
    }
    if (enableConfirmModal) {
        enableConfirmModal.classList.add('is-open');
        enableConfirmModal.setAttribute('aria-hidden', 'false');
        enableConfirmOk?.focus();
    }
}

function closeEnableConfirmModal() {
    pendingEnableUserId = null;
    if (enableConfirmModal) {
        enableConfirmModal.classList.remove('is-open');
        enableConfirmModal.setAttribute('aria-hidden', 'true');
    }
}

async function postToggleStatus(userId, nextStatus) {
    const res = await fetch('../../app/api/user_actions.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'toggle_status', user_id: String(userId), account_status: nextStatus }),
        credentials: 'include'
    });
    return res.json();
}

deleteConfirmBackdrop?.addEventListener('click', closeDeleteConfirmModal);
deleteConfirmCancel?.addEventListener('click', closeDeleteConfirmModal);
deleteConfirmOk?.addEventListener('click', async () => {
    if (pendingDeleteDepartmentId) {
        const departmentId = pendingDeleteDepartmentId;
        deleteConfirmOk.disabled = true;
        try {
            const res = await fetch('../../app/api/department_actions.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'delete', department_id: String(departmentId) }),
                credentials: 'include',
            });
            const data = await res.json();
            if (data.success) {
                closeDeleteConfirmModal();
                loadDepartments();
                openAccountSuccessModal('The department has been deleted successfully.');
            } else {
                showToast(data.message || 'Failed to delete department', 'error');
            }
        } catch (_) {
            showToast('Network error', 'error');
        } finally {
            deleteConfirmOk.disabled = false;
        }
        return;
    }

    if (!pendingDeleteUserId) return;
    const userId = pendingDeleteUserId;
    deleteConfirmOk.disabled = true;
    try {
        const res = await fetch('../../app/api/user_actions.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'delete', user_id: userId }),
            credentials: 'include'
        });
        const data = await res.json();
        if (data.success) {
            closeDeleteConfirmModal();
            loadUsers();
            openAccountSuccessModal('The user account has been deleted successfully.');
        } else {
            showToast(data.message || 'Failed to delete', 'error');
        }
    } catch (err) {
        showToast('Network error', 'error');
    } finally {
        deleteConfirmOk.disabled = false;
    }
});

disableConfirmBackdrop?.addEventListener('click', closeDisableConfirmModal);
disableConfirmCancel?.addEventListener('click', closeDisableConfirmModal);
disableConfirmOk?.addEventListener('click', async () => {
    if (!pendingDisableUserId) return;
    const userId = pendingDisableUserId;
    disableConfirmOk.disabled = true;
    try {
        const data = await postToggleStatus(userId, 'disabled');
        if (data.success) {
            closeDisableConfirmModal();
            loadUsers();
            openAccountSuccessModal('The account has been disabled successfully.');
        } else {
            showToast(data.message || 'Failed to update status', 'error');
        }
    } catch (_) {
        showToast('Network error', 'error');
    } finally {
        disableConfirmOk.disabled = false;
    }
});

enableConfirmBackdrop?.addEventListener('click', closeEnableConfirmModal);
enableConfirmCancel?.addEventListener('click', closeEnableConfirmModal);
enableConfirmOk?.addEventListener('click', async () => {
    if (!pendingEnableUserId) return;
    const userId = pendingEnableUserId;
    enableConfirmOk.disabled = true;
    try {
        const data = await postToggleStatus(userId, 'active');
        if (data.success) {
            closeEnableConfirmModal();
            loadUsers();
            openAccountSuccessModal('The account has been undisabled successfully.');
        } else {
            showToast(data.message || 'Failed to update status', 'error');
        }
    } catch (_) {
        showToast('Network error', 'error');
    } finally {
        enableConfirmOk.disabled = false;
    }
});

saveConfirmBackdrop?.addEventListener('click', closeSaveConfirmModal);
saveConfirmCancel?.addEventListener('click', closeSaveConfirmModal);
saveConfirmOk?.addEventListener('click', () => {
    const isDeptSave = pendingDepartmentSave;
    closeSaveConfirmModal();
    if (isDeptSave) {
        submitDepartmentForm();
        return;
    }
    submitUserForm();
});

accountSuccessBackdrop?.addEventListener('click', closeAccountSuccessModal);
accountSuccessOk?.addEventListener('click', closeAccountSuccessModal);

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && departmentModal?.style.display === 'flex') {
        closeDepartmentModalView();
    }
    if (e.key === 'Escape' && viewDepartmentModal?.classList.contains('is-open')) {
        closeViewDepartmentModal();
    }
    if (e.key === 'Escape' && deleteConfirmModal?.classList.contains('is-open')) {
        closeDeleteConfirmModal();
    }
    if (e.key === 'Escape' && disableConfirmModal?.classList.contains('is-open')) {
        closeDisableConfirmModal();
    }
    if (e.key === 'Escape' && enableConfirmModal?.classList.contains('is-open')) {
        closeEnableConfirmModal();
    }
    if (e.key === 'Escape' && saveConfirmModal?.classList.contains('is-open')) {
        closeSaveConfirmModal();
    }
    if (e.key === 'Escape' && accountSuccessModal?.classList.contains('is-open')) {
        closeAccountSuccessModal();
    }
    if (e.key === 'Escape' && viewUserModal?.classList.contains('is-open')) {
        closeViewUserModal();
    }
});
viewUserBackdrop?.addEventListener('click', closeViewUserModal);
viewUserCloseBtn?.addEventListener('click', closeViewUserModal);
document.getElementById('viewUserCloseFooterBtn')?.addEventListener('click', closeViewUserModal);

function openViewDepartmentModal(dept) {
    if (!viewDepartmentModal || !dept) return;

    const name = dept.department_name || '—';
    const abbrev = dept.department_abbreviation || '—';
    const statusRaw = (dept.department_status || 'Active').toLowerCase();
    const statusClass = statusRaw === 'active' ? 'active' : 'disabled';
    const photoUrl = dept.department_photo_url || '';

    const heroName = document.getElementById('view_dept_hero_name');
    const heroAbbrev = document.getElementById('view_dept_hero_abbrev');
    const heroType = document.getElementById('view_dept_hero_type');
    const viewPhoto = document.getElementById('view_dept_photo');
    const viewPhotoPlaceholder = document.getElementById('view_dept_photo_placeholder');
    const statusEl = document.getElementById('view_dept_status');

    if (heroName) heroName.textContent = name;
    if (heroAbbrev) heroAbbrev.textContent = abbrev;
    if (heroType) heroType.textContent = dept.department_type || '—';

    if (viewPhoto && viewPhotoPlaceholder) {
        if (photoUrl) {
            viewPhoto.src = `../${photoUrl}`;
            viewPhoto.alt = `Photo of ${abbrev}`;
            viewPhoto.hidden = false;
            viewPhotoPlaceholder.style.display = 'none';
        } else {
            viewPhoto.hidden = true;
            viewPhoto.src = '';
            viewPhotoPlaceholder.textContent = (abbrev !== '—' ? abbrev : 'D').charAt(0).toUpperCase();
            viewPhotoPlaceholder.style.display = 'flex';
        }
    }

    const usernameEl = document.getElementById('view_dept_username');
    if (usernameEl) usernameEl.textContent = dept.department_username || '—';
    if (statusEl) {
        statusEl.innerHTML = `<span class="status-pill status-${statusClass}">${dept.department_status || 'Active'}</span>`;
    }
    const createdEl = document.getElementById('view_dept_created_at');
    if (createdEl) createdEl.textContent = formatDateTime(dept.department_created_at);
    const updatedEl = document.getElementById('view_dept_updated_at');
    if (updatedEl) updatedEl.textContent = formatDateTime(dept.department_updated_at);

    viewDepartmentModal.classList.add('is-open');
    viewDepartmentModal.setAttribute('aria-hidden', 'false');
}

function closeViewDepartmentModal() {
    if (!viewDepartmentModal) return;
    viewDepartmentModal.classList.remove('is-open');
    viewDepartmentModal.setAttribute('aria-hidden', 'true');
}

viewDepartmentBackdrop?.addEventListener('click', closeViewDepartmentModal);
viewDepartmentCloseBtn?.addEventListener('click', closeViewDepartmentModal);
document.getElementById('viewDepartmentCloseFooterBtn')?.addEventListener('click', closeViewDepartmentModal);

// ---------------- Strict Password Validation ----------------
function validatePasswordStrict(pwd) {
    const tests = {
        length: pwd.length >= 8,
        uppercase: /[A-Z]/.test(pwd),
        lowercase: /[a-z]/.test(pwd),
        number: /\d/.test(pwd),
        special: /[@$!%*?&#\-_.]/.test(pwd)
    };

    document.getElementById('length').classList.toggle('requirement-met', tests.length);
    document.getElementById('uppercase').classList.toggle('requirement-met', tests.uppercase);
    document.getElementById('lowercase').classList.toggle('requirement-met', tests.lowercase);
    document.getElementById('number').classList.toggle('requirement-met', tests.number);
    document.getElementById('special').classList.toggle('requirement-met', tests.special);

    const met = Object.values(tests).filter(Boolean).length;
    let strength = '';
    if (met === 5) strength = 'Strong password';
    else if (met >= 3) strength = 'Medium password';
    else strength = 'Weak password';

    const strengthEl = document.getElementById('passwordStrength');
    if (strengthEl) {
        strengthEl.textContent = strength;
        strengthEl.style.color = met === 5 ? '#10b981' : met >= 3 ? '#f59e0b' : '#ef4444';
    }

    return tests;
}

function resetPasswordFieldStyles() {
    [passwordInput, confirmPasswordInput].forEach(input => {
        input?.closest('.input-icon-wrapper')?.classList.remove('password-input--match', 'password-input--mismatch');
    });
    const matchEl = document.getElementById('passwordMatchStatus');
    if (matchEl) {
        matchEl.textContent = '';
        matchEl.className = 'password-match-status';
    }
}

function updatePasswordFeedback() {
    const pwd = passwordInput?.value || '';
    const confirmPwd = confirmPasswordInput?.value || '';
    const isAdd = modalTitle.textContent.includes('Add');

    if (isAdd || pwd) {
        validatePasswordStrict(pwd);
    } else {
        validatePasswordStrict('');
    }

    const matchEl = document.getElementById('passwordMatchStatus');
    const confirmWrap = confirmPasswordInput?.closest('.input-icon-wrapper');
    const pwdWrap = passwordInput?.closest('.input-icon-wrapper');

    confirmWrap?.classList.remove('password-input--match', 'password-input--mismatch');
    pwdWrap?.classList.remove('password-input--match', 'password-input--mismatch');

    if (!pwd && !confirmPwd) {
        if (matchEl) {
            matchEl.textContent = '';
            matchEl.className = 'password-match-status';
        }
        return;
    }

    if (!confirmPwd) {
        if (matchEl) {
            matchEl.textContent = pwd ? 'Confirm the password above' : '';
            matchEl.className = 'password-match-status password-match-status--pending';
        }
        return;
    }

    if (pwd === confirmPwd) {
        if (matchEl) {
            matchEl.textContent = 'Passwords match';
            matchEl.className = 'password-match-status password-match-status--match';
        }
        confirmWrap?.classList.add('password-input--match');
        if (pwd) pwdWrap?.classList.add('password-input--match');
    } else {
        if (matchEl) {
            matchEl.textContent = 'Passwords do not match';
            matchEl.className = 'password-match-status password-match-status--mismatch';
        }
        confirmWrap?.classList.add('password-input--mismatch');
        if (pwd) pwdWrap?.classList.add('password-input--mismatch');
    }
}

passwordInput?.addEventListener('input', updatePasswordFeedback);
confirmPasswordInput?.addEventListener('input', updatePasswordFeedback);

// Photo preview handler
photoInput?.addEventListener('change', () => {
    const file = photoInput.files[0];
    if (!file) {
        if (photoPreview && photoPlaceholder) {
            photoPreview.src = '';
            photoPreview.style.display = 'none';
            photoPlaceholder.style.display = 'flex';
        }
        return;
    }

    const reader = new FileReader();
    reader.onload = e => {
        if (photoPreview && photoPlaceholder) {
            photoPreview.src = e.target.result;
            photoPreview.style.display = 'block';
            photoPlaceholder.style.display = 'none';
        }
    };
    reader.readAsDataURL(file);
});

// ---------------- Load Users & Departments ----------------
let allUsers = [];
let allDepartments = [];
let filteredDepartments = [];

async function loadUsers() {
    try {
        const res = await fetch('../../app/api/get_users.php', { credentials: 'include' });
        const data = await res.json();
        allUsers = data.users || [];

        searchInput.value = '';
        adminCurrentPage = 1;
        renderAdminUsersTable(getFilteredUsers());
        // Re-render dept panel so dean rows (sourced from allUsers) appear
        filteredDepartments = getFilteredDepartments();
        renderDepartmentPanel();
    } catch (err) {
        console.error(err);
    }
}

async function loadDepartments() {
    try {
        const res = await fetch('../../app/api/department.php', { credentials: 'include' });
        const data = await res.json();
        allDepartments = data.departments || [];
        deptCurrentPage = 1;
        filteredDepartments = getFilteredDepartments();
        renderDepartmentPanel();
    } catch (err) {
        console.error(err);
        if (deptUsersTableBody) {
            deptUsersTableBody.innerHTML = '<tr><td colspan="6" class="users-table-loading">Failed to load departments.</td></tr>';
        }
    }
}

async function populateOfficeDropdown() {
    try {
        const res = await fetch('../../app/api/office.php', { credentials: 'include' });
        const data = await res.json();
        const officeDropdown = document.getElementById('office_id');
        officeDropdown.innerHTML = '<option value="">Select Office</option>';
        if (data.success && data.offices?.length > 0) {
            data.offices.forEach(office => {
                const opt = document.createElement('option');
                opt.value = office.office_id;
                opt.textContent = office.office_name;
                officeDropdown.appendChild(opt);
            });
        }
    } catch (err) {
        console.error(err);
    }
}
populateOfficeDropdown();

async function populateDepartmentOfficeDropdown(selectedId = '') {
    try {
        const res = await fetch('../../app/api/office.php', { credentials: 'include' });
        const data = await res.json();
        const dropdown = document.getElementById('department_office_id');
        if (!dropdown) return;
        dropdown.innerHTML = '<option value="">Select Facility</option>';
        if (data.success && data.offices?.length > 0) {
            data.offices.forEach(office => {
                const opt = document.createElement('option');
                opt.value = office.office_id;
                opt.textContent = office.office_name;
                if (String(office.office_id) === String(selectedId)) opt.selected = true;
                dropdown.appendChild(opt);
            });
        }
    } catch (err) {
        console.error(err);
    }
}
populateDepartmentOfficeDropdown();

const profileRequiredFieldIds = ['full_name', 'contact_number', 'email', 'role', 'office_id'];
const passwordFieldIds = ['password', 'confirm_password'];

function setFormMode(mode) {
    const isAdd = mode === 'add';
    profileRequiredFieldIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.required = true;
    });
    passwordFieldIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.required = isAdd;
    });
    const changePasswordHeading = document.getElementById('changePasswordHeading');
    if (changePasswordHeading) {
        changePasswordHeading.hidden = isAdd;
    }
    const changePasswordHint = document.getElementById('changePasswordHint');
    if (changePasswordHint) {
        changePasswordHint.hidden = isAdd;
    }
    const passwordRequirements = document.getElementById('passwordRequirements');
    if (passwordRequirements) {
        passwordRequirements.style.display = 'block';
    }
    modal?.classList.toggle('user-modal--edit', !isAdd);
}

function validateContactNumberField(requireFilled = false) {
    const el = document.getElementById('contact_number');
    const value = (el?.value || '').trim();
    if (!value) {
        if (requireFilled) {
            showToast('Please fill in Contact Number', 'error');
            el?.focus();
            return false;
        }
        return true;
    }
    if (!/^\d{11}$/.test(value)) {
        showToast('Contact Number must be exactly 11 digits', 'error');
        el?.focus();
        return false;
    }
    return true;
}

function validateProfileFields() {
    const checks = [
        { id: 'full_name', label: 'Full Name' },
        { id: 'contact_number', label: 'Contact Number' },
        { id: 'email', label: 'Email Address' },
        { id: 'role', label: 'Assigned Role' },
        { id: 'office_id', label: 'Office / Department' },
    ];

    for (const { id, label } of checks) {
        const el = document.getElementById(id);
        if (!(el?.value || '').trim()) {
            showToast(`Please fill in ${label}`, 'error');
            el?.focus();
            return false;
        }
    }

    const accountStatus = document.getElementById('account_status');
    if (!(accountStatus?.value || '').trim()) {
        showToast('Please select Account Status', 'error');
        accountStatus?.focus();
        return false;
    }

    if (!validateContactNumberField(true)) {
        return false;
    }

    return true;
}

function validateCreateUserForm() {
    if (!validateProfileFields()) {
        return false;
    }

    const passwordChecks = [
        { id: 'password', label: 'Password' },
        { id: 'confirm_password', label: 'Confirm Password' },
    ];

    for (const { id, label } of passwordChecks) {
        const el = document.getElementById(id);
        if (!(el?.value || '').trim()) {
            showToast(`Please fill in ${label}`, 'error');
            el?.focus();
            return false;
        }
    }

    return true;
}

function validateEditUserForm() {
    return validateProfileFields();
}

// ---------------- Department Modal ----------------
function validateDepartmentPasswordStrict(pwd) {
    const tests = {
        length: pwd.length >= 8,
        uppercase: /[A-Z]/.test(pwd),
        lowercase: /[a-z]/.test(pwd),
        number: /\d/.test(pwd),
        special: /[@$!%*?&#\-_.]/.test(pwd),
    };

    document.getElementById('dept-length')?.classList.toggle('requirement-met', tests.length);
    document.getElementById('dept-uppercase')?.classList.toggle('requirement-met', tests.uppercase);
    document.getElementById('dept-lowercase')?.classList.toggle('requirement-met', tests.lowercase);
    document.getElementById('dept-number')?.classList.toggle('requirement-met', tests.number);
    document.getElementById('dept-special')?.classList.toggle('requirement-met', tests.special);

    const met = Object.values(tests).filter(Boolean).length;
    let strength = '';
    if (met === 5) strength = 'Strong password';
    else if (met >= 3) strength = 'Medium password';
    else strength = 'Weak password';

    const strengthEl = document.getElementById('departmentPasswordStrength');
    if (strengthEl) {
        strengthEl.textContent = strength;
        strengthEl.style.color = met === 5 ? '#10b981' : met >= 3 ? '#f59e0b' : '#ef4444';
    }

    return tests;
}

function resetDepartmentPasswordFieldStyles() {
    [departmentPasswordInput, departmentConfirmPasswordInput].forEach(input => {
        input?.closest('.input-icon-wrapper')?.classList.remove('password-input--match', 'password-input--mismatch');
    });
    const matchEl = document.getElementById('departmentPasswordMatchStatus');
    if (matchEl) {
        matchEl.textContent = '';
        matchEl.className = 'password-match-status';
    }
}

function updateDepartmentPasswordFeedback() {
    const pwd = departmentPasswordInput?.value || '';
    const confirmPwd = departmentConfirmPasswordInput?.value || '';

    validateDepartmentPasswordStrict(pwd);

    const matchEl = document.getElementById('departmentPasswordMatchStatus');
    const confirmWrap = departmentConfirmPasswordInput?.closest('.input-icon-wrapper');
    const pwdWrap = departmentPasswordInput?.closest('.input-icon-wrapper');

    confirmWrap?.classList.remove('password-input--match', 'password-input--mismatch');
    pwdWrap?.classList.remove('password-input--match', 'password-input--mismatch');

    if (!pwd && !confirmPwd) {
        if (matchEl) {
            matchEl.textContent = '';
            matchEl.className = 'password-match-status';
        }
        return;
    }

    if (!confirmPwd) {
        if (matchEl) {
            matchEl.textContent = pwd ? 'Confirm the password above' : '';
            matchEl.className = 'password-match-status password-match-status--pending';
        }
        return;
    }

    if (pwd === confirmPwd) {
        if (matchEl) {
            matchEl.textContent = 'Passwords match';
            matchEl.className = 'password-match-status password-match-status--match';
        }
        confirmWrap?.classList.add('password-input--match');
        if (pwd) pwdWrap?.classList.add('password-input--match');
    } else {
        if (matchEl) {
            matchEl.textContent = 'Passwords do not match';
            matchEl.className = 'password-match-status password-match-status--mismatch';
        }
        confirmWrap?.classList.add('password-input--mismatch');
        if (pwd) pwdWrap?.classList.add('password-input--mismatch');
    }
}

function getDepartmentPlaceholderInitial() {
    const abbreviation = (document.getElementById('department_abbreviation')?.value || '').trim();
    if (abbreviation) return abbreviation.charAt(0).toUpperCase();
    const name = (document.getElementById('department_name')?.value || '').trim();
    if (name) return name.charAt(0).toUpperCase();
    return 'D';
}

function resetDepartmentPhotoPreview() {
    if (departmentPhotoPreview && departmentPhotoPlaceholder) {
        departmentPhotoPreview.src = '';
        departmentPhotoPreview.style.display = 'none';
        departmentPhotoPlaceholder.textContent = getDepartmentPlaceholderInitial();
        departmentPhotoPlaceholder.style.display = 'flex';
    }
}

function setDepartmentFormMode(mode) {
    const isAdd = mode === 'add';
    if (departmentModalTitle) {
        departmentModalTitle.textContent = isAdd ? 'Add Department' : 'Edit Department';
    }
    if (departmentPasswordInput) departmentPasswordInput.required = isAdd;
    if (departmentConfirmPasswordInput) departmentConfirmPasswordInput.required = isAdd;
    const changePasswordHeading = document.getElementById('deptChangePasswordHeading');
    if (changePasswordHeading) changePasswordHeading.hidden = isAdd;
    const changePasswordHint = document.getElementById('deptChangePasswordHint');
    if (changePasswordHint) changePasswordHint.hidden = isAdd;
    departmentModal?.classList.toggle('user-modal--edit', !isAdd);
}

function openDepartmentModal() {
    departmentForm?.reset();
    const deptIdInput = document.getElementById('department_id');
    if (deptIdInput) deptIdInput.value = '';
    const editTypeInput = document.getElementById('department_edit_type');
    if (editTypeInput) editTypeInput.value = '';
    const statusSelect = document.getElementById('department_status');
    if (statusSelect) statusSelect.value = 'Active';
    populateDepartmentOfficeDropdown();
    setDepartmentFormMode('add');
    resetDepartmentPhotoPreview();
    resetDepartmentPasswordFieldStyles();
    updateDepartmentPasswordFeedback();
    if (departmentModal) departmentModal.style.display = 'flex';
}

function openDepartmentEditModal(dept) {
    if (!dept) return;
    departmentForm?.reset();
    setDepartmentFormMode('edit');
    document.getElementById('department_id').value = dept.department_id;
    document.getElementById('department_edit_type').value = 'legacy';
    document.getElementById('department_name').value = dept.department_name || '';
    document.getElementById('department_abbreviation').value = dept.department_abbreviation || '';
    document.getElementById('department_type').value = dept.department_type || '';
    document.getElementById('department_status').value = dept.department_status || 'Active';
    document.getElementById('department_username').value = dept.department_username || '';
    populateDepartmentOfficeDropdown(dept.office_id || '');
    if (departmentPasswordInput) departmentPasswordInput.value = '';
    if (departmentConfirmPasswordInput) departmentConfirmPasswordInput.value = '';
    resetDepartmentPasswordFieldStyles();
    updateDepartmentPasswordFeedback();

    const photoUrl = dept.department_photo_url || '';
    if (departmentPhotoPreview && departmentPhotoPlaceholder) {
        if (photoUrl) {
            departmentPhotoPreview.src = `../${photoUrl}`;
            departmentPhotoPreview.style.display = 'block';
            departmentPhotoPlaceholder.style.display = 'none';
        } else {
            resetDepartmentPhotoPreview();
        }
    }

    if (departmentModal) departmentModal.style.display = 'flex';
}

function openDepartmentEditModalForUser(user) {
    if (!user) return;
    // Close User modal if it was open to avoid overlapping forms.
    if (modal) modal.style.display = 'none';
    departmentForm?.reset();
    setDepartmentFormMode('edit');
    const deptIdEl = document.getElementById('department_id');
    const editTypeEl = document.getElementById('department_edit_type');
    if (deptIdEl) deptIdEl.value = user.user_id;
    if (editTypeEl) editTypeEl.value = 'user_dept';
    document.getElementById('department_name').value = user.full_name || '';
    document.getElementById('department_abbreviation').value = user.abbreviation || '';
    document.getElementById('department_type').value = user.department_type || '';
    const statusRaw = (user.account_status || 'active').toLowerCase();
    document.getElementById('department_status').value = statusRaw === 'active' ? 'Active' : 'Inactive';
    document.getElementById('department_username').value = user.email || '';
    populateDepartmentOfficeDropdown(user.office_id || '');
    if (departmentPasswordInput) departmentPasswordInput.value = '';
    if (departmentConfirmPasswordInput) departmentConfirmPasswordInput.value = '';
    resetDepartmentPasswordFieldStyles();
    updateDepartmentPasswordFeedback();

    if (departmentPhotoPreview && departmentPhotoPlaceholder) {
        if (user.photo_url) {
            departmentPhotoPreview.src = `../${user.photo_url}`;
            departmentPhotoPreview.style.display = 'block';
            departmentPhotoPlaceholder.style.display = 'none';
        } else {
            resetDepartmentPhotoPreview();
        }
    }

    if (departmentModal) departmentModal.style.display = 'flex';
}

function closeDepartmentModalView() {
    if (departmentModal) departmentModal.style.display = 'none';
}

function validateDepartmentForm(isEdit = false) {
    const checks = [
        { id: 'department_name', label: 'Department Name' },
        { id: 'department_abbreviation', label: 'Abbreviation' },
        { id: 'department_type', label: 'Department Type' },
        { id: 'department_status', label: 'Status' },
        { id: 'department_username', label: 'Username' },
    ];

    for (const { id, label } of checks) {
        const el = document.getElementById(id);
        if (!(el?.value || '').trim()) {
            showToast(`Please fill in ${label}`, 'error');
            el?.focus();
            return false;
        }
    }

    const abbreviationEl = document.getElementById('department_abbreviation');
    const abbreviation = (abbreviationEl?.value || '').trim().toUpperCase();
    if (!/^[A-Z0-9\-_]+$/.test(abbreviation)) {
        showToast('Abbreviation may only contain letters, numbers, hyphens, and underscores', 'error');
        abbreviationEl?.focus();
        return false;
    }
    if (abbreviationEl) abbreviationEl.value = abbreviation;

    const usernameEl = document.getElementById('department_username');
    const username = (usernameEl?.value || '').trim();
    if (!/^[A-Za-z0-9._-]+$/.test(username)) {
        showToast('Username may only contain letters, numbers, dots, hyphens, and underscores', 'error');
        usernameEl?.focus();
        return false;
    }
    if (usernameEl) usernameEl.value = username;

    const pwd = departmentPasswordInput?.value || '';
    const confirmPwd = departmentConfirmPasswordInput?.value || '';

    if (!isEdit) {
        if (!pwd || !confirmPwd) {
            showToast('Please fill in Password and Confirm Password', 'error');
            (pwd ? departmentConfirmPasswordInput : departmentPasswordInput)?.focus();
            return false;
        }
    }

    if (pwd || confirmPwd) {
        if (pwd !== confirmPwd) {
            showToast('Passwords do not match', 'error');
            departmentConfirmPasswordInput?.focus();
            return false;
        }
        const tests = validateDepartmentPasswordStrict(pwd);
        if (!Object.values(tests).every(Boolean)) {
            showToast('Password must meet all requirements!', 'error');
            departmentPasswordInput?.focus();
            return false;
        }
    }

    return true;
}

async function submitDepartmentForm() {
    const deptId = document.getElementById('department_id')?.value || '';
    const editType = document.getElementById('department_edit_type')?.value || '';
    const isEdit = !!deptId;
    if (!validateDepartmentForm(isEdit)) return;

    saveDepartmentBtn.disabled = true;
    saveDepartmentBtn.textContent = 'Saving...';

    let action = 'add';
    if (isEdit) {
        action = editType === 'user_dept' ? 'edit_user_dept' : 'edit';
    }

    const formData = new FormData(departmentForm);
    formData.set('action', action);
    if (isEdit) formData.set('department_id', deptId);

    try {
        const res = await fetch('../../app/api/department_actions.php', {
            method: 'POST',
            body: formData,
            credentials: 'include',
        });
        const data = await res.json();
        if (data.success) {
            closeDepartmentModalView();
            if (isEdit && editType === 'user_dept') {
                loadUsers();
            } else if (isEdit) {
                loadDepartments();
            } else {
                loadUsers();
            }
            openAccountSuccessModal(
                isEdit
                    ? 'Your department changes have been successfully saved.'
                    : 'The new department has been added successfully.'
            );
        } else {
            showToast(data.message || 'Failed to save department', 'error');
        }
    } catch (_) {
        showToast('An error occurred', 'error');
    } finally {
        saveDepartmentBtn.disabled = false;
        saveDepartmentBtn.textContent = 'Save';
    }
}

addDepartmentBtn?.addEventListener('click', openDepartmentModal);
closeDepartmentModal?.addEventListener('click', closeDepartmentModalView);
document.getElementById('cancelDepartmentModalBtn')?.addEventListener('click', closeDepartmentModalView);
window.addEventListener('click', e => {
    if (e.target === departmentModal) closeDepartmentModalView();
});

document.getElementById('department_abbreviation')?.addEventListener('input', e => {
    e.target.value = e.target.value.replace(/[^A-Za-z0-9\-_]/g, '').toUpperCase().slice(0, 20);
    if (departmentPhotoPreview?.style.display !== 'block' && departmentPhotoPlaceholder) {
        departmentPhotoPlaceholder.textContent = getDepartmentPlaceholderInitial();
    }
});

document.getElementById('department_name')?.addEventListener('input', () => {
    if (departmentPhotoPreview?.style.display !== 'block' && departmentPhotoPlaceholder) {
        departmentPhotoPlaceholder.textContent = getDepartmentPlaceholderInitial();
    }
});

departmentPhotoInput?.addEventListener('change', () => {
    const file = departmentPhotoInput.files?.[0];
    if (!file) {
        resetDepartmentPhotoPreview();
        return;
    }

    const reader = new FileReader();
    reader.onload = e => {
        if (departmentPhotoPreview && departmentPhotoPlaceholder) {
            departmentPhotoPreview.src = e.target.result;
            departmentPhotoPreview.style.display = 'block';
            departmentPhotoPlaceholder.style.display = 'none';
        }
    };
    reader.readAsDataURL(file);
});

document.getElementById('department_username')?.addEventListener('input', e => {
    e.target.value = e.target.value.replace(/[^A-Za-z0-9._-]/g, '').slice(0, 50);
});

departmentPasswordInput?.addEventListener('input', updateDepartmentPasswordFeedback);
departmentConfirmPasswordInput?.addEventListener('input', updateDepartmentPasswordFeedback);

departmentForm?.addEventListener('submit', e => {
    e.preventDefault();
    const isEdit = !!(document.getElementById('department_id')?.value || '').trim();
    if (!validateDepartmentForm(isEdit)) return;
    if (isEdit) {
        pendingDepartmentSave = true;
        openSaveConfirmModal();
        return;
    }
    submitDepartmentForm();
});

// ---------------- Modal & Submit (STRICT VALIDATION) ----------------
addUserBtn.addEventListener('click', () => {
    modalTitle.textContent = 'Add User';
    userForm.reset();
    setFormMode('add');
    // Reset photo preview
    if (photoPreview && photoPlaceholder) {
        photoPreview.src = '';
        photoPreview.style.display = 'none';
        photoPlaceholder.textContent = (document.getElementById('email').value || '').charAt(0).toUpperCase() || '';
    }
    document.getElementById('user_id').value = '';
    resetPasswordFieldStyles();
    modal.style.display = 'flex';
    updatePasswordFeedback();
});

function closeUserModal() {
    if (modal) modal.style.display = 'none';
}

closeModal.addEventListener('click', closeUserModal);
document.getElementById('cancelModalBtn')?.addEventListener('click', closeUserModal);
window.addEventListener('click', e => { if (e.target === modal) closeUserModal(); });

document.addEventListener('click', e => {
    if (e.target.classList.contains('toggle-password')) {
        const input = e.target.closest('.input-icon-wrapper')?.querySelector('input');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        e.target.classList.toggle('fa-eye-slash');
    }
});

document.getElementById('contact_number')?.addEventListener('input', e => {
    e.target.value = e.target.value.replace(/\D/g, '').slice(0, 11);
});

userForm.addEventListener('submit', e => {
    e.preventDefault();

    const isAdd = modalTitle.textContent.includes('Add');

    if (isAdd && !validateCreateUserForm()) {
        return;
    }

    if (!isAdd && !validateEditUserForm()) {
        return;
    }

    const pwd = passwordInput.value;
    const confirmPwd = confirmPasswordInput.value;

    if (isAdd && pwd !== confirmPwd) {
        showToast('Passwords do not match', 'error');
        confirmPasswordInput?.focus();
        return;
    }

    if (!isAdd && pwd && pwd !== confirmPwd) {
        showToast('Passwords do not match', 'error');
        confirmPasswordInput?.focus();
        return;
    }

    if (isAdd || pwd) {
        const tests = validatePasswordStrict(pwd);
        const allPassed = Object.values(tests).every(Boolean);
        if (!allPassed) {
            showToast('Password must meet all requirements!', 'error');
            return;
        }
    }

    if (modalTitle.textContent.includes('Edit')) {
        openSaveConfirmModal();
        return;
    }

    submitUserForm();
});

async function submitUserForm() {
    const isAdd = modalTitle.textContent.includes('Add');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    const formData = new FormData(userForm);
    try {
        const res = await fetch('../../app/api/user_actions.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        const data = await res.json();
        if (data.success) {
            modal.style.display = 'none';
            loadUsers();
            openAccountSuccessModal(
                isAdd
                    ? 'The new user has been added successfully.'
                    : 'Your changes have been successfully saved.'
            );
        } else {
            showToast(data.message || 'Action failed', 'error');
        }
    } catch (_) {
        showToast('An error occurred', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save';
    }
}

// ---------------- Photo Lightbox helpers ----------------
function openPhotoLightbox(src, email) {
    if (!photoLightbox || !photoLightboxImg) return;
    if (src) {
        photoLightboxImg.src = src;
        photoLightboxImg.style.display = 'block';
    } else {
        photoLightboxImg.src = '';
        photoLightboxImg.style.display = 'none';
    }
    if (photoLightboxMeta) {
        photoLightboxMeta.textContent = email ? `Account: ${email}` : '';
    }
    photoLightbox.classList.add('open');
}

function closePhotoLightbox() {
    if (!photoLightbox) return;
    photoLightbox.classList.remove('open');
}

photoLightboxClose?.addEventListener('click', closePhotoLightbox);
photoLightbox?.addEventListener('click', e => {
    if (e.target === photoLightbox) closePhotoLightbox();
});

// ---------------- Edit & DELETE (Now Instant Update) + photo click ----------------
function formatUsersLabel(count) {
    return `${count} User${count === 1 ? '' : 's'}`;
}

function formatDepartmentsLabel(count) {
    return `${count} Department${count === 1 ? '' : 's'}`;
}

function formatPageInfo(total, page, perPage, nounSingular = 'user', nounPlural = 'users') {
    if (total <= 0) return `Showing 0 to 0 of 0 ${nounPlural}`;
    const start = (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);
    const noun = total === 1 ? nounSingular : nounPlural;
    return `Showing ${start} to ${end} of ${total} ${noun}`;
}

function updatePanelPagination(panel, total, currentPage, perPage) {
    const totalPages = Math.max(1, Math.ceil(total / perPage) || 1);
    const safePage = Math.min(Math.max(1, currentPage), totalPages);

    if (panel === 'admin') {
        if (adminPageInfo) adminPageInfo.textContent = formatPageInfo(total, safePage, perPage);
        if (adminPageNum) adminPageNum.textContent = String(safePage);
        if (adminPrevBtn) adminPrevBtn.disabled = safePage <= 1;
        if (adminNextBtn) adminNextBtn.disabled = safePage >= totalPages || total === 0;
        return safePage;
    }

    if (deptPageInfo) {
        deptPageInfo.textContent = formatPageInfo(total, safePage, perPage, 'department', 'departments');
    }
    if (deptPageNum) deptPageNum.textContent = String(safePage);
    if (deptPrevBtn) deptPrevBtn.disabled = safePage <= 1;
    if (deptNextBtn) deptNextBtn.disabled = safePage >= totalPages || total === 0;
    return safePage;
}

accountManagementRoot?.addEventListener('click', async e => {
    // Photo click -> open preview
    const photoWrapper = e.target.closest('.user-photo-wrapper');
    if (photoWrapper) {
        const email = photoWrapper.dataset.email || '';
        const src = photoWrapper.dataset.photo || '';
        if (src) {
            openPhotoLightbox(src, email);
        } else {
            // No photo uploaded: just show placeholder info
            openPhotoLightbox('', email);
        }
        return;
    }

    const editBtn = e.target.closest('.action-btn.edit');
    const deleteBtn = e.target.closest('.action-btn.delete');
    const statusBtn = e.target.closest('.action-btn.status-toggle');
    const viewBtn = e.target.closest('.action-btn.view');
    const inDeptTable = !!e.target.closest('.users-management-table--dept');

    if (viewBtn && inDeptTable) {
        if (viewBtn.dataset.rowType === 'dean') {
            const user = allUsers.find(u => String(u.user_id) === String(viewBtn.dataset.id));
            if (!user) { showToast('User not found', 'error'); return; }
            openViewUserModal(user);
            return;
        }
        const dept = allDepartments.find(d => String(d.department_id) === String(viewBtn.dataset.id));
        if (!dept) {
            showToast('Department not found', 'error');
            return;
        }
        openViewDepartmentModal(dept);
        return;
    }

    if (editBtn && inDeptTable) {
        if (editBtn.dataset.rowType === 'dean') {
            const user = allUsers.find(u => String(u.user_id) === String(editBtn.dataset.id));
            if (!user) { showToast('User not found', 'error'); return; }
            const roleNorm = (user.role || '').toLowerCase().trim();
            if (roleNorm === 'user') {
                openDepartmentEditModalForUser(user);
                return;
            }
            modalTitle.textContent = 'Edit User';
            setFormMode('edit');
            document.getElementById('user_id').value = user.user_id;
            document.getElementById('email').value = user.email;
            document.getElementById('full_name').value = user.full_name || '';
            document.getElementById('contact_number').value = user.contact_number || '';
            document.getElementById('role').value = user.role;
            document.getElementById('office_id').value = user.office_id || '';
            document.getElementById('account_status').value = (user.account_status || 'active').toLowerCase();
            document.getElementById('password').value = '';
            document.getElementById('confirm_password').value = '';
            resetPasswordFieldStyles();
            updatePasswordFeedback();
            if (photoPreview && photoPlaceholder) {
                if (user.photo_url) {
                    photoPreview.src = `../${user.photo_url}`;
                    photoPreview.style.display = 'block';
                    photoPlaceholder.style.display = 'none';
                } else {
                    photoPreview.src = '';
                    photoPreview.style.display = 'none';
                    photoPlaceholder.style.display = 'flex';
                    photoPlaceholder.textContent = (user.email || '').charAt(0).toUpperCase();
                }
            }
            modal.style.display = 'flex';
            return;
        }
        const dept = allDepartments.find(d => String(d.department_id) === String(editBtn.dataset.id));
        if (!dept) {
            showToast('Department not found', 'error');
            return;
        }
        openDepartmentEditModal(dept);
        return;
    }

    if (deleteBtn && inDeptTable) {
        if (deleteBtn.dataset.rowType === 'dean') {
            const uid = deleteBtn.dataset.id;
            const rowUser = allUsers.find(u => String(u.user_id) === String(uid));
            openDeleteConfirmModal(uid, rowUser?.email || '');
            return;
        }
        const dept = allDepartments.find(d => String(d.department_id) === String(deleteBtn.dataset.id));
        if (!dept) {
            showToast('Department not found', 'error');
            return;
        }
        const label = `${dept.department_name || ''} (${dept.department_abbreviation || ''})`.trim();
        openDeleteDepartmentConfirmModal(dept.department_id, label);
        return;
    }

    if (statusBtn) {
        const uid = statusBtn.dataset.id;
        const nextStatus = statusBtn.dataset.next;
        if (nextStatus === 'disabled') {
            const rowUser = allUsers.find(u => String(u.user_id) === String(uid));
            openDisableConfirmModal(uid, rowUser?.email || '');
            return;
        }
        if (nextStatus === 'active') {
            const rowUser = allUsers.find(u => String(u.user_id) === String(uid));
            openEnableConfirmModal(uid, rowUser?.email || '');
            return;
        }
        return;
    }

    if(editBtn){
        const user = allUsers.find(u => u.user_id == editBtn.dataset.id);
        if(!user){ showToast('User not found','error'); return; }
        modalTitle.textContent='Edit User';
        setFormMode('edit');
        document.getElementById('user_id').value = user.user_id;
        document.getElementById('email').value = user.email;
        document.getElementById('full_name').value = user.full_name || '';
        document.getElementById('contact_number').value = user.contact_number || '';
        document.getElementById('role').value = user.role;
        document.getElementById('office_id').value = user.office_id || '';
        document.getElementById('account_status').value = (user.account_status || 'active').toLowerCase();
        document.getElementById('password').value = '';
        document.getElementById('confirm_password').value = '';
        resetPasswordFieldStyles();
        updatePasswordFeedback();

        // Set photo preview for existing user
        if (photoPreview && photoPlaceholder) {
            if (user.photo_url) {
                photoPreview.src = `../${user.photo_url}`;
                photoPreview.style.display = 'block';
                photoPlaceholder.style.display = 'none';
            } else {
                photoPreview.src = '';
                photoPreview.style.display = 'none';
                photoPlaceholder.style.display = 'flex';
                photoPlaceholder.textContent = (user.email || '').charAt(0).toUpperCase();
            }
        }
        modal.style.display='flex';
    }

    if(viewBtn){
        const user = allUsers.find(u => String(u.user_id) === String(viewBtn.dataset.id));
        if(!user){ showToast('User not found','error'); return; }
        openViewUserModal(user);
        return;
    }

    if(deleteBtn){
        const uid = deleteBtn.dataset.id;
        const rowUser = allUsers.find(u => String(u.user_id) === String(uid));
        openDeleteConfirmModal(uid, rowUser?.email || '');
    }
});

// -------- SEARCH, SORT & SPLIT USER TABLES --------
const searchInput = document.getElementById('searchInput');
const ADMIN_ROLE_DEFS = [
    { role: 'GSD officer', label: 'GSD' },
    { role: 'Comptroller', label: 'Comptroller' },
    { role: 'President', label: 'President' },
    { role: 'Canvasser', label: 'Canvasser' },
    { role: 'Laboratory Manager', label: 'Laboratory Manager' },
    { role: 'Employee', label: 'Employee' },
];

function normalizeUserRole(role) {
    return String(role || '').trim().toLowerCase();
}

const CANVASSER_ELIGIBLE_ROLES = ['canvasser', 'employee', 'laboratory manager'];

function isCanvasserAssignee(user) {
    if (!user) return false;
    const role = normalizeUserRole(user.role);
    if (!CANVASSER_ELIGIBLE_ROLES.includes(role)) return false;
    return Number(user.is_canvasser_assignee) === 1 || role === 'canvasser';
}

function usersForRoleDef(users, roleDef) {
    if (roleDef.role === 'Canvasser') {
        return users.filter(u => isCanvasserAssignee(u));
    }
    if (roleDef.role === 'Employee') {
        return users.filter(u => (u.role || '').trim() === 'Employee' && !isCanvasserAssignee(u));
    }
    return users.filter(u => (u.role || '').trim() === roleDef.role);
}

function isAdminPanelUser(user) {
    const role = normalizeUserRole(user.role);
    return role !== 'dean' && role !== 'user' && role !== 'inventory manager';
}

function flattenPanelUsers(users, roleDefs) {
    const flat = [];
    roleDefs.forEach((roleDef) => {
        flat.push(...usersForRoleDef(users, roleDef));
    });
    return flat;
}

function buildUserRowHtml(user, rowNum, { showRole = false, showOffice = false } = {}) {
    const firstLetter = (user.email || '').charAt(0).toUpperCase();
    const hasPhoto = !!user.photo_url;
    const photoSrc = hasPhoto ? `../${user.photo_url}` : '';
    const status = (user.account_status || 'active').toLowerCase();
    const nextStatus = status === 'active' ? 'disabled' : 'active';
    const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
    const roleLabel = showRole && isCanvasserAssignee(user) ? 'Canvasser' : (user.role || '');
    const roleCell = showRole ? `<td>${roleLabel}</td>` : '';
    const officeCell = showOffice ? `<td>${user.office_name || '—'}</td>` : '';
    const fullName = (user.full_name || '').trim() || '—';

    return `
        <td>${rowNum}</td>
        <td>
            <div class="user-photo-wrapper" data-email="${user.email}" data-photo="${photoSrc}">
                ${hasPhoto
                    ? `<img src="${photoSrc}" alt="Photo of ${user.email}" class="user-photo-thumb">`
                    : `<div class="user-photo-placeholder">${firstLetter}</div>`
                }
            </div>
        </td>
        <td class="users-col-full-name" title="${fullName}">${fullName}</td>
        <td>${user.email}</td>
        ${roleCell}
        <td class="users-col-status"><span class="status-pill status-${status}">${statusLabel}</span></td>
        ${officeCell}
        <td>
            <div class="user-action-cell">
                <button type="button" class="action-btn view" data-id="${user.user_id}" title="View details">
                    <i class="fas fa-eye"></i>
                </button>
                <button type="button" class="action-btn status-toggle" data-id="${user.user_id}" data-next="${nextStatus}" title="Set ${nextStatus}">
                    <i class="fas fa-user-lock"></i>
                </button>
                <button type="button" class="action-btn edit" data-id="${user.user_id}" title="Edit user">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="action-btn delete" data-id="${user.user_id}" title="Delete user">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    `;
}

function renderUserPanel(tbody, users, panelConfig) {
    const { colspan, showRole, showOffice, page, perPage } = panelConfig;
    const total = users.length;
    const totalPages = Math.max(1, Math.ceil(total / perPage) || 1);
    const safePage = Math.min(Math.max(1, page), totalPages);
    const start = (safePage - 1) * perPage;
    const pageUsers = users.slice(start, start + perPage);

    tbody.innerHTML = '';

    if (!pageUsers.length) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="users-table-loading">No users found.</td></tr>`;
        return safePage;
    }

    pageUsers.forEach((user, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = buildUserRowHtml(user, start + index + 1, { showRole, showOffice });
        tbody.appendChild(tr);
    });

    return safePage;
}

function buildDepartmentRowHtml(dept, rowNum) {
    const abbrev = (dept.department_abbreviation || '—').trim();
    const photoUrl = dept.department_photo_url || '';
    const hasPhoto = !!photoUrl;
    const photoSrc = hasPhoto ? `../${photoUrl}` : '';
    const statusRaw = (dept.department_status || 'Active').toLowerCase();
    const statusClass = statusRaw === 'active' ? 'active' : 'disabled';
    const statusLabel = dept.department_status || 'Active';
    const name = (dept.department_name || abbrev).trim();

    return `
        <td>${rowNum}</td>
        <td>
            <div class="user-photo-wrapper" data-email="${name}" data-photo="${photoSrc}">
                ${hasPhoto
                    ? `<img src="${photoSrc}" alt="Photo of ${abbrev}" class="user-photo-thumb">`
                    : `<div class="user-photo-placeholder">${(abbrev.charAt(0) || 'D').toUpperCase()}</div>`
                }
            </div>
        </td>
        <td class="users-col-abbrev" title="${abbrev}">${abbrev}</td>
        <td>${dept.department_type || '—'}</td>
        <td class="users-col-status"><span class="status-pill status-${statusClass}">${statusLabel}</span></td>
        <td>
            <div class="user-action-cell">
                <button type="button" class="action-btn view" data-id="${dept.department_id}" title="View details">
                    <i class="fas fa-eye"></i>
                </button>
                <button type="button" class="action-btn edit" data-id="${dept.department_id}" title="Edit department">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="action-btn delete" data-id="${dept.department_id}" title="Delete department">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    `;
}

function buildDeanRowInDeptTableHtml(user, rowNum) {
    const firstLetter = (user.full_name || user.email || '').charAt(0).toUpperCase() || 'D';
    const hasPhoto = !!user.photo_url;
    const photoSrc = hasPhoto ? `../${user.photo_url}` : '';
    const abbrev = (user.abbreviation || '—').trim();
    const statusRaw = (user.account_status || 'active').toLowerCase();
    const statusClass = statusRaw === 'active' ? 'active' : statusRaw === 'disabled' ? 'disabled' : 'locked';
    const statusLabel = statusRaw.charAt(0).toUpperCase() + statusRaw.slice(1);

    return `
        <td>${rowNum}</td>
        <td>
            <div class="user-photo-wrapper" data-email="${user.email || ''}" data-photo="${photoSrc}">
                ${hasPhoto
                    ? `<img src="${photoSrc}" alt="Photo of ${user.email}" class="user-photo-thumb">`
                    : `<div class="user-photo-placeholder">${firstLetter}</div>`
                }
            </div>
        </td>
        <td class="users-col-abbrev" title="${abbrev}">${abbrev}</td>
        <td>${user.department_type || '—'}</td>
        <td class="users-col-status"><span class="status-pill status-${statusClass}">${statusLabel}</span></td>
        <td>
            <div class="user-action-cell">
                <button type="button" class="action-btn view" data-id="${user.user_id}" data-row-type="dean" title="View details">
                    <i class="fas fa-eye"></i>
                </button>
                <button type="button" class="action-btn edit" data-id="${user.user_id}" data-row-type="dean" title="Edit user">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="action-btn delete" data-id="${user.user_id}" data-row-type="dean" title="Delete user">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    `;
}

function renderDepartmentPanel() {
    if (!deptUsersTableBody) return;

    const departments = filteredDepartments;
    const total = departments.length;
    const perPage = USERS_PER_PAGE;
    const totalPages = Math.max(1, Math.ceil(total / perPage) || 1);
    const safePage = Math.min(Math.max(1, deptCurrentPage), totalPages);
    const start = (safePage - 1) * perPage;
    const pageDepartments = departments.slice(start, start + perPage);

    deptUsersTableBody.innerHTML = '';

    if (!pageDepartments.length) {
        deptUsersTableBody.innerHTML = '<tr><td colspan="6" class="users-table-loading">No departments found.</td></tr>';
    } else {
        pageDepartments.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = row._isDean
                ? buildDeanRowInDeptTableHtml(row, start + index + 1)
                : buildDepartmentRowHtml(row, start + index + 1);
            deptUsersTableBody.appendChild(tr);
        });
    }

    if (deptUsersCountBadge) {
        deptUsersCountBadge.textContent = formatDepartmentsLabel(total);
    }

    deptCurrentPage = updatePanelPagination('dept', total, safePage, perPage);
}

function renderAdminUsersTable(users) {
    if (!adminUsersTableBody) return;

    filteredAdminUsers = flattenPanelUsers(users.filter(isAdminPanelUser), ADMIN_ROLE_DEFS);

    if (adminUsersCountBadge) {
        adminUsersCountBadge.textContent = formatUsersLabel(filteredAdminUsers.length);
    }

    adminCurrentPage = renderUserPanel(adminUsersTableBody, filteredAdminUsers, {
        colspan: 7,
        showRole: true,
        showOffice: false,
        page: adminCurrentPage,
        perPage: USERS_PER_PAGE,
    });
    adminCurrentPage = updatePanelPagination('admin', filteredAdminUsers.length, adminCurrentPage, USERS_PER_PAGE);
}

function renderSplitUserTables(users) {
    renderAdminUsersTable(users);
    filteredDepartments = getFilteredDepartments();
    renderDepartmentPanel();
}

function getFilteredDepartments() {
    const searchTerm = (searchInput?.value || '').toLowerCase().trim();

    const depts = allDepartments.filter((dept) => {
        if (!searchTerm) return true;
        const name = (dept.department_name || '').toLowerCase();
        const abbrev = (dept.department_abbreviation || '').toLowerCase();
        const username = (dept.department_username || '').toLowerCase();
        const type = (dept.department_type || '').toLowerCase();
        const status = (dept.department_status || '').toLowerCase();
        return name.includes(searchTerm) || abbrev.includes(searchTerm) || username.includes(searchTerm) || type.includes(searchTerm) || status.includes(searchTerm);
    }).map(d => ({ ...d, _isDean: false }));

    const deans = allUsers
        .filter(u => ['dean', 'user'].includes(normalizeUserRole(u.role)))
        .filter(u => {
            if (!searchTerm) return true;
            const name = (u.full_name || '').toLowerCase();
            const email = (u.email || '').toLowerCase();
            const status = (u.account_status || '').toLowerCase();
            const role = normalizeUserRole(u.role);
            return name.includes(searchTerm) || email.includes(searchTerm) || status.includes(searchTerm) || role.includes(searchTerm);
        })
        .map(u => ({ ...u, _isDean: true }));

    return [...depts, ...deans];
}

function getFilteredUsers() {
    const searchTerm = (searchInput?.value || '').toLowerCase().trim();

    return allUsers.filter((user) => {
        if (!searchTerm) return true;
        const email = (user.email || '').toLowerCase();
        const fullName = (user.full_name || '').toLowerCase();
        const role = (user.role || '').toLowerCase();
        const office = (user.office_name || '').toLowerCase();

        return email.includes(searchTerm)
            || fullName.includes(searchTerm)
            || role.includes(searchTerm)
            || office.includes(searchTerm);
    });
}

function searchAndFilterUsers() {
    adminCurrentPage = 1;
    deptCurrentPage = 1;
    renderSplitUserTables(getFilteredUsers());
}

function sortUsers(users, sortOption) {
    const usersCopy = [...users];

    switch(sortOption) {
        case 'email-asc':
            return usersCopy.sort((a, b) => (a.email || '').localeCompare(b.email || ''));
        case 'email-desc':
            return usersCopy.sort((a, b) => (b.email || '').localeCompare(a.email || ''));
        case 'role-asc':
            return usersCopy.sort((a, b) => (a.role || '').localeCompare(b.role || ''));
        case 'role-desc':
            return usersCopy.sort((a, b) => (b.role || '').localeCompare(a.role || ''));
        case 'office-asc':
            return usersCopy.sort((a, b) => (a.office_name || '').localeCompare(b.office_name || ''));
        case 'office-desc':
            return usersCopy.sort((a, b) => (b.office_name || '').localeCompare(a.office_name || ''));
        case 'date-asc':
            return usersCopy.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        case 'date-desc':
            return usersCopy.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        default:
            return usersCopy;
    }
}

searchInput?.addEventListener('input', searchAndFilterUsers);

adminPrevBtn?.addEventListener('click', () => {
    if (adminCurrentPage > 1) {
        adminCurrentPage -= 1;
        renderSplitUserTables(getFilteredUsers());
    }
});

adminNextBtn?.addEventListener('click', () => {
    const totalPages = Math.max(1, Math.ceil(filteredAdminUsers.length / USERS_PER_PAGE));
    if (adminCurrentPage < totalPages) {
        adminCurrentPage += 1;
        renderSplitUserTables(getFilteredUsers());
    }
});

deptPrevBtn?.addEventListener('click', () => {
    if (deptCurrentPage > 1) {
        deptCurrentPage -= 1;
        renderDepartmentPanel();
    }
});

deptNextBtn?.addEventListener('click', () => {
    const totalPages = Math.max(1, Math.ceil(filteredDepartments.length / USERS_PER_PAGE));
    if (deptCurrentPage < totalPages) {
        deptCurrentPage += 1;
        renderDepartmentPanel();
    }
});

loadUsers();
loadDepartments();