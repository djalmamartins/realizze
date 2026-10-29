/* Apenas navegação responsiva da prévia local; não executa ações administrativas. */
(() => {
  'use strict';
  const initialize = () => {
    const sidebar = document.getElementById('cms-sidebar');
    const toggle = document.getElementById('cms-menu-toggle');
    const nav = document.getElementById('cms-navigation');
    if (!sidebar || !toggle || !nav) return;
    sidebar.classList.add('cms-js');
    const close = (restoreFocus = false) => {
      sidebar.classList.remove('cms-menu-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Abrir navegação');
      if (restoreFocus && !desktop.matches) toggle.focus();
    };
    toggle.addEventListener('click', () => {
      const open = !sidebar.classList.contains('cms-menu-open');
      sidebar.classList.toggle('cms-menu-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Fechar navegação' : 'Abrir navegação');
      if (open) nav.querySelector('a')?.focus();
    });
    nav.addEventListener('click', (event) => {
      if (event.target.closest('a')) close();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && sidebar.classList.contains('cms-menu-open')) {
        event.preventDefault();
        close(true);
      }
    });
    // Disclosure menu: close on outside interaction without trapping focus in the page.
    document.addEventListener('pointerdown', (event) => {
      if (sidebar.classList.contains('cms-menu-open') && !sidebar.contains(event.target)) close();
    });
    // Keyboard users may Tab out of the menu; never leave it visually open
    // once focus has moved into the main content.
    document.addEventListener('focusin', (event) => {
      if (sidebar.classList.contains('cms-menu-open') && !sidebar.contains(event.target)) close();
    });
    const desktop = window.matchMedia('(min-width: 721px)');
    if (typeof desktop.addEventListener === 'function') {
      desktop.addEventListener('change', (event) => {
        if (event.matches) {
          // The mobile toggle disappears at this breakpoint. Recover focus to
          // a visible navigation link only when it was on that toggle.
          const focusWasOnToggle = document.activeElement === toggle;
          close();
          if (focusWasOnToggle) nav.querySelector('a')?.focus();
        } else if (nav.contains(document.activeElement)) {
          // Desktop links become hidden when the mobile disclosure is collapsed.
          toggle.focus();
        }
      });
    }
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
