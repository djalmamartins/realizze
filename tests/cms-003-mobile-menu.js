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
let desktopMatches = false;
const sidebar = {
  classList: {
    add: (name) => classes.add(name),
    remove: (name) => classes.delete(name),
    contains: (name) => classes.has(name),
    toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name),
  },
  contains: (target) => target === sidebar || target === toggle || target === nav || target === link,
};
const link = { focus: () => { linkFocus += 1; document.activeElement = link; } };
const nav = {
  querySelector: () => link,
  contains: (target) => target === link,
  addEventListener: (name, handler) => { events['nav:' + name] = handler; },
};
const toggle = {
  setAttribute: (name, value) => { attrs[name] = value; },
  addEventListener: (name, handler) => { events['toggle:' + name] = handler; },
  focus: () => { toggleFocus += 1; document.activeElement = toggle; },
};
const document = {
  readyState: 'complete',
  activeElement: null,
  getElementById: (id) => ({
    'cms-sidebar': sidebar,
    'cms-menu-toggle': toggle,
    'cms-navigation': nav,
  })[id],
  addEventListener: (name, handler) => { events[name] = handler; },
};
const window = {
  matchMedia: () => ({
    get matches() { return desktopMatches; },
    addEventListener: (name, handler) => { if (name === 'change') breakpointHandler = handler; },
  }),
};
vm.runInNewContext(script, { document, window });
assert.equal(classes.has('cms-js'), true, 'JS mode initialized');
assert.equal(classes.has('cms-menu-open'), false, 'menu starts closed');
events['toggle:click']();
assert.equal(attrs['aria-expanded'], 'true', 'menu expanded');
assert.equal(linkFocus, 1, 'first navigation link receives focus');
const outsideContent = {};
document.activeElement = outsideContent;
events.focusin({ target: outsideContent });
assert.equal(document.activeElement, outsideContent, 'Tabbing into content preserves external focus');
assert.equal(classes.has('cms-menu-open'), false, 'Tabbing into content closes menu');
assert.equal(attrs['aria-expanded'], 'false', 'ARIA state resets after focus leaves');
events['toggle:click']();
let prevented = false;
events.keydown({ key: 'Escape', preventDefault: () => { prevented = true; } });
assert.equal(prevented, true, 'Escape handled');
assert.equal(toggleFocus, 1, 'Escape restores toggle focus');
events['toggle:click']();
const pointerFocusBefore = toggleFocus;
events.pointerdown({ target: {} });
assert.equal(classes.has('cms-menu-open'), false, 'outside pointer closes menu');
assert.equal(document.activeElement, toggle, 'outside pointer never leaves focus in hidden navigation');
assert.equal(toggleFocus, pointerFocusBefore + 1, 'focus returns to the mobile toggle before the nav is hidden');
events['toggle:click']();
desktopMatches = true;
breakpointHandler({ matches: true });
assert.equal(document.activeElement, link, 'desktop navigation keeps focus on its visible link');
const focusBeforeMobile = toggleFocus;
desktopMatches = false;
breakpointHandler({ matches: false });
assert.equal(toggleFocus, focusBeforeMobile + 1, 'desktop-to-mobile transition moves focus out of hidden links');
assert.equal(document.activeElement, toggle, 'mobile toggle receives focus before navigation is hidden');
// The toggle becomes display:none on desktop; keyboard focus must not be stranded.
const linkFocusBeforeDesktop = linkFocus;
desktopMatches = true;
breakpointHandler({ matches: true });
assert.equal(document.activeElement, link, 'desktop transition restores focus from hidden toggle to visible navigation');
assert.equal(linkFocus, linkFocusBeforeDesktop + 1, 'visible link receives focus exactly once after returning to desktop');
desktopMatches = false;
breakpointHandler({ matches: false });
assert.equal(document.activeElement, toggle, 'another desktop-to-mobile transition restores toggle focus');
assert.equal(classes.has('cms-menu-open'), false, 'desktop transition closes mobile menu');
events['toggle:click']();
events['nav:click']({ target: { closest: (selector) => selector === 'a' ? link : null } });
assert.equal(classes.has('cms-menu-open'), false, 'navigation link closes menu');
assert.equal(document.activeElement, toggle, 'closing a mobile navigation link restores visible keyboard focus');

