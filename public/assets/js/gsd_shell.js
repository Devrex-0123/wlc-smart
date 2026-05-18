/**
 * GSD workspace — mobile sidebar toggle (same behavior as inventory / comptroller shells).
 */
(function () {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) {
        return;
    }

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('open');
        });
    }

    document.addEventListener('click', (e) => {
        if (
            window.innerWidth <= 768 &&
            sidebar.classList.contains('open') &&
            !sidebar.contains(e.target) &&
            mobileMenuBtn &&
            !mobileMenuBtn.contains(e.target)
        ) {
            sidebar.classList.remove('open');
        }
    });
})();
