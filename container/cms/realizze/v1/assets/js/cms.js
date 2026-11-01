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
    document.querySelectorAll('[data-counter]').forEach((field) => {
      const output = document.getElementById(field.dataset.counter);
      const update = () => { output.textContent = field.value.length + '/' + field.maxLength + ' caracteres'; };
      field.addEventListener('input', update);
      update();
    });
    const slugify = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    document.querySelectorAll('[data-slug-preview]').forEach((output) => {
      const form = output.closest('form');
      const slug = form.querySelector('[name="' + (output.dataset.slugField || 'uri') + '"]');
      const title = form.querySelector('[name="title"]');
      const update = () => { output.textContent = output.dataset.slugPreview + (slugify(slug.value || title.value) || 'endereco'); };
      slug.addEventListener('input', update);
      title.addEventListener('input', update);
      update();
    });
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
      const format = document.createElement('select');
      format.setAttribute('aria-label', 'Estilo do texto');
      [['p', 'Parágrafo'], ['h2', 'Título 2'], ['h3', 'Título 3'], ['h4', 'Título 4'], ['blockquote', 'Citação']].forEach(([value, label]) => format.add(new Option(label, value)));
      format.addEventListener('change', () => run('formatBlock', format.value));
      bar.appendChild(format);
      const tools = [['↶', 'Desfazer', () => run('undo')], ['↷', 'Refazer', () => run('redo')], ['B', 'Negrito', () => run('bold')], ['I', 'Itálico', () => run('italic')], ['U', 'Sublinhado', () => run('underline')], ['S', 'Tachado', () => run('strikeThrough')], ['• Lista', 'Lista', () => run('insertUnorderedList')], ['1. Lista', 'Lista numerada', () => run('insertOrderedList')], ['⇤', 'Alinhar à esquerda', () => run('justifyLeft')], ['⇔', 'Centralizar', () => run('justifyCenter')], ['⇥', 'Alinhar à direita', () => run('justifyRight')], ['Limpar', 'Limpar formatação', () => run('removeFormat')], ['Link', 'Inserir link', () => { const url = window.prompt('Endereço do link (https:// ou /)'); if (url) run('createLink', url); }], ['―', 'Linha divisória', () => run('insertHorizontalRule')]];
      tools.forEach(([label, name, action]) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.title = name;
        button.setAttribute('aria-label', name);
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
      const expand = document.createElement('button');
      expand.type = 'button';
      expand.textContent = 'Tela cheia';
      expand.addEventListener('click', () => expand.setAttribute('aria-pressed', String(wrap.classList.toggle('cms-editor-full'))));
      bar.appendChild(expand);
      const reading = document.createElement('div');
      reading.className = 'cms-editor-foot';
      const updateReading = () => {
        const words = (area.hidden ? body.textContent : area.value.replace(/<[^>]*>/g, ' ')).trim().split(/\s+/).filter(Boolean).length;
        reading.textContent = 'Tempo estimado: ' + Math.max(1, Math.ceil(words / 200)) + ' min de leitura';
      };
      body.addEventListener('input', updateReading);
      area.addEventListener('input', updateReading);
      updateReading();
      area.parentNode.insertBefore(wrap, area);
      wrap.append(bar, body, area, reading);
      area.hidden = true;
      area.removeAttribute('required');
      body.addEventListener('input', sync);
      // O rótulo aponta para o textarea oculto: leva o foco para a área visível.
      const label = area.id ? document.querySelector('label[for="' + area.id + '"]') : null;
      if (label) label.addEventListener('click', (event) => { if (area.hidden) { event.preventDefault(); body.focus(); } });
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
