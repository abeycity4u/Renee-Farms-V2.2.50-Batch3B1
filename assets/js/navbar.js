/**
 * Shared authenticated navbar behavior.
 * Externalized for CSP compatibility.
 */
document.addEventListener('DOMContentLoaded', function () {
  const navDebugEnabled = (() => {
    const urlDebug = new URLSearchParams(window.location.search).get('nav_debug');
    if (urlDebug === '1') localStorage.setItem('nav_debug', '1');
    if (urlDebug === '0') localStorage.removeItem('nav_debug');
    return localStorage.getItem('nav_debug') === '1';
  })();

  const navDebug = (...args) => {
    if (!navDebugEnabled) return;
    console.log('[NavbarDebug]', ...args);
  };

  const toggles = document.querySelectorAll('.navbar [data-nav-dropdown-toggle="dropdown"]');
  navDebug('DOMContentLoaded', {
    page: window.location.pathname,
    toggleCount: toggles.length,
    bootstrapPresent: !!window.bootstrap,
    dropdownPluginPresent: !!(window.bootstrap && window.bootstrap.Dropdown),
    popperPresent: !!window.Popper,
    popperCreatePopperPresent: !!(window.Popper && typeof window.Popper.createPopper === 'function')
  });

  if (!toggles.length) return;

  // Always use a local navbar dropdown controller so Popper/Bootstrap mismatch cannot break navigation.
  navDebug('Using local navbar dropdown controller');

  const closeAll = () => {
    navDebug('Fallback closeAll');
    toggles.forEach((toggle) => {
      toggle.setAttribute('aria-expanded', 'false');
      const menu = toggle.parentElement?.querySelector('.dropdown-menu');
      if (menu) menu.classList.remove('show');
    });
  };

  toggles.forEach((toggle) => {
    toggle.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      const menu = this.parentElement?.querySelector('.dropdown-menu');
      if (!menu) return;

      const isOpen = menu.classList.contains('show');
      navDebug('Fallback toggle click', {
        id: this.id || '(no-id)',
        wasOpen: isOpen
      });
      closeAll();
      if (!isOpen) {
        menu.classList.add('show');
        this.setAttribute('aria-expanded', 'true');
        navDebug('Fallback menu opened', {
          id: this.id || '(no-id)'
        });
      }
    });
  });

  document.addEventListener('click', (event) => {
    navDebug('Document click closes menus', {
      target: event.target?.tagName || '(unknown)'
    });
    closeAll();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeAll();
    }
  });

  toggles.forEach((toggle) => {
    const menu = toggle.parentElement?.querySelector('.dropdown-menu');
    if (!menu) return;
    const items = () => Array.from(menu.querySelectorAll('.dropdown-item'));

    toggle.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        closeAll();
        menu.classList.add('show');
        toggle.setAttribute('aria-expanded', 'true');
        items()[0]?.focus();
      }
    });

    menu.addEventListener('keydown', (event) => {
      const list = items();
      if (!list.length) return;
      const currentIndex = list.indexOf(document.activeElement);

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        const nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % list.length;
        list[nextIndex].focus();
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        const prevIndex = currentIndex <= 0 ? list.length - 1 : currentIndex - 1;
        list[prevIndex].focus();
      } else if (event.key === 'Home') {
        event.preventDefault();
        list[0]?.focus();
      } else if (event.key === 'End') {
        event.preventDefault();
        list[list.length - 1]?.focus();
      } else if (event.key === 'Escape') {
        event.preventDefault();
        closeAll();
        toggle.focus();
      }
    });
  });

  const currentPath = window.location.pathname.replace(/\/+$/, '');
  const allLinks = document.querySelectorAll('.navbar .nav-link[href], .navbar .dropdown-item[href]');
  allLinks.forEach((link) => {
    const href = link.getAttribute('href');
    if (!href || href.startsWith('#')) return;
    const linkPath = new URL(href, window.location.origin).pathname.replace(/\/+$/, '');
    if (linkPath && linkPath === currentPath) {
      link.classList.add('active');
      const parentDropdown = link.closest('.dropdown');
      if (parentDropdown) {
        const toggle = parentDropdown.querySelector('.nav-link.dropdown-toggle');
        if (toggle) toggle.classList.add('active');
      }
    }
  });

  const navbar = document.getElementById('appNavbar');
  if (navbar) {
    const COMPACT_ENTER_Y = 52;
    const COMPACT_EXIT_Y = 20;

    const setCompactState = () => {
      const canCompact = window.innerWidth >= 992;
      if (!canCompact) {
        navbar.classList.remove('is-compact');
        return;
      }
      const currentlyCompact = navbar.classList.contains('is-compact');
      if (!currentlyCompact && window.scrollY > COMPACT_ENTER_Y) {
        navbar.classList.add('is-compact');
      } else if (currentlyCompact && window.scrollY < COMPACT_EXIT_Y) {
        navbar.classList.remove('is-compact');
      }
    };

    setCompactState();
    window.addEventListener('scroll', setCompactState, { passive: true });
    window.addEventListener('resize', setCompactState);
  }
});

document.addEventListener('DOMContentLoaded',function(){const menu=document.getElementById('themeToggle');const quick=document.getElementById('themeQuickToggle');const apply=(n)=>{document.documentElement.setAttribute('data-theme',n);document.documentElement.setAttribute('data-bs-theme',n);try{localStorage.setItem('farm-theme',n);}catch(e){}paint();};const paint=()=>{const d=document.documentElement.getAttribute('data-theme')==='dark';if(menu){const i=menu.querySelector('i'),t=menu.querySelector('span');if(i)i.className='bi '+(d?'bi-sun':'bi-moon-stars')+' menu-icon me-2';if(t)t.textContent=d?'Light mode':'Dark mode';}if(quick){const i=quick.querySelector('i');if(i)i.className='bi '+(d?'bi-sun':'bi-moon-stars');quick.setAttribute('aria-label',d?'Switch to light mode':'Switch to dark mode');quick.setAttribute('title',d?'Switch to light mode':'Dark mode');}};const toggle=()=>apply(document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark');paint();if(menu)menu.addEventListener('click',toggle);if(quick)quick.addEventListener('click',toggle);});
