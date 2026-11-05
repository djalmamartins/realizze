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

const faviconFor = (context, doc = document) => {
  const linked = doc.querySelector('link[rel="icon"]');
  if (linked) return linked.getAttribute('href');
  return context === 'pj'
    ? '/assets/realizze/v1/images/favicon-realizze-empresa.svg'
    : '/assets/realizze/v1/images/favicon-realizze.svg';
};
const loaderLogo = document.createElement('img');
loaderLogo.alt = '';
loaderLogo.width = 44;
loaderLogo.height = 44;
const syncAppFavicon = (nextDocument = document) => {
  const nextContext = nextDocument.body?.dataset.context || document.body.dataset.context || 'pf';
  const nextIcon = nextDocument.querySelector('link[rel="icon"]');
  const desired = nextIcon?.getAttribute('href') || faviconFor(nextContext, nextDocument);
  const favicon = document.querySelector('link[rel="icon"]');
  if (favicon && favicon.getAttribute('href') !== desired) favicon.setAttribute('href', desired);
  loaderLogo.src = nextContext === 'pj'
    ? (nextDocument.body?.dataset.logoPj || document.body.dataset.logoPj)
    : (nextDocument.body?.dataset.logoPf || document.body.dataset.logoPf);
};
const loader = document.createElement('div');
  loader.className = 'app-loader';
  loader.setAttribute('aria-hidden', 'true');
  loader.innerHTML = '<span class="app-loader-bar"></span><span class="app-loader-mark"></span>';
loader.querySelector('.app-loader-mark').append(loaderLogo);
  document.body.append(loader);
