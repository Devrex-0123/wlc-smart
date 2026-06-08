(function () {
    const navLinks = document.querySelectorAll('.nav-links a');
    const featuresSection = document.getElementById('features');
    const aboutSection = document.getElementById('about');
    const headerOffset = 100;

    function setActiveNav(hash) {
        const targetHash = hash || '#';

        navLinks.forEach(function (link) {
            const href = link.getAttribute('href');
            link.classList.toggle('active', href === targetHash);
        });
    }

    function getActiveHashFromScroll() {
        const scrollPos = window.scrollY + headerOffset;

        if (aboutSection && scrollPos >= aboutSection.offsetTop) {
            return '#about';
        }

        if (featuresSection && scrollPos >= featuresSection.offsetTop) {
            return '#features';
        }

        return '#';
    }

    navLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            const href = link.getAttribute('href');

            if (href === '#') {
                event.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
                setActiveNav('#');
                return;
            }

            if (href && href.startsWith('#')) {
                setActiveNav(href);
            }
        });
    });

    function updateActiveNav() {
        setActiveNav(getActiveHashFromScroll());
    }

    window.addEventListener('scroll', updateActiveNav, { passive: true });
    window.addEventListener('hashchange', function () {
        setActiveNav(window.location.hash || '#');
    });

    updateActiveNav();
})();
