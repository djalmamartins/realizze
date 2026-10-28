(() => {
  'use strict';

  const initialize = () => {
    const $ = (selector) => document.querySelector(selector);
    const shell = $('#shell');
    const sidebar = $('#app-sidebar');
    const toggle = $('#sidebar-toggle');
    const closeButton = $('#sidebar-close');
    const backdrop = $('#sidebar-backdrop');
    const toast = $('#toast');
    let toastTimer;
    const desktopViewport = window.matchMedia('(min-width: 851px)');
    // Prevent keyboard focus from entering the off-canvas mobile sidebar.
    const updateSidebarInert = () => {
      if (sidebar) sidebar.inert = !desktopViewport.matches && !shell?.classList.contains('menu-open');
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
      if (restoreFocus || sidebar?.contains(document.activeElement)) toggle?.focus();
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

    toggle?.addEventListener('click', () => {
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

    document.addEventListener('keydown', (event) => {
      if (!shell?.classList.contains('menu-open')) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        closeMenu(true);
        return;
      }
      if (event.key !== 'Tab' || !sidebar) return;
      const focusable = Array.from(sidebar.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
      )).filter((element) => element.getClientRects().length > 0);
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      const outside = !sidebar.contains(document.activeElement);
      if (event.shiftKey && (outside || document.activeElement === first)) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && (outside || document.activeElement === last)) {
        event.preventDefault();
        first.focus();
      }
    });
    updateSidebarInert();
    if (typeof desktopViewport.addEventListener === 'function') {
      desktopViewport.addEventListener('change', (event) => {
        if (event.matches) closeMenu();
      });
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
    if (enterpriseSearch && enterpriseStatus) {
      const modules = Array.from(document.querySelectorAll('.enterprise-module-group .enterprise-module'));
      const groups = Array.from(document.querySelectorAll('.enterprise-module-group'));
      const jumpLinks = Array.from(document.querySelectorAll('.enterprise-jump a[href^="#enterprise-"]'));
      const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');
      const applyFilter = () => {
        const term = normalize(enterpriseSearch.value.trim());
        let visible = 0;
        modules.forEach((module) => {
          const heading = module.querySelector('h4')?.textContent || '';
          const description = module.querySelector('.enterprise-module-header p')?.textContent || '';
          const matches = !term || normalize(heading + ' ' + description).includes(term);
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
        if (enterpriseEmpty) enterpriseEmpty.hidden = visible !== 0;
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
