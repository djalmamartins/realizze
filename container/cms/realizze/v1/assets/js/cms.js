/* Navegação responsiva e confirmação de ações sensíveis do CMS. */
(() => {
  'use strict';
  document.addEventListener('submit', (event) => {
    const message = event.target && event.target.dataset ? event.target.dataset.confirm : null;
    if (message && !window.confirm(message)) event.preventDefault();
  });
  // Editor simples sobre o textarea; o servidor continua sanitizando o HTML ao salvar.
  const enhanceEditors = () => {
    if (typeof document.querySelectorAll !== 'function') return;
    document.querySelectorAll('[data-quick-reply]').forEach((select) => {
      select.addEventListener('change', () => {
        const target = document.getElementById(select.dataset.quickReply);
        if (target && select.value) target.value = (target.value ? target.value + '\n\n' : '') + select.value;
        select.value = '';
        if (target) target.focus();
      });
    });
    document.querySelectorAll('textarea[data-editor]').forEach((area) => {
      const wrap = document.createElement('div');
      wrap.className = 'cms-editor';
      const bar = document.createElement('div');
      bar.className = 'cms-editor-bar';
      bar.setAttribute('role', 'toolbar');
      bar.setAttribute('aria-label', 'Formatação do conteúdo');
      const body = document.createElement('div');
      body.className = 'cms-editor-body';
      body.contentEditable = 'true';
      body.setAttribute('role', 'textbox');
      body.setAttribute('aria-multiline', 'true');
      body.setAttribute('aria-label', 'Conteúdo');
      body.innerHTML = area.value;
      const sync = () => { if (!area.hidden) return; area.value = body.innerHTML; };
      const run = (command, value) => { body.focus(); document.execCommand(command, false, value); sync(); };
      const tools = [['Título 2', () => run('formatBlock', 'h2')], ['Título 3', () => run('formatBlock', 'h3')], ['Parágrafo', () => run('formatBlock', 'p')], ['Negrito', () => run('bold')], ['Itálico', () => run('italic')], ['Lista', () => run('insertUnorderedList')], ['Numerada', () => run('insertOrderedList')], ['Citação', () => run('formatBlock', 'blockquote')], ['Link', () => { const url = window.prompt('Endereço do link (https:// ou /)'); if (url) run('createLink', url); }], ['Limpar', () => run('removeFormat')]];
      tools.forEach(([label, action]) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', action);
        bar.appendChild(button);
      });
      const library = area.form.querySelector('select[name="image_path"], select[name="cover"]');
      if (library && library.options.length > 1) {
        const picker = library.cloneNode(true);
        picker.removeAttribute('name');
        picker.removeAttribute('id');
        picker.setAttribute('aria-label', 'Inserir imagem da biblioteca');
        picker.options[0].textContent = 'Inserir imagem';
        picker.value = '';
        picker.addEventListener('change', () => { if (picker.value) run('insertImage', picker.value); picker.value = ''; });
        bar.appendChild(picker);
      }
      const source = document.createElement('button');
      source.type = 'button';
      source.textContent = 'HTML';
      source.setAttribute('aria-pressed', 'false');
      source.addEventListener('click', () => {
        const showSource = !area.hidden === false;
        if (showSource) { area.value = body.innerHTML; } else { body.innerHTML = area.value; }
        area.hidden = !showSource;
        body.hidden = showSource;
        source.setAttribute('aria-pressed', String(showSource));
      });
      bar.appendChild(source);
      area.parentNode.insertBefore(wrap, area);
      wrap.append(bar, body, area);
      area.hidden = true;
      area.removeAttribute('required');
      body.addEventListener('input', sync);
      area.form.addEventListener('submit', sync);
    });
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceEditors, { once: true });
  } else {
    enhanceEditors();
  }
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
      desktop = window.matchMedia('(min-width: 851px)');
    } catch (_error) {
      return;
    }
    if (!desktop || typeof desktop.matches !== 'boolean' ||
        (typeof desktop.addEventListener !== 'function' && typeof desktop.addListener !== 'function')) return;
    sidebar.classList.add('cms-js');
    const close = (restoreFocus = false) => {
      sidebar.classList.remove('cms-menu-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Abrir navegação');
      // Closing a mobile disclosure must not strand keyboard focus in a
      // navigation link that becomes display:none. Preserve focus if it has
      // already moved into the page or if the navigation stays visible.
      if (!desktop.matches && (restoreFocus || nav.contains(document.activeElement))) toggle.focus();
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
