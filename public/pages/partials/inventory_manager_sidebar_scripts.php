<script>
(function () {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    if (mobileMenuBtn && sidebar) {
        mobileMenuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            sidebar.classList.toggle('open');
        });
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const sidebarNav = document.querySelector('.sidebar-nav');
        if (!sidebarNav) {
            return;
        }
        const scrollPosKey = 'sidebarScrollPos';
        const savedScrollPos = sessionStorage.getItem(scrollPosKey);
        if (savedScrollPos) {
            sidebarNav.scrollTop = parseInt(savedScrollPos, 10);
        }
        document.querySelectorAll('.sidebar-nav a').forEach(function (link) {
            link.addEventListener('click', function () {
                sessionStorage.setItem(scrollPosKey, String(sidebarNav.scrollTop));
            });
        });
    });
})();
</script>
