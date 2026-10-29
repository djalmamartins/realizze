/* Apenas navegação responsiva da prévia local; não executa ações administrativas. */
(() => {
  'use strict';
  const initialize = () => {
    const sidebar = document.getElementById('cms-sidebar');
    const toggle = document.getElementById('cms-menu-toggle');
    const nav = document.getElementById('cms-navigation');
    // Keep the CSS navigation fallback visible unless the complete disclosure
    // and viewport APIs are available. Never hide the links on partial startup.
    if (!sidebar || !toggle || !nav || typeof window.matchMedia !== 'function') return;
    const firstLink = nav.querySelector('a[href]');
    if (!firstLink) return;
    let desktop;
    try {
      desktop = window.matchMedia('(min-width: 721px)');
    } catch (_error) {
      return;
    }
    if (!desktop || typeof desktop.matches !== 'boolean') return;
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
      if (open) firstLink.focus();
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
    const onViewportChange = (event) => {
        if (event.matches) {
          // The mobile toggle disappears at this breakpoint. Recover focus to
          // a visible navigation link only when it was on that toggle.
          const focusWasOnToggle = document.activeElement === toggle;
          close();
          if (focusWasOnToggle) firstLink.focus();
        } else if (nav.contains(document.activeElement)) {
          // Desktop links become hidden when the mobile disclosure is collapsed.
          toggle.focus();
        }
    };
    if (typeof desktop.addEventListener === 'function') {
      desktop.addEventListener('change', onViewportChange);
    } else if (typeof desktop.addListener === 'function') {
      // Older WebKit/Chromium versions still use the legacy MediaQueryList API.
      desktop.addListener(onViewportChange);
    }
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
