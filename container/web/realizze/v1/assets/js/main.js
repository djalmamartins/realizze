'use strict';
document.addEventListener('DOMContentLoaded', () => {
  document.documentElement.classList.add('js-enabled');
  const button = document.querySelector('.menu-toggle');
  const nav = document.getElementById('main-navigation');
  const header = document.querySelector('.header-inner');
  if (button && nav && header) {
    button.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      header.classList.toggle('menu-open', open);
      button.setAttribute('aria-expanded', String(open));
      button.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    });
    nav.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
      nav.classList.remove('is-open');
      header.classList.remove('menu-open');
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-label', 'Abrir menu');
    }));
  }
  document.querySelectorAll('[data-demo-form]').forEach(form => {
    form.addEventListener('submit', event => {
      event.preventDefault();
      const feedback = form.querySelector('.form-feedback');
      if (feedback) feedback.textContent = 'Esta é uma prévia visual. O envio ainda não está conectado ao sistema.';
    });
  });
});
