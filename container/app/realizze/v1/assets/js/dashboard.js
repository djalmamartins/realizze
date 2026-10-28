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
      toggle?.setAttribute('aria-expanded', 'false');
      backdrop?.setAttribute('aria-hidden', 'true');
      if (restoreFocus) toggle?.focus();
    };

    const openMenu = () => {
      if (!shell) return;
      shell.classList.add('menu-open');
      toggle?.setAttribute('aria-expanded', 'true');
      backdrop?.setAttribute('aria-hidden', 'false');
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
      if (event.key === 'Escape' && shell?.classList.contains('menu-open')) {
        closeMenu(true);
      }
    });
    const desktopViewport = window.matchMedia('(min-width: 851px)');
    if (typeof desktopViewport.addEventListener === 'function') {
      desktopViewport.addEventListener('change', (event) => {
        if (event.matches) closeMenu();
      });
    }

    document.querySelectorAll('[data-demo]').forEach((element) => {
      element.addEventListener('click', (event) => {
        event.preventDefault();
        notify('Recurso em preparação. Nenhum dado financeiro foi alterado.');
      });
    });

    // Only August 2026 has illustrative values. Do not show the same values
    // under a different month or switch to annual totals.
    ['#prev-month', '#next-month', '#previous-month-label', '#current-month-label'].forEach((selector) => {
      $(selector)?.addEventListener('click', () => {
        notify('Os dados ilustrativos são de agosto de 2026. Outros períodos estarão disponíveis com a integração financeira.');
      });
    });
    document.querySelectorAll('[data-period]').forEach((element) => {
      element.addEventListener('click', () => {
        if (element.dataset.period !== 'mensal') {
          notify('A visão anual será disponibilizada quando houver dados financeiros reais.');
        }
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
