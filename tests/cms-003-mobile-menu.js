'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const script = fs.readFileSync(path.join(__dirname, '../container/cms/realizze/v1/assets/js/cms.js'), 'utf8');
const events = {};
const classes = new Set();
const attrs = {};
let linkFocus = 0;
let toggleFocus = 0;
let breakpointHandler = null;
const sidebar = {
  classList: {
    add: (name) => classes.add(name),
    remove: (name) => classes.delete(name),
    contains: (name) => classes.has(name),
    toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name),
  },
  contains: (target) => target === sidebar || target === toggle || target === nav || target === link,
};
const link = { focus: () => { linkFocus += 1; } };
const nav = {
  querySelector: () => link,
  addEventListener: (name, handler) => { events['nav:' + name] = handler; },
};
const toggle = {
  setAttribute: (name, value) => { attrs[name] = value; },
  addEventListener: (name, handler) => { events['toggle:' + name] = handler; },
  focus: () => { toggleFocus += 1; },
};
const document = {
  readyState: 'complete',
  getElementById: (id) => ({
    'cms-sidebar': sidebar,
    'cms-menu-toggle': toggle,
    'cms-navigation': nav,
  })[id],
  addEventListener: (name, handler) => { events[name] = handler; },
};
const window = {
  matchMedia: () => ({
    addEventListener: (name, handler) => { if (name === 'change') breakpointHandler = handler; },
  }),
};
vm.runInNewContext(script, { document, window });
assert.equal(classes.has('cms-js'), true, 'JS mode initialized');
assert.equal(classes.has('cms-menu-open'), false, 'menu starts closed');
events['toggle:click']();
assert.equal(attrs['aria-expanded'], 'true', 'menu expanded');
assert.equal(linkFocus, 1, 'first navigation link receives focus');
events.focusin({ target: {} });
assert.equal(classes.has('cms-menu-open'), false, 'Tabbing into content closes menu');
assert.equal(attrs['aria-expanded'], 'false', 'ARIA state resets after focus leaves');
events['toggle:click']();
let prevented = false;
events.keydown({ key: 'Escape', preventDefault: () => { prevented = true; } });
assert.equal(prevented, true, 'Escape handled');
assert.equal(toggleFocus, 1, 'Escape restores toggle focus');
events['toggle:click']();
events.pointerdown({ target: {} });
assert.equal(classes.has('cms-menu-open'), false, 'outside pointer closes menu');
events['toggle:click']();
breakpointHandler({ matches: true });
assert.equal(classes.has('cms-menu-open'), false, 'desktop transition closes mobile menu');
events['toggle:click']();
events['nav:click']({ target: { closest: (selector) => selector === 'a' ? link : null } });
assert.equal(classes.has('cms-menu-open'), false, 'navigation link closes menu');
console.log('OK - CMS-A11Y-049: keyboard, outside pointer, breakpoint and link navigation.');
