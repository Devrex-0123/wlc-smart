/**
 * Logout: full-screen loader + API call + redirect.
 * Uses event delegation so every module’s logout control works the same.
 * Brand text is centralized here (not "IMRMS").
 */
(function () {
    const BRAND_NAME = 'WLC-SMART';

    function ensureAvatarPopoutMenu() {
        document.querySelectorAll('.sidebar-footer').forEach((footer) => {
            const userProfile = footer.querySelector('.user-profile');
            if (!userProfile) return;

            // Remove old button/menu variants.
            footer.querySelector('.sidebar-profile-menu')?.remove();
            footer.querySelector('#logoutBtn')?.remove();
            footer.querySelector('.btn-logout-sidebar')?.remove();

            if (userProfile.querySelector('.user-profile-popout')) return;
            userProfile.style.position = 'relative';

            const isDepartment = document.body.dataset.loginType === 'department';
            const profileHref = isDepartment ? 'department_profile.php' : 'my_profile.php';
            const profileLabel = isDepartment ? 'Department Profile' : 'Edit Profile';
            const profileIcon = isDepartment ? 'fa-building' : 'fa-user';

            const popout = document.createElement('div');
            popout.className = 'user-profile-popout';
            popout.innerHTML = `
                <a class="user-profile-popout-link" href="${profileHref}"><i class="fas ${profileIcon}"></i> ${profileLabel}</a>
                <button type="button" class="user-profile-popout-link" data-action="logout"><i class="fas fa-sign-out-alt"></i> Logout</button>
            `;
            userProfile.appendChild(popout);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureAvatarPopoutMenu);
    } else {
        ensureAvatarPopoutMenu();
    }

    document.addEventListener('click', (e) => {
        const profileClick = e.target.closest('.user-profile .user-avatar, .user-profile .user-details');
        if (profileClick) {
            e.preventDefault();
            e.stopPropagation();
            const menu = profileClick.closest('.user-profile')?.querySelector('.user-profile-popout');
            if (menu) {
                menu.classList.toggle('open');
            }
            return;
        }

        document.querySelectorAll('.user-profile-popout.open').forEach((el) => {
            if (!el.contains(e.target)) {
                el.classList.remove('open');
            }
        });
    });

    document.addEventListener(
        'click',
        async (e) => {
            const logoutBtn = e.target.closest('[data-action="logout"], #logoutBtn, .btn-logout-sidebar');
            if (!logoutBtn) {
                return;
            }
            e.preventDefault();

            if (!document.getElementById('loadingScreen')) {
                document.body.insertAdjacentHTML(
                    'beforeend',
                    `
                <div class="loading-screen" id="loadingScreen">
                    <div class="loading-logo">${BRAND_NAME}</div>
                    <div class="loading-spinner"></div>
                    <div class="loading-text">
                        Logging out<span class="loading-dots">
                            <span></span><span></span><span></span>
                        </span>
                    </div>
                </div>
            `
                );
            }

            try {
                await fetch('../../app/api/logout.php', {
                    method: 'POST',
                    credentials: 'include',
                });
            } catch (err) {
                console.error('Logout error:', err);
            } finally {
                setTimeout(() => {
                    window.location.href = '../../index.php';
                }, 3000);
            }
        },
        false
    );
})();