/* CMS-A11Y-069: incomplete startup must never hide the CSS-only navigation. */
for (const missing of ['cms-sidebar', 'cms-menu-toggle', 'cms-navigation',
  'matchMedia', 'first-link', 'matchMedia-throws', 'matchMedia-invalid', 'matchMedia-no-listener']) {
  const fallbackClasses = new Set();
  const fallbackEvents = {};
  const fallbackSidebar = { classList: { add: (name) => fallbackClasses.add(name) } };
  const fallbackToggle = { addEventListener: (name) => { fallbackEvents['toggle:' + name] = true; } };
  const fallbackNav = {
    querySelector: () => missing === 'first-link' ? null : { focus: () => {} },
    addEventListener: (name) => { fallbackEvents['nav:' + name] = true; },
  };
  const fallbackDocument = {
    readyState: 'complete',
    getElementById: (id) => id === missing ? null : ({
      'cms-sidebar': fallbackSidebar,
      'cms-menu-toggle': fallbackToggle,
      'cms-navigation': fallbackNav,
    })[id],
    addEventListener: (name) => { fallbackEvents[name] = true; },
  };
  const fallbackWindow = missing === 'matchMedia' ? {} : {
    matchMedia: () => {
      if (missing === 'matchMedia-throws') throw new Error('viewport API unavailable');
      return missing === 'matchMedia-invalid' ? {} : { matches: false };
    },
  };
  vm.runInNewContext(script, { document: fallbackDocument, window: fallbackWindow });
  assert.equal(fallbackClasses.has('cms-js'), false,
    'incomplete initialization keeps CSS navigation visible: ' + missing);
  assert.equal(fallbackEvents['toggle:click'], undefined,
    'incomplete initialization does not install a broken disclosure: ' + missing);
}
// Legacy MediaQueryList.addListener remains supported without changing security gates.
let legacyBreakpoint = null;
const legacyClasses = new Set();
const legacyNav = {
  querySelector: () => ({ focus: () => {} }),
  addEventListener: () => {},
  contains: () => false,
};
const legacySidebar = {
  classList: {
    add: (name) => legacyClasses.add(name),
    remove: (name) => legacyClasses.delete(name),
    contains: (name) => legacyClasses.has(name),
    toggle: (name, enabled) => enabled ? legacyClasses.add(name) : legacyClasses.delete(name),
  },
  contains: () => false,
};
const legacyToggle = { setAttribute: () => {}, addEventListener: () => {}, focus: () => {} };
const legacyDocument = {
  readyState: 'complete', activeElement: null,
  getElementById: (id) => ({
    'cms-sidebar': legacySidebar,
    'cms-menu-toggle': legacyToggle,
    'cms-navigation': legacyNav,
  })[id],
  addEventListener: () => {},
};
vm.runInNewContext(script, {
  document: legacyDocument,
  window: { matchMedia: () => ({ matches: false, addListener: (handler) => { legacyBreakpoint = handler; } }) },
});
assert.equal(legacyClasses.has('cms-js'), true, 'legacy viewport API activates complete navigation');
assert.equal(typeof legacyBreakpoint, 'function', 'legacy viewport changes remain observed');
legacyBreakpoint({ matches: true });
assert.equal(legacyClasses.has('cms-menu-open'), false, 'legacy desktop transition closes mobile menu');

console.log('OK - CMS-A11Y-061: keyboard, pointer, bidirectional breakpoint and hidden-toggle focus recovery.');

// CMS-A11Y-083: administrative preview navigation stays perceivable in high contrast.
const cmsCss = fs.readFileSync(path.join(__dirname, '../container/cms/realizze/v1/assets/css/responsive-fixes.css'), 'utf8');
assert.match(cmsCss, /@media\s*\(forced-colors:\s*active\)/,
  'CMS supports system forced-color mode');
assert.match(cmsCss, /\.cms-nav a\.selected[^}]*outline:\s*2px solid Highlight/s,
  'selected CMS page is outlined');
assert.match(cmsCss, /\.cms-skip-link:focus-visible[^}]*outline:\s*3px solid Highlight/s,
  'CMS skip link retains visible focus');
assert.match(cmsCss, /\.cms-preview-status[^}]*border:\s*1px solid CanvasText/s,
  'read-only preview notice is distinguishable');
console.log('OK - CMS-A11Y-083: navegação e avisos visíveis em alto contraste.');
