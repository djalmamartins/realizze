(() => {
  'use strict';

  const initialize = () => {
    const $ = (selector) => document.querySelector(selector);
    const shell = $('#shell');
    const sidebar = $('#app-sidebar');
    const toggle = $('#sidebar-toggle');
    const closeButton = $('#sidebar-close');
    const backdrop = $('#sidebar-backdrop');
    // Keep the CSS fallback accessible if the viewport API is absent, broken
    // or cannot notify us about changes between mobile and desktop.
    // Without native inert, off-canvas links can still receive keyboard focus:
    // keep the visible CSS-only navigation instead of activating the drawer.
    let desktopViewport = null;
    if (shell && sidebar && 'inert' in sidebar && toggle && closeButton && backdrop && typeof window.matchMedia === 'function') {
      try {
        const candidate = window.matchMedia('(min-width: 851px)');
        if (candidate && typeof candidate.matches === 'boolean' &&
          (typeof candidate.addEventListener === 'function' || typeof candidate.addListener === 'function')) {
          desktopViewport = candidate;
        }
      } catch (_error) {
        // Do not activate the off-canvas navigation after a failed media query.
      }
    }
    const navigationReady = Boolean(desktopViewport);
    if (navigationReady) shell.classList.add('app-js');
    const toast = $('#toast');
    let toastTimer;
    // Prevent keyboard focus from entering the off-canvas mobile sidebar.
    const updateSidebarInert = () => {
      if (navigationReady) sidebar.inert = !desktopViewport.matches && !shell.classList.contains('menu-open');
    };

    const notify = (message) => {
      if (!toast) return;
      toast.textContent = message;
      toast.classList.add('visible');
      window.clearTimeout(toastTimer);
      toastTimer = window.setTimeout(() => toast.classList.remove('visible'), 3600);
    };

    const closeMenu = (restoreFocus = false) => {
      if (!shell) return;
      shell.classList.remove('menu-open');
      document.body.classList.remove('app-menu-locked');
      toggle?.setAttribute('aria-expanded', 'false');
      toggle?.setAttribute('aria-label', 'Abrir menu');
      backdrop?.setAttribute('aria-hidden', 'true');
      // The mobile toggle is hidden on desktop: keep focus inside the visible sidebar.
      if (!desktopViewport.matches && (restoreFocus || sidebar?.contains(document.activeElement))) toggle?.focus();
      updateSidebarInert();
    };

    const openMenu = () => {
      if (!shell) return;
      shell.classList.add('menu-open');
      document.body.classList.add('app-menu-locked');
      toggle?.setAttribute('aria-expanded', 'true');
      toggle?.setAttribute('aria-label', 'Fechar menu');
      backdrop?.setAttribute('aria-hidden', 'false');
      updateSidebarInert();
      (closeButton || sidebar?.querySelector('a'))?.focus();
    };

    // A partially rendered shell must keep the CSS fallback usable; do not
    // install controls that could hide links or move focus without a full menu.
    if (navigationReady) {
    toggle.addEventListener('click', () => {
      if (shell?.classList.contains('menu-open')) closeMenu(true);
      else openMenu();
    });
    closeButton?.addEventListener('click', () => closeMenu(true));
    backdrop?.addEventListener('click', () => closeMenu(true));
    sidebar?.querySelectorAll('a[href]').forEach((link) => {
      link.addEventListener('click', () => {
        if (shell?.classList.contains('menu-open')) closeMenu();
      });
    });

    // Disclosure navigation is not a modal: Tab may leave the menu.
    // Closing on focus exit prevents an off-canvas panel from staying open.
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && shell?.classList.contains('menu-open')) {
        event.preventDefault();
        closeMenu(true);
      }
    });
    document.addEventListener('focusin', (event) => {
      if (shell?.classList.contains('menu-open') && sidebar && !sidebar.contains(event.target)) {
        closeMenu();
      }
    });
    updateSidebarInert();
    const onViewportChange = (event) => {
      if (event.matches) {
        // Mobile-only controls disappear at the desktop breakpoint.
        // Move focus to a visible navigation link before hiding them.
        const focusWasOnMobileControl = document.activeElement === closeButton || document.activeElement === toggle;
        closeMenu();
        if (focusWasOnMobileControl) sidebar?.querySelector('.nav a[href], .brand')?.focus();
      } else {
        // The desktop sidebar becomes off-canvas; move focus before applying inert.
        if (sidebar?.contains(document.activeElement)) toggle?.focus();
        updateSidebarInert();
      }
    };
    if (typeof desktopViewport.addEventListener === 'function') {
      desktopViewport.addEventListener('change', onViewportChange);
    } else {
      // Legacy MediaQueryList implementations use addListener(callback).
      desktopViewport.addListener(onViewportChange);
    }
    }

    // Anchors in the PJ workspace move keyboard focus to the selected module.
    document.querySelectorAll('.enterprise-jump a[href^="#enterprise-"]').forEach((link) => {
      link.addEventListener('click', () => {
        const targetId = link.getAttribute('href')?.slice(1);
        const target = targetId ? document.getElementById(targetId) : null;
        if (target) window.setTimeout(() => target.focus({ preventScroll: true }), 0);
      });
    });

    // Client-side discovery only: never loads organizations or financial records.
    const enterpriseSearch = $('#enterprise-module-search');
    const enterpriseClear = $('#enterprise-module-clear');
    const enterpriseStatus = $('#enterprise-search-status');
    const enterpriseEmpty = $('#enterprise-search-empty');
    const enterpriseFilter = document.querySelector('.enterprise-module-filter');
    const enterpriseJump = document.querySelector('.enterprise-jump');
    const modules = Array.from(document.querySelectorAll('.enterprise-module-group .enterprise-module'));
    const groups = Array.from(document.querySelectorAll('.enterprise-module-group'));
    const jumpLinks = Array.from(document.querySelectorAll('.enterprise-jump a[href^="#enterprise-"]'));
    // Do not reveal controls until the entire client-side filter is initialized.
    // A partial DOM or failed initialization must preserve the visible module list.
    if (enterpriseSearch && enterpriseClear && enterpriseStatus && enterpriseEmpty &&
        enterpriseFilter && enterpriseJump && modules.length > 0 && groups.length > 0 &&
        jumpLinks.length === modules.length) {
      const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').replace(/\s+/g, ' ').trim();
      const applyFilter = () => {
        // Browsers can reset activeElement when a focused node becomes hidden.
        // Capture focus before changing visibility, then recover it if necessary.
        const focusedBeforeFilter = document.activeElement;
        const terms = normalize(enterpriseSearch.value).split(' ').filter(Boolean);
        let visible = 0;
        modules.forEach((module) => {
          const heading = module.querySelector('h4')?.textContent || '';
          const description = module.querySelector('.enterprise-module-header p')?.textContent || '';
          const groupTitle = module.closest('.enterprise-module-group')?.querySelector('h3')?.textContent || '';
          const columns = Array.from(module.querySelectorAll('.enterprise-module-table th'))
            .map((column) => column.textContent || '').join(' ');
          const searchable = normalize([heading, description, groupTitle, columns].join(' '));
          const matches = terms.every((term) => searchable.includes(term));
          module.hidden = !matches;
          if (matches) visible += 1;
        });
        groups.forEach((group) => {
          group.hidden = !Array.from(group.querySelectorAll('.enterprise-module')).some((module) => !module.hidden);
        });
        jumpLinks.forEach((link) => {
          const targetId = link.getAttribute('href')?.slice(1);
          const target = targetId ? document.getElementById(targetId) : null;
          link.hidden = !target || target.hidden;
        });
        // Filtering may hide a focused module or shortcut. Return keyboard focus
        // to the visible search field rather than leaving it inside hidden content.
        const active = focusedBeforeFilter;
        if (active && (
          modules.some((module) => module.hidden && module.contains(active)) ||
          jumpLinks.some((link) => link.hidden && link.contains(active))
        )) enterpriseSearch.focus();
        if (enterpriseEmpty) enterpriseEmpty.hidden = visible !== 0;
        if (enterpriseJump) enterpriseJump.hidden = visible === 0;
        if (enterpriseClear) enterpriseClear.disabled = enterpriseSearch.value.length === 0;
        enterpriseStatus.textContent = visible === 0
          ? 'Nenhum módulo corresponde à pesquisa.'
          : visible === 1 ? '1 módulo encontrado.' : visible + ' módulos encontrados.';
      };
      enterpriseSearch.addEventListener('input', applyFilter);
      enterpriseSearch.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || enterpriseSearch.value === '') return;
        event.preventDefault();
        enterpriseSearch.value = '';
        applyFilter();
      });
      enterpriseClear?.addEventListener('click', () => {
        enterpriseSearch.value = '';
        applyFilter();
        enterpriseSearch.focus();
      });
      applyFilter();
      enterpriseFilter.hidden = false;
    }

    document.querySelectorAll('[data-demo]').forEach((element) => {
      element.addEventListener('click', (event) => {
        event.preventDefault();
        notify('Recurso em preparação. Nenhum dado financeiro foi alterado.');
      });
    });

    $('#theme-toggle')?.addEventListener('click', () => {
      notify('O tema escuro será disponibilizado em uma próxima etapa.');
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