syncAppFavicon();
  // Navegação rápida não mostra nada; a marca só aparece se demorar; o limite cobre
  // links que baixam arquivo ou respostas que nunca chegam.
  const loaderTimers = [];
  const clearLoaderTimers = () => loaderTimers.splice(0).forEach((timer) => window.clearTimeout(timer));
  const hideLoader = () => {
    clearLoaderTimers();
    loader.classList.remove('is-active', 'is-slow');
  };
  const showLoader = () => {
    clearLoaderTimers();
    loaderTimers.push(
      window.setTimeout(() => loader.classList.add('is-active'), 120),
      window.setTimeout(() => loader.classList.add('is-slow'), 420),
      window.setTimeout(hideLoader, 12000),
    );
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
    if (brand && incomingBrand) {
      brand.setAttribute('href', incomingBrand.getAttribute('href') || '/p');
      const brandImage = brand.querySelector('img');
      const incomingBrandImage = incomingBrand.querySelector('img');
      if (brandImage && incomingBrandImage) brandImage.setAttribute('src', incomingBrandImage.getAttribute('src') || '');
    }
    const profile = document.querySelector('#app-sidebar .profile-link');
    const incomingProfile = next.querySelector('#app-sidebar .profile-link');
    if (profile && incomingProfile) profile.setAttribute('href', incomingProfile.getAttribute('href') || '/p/perfil');
    document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
    document.body.dataset.context = next.body.dataset.context || 'pf';
    document.body.dataset.logoPf = next.body.dataset.logoPf || document.body.dataset.logoPf || '';
    document.body.dataset.logoPj = next.body.dataset.logoPj || document.body.dataset.logoPj || '';
    syncAppFavicon(next);
    document.body.dataset.organizationId = next.body.dataset.organizationId || '0';
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

  let lastNavigation = 0;
  const go = async (url, options = {}, push = true) => {
    const navigation = ++lastNavigation;
    showLoader();
    try {
      const response = await fetch(url, { credentials: 'same-origin', ...options });
      const finalUrl = new URL(response.url);
      const html = await response.text();
      // Outro clique veio depois deste: a resposta antiga não pode trocar a tela.
      if (navigation !== lastNavigation) return false;
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
      if (navigation === lastNavigation) hideLoader();
    }
  };

  // Novo lançamento: categoria pesquisável, painéis de opções, anexos e tags.
  const normalize = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

  const setupEntryCategory = (form) => {
    const select = form.querySelector('[data-entry-category]');
    if (!select) return;
    const box = select.closest('.entry-category');
    const label = box.querySelector('label');
    const listId = `${form.closest('dialog').id}-categories`;
    const input = document.createElement('input');
    input.type = 'text';
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', listId);
    input.value = select.selectedOptions[0]?.textContent || '';
    const list = document.createElement('ul');
    list.id = listId;
    list.className = 'entry-options';
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    select.hidden = true;
    select.tabIndex = -1;
    label.append(input);
    box.append(select, list);
    let active = -1;
    const items = () => [...list.querySelectorAll('[role="option"]')];
    const mark = (index) => {
      const options = items();
      active = options.length ? (index + options.length) % options.length : -1;
      options.forEach((option, i) => option.setAttribute('aria-selected', i === active ? 'true' : 'false'));
      if (active >= 0) {
        input.setAttribute('aria-activedescendant', options[active].id);
        options[active].scrollIntoView({ block: 'nearest' });
      } else input.removeAttribute('aria-activedescendant');
    };
    const show = (filter) => {
      const query = normalize(filter);
      list.replaceChildren();
      [...select.options].filter((option) => (!option.dataset.kind || option.dataset.kind === form.elements.kind.value) && normalize(option.textContent).includes(query)).forEach((option) => {
        const item = document.createElement('li');
        item.id = `${listId}-${option.value || 'none'}`;
        item.setAttribute('role', 'option');
        item.dataset.value = option.value;
        item.textContent = option.textContent;
        if (option.dataset.color) {
          const dot = document.createElement('span');
          dot.className = 'finance-dot';
          dot.style.background = option.dataset.color;
          item.prepend(dot);
        }
        list.append(item);
      });
      if (!list.children.length) {
        const empty = document.createElement('li');
        empty.className = 'entry-options-empty';
        empty.textContent = 'Nenhuma categoria encontrada';
        list.append(empty);
      }
      list.hidden = false;
      place();
      input.setAttribute('aria-expanded', 'true');
      mark(items().length ? 0 : -1);
    };
    // A lista vai para a camada superior, acima da própria modal: a borda da modal não a corta
    // nem cria rolagem. Abre para cima quando falta espaço embaixo.
    const place = () => {
      if (list.hidden || typeof list.showPopover !== 'function') return;
      if (!list.matches(':popover-open')) list.showPopover();
      const field = input.getBoundingClientRect();
      Object.assign(list.style, { position: 'fixed', margin: '0', inset: 'auto', width: `${field.width}px`, left: `${field.left}px`, maxHeight: '' });
      const room = window.innerHeight - field.bottom - 12;
      const above = field.top - 12;
      const height = Math.min(list.scrollHeight, 260);
      const up = room < height && above > room;
      list.style.maxHeight = `${Math.max(120, Math.min(260, up ? above : room))}px`;
      list.style.top = `${up ? Math.max(8, field.top - 4 - list.getBoundingClientRect().height) : field.bottom + 4}px`;
    };
    list.popover = 'manual';
    form.closest('dialog').addEventListener('scroll', place, true);
    window.addEventListener('resize', place);
    const hide = () => {
      if (list.matches?.(':popover-open')) list.hidePopover();
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      active = -1;
    };
    const choose = (item) => {
      select.value = item.dataset.value;
      input.value = select.selectedOptions[0]?.textContent || '';
      hide();
    };
    select.addEventListener('entry:refresh', () => { input.value = select.selectedOptions[0]?.textContent || ''; });
    input.addEventListener('focus', () => { input.select(); show(''); });
    input.addEventListener('input', () => show(input.value));
    input.addEventListener('blur', () => { input.value = select.selectedOptions[0]?.textContent || ''; hide(); });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (list.hidden) show('');
        else mark(active + (event.key === 'ArrowDown' ? 1 : -1));
      } else if (event.key === 'Enter') {
        event.preventDefault();
        if (!list.hidden && items()[active]) choose(items()[active]);
      } else if (event.key === 'Escape' && !list.hidden) {
        event.preventDefault();
        event.stopPropagation();
        hide();
      }
    });
    list.addEventListener('mousedown', (event) => {
      event.preventDefault();
      const item = event.target.closest('[role="option"]');
      if (item) choose(item);
    });
  };

  const setupEntryTags = (form) => {
    const input = form.querySelector('[data-entry-tag-input]');
    if (!input) return;
    const field = form.querySelector('[data-entry-tags]');
    const list = form.querySelector('[data-entry-tag-list]');
    let tags = [];
    const render = () => {
      field.value = tags.join(',');
      list.replaceChildren(...tags.map((tag) => {
        const item = document.createElement('li');
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = '×';
        remove.setAttribute('aria-label', `Remover ${tag}`);
        remove.addEventListener('click', () => { tags = tags.filter((other) => other !== tag); render(); });
        item.append(tag, remove);
        return item;
      }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const add = () => {
      const tag = input.value.replace(/,/g, ' ').replace(/\s+/g, ' ').trim();
      input.value = '';
      if (tag && tags.length < 10 && !tags.some((other) => normalize(other) === normalize(tag))) {
        tags.push(tag);
        render();
      }
    };
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        add();
      } else if (event.key === 'Backspace' && input.value === '' && tags.length) {
        tags.pop();
        render();
      }
    });
    input.addEventListener('blur', add);
    form.addEventListener('entry:reset', (event) => { tags = [...(event.detail.tags || [])]; input.value = ''; render(); });
  };

  const setupEntryFiles = (form, showError) => {
    const input = form.querySelector('[data-entry-files]');
    if (!input) return;
    const list = form.querySelector('[data-entry-file-list]');
    let files = [];
    const render = () => {
      const transfer = new DataTransfer();
      files.forEach((file) => transfer.items.add(file));
      input.files = transfer.files;
      list.replaceChildren(...files.map((file) => {
        const item = document.createElement('li');
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = '×';
        remove.setAttribute('aria-label', `Remover ${file.name}`);
        remove.addEventListener('click', () => { files = files.filter((other) => other !== file); render(); form.dispatchEvent(new Event('entry:changed')); });
        item.append(file.name, remove);
        return item;
      }));
    };
    input.addEventListener('change', () => {
      const added = [...input.files];
      const large = added.some((file) => file.size > 10485760);
      files = [...files, ...added.filter((file) => file.size <= 10485760)];
      const excess = files.length > 5;
      files = files.slice(0, 5);
      showError(large ? 'Cada anexo pode ter até 10 MB.' : excess ? 'Envie no máximo 5 anexos por lançamento.' : '');
      render();
    });
    // Anexos já gravados: remover só marca; vale ao salvar e pode ser desfeito antes disso.
    const existing = form.querySelector('[data-entry-existing]');
    form.addEventListener('entry:reset', (event) => {
      files = [];
      render();
      existing.replaceChildren(...(event.detail.attachments || []).map((attachment) => {
        const item = document.createElement('li');
        const name = document.createElement('span');
        name.textContent = attachment.name;
        const view = document.createElement('a');
        view.href = `/minha-conta/financeiro/anexo?id=${attachment.id}&ver=1`;
        view.target = '_blank';
        view.rel = 'noopener';
        view.textContent = 'Ver';
        const save = document.createElement('a');
        save.href = `/minha-conta/financeiro/anexo?id=${attachment.id}`;
        save.setAttribute('download', '');
        save.textContent = 'Baixar';
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = 'Remover';
        remove.addEventListener('click', () => {
          const marked = item.querySelector('input');
          if (marked) marked.remove();
          else {
            const field = document.createElement('input');
            field.type = 'hidden';
            field.name = 'remove_attachments[]';
            field.value = attachment.id;
            item.append(field);
          }
          item.classList.toggle('is-removed', !marked);
          remove.textContent = marked ? 'Remover' : 'Desfazer';
        });
        item.append(name, view, save, remove);
        return item;
      }));
    });
  };

  const entryMode = (form) => form.elements.repeat_mode.value;

  const setupEntryRepeat = (form) => {
    const hint = form.querySelector('[data-entry-repeat-hint]');
    const firstBox = form.querySelector('[data-entry-first-due]');
    const first = form.querySelector('[data-entry-first-input]');
    const sync = () => {
      const mode = entryMode(form);
      form.querySelector('[data-entry-term]').hidden = mode !== 'term';
      form.querySelector('[data-entry-subscription]').hidden = mode !== 'subscription';
      firstBox.hidden = mode === '';
      hint.hidden = mode === '';
      first.value = form.elements.due_date.value;
      const editing = form.elements.source.value === 'recurring';
      form.querySelector('[data-entry-first-label]').textContent = editing ? 'Próxima cobrança' : 'Primeiro vencimento';
      form.elements.notes.maxLength = mode === 'subscription' ? 500 : 5000;
      if (mode === 'subscription') form.elements.description.setAttribute('list', 'entry-brand-suggestions');
      else form.elements.description.removeAttribute('list');
      if (mode === '') return;
      if (mode === 'subscription') {
        hint.textContent = editing ? 'A alteração vale para as próximas cobranças. Os lançamentos já gerados não mudam.' : 'Repete até você cancelar. Os lançamentos são gerados a cada vencimento, sem criar registros futuros. Tags e anexos ficam no primeiro lançamento.';
        return;
      }
      const plan = entryPlan(form);
      let text = `Cria ${plan.times} lançamentos com ${plan.cents ? entryMoney(plan.cents) : 'o valor informado'} em cada um. A primeira parcela já está incluída.`;
      if (plan.type === 'installment') {
        const each = plan.times ? Math.floor(plan.cents / plan.times) : 0;
        const rest = plan.times ? plan.cents % plan.times : 0;
        text = plan.cents && plan.times ? `O valor é o total: ${rest ? `1x de ${entryMoney(each + rest)} e ${plan.times - 1}x de ${entryMoney(each)}` : `${plan.times}x de ${entryMoney(each)}`}.` : 'O valor informado é o total, dividido entre as parcelas.';
      }
      hint.textContent = form.elements.paid.checked ? `${text} Só o primeiro fica baixado.` : text;
    };
    first.addEventListener('input', () => { form.elements.due_date.value = first.value; });
    form.addEventListener('input', sync);
    form.addEventListener('change', sync);
    sync();
  };

  const setupEntryTools = (form) => {
    const buttons = [...form.querySelectorAll('[data-entry-tool]')];
    const filled = (panel) => !!panel && ([...panel.querySelectorAll('textarea, [data-entry-tags], [data-entry-files], input[name="repeat_mode"]:checked')]
      .some((field) => (field.type === 'file' ? field.files.length > 0 : field.value !== '')) || !!panel.querySelector('[data-entry-existing] li'));
    const refresh = () => buttons.forEach((button) => button.classList.toggle('has-value', filled(document.getElementById(button.dataset.entryTool))));
    buttons.forEach((button) => button.addEventListener('click', () => {
      const panel = document.getElementById(button.dataset.entryTool);
      if (!panel) return;
      panel.hidden = !panel.hidden;
      button.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
      if (!panel.hidden) panel.querySelector('input:not([type="hidden"]), textarea')?.focus();
    }));
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    form.addEventListener('entry:changed', refresh);
  };

  // Mesmas contas do servidor: parcelas com a sobra na primeira e dia ajustado nos meses curtos.
  const entryPlan = (form) => {
    const cents = /^[\d.]+(,\d{1,2})?$/.test(form.elements.amount.value)
      ? Math.round(Number(form.elements.amount.value.replace(/\./g, '').replace(',', '.')) * 100) : 0;
    const type = entryMode(form) === 'term' ? form.elements.repeat_value.value : '';
    const times = type === '' ? 1 : Math.min(Math.max(Number(form.elements.repeat_count.value) || 0, 0), 120);
    const steps = { weekly: [0, 7], biweekly: [0, 15], monthly: [1, 0], bimonthly: [2, 0], quarterly: [3, 0], semiannual: [6, 0], yearly: [12, 0] };
    const [months, days] = steps[form.elements.repeat_every?.value] || [1, 0];
    const start = form.elements.due_date.value ? new Date(`${form.elements.due_date.value}T12:00:00`) : null;
    const rows = [];
    for (let i = 0; start && i < times; i += 1) {
      let date = new Date(start);
      if (months) {
        date = new Date(start.getFullYear(), start.getMonth() + months * i, 1, 12);
        date.setDate(Math.min(start.getDate(), new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate()));
      } else date.setDate(start.getDate() + days * i);
      const value = type === 'installment' ? Math.floor(cents / times) + (i === 0 ? cents % times : 0) : cents;
      rows.push({ date: date.toLocaleDateString('pt-BR'), value });
    }
    return { type, times, cents, rows, total: type === 'fixed' ? cents * times : cents };
  };
  const entryMoney = (value) => (value / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

  const setupEntryTotal = (form) => {
    const total = form.querySelector('[data-entry-total]');
    const table = form.querySelector('[data-entry-parcels]');
    const sync = () => {
      const plan = entryPlan(form);
      const period = { weekly: 'por semana', monthly: 'por mês', quarterly: 'por trimestre', yearly: 'por ano' }[form.elements.frequency.value];
      if (total) total.textContent = entryMode(form) === 'subscription' ? `${entryMoney(plan.cents)} ${period}` : plan.times > 1 ? `${entryMoney(plan.total)} em ${plan.times}x` : entryMoney(plan.total);
      if (!table) return;
      table.hidden = plan.type === '' || !plan.cents || plan.rows.length < 2;
      const body = table.tBodies[0];
      body.replaceChildren();
      plan.rows.slice(0, 6).forEach((row, index) => {
        const line = body.insertRow();
        line.insertCell().textContent = `${index + 1}/${plan.times}`;
        line.insertCell().textContent = row.date;
        const value = line.insertCell();
        value.className = 'num';
        value.textContent = entryMoney(row.value);
      });
      if (plan.rows.length > 6) {
        const more = body.insertRow().insertCell();
        more.colSpan = 3;
        more.textContent = `e mais ${plan.rows.length - 6}`;
      }
    };
    form.addEventListener('input', sync);
    form.addEventListener('change', sync);
    sync();
  };

  const setupEntryForm = (dialog) => {
    const form = dialog?.querySelector('[data-entry-form]');
    if (!form || form.dataset.entryReady) return;
    form.dataset.entryReady = '1';
    const error = form.querySelector('[data-entry-error]');
    const showError = (message) => { error.textContent = message; error.hidden = message === ''; };
    setupEntryCategory(form);
    setupEntryTags(form);
    setupEntryFiles(form, showError);
    setupEntryRepeat(form);
    setupEntryTools(form);
    setupEntryTotal(form);
  };

  // A modal é uma só: cada abertura define o tipo, o modo e o que aparece.
  const openEntry = (preset) => {
    const dialog = document.getElementById('entry-dialog');
    const form = dialog?.querySelector('[data-entry-form]');
    if (!form) return;
    setupEntryForm(dialog);
    const data = preset.edit || {};
    const editing = !!preset.edit;
    const recurring = data.source === 'recurring';
    const kind = data.kind || preset.kind;
    const income = kind === 'income';
    form.reset();
    form.action = `/minha-conta/financeiro/${editing ? 'lancamento-editar' : 'lancamento'}`;
    form.elements.kind.value = kind;
    form.elements.id.value = data.id || '';
    form.elements.source.value = data.source || 'entry';
    const noun = recurring ? (data.item_type === 'recurring' ? 'recorrência' : 'assinatura') : (income ? 'receita' : 'despesa');
    form.querySelector('[data-entry-title]').textContent = editing ? `Editar ${noun}` : (income ? 'Nova receita' : 'Nova despesa');
    form.querySelector('[data-entry-paid-label]').textContent = income ? 'Já foi recebido' : 'Já foi pago';
    form.querySelector('[data-entry-paid]').hidden = editing;
    form.querySelector('[data-entry-submit]').textContent = editing ? 'Salvar alterações' : 'Salvar';
    form.querySelector('[data-entry-income]').hidden = !income || recurring;
    form.querySelector('[data-entry-income]').open = false;
    const supplier = form.querySelector('[data-entry-expense]');
    if (supplier) supplier.hidden = income || recurring;
    ['description', 'amount', 'due_date', 'account_id', 'notes', 'provider', 'frequency', 'item_type', 'competence', 'counterparty_name', 'document_number', 'payment_method'].forEach((name) => {
      if (data[name] !== undefined && data[name] !== '') form.elements[name].value = data[name];
    });
    form.elements.item_type.value = data.item_type || (income ? 'recurring' : 'subscription');
    form.elements.category_id.value = data.category_id || '';
    form.elements.contact_id.value = data.contact_id || '';
    if (form.elements.supplier_id) form.elements.supplier_id.value = !income && data.contact_id ? data.contact_id : '';
    if (form.elements.cost_center_id) form.elements.cost_center_id.value = data.cost_center_id || '';
    form.elements.category_id.dispatchEvent(new Event('entry:refresh'));
    if (recurring && !form.elements.auto_create.disabled) form.elements.auto_create.checked = data.auto_create;
    form.dispatchEvent(new CustomEvent('entry:reset', { detail: { tags: data.tags, attachments: data.attachments } }));
    const mode = recurring || preset.repeat === 'subscription' ? 'subscription' : '';
    form.querySelectorAll('input[name="repeat_mode"]').forEach((radio) => {
      radio.checked = radio.value === mode;
      radio.disabled = radio.hasAttribute('data-unavailable') || (recurring && radio.value !== 'subscription');
    });
    const note = form.querySelector('[data-entry-edit-note]');
    note.hidden = !data.series;
    note.textContent = data.series ? `Parcela ${data.series}: a alteração vale só para esta ocorrência.` : '';
    const open = { repeat: mode !== '', notes: !!data.notes, files: !!data.attachments?.length, tags: !!data.tags?.length };
    const shown = { repeat: !editing || recurring, notes: true, files: !recurring, tags: !recurring };
    form.querySelectorAll('[data-entry-tool]').forEach((button) => {
      const name = button.dataset.entryTool.replace('entry-dialog-', '');
      const panel = document.getElementById(button.dataset.entryTool);
      button.hidden = !shown[name];
      if (panel) panel.hidden = !shown[name] || !open[name];
      button.setAttribute('aria-expanded', panel && !panel.hidden ? 'true' : 'false');
    });
    form.querySelector('[data-entry-error]').hidden = true;
    form.dispatchEvent(new Event('change'));
    dialog.showModal();
  };

  document.addEventListener('click', (event) => {
    const preset = event.target.closest?.('[data-filter-preset]');
    if (preset) {
      const form = preset.closest('form');
      form.elements.inicio.value = preset.dataset.start;
      form.elements.fim.value = preset.dataset.end;
      form.querySelectorAll('[data-filter-preset]').forEach((item) => item.setAttribute('aria-pressed', item === preset ? 'true' : 'false'));
      return;
    }
    const entry = event.target.closest?.('[data-entry-open], [data-entry-edit]');
    if (entry) {
      entry.closest('dialog')?.close();
      openEntry(entry.dataset.entryEdit ? { edit: JSON.parse(entry.dataset.entryEdit) } : { kind: entry.dataset.entryOpen, repeat: entry.dataset.entryRepeat });
      return;
    }
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
      const pageTitle = document.title;
      document.title = print.closest('[data-print-title]')?.dataset.printTitle || pageTitle;
      window.print();
      document.title = pageTitle;
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

  // Todo envio leva a empresa da tela: o servidor recusa se outra aba trocou a empresa da sessão.
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if ((form.getAttribute('method') || '').toLowerCase() !== 'post' || /\/(minha-conta\/contexto|sair|entrar)$/.test(form.action)) return;
    let field = form.querySelector('input[name="context_organization_id"]');
    if (!field) {
      field = document.createElement('input');
      field.type = 'hidden';
      field.name = 'context_organization_id';
      form.append(field);
    }
    field.value = document.body.dataset.organizationId || '0';
  }, true);

  // Foto da meta: mostra a imagem escolhida antes de salvar e avisa logo se passar do limite.
  document.addEventListener('change', (event) => {
    const companyLogo = event.target.closest?.('[data-company-logo-input]');
    if (companyLogo) {
      const file = companyLogo.files?.[0];
      const preview = document.querySelector('[data-company-logo-preview]');
      if (file && preview) {
        const url = URL.createObjectURL(file);
        const image = Object.assign(document.createElement('img'), { src: url, alt: '' });
        image.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
        preview.replaceChildren(image);
      }
      return;
    }
    const input = event.target.closest?.('[data-goal-photo-input]');
    if (!input) return;
    const box = input.closest('[data-goal-photo]');
    const file = input.files?.[0];
    const error = box.querySelector('[data-goal-photo-error]');
    const tooBig = Boolean(file && file.size > 5242880);
    error.hidden = !tooBig;
    if (tooBig) input.value = '';
    const preview = box.querySelector('[data-goal-photo-preview]');
    if (file && !tooBig) {
      preview.style.backgroundImage = `url("${URL.createObjectURL(file)}")`;
      preview.classList.add('has-photo');
    } else if (tooBig) {
      preview.style.backgroundImage = '';
      preview.classList.remove('has-photo');
    }
  });

  // Listagem de metas: ordenação aplica ao escolher; o menu de ações fecha ao clicar fora.
  document.addEventListener('change', (event) => {
    if (event.target.matches?.('[data-goal-sort]')) event.target.form.requestSubmit();
  });
  const rowMenus = 'details.goal-menu[open],details.recurring-menu[open]';
  document.addEventListener('click', (event) => {
    document.querySelectorAll(rowMenus).forEach((menu) => {
      if (!menu.contains(event.target) || event.target.closest('button')) menu.open = false;
    });
  });
  // O menu de ações vai para a camada superior do navegador: nenhum recorte de tabela ou cartão o cobre.
  // Fica ao lado do botão e abre para cima se faltar espaço.
  const placeRowMenu = (menu) => {
    const panel = menu.querySelector(':scope > div');
    panel.popover = 'manual';
    panel.showPopover();
    panel.style.position = 'fixed';
    panel.style.margin = '0';
    panel.style.inset = 'auto';
    const button = menu.querySelector('summary').getBoundingClientRect();
    const { width, height } = panel.getBoundingClientRect();
    const below = button.bottom + 4;
    const fitsBelow = below + height <= window.innerHeight - 8;
    panel.style.top = `${Math.max(8, fitsBelow ? below : button.top - height - 4)}px`;
    panel.style.left = `${Math.max(8, Math.min(button.right - width, window.innerWidth - width - 8))}px`;
  };
  document.addEventListener('toggle', (event) => {
    if (!event.target.matches?.('details.goal-menu,details.recurring-menu')) return;
    if (event.target.open) {
      placeRowMenu(event.target);
    } else {
      const panel = event.target.querySelector(':scope > div');
      if (panel.matches(':popover-open')) panel.hidePopover();
    }
  }, true);
  const closeRowMenus = () => document.querySelectorAll(rowMenus).forEach((menu) => { menu.open = false; });
  window.addEventListener('resize', closeRowMenus);
  document.addEventListener('scroll', (event) => {
    if (!event.target.closest?.('details.goal-menu,details.recurring-menu')) closeRowMenus();
  }, true);

  // Criação de meta em passos: resumo ao vivo e análise com o histórico real recebido do servidor.
  const goalMoney = (cents) => (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const goalCents = (value) => {
    const text = String(value || '').trim();
    if (!/^[0-9.]+(,[0-9]{1,2})?$/.test(text)) return 0;
    return Math.round(Number(text.replace(/\./g, '').replace(',', '.')) * 100);
  };
  const goalMonthLabel = (date) => date.toLocaleDateString('pt-BR', { month: 'short', year: 'numeric', timeZone: 'UTC' }).replace(' de ', '/').replace('.', '');
  const goalDuration = (months) => {
    const years = Math.floor(months / 12);
    const rest = months % 12;
    return [years ? `${years} ano${years > 1 ? 's' : ''}` : '', rest ? `${rest} ${rest > 1 ? 'meses' : 'mês'}` : ''].filter(Boolean).join(' e ') || 'este mês';
  };
  const goalEl = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };
  const goalState = (dialog) => {
    const form = dialog.querySelector('form');
    const baseline = JSON.parse(dialog.dataset.goalBaseline);
    const value = (name) => form.elements[name]?.value ?? '';
    const today = new Date(`${dialog.dataset.goalToday}T00:00:00Z`);
    const target = goalCents(value('amount'));
    const initial = goalCents(value('initial'));
    const deadline = value('deadline') ? new Date(`${value('deadline')}T00:00:00Z`) : null;
    const monthsLeft = deadline ? (deadline.getUTCFullYear() - today.getUTCFullYear()) * 12 + deadline.getUTCMonth() - today.getUTCMonth() + 1 : null;
    const free = baseline.income_cents - baseline.expense_cents - baseline.committed_cents;
    return { form, baseline, value, today, target, initial, deadline, monthsLeft, free, hasHistory: baseline.months > 0, remaining: Math.max(0, target - initial), monthly: goalCents(value('monthly')) };
  };
  const goalFinish = (state, monthly) => {
    if (monthly <= 0 || state.remaining <= 0) return null;
    const months = Math.ceil(state.remaining / monthly);
    const end = new Date(Date.UTC(state.today.getUTCFullYear(), state.today.getUTCMonth() + months - 1, 1));
    return { months, label: goalMonthLabel(end) };
  };
  const goalRenderCapacity = (dialog, state) => {
    const box = dialog.querySelector('[data-goal-capacity]');
    box.replaceChildren();
    if (!state.hasHistory) {
      box.append(goalEl('strong', '', 'Ainda sem histórico para estimar quanto sobra'), goalEl('p', 'muted', 'Quando houver receitas e despesas pagas, o Realizze mostra a sua média aqui. Por enquanto, informe o valor que você pretende guardar.'));
      return;
    }
    box.append(goalEl('span', 'muted', `Sobra média por mês (${state.baseline.months} ${state.baseline.months > 1 ? 'meses' : 'mês'} de lançamentos pagos)`), goalEl('strong', state.free < 0 ? 'red' : 'green', goalMoney(state.free)));
    const parts = [`Receitas ${goalMoney(state.baseline.income_cents)}`, `despesas ${goalMoney(state.baseline.expense_cents)}`];
    if (state.baseline.committed_cents > 0) parts.push(`outras metas ${goalMoney(state.baseline.committed_cents)}`);
    box.append(goalEl('p', 'muted', parts.join(' · ')));
  };
  const goalRenderAnalysis = (dialog, state) => {
    const box = dialog.querySelector('[data-goal-analysis]');
    box.replaceChildren();
    if (state.target > 0 && state.remaining === 0) {
      box.append(goalEl('p', 'goal-note', 'O valor que você já tem guardado cobre a meta.'));
      return;
    }
    const needed = state.monthsLeft ? Math.ceil(state.remaining / state.monthsLeft) : null;
    const recommended = needed ?? (state.free > 0 ? Math.ceil(state.free / 2) : 0);
    const accelerated = state.free > 0 ? state.free : 0;
    const scenarios = [
      ['Atual', state.monthly, 'A contribuição que você informou.'],
      ['Recomendado', recommended, needed ? 'O necessário para chegar no prazo.' : 'Metade do que sobra por mês.'],
      ['Acelerado', accelerated, 'Tudo o que sobra por mês.'],
    ];
    const grid = goalEl('div', 'goal-scenarios');
    scenarios.forEach(([title, monthly, hint]) => {
      const finish = goalFinish(state, monthly);
      const card = goalEl('div', 'goal-scenario');
      card.append(goalEl('span', '', title), goalEl('strong', '', monthly > 0 ? `${goalMoney(monthly)}/mês` : 'Sem dados'), goalEl('small', '', finish ? `${goalDuration(finish.months)} · ${finish.label}` : 'Sem estimativa de prazo'), goalEl('p', 'muted', hint));
      grid.append(card);
    });
    box.append(grid);
    if (state.hasHistory && needed !== null && needed > state.free) {
      box.append(goalEl('p', 'goal-note is-warning', `Para chegar no prazo são ${goalMoney(needed)}/mês, mais do que a sua sobra média de ${goalMoney(state.free)}. Vale ajustar o prazo, o valor ou as despesas.`));
    }
    if (state.hasHistory && state.baseline.income_cents > 0 && state.baseline.categories.length) {
      const impact = goalEl('div', 'goal-impact');
      impact.append(goalEl('h4', '', 'Peso das despesas no objetivo'));
      const list = goalEl('ul');
      state.baseline.categories.forEach((item) => {
        const row = goalEl('li');
        row.append(goalEl('span', '', item.name), goalEl('strong', '', `${goalMoney(item.cents)}/mês · ${Math.round(item.cents / state.baseline.income_cents * 100)}% da receita`));
        list.append(row);
      });
      impact.append(list);
      const top = state.baseline.categories[0];
      const base = state.monthly > 0 ? state.monthly : accelerated;
      const before = goalFinish(state, base);
      const after = goalFinish(state, base + Math.round(top.cents * 0.1));
      if (before && after && before.months > after.months) {
        impact.append(goalEl('p', 'muted', `Simulação: guardando também 10% do que vai para ${top.name}, a meta chega ${goalDuration(before.months - after.months)} antes.`));
      }
      box.append(impact);
    } else if (!state.hasHistory) {
      box.append(goalEl('p', 'goal-note', 'Ainda não há lançamentos pagos nos últimos três meses. Os cenários com a sua sobra e o peso das despesas aparecem quando houver histórico.'));
    }
    box.append(goalEl('p', 'muted', 'Estimativa sem juros nem rendimento, feita com a média dos seus lançamentos pagos nos últimos três meses completos.'));
  };
  const goalRender = (dialog) => {
    const state = goalState(dialog);
    const category = state.form.querySelector('input[name="category"]:checked');
    const categoryLabel = category?.closest('label').textContent.trim() || '';
    const set = (name, text) => { dialog.querySelector(`[data-goal-summary-${name}]`).textContent = text; };
    set('name', state.value('name').trim() || 'Sua meta');
    set('purpose', state.value('purpose').trim());
    set('target', goalMoney(state.target));
    set('deadline', state.deadline ? goalMonthLabel(state.deadline) : 'A definir');
    set('monthly', goalMoney(state.monthly));
    const chip = dialog.querySelector('[data-goal-summary-category]');
    chip.textContent = categoryLabel;
    chip.hidden = !categoryLabel || category.value === 'outra';
    const picked = state.form.querySelector('input[name="icon"]:checked');
    const svg = picked && dialog.querySelector(`[data-goal-icon-svg="${picked.value}"] svg`);
    if (svg) {
      const copy = svg.cloneNode(true);
      copy.setAttribute('width', '34');
      copy.setAttribute('height', '34');
      dialog.querySelector('[data-goal-summary-icon]').replaceChildren(copy);
    }
    const preview = dialog.querySelector('.has-photo[data-goal-photo-preview]');
    const image = dialog.querySelector('[data-goal-summary-image]');
    image.style.backgroundImage = preview ? preview.style.backgroundImage : '';
    image.classList.toggle('has-photo', Boolean(preview));
    dialog.querySelectorAll('[data-goal-count]').forEach((field) => {
      field.parentElement.querySelector('[data-goal-counter]').textContent = `${field.value.length}/${field.maxLength}`;
    });
    goalRenderCapacity(dialog, state);
    goalRenderAnalysis(dialog, state);
    const review = dialog.querySelector('[data-goal-review]');
    const bank = state.form.elements.institution_id?.selectedOptions[0];
    const place = [state.value('savings_place').trim(), state.form.elements.institution_id?.value ? bank.textContent : state.value('institution').trim()].filter(Boolean).join(' · ');
    const finish = goalFinish(state, state.monthly);
    review.replaceChildren();
    [['Meta', state.value('name').trim()], ['Categoria', categoryLabel], ['Descrição', state.value('purpose').trim()], ['Valor da meta', goalMoney(state.target)], ['Conta de referência', state.form.elements.account_id?.value ? state.form.elements.account_id.selectedOptions[0].textContent.split(' · ')[0] : ''], [state.form.elements.account_id?.value ? 'Reservar agora' : 'Já guardado', state.initial ? goalMoney(state.initial) : ''], ['Prazo', state.deadline ? state.deadline.toLocaleDateString('pt-BR', { timeZone: 'UTC' }) : 'A definir'], ['Contribuição mensal', state.monthly ? goalMoney(state.monthly) : ''], ['Previsão com essa contribuição', finish ? `${goalDuration(finish.months)} · ${finish.label}` : ''], ['Guardando em', place]].forEach(([label, text]) => {
      if (!text) return;
      const row = goalEl('div');
      row.append(goalEl('dt', '', label), goalEl('dd', '', text));
      review.append(row);
    });
  };
  const goalCurrent = (dialog) => Number(dialog.querySelector('[data-goal-panel]:not([hidden])').dataset.goalPanel);
  const goalShow = (dialog, step) => {
    dialog.querySelectorAll('[data-goal-panel]').forEach((panel) => { panel.hidden = Number(panel.dataset.goalPanel) !== step; });
    dialog.querySelectorAll('[data-goal-step-item]').forEach((item) => {
      const index = Number(item.dataset.goalStepItem);
      item.classList.toggle('is-current', index === step);
      item.classList.toggle('is-done', index < step);
      if (index === step) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current');
      item.querySelector('button').disabled = index > step;
    });
    dialog.querySelector('[data-goal-back]').hidden = step === 0;
    dialog.querySelector('[data-goal-next]').hidden = step === 4;
    dialog.querySelector('[data-goal-submit]').hidden = step !== 4;
    goalRender(dialog);
    dialog.querySelector('.goal-panels').scrollTop = 0;
  };
  const goalPanelValid = (dialog) => {
    const fields = dialog.querySelectorAll('[data-goal-panel]:not([hidden]) input, [data-goal-panel]:not([hidden]) textarea, [data-goal-panel]:not([hidden]) select');
    return [...fields].every((field) => field.reportValidity());
  };
  const goalNext = (dialog) => {
    if (goalPanelValid(dialog)) goalShow(dialog, Math.min(4, goalCurrent(dialog) + 1));
  };
  document.addEventListener('click', (event) => {
    const dialog = event.target.closest?.('[data-goal-wizard]');
    if (!dialog) return;
    if (event.target.closest('[data-goal-next]')) goalNext(dialog);
    else if (event.target.closest('[data-goal-back]')) goalShow(dialog, Math.max(0, goalCurrent(dialog) - 1));
    else if (event.target.closest('[data-goal-step-go]')) goalShow(dialog, Number(event.target.closest('[data-goal-step-go]').dataset.goalStepGo));
  });
  document.addEventListener('keydown', (event) => {
    const dialog = event.target.closest?.('[data-goal-wizard]');
    if (dialog && event.key === 'Enter' && event.target.matches('input') && goalCurrent(dialog) < 4) {
      event.preventDefault();
      goalNext(dialog);
    }
  });
  document.addEventListener('input', (event) => {
    const dialog = event.target.closest?.('[data-goal-wizard]');
    if (dialog) goalRender(dialog);
  });
  document.addEventListener('change', (event) => {
    const dialog = event.target.closest?.('[data-goal-wizard]');
    if (!dialog) return;
    // A categoria sugere o ícone; a pessoa pode trocar depois na grade.
    if (event.target.dataset.goalIconFor) {
      const icon = dialog.querySelector(`input[name="icon"][value="${event.target.dataset.goalIconFor}"]`);
      if (icon) icon.checked = true;
    }
    goalRender(dialog);
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

  // Painéis do topo: um por vez; fecham ao clicar fora ou com Esc.
  document.addEventListener('toggle', (event) => {
    if (!event.target.matches?.('.tool-menu') || !event.target.open) return;
    document.querySelectorAll('.tool-menu[open]').forEach((menu) => { if (menu !== event.target) menu.open = false; });
  }, true);
  document.addEventListener('click', (event) => {
    document.querySelectorAll('.tool-menu[open]').forEach((menu) => { if (!menu.contains(event.target)) menu.open = false; });
  });
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.tool-menu[open]').forEach((menu) => { menu.open = false; menu.querySelector('summary')?.focus(); });
  });

  window.addEventListener('popstate', () => {
    const url = new URL(location.href);
    if (document.body.dataset.appErrorStatus) location.reload();
    else if (swappable(url)) go(url.href, {}, false);
    else location.reload();
  });
  window.addEventListener('pageshow', hideLoader);

  animate();
})();
