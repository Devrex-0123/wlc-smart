// GSD account management — same behavior as dean_account_management.js; mobile sidebar is handled by gsd_shell.js

// ============== Toast Notifications ==============
function showToast(msg, type='success') {
    const container = document.getElementById('toastContainer');
    const div = document.createElement('div');
    div.className = `toast ${type}`;
    div.textContent = msg;
    container.appendChild(div);
    setTimeout(() => { div.remove(); }, 4000);
}

// ============== DOM Elements ==============
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
const searchInput = document.getElementById('searchInput');
const sortDropdown = document.getElementById('sortDropdown');

// ============== Password Validation ==============
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

// ============== Photo Preview Handler ==============
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

// ============== Load Users from Office ==============
let allUsers = [];

async function loadUsers() {
    try {
        const res = await fetch('../../app/api/dean_get_users.php', { credentials: 'include' });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Failed to load users', 'error');
            return;
        }

        allUsers = data.users || [];

        sortDropdown.value = '';
        searchInput.value = '';

        renderUserTable(allUsers);
    } catch(err){
        console.error(err);
        showToast('Error loading users', 'error');
    }
}

loadUsers();

// ============== Render User Table ==============
function renderUserTable(users) {
    if (users.length === 0) {
        usersTableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:50px;color:#64748b;">No users in your office</td></tr>';
        return;
    }

    usersTableBody.innerHTML = users.map((user, index) => {
        const photoHtml = user.photo_url
            ? `<img src="../${htmlspecialchars(user.photo_url)}" alt="${htmlspecialchars(user.email)}" class="user-photo-thumb">`
            : `<div class="user-photo-placeholder">${htmlspecialchars(user.email[0]).toUpperCase()}</div>`;

        return `
            <tr>
                <td>${index + 1}</td>
                <td>
                    <div class="user-photo-wrapper" data-email="${htmlspecialchars(user.email)}" data-photo="${htmlspecialchars(user.photo_url)}">
                        ${photoHtml}
                    </div>
                </td>
                <td>${htmlspecialchars(user.email)}</td>
                <td>${htmlspecialchars(user.role)}</td>
                <td>${formatDate(user.created_at)}</td>
                <td>
                    <div class="actions-cell">
                        <button class="edit" data-id="${user.user_id}" title="Edit User">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="delete" data-id="${user.user_id}" title="Delete User">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function htmlspecialchars(str) {
    if (!str) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return str.replace(/[&<>"']/g, m => map[m]);
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// ============== Search & Filter ==============
searchInput?.addEventListener('input', () => {
    const query = searchInput.value.toLowerCase();
    const filtered = allUsers.filter(user =>
        user.email.toLowerCase().includes(query) ||
        user.role.toLowerCase().includes(query)
    );

    if (sortDropdown.value) {
        sortUsers(filtered, sortDropdown.value);
    }

    renderUserTable(filtered);
});

// ============== Sort Functionality ==============
sortDropdown?.addEventListener('change', () => {
    if (!sortDropdown.value) {
        renderUserTable(allUsers);
        return;
    }

    const sorted = [...allUsers];
    sortUsers(sorted, sortDropdown.value);
    renderUserTable(sorted);
});

function sortUsers(users, sortType) {
    switch(sortType) {
        case 'email-asc':
            users.sort((a, b) => a.email.localeCompare(b.email));
            break;
        case 'email-desc':
            users.sort((a, b) => b.email.localeCompare(a.email));
            break;
        case 'role-asc':
            users.sort((a, b) => a.role.localeCompare(b.role));
            break;
        case 'role-desc':
            users.sort((a, b) => b.role.localeCompare(a.role));
            break;
        case 'date-asc':
            users.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
            break;
        case 'date-desc':
            users.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            break;
    }
}

// ============== Modal Control ==============
addUserBtn.addEventListener('click', () => {
    modalTitle.textContent = 'Add User';
    userForm.reset();

    if (photoPreview && photoPlaceholder) {
        photoPreview.src = '';
        photoPreview.style.display = 'none';
        photoPlaceholder.style.display = 'flex';
        photoPlaceholder.textContent = 'U';
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

window.addEventListener('click', e => {
    if(e.target === modal) modal.style.display='none';
});

// ============== Password Toggle ==============
document.addEventListener('click', e => {
    if(e.target.classList.contains('toggle-password')){
        const input = e.target.previousElementSibling;
        input.type = input.type==='password'?'text':'password';
        e.target.classList.toggle('fa-eye-slash');
    }
});

// ============== Form Submission ==============
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
        const res = await fetch('../../app/api/dean_user_actions.php', {
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
        console.error(err);
        showToast('An error occurred', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = "Save";
    }
});

// ============== Edit & Delete User ==============
usersTableBody.addEventListener('click', async e => {
    const editBtn = e.target.closest(".edit");
    const deleteBtn = e.target.closest(".delete");

    if(editBtn){
        const user = allUsers.find(u => u.user_id == editBtn.dataset.id);
        if(!user){
            showToast('User not found','error');
            return;
        }

        modalTitle.textContent='Edit User';
        document.getElementById('user_id').value = user.user_id;
        document.getElementById('email').value = user.email;
        document.getElementById('role').value = user.role;
        document.getElementById('password').required = false;
        document.getElementById('confirm_password').required = false;
        document.getElementById('passwordRequirements').style.display = 'none';
        document.getElementById('passwordStrength').textContent = 'Leave blank to keep current password';

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

    if(deleteBtn){
        if(!confirm('Are you sure you want to delete this user from your office?')) return;

        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('user_id', deleteBtn.dataset.id);
            formData.append('dept_name', document.querySelector('.page-header h1').textContent);
            formData.append('office_id', document.getElementById('office_id').value);

            const res = await fetch('../../app/api/dean_user_actions.php', {
                method:'POST',
                body: formData,
                credentials:'include'
            });

            const data = await res.json();
            if(data.success){
                showToast(data.message || 'User deleted');
                loadUsers();
            } else {
                showToast(data.message || 'Delete failed', 'error');
            }
        } catch(err){
            console.error(err);
            showToast('An error occurred', 'error');
        }
    }
});
