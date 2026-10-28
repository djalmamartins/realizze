'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '../container/app/realizze/v1/assets/js/dashboard.js'), 'utf8');
const listeners = {};
const attrs = {};
const shellClasses = new Set();
const bodyClasses = new Set();
let toggleFocus = 0;
let closeFocus = 0;
let desktopChange;
const classList = (values) => ({
  add: (name) => values.add(name),
  remove: (name) => values.delete(name),
  contains: (name) => values.has(name),
});
const toggle = {
  setAttribute: (name, value) => { attrs[name] = value; },
  addEventListener: (name, handler) => { listeners['toggle:' + name] = handler; },
  focus: () => { toggleFocus++; document.activeElement = toggle; },
};
const closeButton = {
  addEventListener: (name, handler) => { listeners['close:' + name] = handler; },
  focus: () => { closeFocus++; document.activeElement = closeButton; },
};
const shell = { classList: classList(shellClasses) };
const sidebar = {
  inert: false,
  contains: (target) => target === closeButton || target === sidebar,
  querySelectorAll: () => [],
};
const backdrop = {
  setAttribute: () => {},
  addEventListener: (name, handler) => { listeners['backdrop:' + name] = handler; },
};
const document = {
  readyState: 'complete',
  activeElement: null,
  body: { classList: classList(bodyClasses) },
  querySelector: (selector) => ({
    '#shell': shell, '#app-sidebar': sidebar, '#sidebar-toggle': toggle,
    '#sidebar-close': closeButton, '#sidebar-backdrop': backdrop,
  })[selector] || null,
  querySelectorAll: () => [],
  addEventListener: (name, handler) => { listeners[name] = handler; },
};
const window = {
  matchMedia: () => ({
    matches: false,
    addEventListener: (name, handler) => { if (name === 'change') desktopChange = handler; },
  }),
};
vm.runInNewContext(script, { document, window });
assert.equal(sidebar.inert, true, 'mobile navigation is inert while closed');
listeners['toggle:click']();
assert.equal(shellClasses.has('menu-open'), true, 'menu opens');
assert.equal(sidebar.inert, false, 'open navigation is keyboard reachable');
assert.equal(attrs['aria-expanded'], 'true', 'expanded state is announced');
assert.equal(closeFocus, 1, 'focus enters the open navigation');
let prevented = false;
listeners.keydown({ key: 'Tab', shiftKey: false, preventDefault: () => { prevented = true; } });
assert.equal(prevented, false, 'Tab is not trapped in a non-modal navigation');
listeners.focusin({ target: toggle });
assert.equal(shellClasses.has('menu-open'), false, 'focus leaving sidebar closes the menu');
assert.equal(sidebar.inert, true, 'closed menu cannot retain keyboard focus');
assert.equal(attrs['aria-expanded'], 'false', 'collapsed state is announced');
assert.equal(bodyClasses.has('app-menu-locked'), false, 'page scrolling is restored');
listeners['toggle:click']();
prevented = false;
listeners.keydown({ key: 'Escape', preventDefault: () => { prevented = true; } });
assert.equal(prevented, true, 'Escape is handled');
assert.equal(toggleFocus, 1, 'Escape restores focus to menu toggle');
assert.equal(sidebar.inert, true, 'Escape restores inert state');
listeners['toggle:click']();
listeners['backdrop:click']();
assert.equal(shellClasses.has('menu-open'), false, 'backdrop closes navigation');
listeners['toggle:click']();
desktopChange({ matches: true });
assert.equal(shellClasses.has('menu-open'), false, 'desktop transition closes mobile menu');
console.log('OK - APP-A11Y-050: mobile disclosure navigation, focus, inert and Escape.');
