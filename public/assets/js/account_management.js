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
const modalTitle = document.getElementById('modalTitle');
const userForm = document.getElementById('userForm');
const usersTableBody = document.getElementById("usersTableBody");
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
let pendingDeleteUserId = null;

// ---------------- Disable account confirmation (same UX as delete modal) ----------------
const disableConfirmModal = document.getElementById('disableConfirmModal');
const disableConfirmBackdrop = document.getElementById('disableConfirmBackdrop');
const disableConfirmCancel = document.getElementById('disableConfirmCancel');
const disableConfirmOk = document.getElementById('disableConfirmOk');
const disableConfirmEmail = document.getElementById('disableConfirmEmail');
let pendingDisableUserId = null;
const viewUserModal = document.getElementById('viewUserModal');
const viewUserBackdrop = document.getElementById('viewUserBackdrop');
const viewUserCloseBtn = document.getElementById('viewUserCloseBtn');

function formatDateTime(value) {
    if (!value) return '—';
    const dt = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(dt.getTime())) return String(value);
    return dt.toLocaleString();
}

function openViewUserModal(user) {
    if (!viewUserModal || !user) return;
    const consentText = user.has_consented == 1 ? `Accepted (${user.consent_version || 'n/a'})` : 'Not yet accepted';
    document.getElementById('view_full_name').textContent = user.full_name || '—';
    document.getElementById('view_email').textContent = user.email || '—';
    document.getElementById('view_role').textContent = user.role || '—';
    document.getElementById('view_status').textContent = user.account_status || '—';
    document.getElementById('view_office').textContent = user.office_name || '—';
    document.getElementById('view_consent').textContent = consentText;
    document.getElementById('view_last_login').textContent = formatDateTime(user.last_login);
    document.getElementById('view_created_at').textContent = formatDateTime(user.created_at);
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

function closeDeleteConfirmModal() {
    pendingDeleteUserId = null;
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
            showToast('User soft-deleted successfully');
            closeDeleteConfirmModal();
            loadUsers();
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
            showToast(data.message || 'Account disabled');
            closeDisableConfirmModal();
            loadUsers();
        } else {
            showToast(data.message || 'Failed to update status', 'error');
        }
    } catch (_) {
        showToast('Network error', 'error');
    } finally {
        disableConfirmOk.disabled = false;
    }
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && deleteConfirmModal?.classList.contains('is-open')) {
        closeDeleteConfirmModal();
    }
    if (e.key === 'Escape' && disableConfirmModal?.classList.contains('is-open')) {
        closeDisableConfirmModal();
    }
    if (e.key === 'Escape' && viewUserModal?.classList.contains('is-open')) {
        closeViewUserModal();
    }
});
viewUserBackdrop?.addEventListener('click', closeViewUserModal);
viewUserCloseBtn?.addEventListener('click', closeViewUserModal);

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

    document.getElementById('passwordStrength').textContent = strength;
    document.getElementById('passwordStrength').style.color = met === 5 ? '#10b981' : met >= 3 ? '#f59e0b' : '#ef4444';

    return tests;
}

passwordInput?.addEventListener('input', () => {
    if (modalTitle.textContent.includes('Add') || passwordInput.value) {
        validatePasswordStrict(passwordInput.value);
    }
});

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

// ---------------- Load Users & Offices (unchanged) ----------------
let allUsers = [];
async function loadUsers() {
    try {
        const res = await fetch('../../app/api/get_users.php', { credentials: 'include' });
        const data = await res.json();
        allUsers = data.users || [];
        
        // Clear sort and search when reloading
        sortDropdown.value = '';
        searchInput.value = '';
        
        renderUserTable(allUsers);
    } catch(err){
        console.error(err);
    }
}

async function populateOfficeDropdown() {
    try {
        const res = await fetch('../../app/api/office.php', { credentials: 'include' });
        const data = await res.json();
        const officeDropdown = document.getElementById('office_id');
        officeDropdown.innerHTML = '<option value="">Select Office</option>';
        if(data.success && data.offices.length > 0){
            data.offices.forEach(dept => {
                const opt = document.createElement('option');
                opt.value = dept.office_id;
                opt.textContent = dept.office_name;
                officeDropdown.appendChild(opt);
            });
        }
    } catch(err){
        console.error(err);
    }
}
populateOfficeDropdown();
loadUsers();

// ---------------- Modal & Submit (STRICT VALIDATION) ----------------
addUserBtn.addEventListener('click', () => {
    modalTitle.textContent = 'Add User';
    userForm.reset();
    // Reset photo preview
    if (photoPreview && photoPlaceholder) {
        photoPreview.src = '';
        photoPreview.style.display = 'none';
        photoPlaceholder.textContent = (document.getElementById('email').value || '').charAt(0).toUpperCase() || '';
    }
    document.getElementById('password').required = true;
    document.getElementById('confirm_password').required = true;
    document.getElementById('user_id').value = '';
    document.getElementById('passwordRequirements').style.display = 'block';
    document.getElementById('passwordStrength').textContent = '';
    modal.style.display = 'flex';
    validatePasswordStrict('');
});

closeModal.addEventListener('click', () => modal.style.display = 'none');
window.addEventListener('click', e => { if(e.target === modal) modal.style.display='none'; });

document.addEventListener('click', e => {
    if(e.target.classList.contains('toggle-password')){
        const input = e.target.previousElementSibling;
        input.type = input.type==='password'?'text':'password';
        e.target.classList.toggle('fa-eye-slash');
    }
});

