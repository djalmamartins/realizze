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
let navigationFocus = 0;
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
const navigationLink = {
  focus: () => { navigationFocus++; document.activeElement = navigationLink; },
};
const shell = { classList: classList(shellClasses) };
const sidebar = {
  inert: false,
  contains: (target) => target === closeButton || target === navigationLink || target === sidebar,
  querySelector: (selector) => selector === '.nav a[href], .brand' ? navigationLink : null,
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
const desktopMedia = {
  matches: false,
  addEventListener: (name, handler) => { if (name === 'change') desktopChange = handler; },
};
const window = { matchMedia: () => desktopMedia };
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
document.activeElement = toggle;
listeners.focusin({ target: toggle });
assert.equal(toggleFocus, 0, 'focus exit does not steal focus');
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
document.activeElement = closeButton;
const focusBeforeDesktop = toggleFocus;
desktopMedia.matches = true;
desktopChange({ matches: true });
assert.equal(shellClasses.has('menu-open'), false, 'desktop transition closes mobile menu');
assert.equal(sidebar.inert, false, 'visible desktop sidebar remains keyboard reachable');
assert.equal(toggleFocus, focusBeforeDesktop, 'desktop transition never focuses hidden mobile toggle');
assert.equal(document.activeElement, navigationLink, 'desktop transition moves focus off the now-hidden mobile close button');
assert.equal(navigationFocus, 1, 'visible desktop navigation receives focus');
desktopMedia.matches = false;
desktopChange({ matches: false });
assert.equal(sidebar.inert, true, 'desktop-to-mobile transition hides closed sidebar from keyboard');
assert.equal(toggleFocus, focusBeforeDesktop + 1, 'focus moves to visible mobile toggle before sidebar becomes inert');
assert.equal(document.activeElement, toggle, 'focus stays on visible mobile control');
desktopMedia.matches = true;
desktopChange({ matches: true });
assert.equal(document.activeElement, navigationLink, 'desktop transition moves focus off the now-hidden mobile toggle');
assert.equal(navigationFocus, 2, 'desktop navigation receives focus from both mobile-only buttons');
assert.equal(sidebar.inert, false, 'desktop sidebar remains reachable after returning to desktop');
console.log('OK - APP-A11Y-060: mobile disclosure, focus, inert and breakpoint focus recovery.');
