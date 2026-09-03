(function () {
  'use strict';

  function initNavigation() {
    const navbar = document.getElementById('appNavbar');
    if (!navbar || navbar.dataset.appNavigationReady === '1') return;
    navbar.dataset.appNavigationReady = '1';

    const mobileToggle = navbar.querySelector('[data-app-navbar-toggle]');
    const menu = navbar.querySelector('#navbarMenu');

    const setMobileMenu = (open) => {
      if (!menu || !mobileToggle) return;
      menu.classList.toggle('show', !!open);
      mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      mobileToggle.classList.toggle('collapsed', !open);
    };

    if (mobileToggle && menu) {
      // Navigation must not depend on a page remembering to load Bootstrap JS.
      // Capture the click before Bootstrap's delegated data-api (if present)
      // so the same controller is authoritative on every page hierarchy.
      mobileToggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        setMobileMenu(!menu.classList.contains('show'));
      }, true);

      menu.querySelectorAll('a.nav-link, a.dropdown-item').forEach((link) => {
        link.addEventListener('click', () => {
          if (window.innerWidth < 992) setMobileMenu(false);
        });
      });

      window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
          mobileToggle.setAttribute('aria-expanded', 'false');
          mobileToggle.classList.add('collapsed');
          menu.classList.remove('show');
        }
      }, { passive: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNavigation, { once: true });
  } else {
    initNavigation();
  }
})();
