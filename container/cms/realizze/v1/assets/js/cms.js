(function () {
  'use strict';
  const nav = document.querySelector('.cms-nav');
  if (!nav) return;
  const buttons = Array.from(nav.querySelectorAll('button[data-cms-preview]'));
  const panels = Array.from(document.querySelectorAll('.cms-preview-panel'));
  function select(button) {
    const slug = button.dataset.cmsPreview;
    const target = document.getElementById('cms-preview-' + slug);
    if (!target || !panels.includes(target)) return;
    panels.forEach(panel => { panel.hidden = panel !== target; });
    buttons.forEach(item => {
      const active = item === button;
      item.classList.toggle('selected', active);
      if (active) item.setAttribute('aria-current', 'page');
      else item.removeAttribute('aria-current');
    });
    const heading = target.querySelector('h1');
    if (heading) {
      heading.tabIndex = -1;
      heading.focus({ preventScroll: true });
    }
  }
  nav.addEventListener('click', event => {
    const button = event.target.closest('button[data-cms-preview]');
    if (button && nav.contains(button)) select(button);
  });
})();
