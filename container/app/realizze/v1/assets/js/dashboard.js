(() => {
  'use strict';

  const initialize = () => {
    const $ = (selector) => document.querySelector(selector);
    const shell = $('#shell');
    const sidebar = $('#app-sidebar');
    let toggle = $('#sidebar-toggle');
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
    document.addEventListener('click', (event) => {
      if (event.target.closest?.('#sidebar-toggle')) {
        toggle = $('#sidebar-toggle');
        if (shell?.classList.contains('menu-open')) closeMenu(true);
        else openMenu();
        return;
      }
      // Os links da navegação são substituídos durante a troca AJAX de
      // página/contexto. A delegação mantém o fechamento do drawer também
      // para os links novos, sem reinstalar listeners a cada resposta.
      if (event.target.closest?.('#app-sidebar a[href]') && shell?.classList.contains('menu-open')) closeMenu();
    });
    closeButton?.addEventListener('click', () => closeMenu(true));
    backdrop?.addEventListener('click', () => closeMenu(true));

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

    // Only the most recent shortcut may move focus after a deferred click.
    // Rapid keyboard activation must not return focus to an older module.
    let enterpriseFocusRequest = 0;
    // Anchors in the PJ workspace move keyboard focus to the selected module.
    document.querySelectorAll('.enterprise-jump a[href^="#enterprise-"]').forEach((link) => {
      link.addEventListener('click', () => {
        const request = ++enterpriseFocusRequest;
        const targetId = link.getAttribute('href')?.slice(1);
        const target = targetId ? document.getElementById(targetId) : null;
        // A filter can hide a module between click and the deferred focus.
        // Never move keyboard focus into hidden content.
        if (target && !target.hidden && target.closest('.enterprise-module-group') &&
            !target.closest('.enterprise-module-group').hidden) {
          window.setTimeout(() => {
            if (request === enterpriseFocusRequest && target.isConnected && !target.hidden &&
                target.closest('.enterprise-module-group') && !target.closest('.enterprise-module-group').hidden &&
                !link.hidden && link.isConnected &&
                link.getAttribute('href') === '#' + targetId) {
              target.focus({ preventScroll: true });
            }
          }, 0);
        }
      });
    });

    // Client-side discovery only: never loads organizations or financial records.
    const enterpriseSearch = $('#enterprise-module-search');
    const enterpriseClear = $('#enterprise-module-clear');
    const enterpriseStatus = $('#enterprise-search-status');
    const enterpriseEmpty = $('#enterprise-search-empty');
    const enterpriseFilter = document.querySelector('.enterprise-module-filter');
    const enterpriseJump = document.querySelector('.enterprise-jump');
    const enterpriseJumpHelp = $('#enterprise-jump-help');
    const modules = Array.from(document.querySelectorAll('.enterprise-module-group .enterprise-module'));
    const groups = Array.from(document.querySelectorAll('.enterprise-module-group'));
    const jumpLinks = Array.from(document.querySelectorAll('.enterprise-jump a[href^="#enterprise-"]'));
    // Each shortcut must resolve to one unique rendered module. Equal counts
    // alone can conceal duplicate or stale targets after a template change.
    const moduleIds = new Set(modules.map((module) => module.id));
    const shortcutIds = jumpLinks.map((link) => link.getAttribute('href')?.slice(1));
    const shortcutsReady = modules.length > 0 && moduleIds.size === modules.length &&
      shortcutIds.length === modules.length &&
      new Set(shortcutIds).size === modules.length &&
      shortcutIds.every((id) => moduleIds.has(id));
    // Do not reveal a nonfunctional search when Unicode normalization is absent.
    // A partial DOM, invalid navigation or unsupported browser keeps all modules
    // available through the ordinary visible links and headings.
    if (enterpriseSearch && enterpriseClear && enterpriseStatus && enterpriseEmpty &&
        enterpriseFilter && enterpriseJump && enterpriseJumpHelp && groups.length > 0 && shortcutsReady &&
        typeof ''.normalize === 'function') {
      const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').replace(/\s+/g, ' ').trim();
      const applyFilter = () => {
        // A pending shortcut focus belongs to the previous filter state.
        // Invalidate it even if the same module becomes visible again before
        // the queued callback runs.
        enterpriseFocusRequest += 1;
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
        enterpriseJumpHelp.hidden = visible === 0;
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

// Navegação sem recarregar, modais, carregamento e animações do app.
(() => {
  'use strict';
  if (typeof document.createElement !== 'function' || !document.body) return;
  const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const main = () => document.querySelector('#overview');
  const brl = (cents) => (cents < 0 ? '- ' : '') + 'R$ ' + (Math.abs(cents) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const loader = document.createElement('div');
  loader.className = 'app-loader';
  loader.setAttribute('aria-hidden', 'true');
  loader.innerHTML = '<span class="app-loader-bar"></span><span class="app-loader-mark"><img src="/assets/realizze/v1/images/favicon.svg" alt="" width="44" height="44"></span>';
  document.body.append(loader);
  let loaderTimer;
  const showLoader = () => {
    loader.classList.add('is-active');
    window.clearTimeout(loaderTimer);
    loaderTimer = window.setTimeout(() => loader.classList.add('is-slow'), 220);
  };
  const hideLoader = () => {
    window.clearTimeout(loaderTimer);
    loader.classList.remove('is-active', 'is-slow');
  };

  // Seções entram em sequência; números contam e gráficos crescem a partir de zero.
  const animate = () => {
    const root = main();
    if (!root) return;
    const items = [...root.children].filter((el) => !['DIALOG', 'SCRIPT'].includes(el.tagName));
    const expanded = items.flatMap((el) => (el.matches('.cards, .content-grid') ? [...el.children] : [el]));
    expanded.forEach((el, index) => {
      el.style.setProperty('--enter-delay', Math.min(index, 12) * 55 + 'ms');
      el.classList.remove('app-enter');
      void el.offsetWidth;
      el.classList.add('app-enter');
    });
    const finish = reduced();
    root.querySelectorAll('[data-width]').forEach((bar) => {
      bar.style.width = finish ? bar.dataset.width + '%' : '0%';
      if (!finish) requestAnimationFrame(() => requestAnimationFrame(() => { bar.style.width = bar.dataset.width + '%'; }));
    });
    root.querySelectorAll('[data-height]').forEach((bar, index) => {
      bar.style.height = finish ? bar.dataset.height + '%' : '0%';
      if (!finish) {
        bar.style.transitionDelay = index * 40 + 'ms';
        requestAnimationFrame(() => requestAnimationFrame(() => { bar.style.height = bar.dataset.height + '%'; }));
      }
    });
    root.querySelectorAll('.donut-slice').forEach((slice, index) => {
      if (finish) return;
      const target = slice.getAttribute('stroke-dasharray');
      slice.style.transition = 'none';
      slice.setAttribute('stroke-dasharray', '0 100');
      requestAnimationFrame(() => requestAnimationFrame(() => {
        slice.style.transition = 'stroke-dasharray .8s cubic-bezier(.2,.7,.2,1) ' + index * 60 + 'ms';
        slice.setAttribute('stroke-dasharray', target);
      }));
    });
    if (finish) return;
    root.querySelectorAll('[data-count]').forEach((el) => {
      const target = Number(el.dataset.count);
      const started = performance.now();
      const tick = (now) => {
        const progress = Math.min((now - started) / 700, 1);
        el.textContent = brl(Math.round(target * (1 - (1 - progress) ** 3)));
        if (progress < 1 && el.isConnected) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    });
  };

  // Só as telas financeiras trocam sem recarregar; as demais recarregam normalmente.
  const appPath = (url) => url.pathname === '/'
    || /^\/(p|e\/[a-z0-9][a-z0-9-]{0,189}|pf\/pessoal|pj\/minha-empresa)(\/|$)/.test(url.pathname)
      && !url.pathname.startsWith('/minha-conta/');
  const swappable = (url) => url.origin === location.origin && appPath(url) && url.searchParams.get('pagina') !== 'empresarial' && !url.pathname.endsWith('/empresarial');
  const currentSwappable = () => !document.body.dataset.appErrorStatus && !location.search.includes('pagina=empresarial') && !location.pathname.endsWith('/empresarial');

  const apply = (html, url, push) => {
    const next = new DOMParser().parseFromString(html, 'text/html');
    const incoming = next.querySelector('#overview');
    if (!incoming) return false;
    main().replaceChildren(...incoming.childNodes);
    const nav = document.querySelector('#app-sidebar .nav');
    const incomingNav = next.querySelector('#app-sidebar .nav');
    if (nav && incomingNav) nav.replaceChildren(...incomingNav.childNodes);
    const context = document.querySelector('#app-sidebar .context-switch');
    const incomingContext = next.querySelector('#app-sidebar .context-switch');
    if (context && incomingContext) context.replaceWith(incomingContext);
    // A marca e o perfil também carregam o contexto financeiro na URL. Sem
    // sincronizá-los, trocar PF/PJ via AJAX deixa links antigos na sidebar e o
    // próximo clique muda silenciosamente de contexto.
    const brand = document.querySelector('#app-sidebar .brand');
    const incomingBrand = next.querySelector('#app-sidebar .brand');
    if (brand && incomingBrand) brand.setAttribute('href', incomingBrand.getAttribute('href') || '/p');
    const profile = document.querySelector('#app-sidebar .profile-link');
    const incomingProfile = next.querySelector('#app-sidebar .profile-link');
    if (profile && incomingProfile) profile.setAttribute('href', incomingProfile.getAttribute('href') || '/p/perfil');
    document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
    document.body.dataset.context = next.body.dataset.context || 'pf';
    const errorStatus = next.body.dataset.appErrorStatus;
    if (errorStatus) document.body.dataset.appErrorStatus = errorStatus;
    else delete document.body.dataset.appErrorStatus;
    document.title = next.title;
    document.dispatchEvent(new Event('app:swapped'));
    if (push) history.pushState({}, '', url);
    animate();
    return true;
  };

  const notify = (message) => {
    const toast = document.querySelector('#toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('visible');
    window.setTimeout(() => toast.classList.remove('visible'), 3600);
  };

  const go = async (url, options = {}, push = true) => {
    showLoader();
    try {
      const response = await fetch(url, { credentials: 'same-origin', ...options });
      const finalUrl = new URL(response.url);
      const html = await response.text();
      if (!response.ok) {
        const isAppError = [403, 404, 500].includes(response.status)
          && new DOMParser().parseFromString(html, 'text/html').body.dataset.appErrorStatus === String(response.status);
        if (!isAppError) throw new Error('navigation');
        // Página de erro ocupa a tela inteira: troca o corpo e o histórico, preservando o status recebido.
        const errorPage = new DOMParser().parseFromString(html, 'text/html');
        document.body.className = errorPage.body.className;
        document.body.dataset.appErrorStatus = String(response.status);
        document.body.replaceChildren(...errorPage.body.childNodes);
        document.title = errorPage.title;
        if (push && !options.method) history.pushState({}, '', finalUrl.href);
        window.scrollTo(0, 0);
        return true;
      }
      if (!appPath(finalUrl)) throw new Error('navigation');
      if (!apply(html, finalUrl.href, push)) throw new Error('content');
      if (push && !options.method) window.scrollTo({ top: 0, behavior: reduced() ? 'auto' : 'smooth' });
      return true;
    } catch (_error) {
      if (options.method) {
        notify('Não foi possível salvar. Atualize a página e tente novamente.');
        return false;
      }
      location.href = url;
      return false;
    } finally {
      hideLoader();
    }
  };

  document.addEventListener('click', (event) => {
    const open = event.target.closest?.('[data-finance-open]');
    if (open) {
      document.getElementById(open.dataset.financeOpen)?.showModal();
      return;
    }
    const tab = event.target.closest?.('[data-tab]');
    if (tab) {
      const box = tab.closest('[data-tabs]');
      box.querySelectorAll('[data-tab]').forEach((item) => item.setAttribute('aria-selected', item === tab ? 'true' : 'false'));
      box.querySelectorAll('[data-tab-panel]').forEach((panel) => { panel.hidden = panel.dataset.tabPanel !== tab.dataset.tab; });
      return;
    }
    const print = event.target.closest?.('[data-finance-print]');
    if (print) {
      window.print();
      return;
    }
    const close = event.target.closest?.('[data-finance-close]');
    if (close) {
      close.closest('dialog').close();
      if (close.dataset.financeOpenAfter) document.getElementById(close.dataset.financeOpenAfter)?.showModal();
      return;
    }
    const link = event.target.closest?.('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || link.target) return;
    const url = new URL(link.href, location.href);
    if (url.hash && url.pathname === location.pathname && url.search === location.search) return;
    if (link.hasAttribute('download')) return;
    if (swappable(url) && currentSwappable()) {
      event.preventDefault();
      go(url.href);
    } else if (url.origin === location.origin) {
      showLoader();
    }
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (form.dataset.financeConfirm && !window.confirm(form.dataset.financeConfirm)) {
      event.preventDefault();
      return;
    }
    if (form.hasAttribute('data-swap-get')) {
      event.preventDefault();
      const url = new URL(form.action);
      new FormData(form).forEach((value, key) => { if (value !== '') url.searchParams.set(key, value); });
      go(url.href);
      return;
    }
    if (!/\/minha-conta\/(financeiro|contatos)\//.test(form.action) && !form.action.endsWith('/minha-conta/contexto')) return;
    event.preventDefault();
    const submit = form.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
    const saved = await go(form.action, { method: 'POST', body: new FormData(form) });
    if (!saved && submit) submit.disabled = false;
  });

  // Cadastro de contato: campos e rótulo acompanham pessoa física ou jurídica.
  const syncContact = (select) => {
    const form = select.closest('form');
    const company = select.value === 'company';
    form.querySelectorAll('[data-contact-company]').forEach((el) => { el.hidden = !company; });
    form.querySelectorAll('[data-contact-individual]').forEach((el) => { el.hidden = company; });
    const label = form.querySelector('[data-contact-name-label]');
    if (label) label.textContent = company ? 'Razão social' : 'Nome completo';
  };
  document.addEventListener('change', (event) => {
    if (event.target.matches?.('[data-contact-type]')) syncContact(event.target);
  });
  const syncAllContacts = () => document.querySelectorAll('[data-contact-type]').forEach(syncContact);
  document.addEventListener('app:swapped', syncAllContacts);
  syncAllContacts();

  window.addEventListener('popstate', () => {
    const url = new URL(location.href);
    if (document.body.dataset.appErrorStatus) location.reload();
    else if (swappable(url)) go(url.href, {}, false);
    else location.reload();
  });
  window.addEventListener('pageshow', hideLoader);

  animate();
})();
