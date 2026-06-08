document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('loginModal');
  const loginBtn = document.getElementById('loginBtn');           // Header Login (legacy)
  const getStartedBtn = document.getElementById('getStartedBtn'); // Nav button
  const heroLaunchBtn = document.getElementById('heroLaunchBtn'); // Hero "Launch System"
  const footerLaunchLink = document.getElementById('footerLaunchLink'); // Footer "Launch System"
  const closeBtn = document.querySelector('.modal-close-x') || document.querySelector('.close');
  const togglePassword = document.querySelector('.toggle-password');
  const passwordField = document.getElementById('password');
  const toggleIcon = document.getElementById('toggleIcon');

  const ACTIVE_CLASS = 'display-active';

  function openModal(e) {
    if (e) e.preventDefault();
    modal.classList.add(ACTIVE_CLASS);
  }

  function closeModal() {
    modal.classList.remove(ACTIVE_CLASS);
  }

  if (loginBtn) loginBtn.addEventListener('click', openModal);
  if (getStartedBtn) getStartedBtn.addEventListener('click', openModal);
  if (heroLaunchBtn) heroLaunchBtn.addEventListener('click', openModal);
  if (footerLaunchLink) footerLaunchLink.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);

  // Re-open login modal after returning from policy pages.
  const qs = new URLSearchParams(window.location.search);
  if (qs.get('openLogin') === '1') {
    openModal();
  }

  // Close when clicking the dark overlay (outside the modal box)
  window.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });

  // Toggle password visibility
  if (togglePassword && passwordField && toggleIcon) {
    togglePassword.addEventListener('click', function () {
      const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordField.setAttribute('type', type);
      toggleIcon.classList.toggle('fa-eye');
      toggleIcon.classList.toggle('fa-eye-slash');
    });
  }

  // Close with Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains(ACTIVE_CLASS)) {
      closeModal();
    }
  });
});
