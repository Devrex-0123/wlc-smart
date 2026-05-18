document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('loginModal');
  const loginBtn = document.getElementById('loginBtn');           // Header Login
  const getStartedBtn = document.getElementById('getStartedBtn'); // Hero button
  const closeBtn = document.querySelector('.close');
  const togglePassword = document.querySelector('.toggle-password');
  const passwordField = document.getElementById('password');
  const toggleIcon = document.getElementById('toggleIcon');

  function openModal() {
    modal.style.display = 'block';
    void modal.offsetWidth; 
    modal.classList.add('show');
  }

  function closeModal() {
    modal.classList.remove('show');
    setTimeout(() => {
      modal.style.display = 'none';
    }, 500);
  }

  if (loginBtn) loginBtn.addEventListener('click', openModal);
  if (getStartedBtn) getStartedBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);

  // Re-open login modal after returning from policy pages.
  const qs = new URLSearchParams(window.location.search);
  if (qs.get('openLogin') === '1') {
    openModal();
  }

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
    if (e.key === 'Escape' && modal.classList.contains('show')) {
      closeModal();
    }
  });
});