userForm.addEventListener('submit', async e => {
    e.preventDefault();
    saveBtn.disabled = true;
    saveBtn.textContent = "Saving...";

    const pwd = passwordInput.value;
    const confirmPwd = confirmPasswordInput.value;

    if (pwd !== confirmPwd) {
        showToast('Passwords do not match', 'error');
        saveBtn.disabled = false;
        saveBtn.textContent = "Save";
        return;
    }

    // STRICT: Only allow save if adding user OR password is filled and valid
    if (modalTitle.textContent.includes('Add') || pwd) {
        const tests = validatePasswordStrict(pwd);
        const allPassed = Object.values(tests).every(Boolean);
        if (!allPassed) {
            showToast('Password must meet all requirements!', 'error');
            saveBtn.disabled = false;
            saveBtn.textContent = "Save";
            return;
        }
    }

    const formData = new FormData(userForm);
    try {
        const res = await fetch('../../app/api/user_actions.php', { 
            method:'POST', 
            body: formData, 
            credentials:'include' 
        });
        const data = await res.json();
        if(data.success){ 
            showToast(data.message || 'Saved successfully'); 
            modal.style.display='none'; 
            loadUsers(); 
        } else {
            showToast(data.message || 'Action failed', 'error');
        }
    } catch(err){
        showToast('An error occurred', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = "Save";
    }
});

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
usersTableBody.addEventListener('click', async e => {
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

    const editBtn = e.target.closest(".edit");
    const deleteBtn = e.target.closest(".delete");
    const statusBtn = e.target.closest(".status-toggle");
    const viewBtn = e.target.closest(".view");

    if (statusBtn) {
        const uid = statusBtn.dataset.id;
        const nextStatus = statusBtn.dataset.next;
        if (nextStatus === 'disabled') {
            const rowUser = allUsers.find(u => String(u.user_id) === String(uid));
            openDisableConfirmModal(uid, rowUser?.email || '');
            return;
        }
        try {
            const data = await postToggleStatus(uid, nextStatus);
            if (data.success) {
                showToast(data.message || 'Status updated');
                loadUsers();
            } else {
                showToast(data.message || 'Failed to update status', 'error');
            }
        } catch (_) {
            showToast('Network error', 'error');
        }
        return;
    }

    if(editBtn){
        const user = allUsers.find(u => u.user_id == editBtn.dataset.id);
        if(!user){ showToast('User not found','error'); return; }
        modalTitle.textContent='Edit User';
        document.getElementById('user_id').value = user.user_id;
        document.getElementById('email').value = user.email;
        document.getElementById('full_name').value = user.full_name || '';
        document.getElementById('contact_number').value = user.contact_number || '';
        document.getElementById('role').value = user.role;
        document.getElementById('office_id').value = user.office_id || '';
        document.getElementById('account_status').value = (user.account_status || 'active').toLowerCase();
        document.getElementById('password').required = false;
        document.getElementById('confirm_password').required = false;
        document.getElementById('passwordRequirements').style.display = 'none';
        document.getElementById('passwordStrength').textContent = 'Leave blank to keep current password';

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

// -------- SEARCH & SORT FUNCTIONALITY --------
const searchInput = document.getElementById('searchInput');
const sortDropdown = document.getElementById('sortDropdown');

function renderUserTable(users) {
    usersTableBody.innerHTML = '';
    if (!users || users.length === 0) {
        usersTableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:50px;color:#64748b;">No users found.</td></tr>`;
        return;
    }

    users.forEach((user, index) => {
        const firstLetter = (user.email || '').charAt(0).toUpperCase();
        const hasPhoto = !!user.photo_url;
        const photoSrc = hasPhoto ? `../${user.photo_url}` : '';

        const tr = document.createElement('tr');
        const status = (user.account_status || 'active').toLowerCase();
        const nextStatus = status === 'active' ? 'disabled' : 'active';
        const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        tr.innerHTML = `
            <td>${index+1}</td>
            <td>
                <div class="user-photo-wrapper" data-email="${user.email}" data-photo="${photoSrc}">
                    ${hasPhoto
                        ? `<img src="${photoSrc}" alt="Photo of ${user.email}" class="user-photo-thumb">`
                        : `<div class="user-photo-placeholder">${firstLetter}</div>`
                    }
                </div>
            </td>
            <td>${user.full_name || ''}</td>
            <td>${user.email}</td>
            <td>${user.role}</td>
            <td><span class="status-pill status-${status}">${statusLabel}</span></td>
            <td>${user.office_name || ''}</td>
            <td>
                <button class="action-btn view" data-id="${user.user_id}" title="View details">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="action-btn status-toggle" data-id="${user.user_id}" data-next="${nextStatus}" title="Set ${nextStatus}">
                    <i class="fas fa-user-lock"></i>
                </button>
                <button class="action-btn edit" data-id="${user.user_id}"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete" data-id="${user.user_id}"><i class="fas fa-trash"></i></button>
            </td>
        `;
        usersTableBody.appendChild(tr);
    });
}

function searchAndFilterUsers() {
    const searchTerm = (searchInput.value || '').toLowerCase().trim();
    const sortOption = sortDropdown.value;

    let filteredUsers = allUsers.filter(user => {
        if (!searchTerm) return true;
        const email = (user.email || '').toLowerCase();
        const fullName = (user.full_name || '').toLowerCase();
        const role = (user.role || '').toLowerCase();
        const office = (user.office_name || '').toLowerCase();
        
        return email.includes(searchTerm) || fullName.includes(searchTerm) || role.includes(searchTerm) || office.includes(searchTerm);
    });

    // Apply sorting
    if (sortOption) {
        filteredUsers = sortUsers(filteredUsers, sortOption);
    }

    renderUserTable(filteredUsers);
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

// Event listeners for search and sort
searchInput?.addEventListener('input', searchAndFilterUsers);
sortDropdown?.addEventListener('change', searchAndFilterUsers);