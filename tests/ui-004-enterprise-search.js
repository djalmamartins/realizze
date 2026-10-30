'use strict';
/**
 * APP-UI-052: filtro PJ somente no navegador; nenhum serviço ou dado real.
 * Testa módulos, grupos, colunas, links, estado vazio e atalhos de teclado.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const script = fs.readFileSync(
  path.join(__dirname, '../container/app/realizze/v1/assets/js/dashboard.js'), 'utf8'
);
// APP-A11Y-079: author CSS must not override the HTML hidden attribute
// before JavaScript has installed the complete client-side search.
const css = fs.readFileSync(
  path.join(__dirname, '../container/app/realizze/v1/assets/css/dashboard.css'), 'utf8'
);
assert.match(css, /\.enterprise-module-filter\[hidden\]\{display:none!important\}/,
  'a hidden enterprise search must remain hidden without JavaScript');
const callbacks = {};
const input = {
  value: '',
  focused: false,
  addEventListener: (name, callback) => { callbacks['input:' + name] = callback; },
  focus() { this.focused = true; document.activeElement = this; },
};
const clear = {
  disabled: true,
  addEventListener: (name, callback) => { callbacks['clear:' + name] = callback; },
};
const status = { textContent: '' };
const empty = { hidden: true };
const filter = { hidden: true };
const jump = { hidden: false };
const jumpHelp = { hidden: false };
const groups = [];
const modules = [];
const links = [];
function addGroup(name, definitions) {
  const group = {
    hidden: false,
    querySelector: (selector) => selector === 'h3' ? { textContent: name } : null,
    querySelectorAll: (selector) => selector === '.enterprise-module' ? ownModules : [],
  };
  const ownModules = [];
  groups.push(group);
  definitions.forEach(([id, title, description, columns]) => {
    const module = {
      id: 'enterprise-' + id,
      hidden: false,
      contains: (target) => target === module,
      closest: (selector) => selector === '.enterprise-module-group' ? group : null,
      querySelector: (selector) => {
        if (selector === 'h4') return { textContent: title };
        if (selector === '.enterprise-module-header p') return { textContent: description };
        return null;
      },
      querySelectorAll: (selector) => selector === '.enterprise-module-table th'
        ? columns.map((textContent) => ({ textContent })) : [],
    };
    const link = {
      hidden: false,
      contains: (target) => target === link,
      getAttribute: (name) => name === 'href' ? '#' + module.id : null,
      addEventListener: () => {},
    };
    ownModules.push(module);
    modules.push(module);
    links.push(link);
  });
}
addGroup('Relacionamentos e equipe', [
  ['clientes', 'Clientes', 'Cadastro de contatos.', ['Cliente', 'Documento']],
]);
addGroup('Gestão financeira', [
  ['receber', 'Contas a receber', 'Receitas da empresa.', ['Cliente', 'Vencimento']],
  ['pagar', 'Contas a pagar', 'Despesas da empresa.', ['Fornecedor', 'Vencimento']],
]);
const selectors = {
  '#enterprise-module-search': input,
  '#enterprise-module-clear': clear,
  '#enterprise-search-status': status,
  '#enterprise-search-empty': empty,
  '.enterprise-module-filter': filter,
  '.enterprise-jump': jump,
  '#enterprise-jump-help': jumpHelp,
};
const document = {
  readyState: 'complete',
  activeElement: null,
  querySelector: (selector) => selectors[selector] || null,
  querySelectorAll: (selector) => ({
    '.enterprise-module-group .enterprise-module': modules,
    '.enterprise-module-group': groups,
    '.enterprise-jump a[href^="#enterprise-"]': links,
  })[selector] || [],
  getElementById: (id) => modules.find((module) => module.id === id) || null,
  addEventListener: () => {},
};
const window = {
  matchMedia: () => ({ matches: true, addEventListener: () => {} }),
};
vm.runInNewContext(script, { document, window });
const search = (value) => {
  input.value = value;
  callbacks['input:input']();
};
const visible = () => modules.filter((module) => !module.hidden).map((module) => module.id);
assert.deepEqual(visible(), ['enterprise-clientes', 'enterprise-receber', 'enterprise-pagar']);
assert.equal(status.textContent, '3 módulos encontrados.');
assert.equal(empty.hidden, true);
assert.equal(filter.hidden, false, 'JavaScript habilita o filtro de forma progressiva');
assert.equal(clear.disabled, true, 'limpar inicia desabilitado sem texto');
assert.equal(jump.hidden, false, 'atalhos disponíveis com resultados');
assert.equal(jumpHelp.hidden, false, 'orientação disponível junto dos atalhos');

search('GESTAO FINANCEIRA');
assert.deepEqual(visible(), ['enterprise-receber', 'enterprise-pagar'],
  'nome do grupo é pesquisável sem acentos');
assert.equal(clear.disabled, false, 'digitar habilita o botão de limpar');
assert.equal(groups[0].hidden, true);
assert.equal(groups[1].hidden, false);
assert.equal(links[0].hidden, true, 'atalho oculto não aponta para módulo filtrado');

search('vencimento receber');
assert.deepEqual(visible(), ['enterprise-receber'], 'palavras de campos distintos combinam sem exigir ordem contígua');
search('  GESTÃO    VENCIMENTO  ');
assert.deepEqual(visible(), ['enterprise-receber', 'enterprise-pagar'], 'espaços e acentos não prejudicam busca por múltiplos termos');
search('fornecedor pagar');
assert.deepEqual(visible(), ['enterprise-pagar'], 'múltiplos termos refinam o resultado');
search('gestao documento');
assert.deepEqual(visible(), [], 'todos os termos devem corresponder ao mesmo módulo');

search('documento');
assert.deepEqual(visible(), ['enterprise-clientes'], 'cabeçalhos de tabela são pesquisáveis');
assert.equal(status.textContent, '1 módulo encontrado.');
assert.equal(links[1].hidden, true);

search('nenhum termo corresponde');
assert.deepEqual(visible(), []);
assert.equal(groups.every((group) => group.hidden), true);
assert.equal(links.every((link) => link.hidden), true);
assert.equal(empty.hidden, false);
assert.equal(jump.hidden, true, 'navegação sem links não permanece exposta');
assert.equal(jumpHelp.hidden, true, 'orientação não permanece quando não há atalhos');
assert.equal(status.textContent, 'Nenhum módulo corresponde à pesquisa.');

let prevented = false;
callbacks['input:keydown']({
  key: 'Escape',
  preventDefault() { prevented = true; },
});
assert.equal(prevented, true, 'Escape limpa filtro ativo');
assert.equal(input.value, '');
assert.equal(empty.hidden, true);
assert.equal(jump.hidden, false, 'atalhos voltam após limpar');
assert.equal(jumpHelp.hidden, false, 'orientação volta junto dos atalhos');
assert.equal(clear.disabled, true, 'botão limpar volta a ficar desabilitado');
assert.equal(groups.every((group) => !group.hidden), true);
assert.equal(links.every((link) => !link.hidden), true);
assert.equal(visible().length, 3);

// Focus must never remain inside a filtered-out shortcut or module.
document.activeElement = links[0];
input.focused = false;
search('receber');
assert.equal(document.activeElement, input, 'focus recovers when a shortcut becomes hidden');
assert.equal(input.focused, true, 'visible search field receives recovered focus');
document.activeElement = links[1];
input.focused = false;
search('receber');
assert.equal(document.activeElement, links[1], 'focus remains on a visible shortcut');
assert.equal(input.focused, false, 'visible results do not trigger unwanted focus changes');
document.activeElement = modules[1];
search('pagar');
assert.equal(document.activeElement, input, 'focus recovers when a module is filtered out');

search('clientes');
callbacks['clear:click']();
assert.equal(input.value, '');
assert.equal(input.focused, true, 'botão limpar devolve foco ao campo');
assert.equal(visible().length, 3);
assert.equal(clear.disabled, true, 'botão limpar desabilitado após clique');

// Simulate browsers that clear document.activeElement immediately when hidden
// is applied. Recovery must use the focus captured before changing visibility.
for (const target of [modules[1], links[0]]) {
  let hidden = target.hidden;
  Object.defineProperty(target, 'hidden', {
    configurable: true,
    get() { return hidden; },
    set(value) {
      hidden = value;
      if (value && document.activeElement === target) document.activeElement = null;
    },
  });
}
document.activeElement = modules[1];
input.focused = false;
search('clientes');
assert.equal(document.activeElement, input,
  'focused module recovers even when browser clears activeElement on hide');
assert.equal(input.focused, true);
document.activeElement = links[0];
input.focused = false;
search('pagar');
assert.equal(document.activeElement, input,
  'focused shortcut recovers even when browser clears activeElement on hide');
assert.equal(input.focused, true);

console.log('OK - APP-A11Y-063: filtro PJ recupera foco antes do navegador ocultar o elemento.');


// APP-A11Y-079: incomplete markup cannot expose an unusable search widget.
// All preview modules and their links remain visible without enhancement.
for (const missing of [
  '#enterprise-module-search', '#enterprise-module-clear',
  '#enterprise-search-status', '#enterprise-search-empty',
  '.enterprise-module-filter', '.enterprise-jump', '#enterprise-jump-help',
]) {
  const fallbackFilter = { hidden: true };
  const partialNodes = { ...selectors, '.enterprise-module-filter': fallbackFilter };
  delete partialNodes[missing];
  const partialDocument = {
    ...document,
    querySelector: (selector) => partialNodes[selector] || null,
  };
  vm.runInNewContext(script, { document: partialDocument, window });
  assert.equal(fallbackFilter.hidden, true, 'partial DOM keeps filter hidden: ' + missing);
}
for (const missingSet of ['modules', 'groups', 'links']) {
  const fallbackFilter = { hidden: true };
  const partialDocument = {
    ...document,
    querySelector: (selector) => selector === '.enterprise-module-filter' ? fallbackFilter : document.querySelector(selector),
    querySelectorAll: (selector) => {
      if (missingSet === 'modules' && selector === '.enterprise-module-group .enterprise-module') return [];
      if (missingSet === 'groups' && selector === '.enterprise-module-group') return [];
      if (missingSet === 'links' && selector === '.enterprise-jump a[href^="#enterprise-"]') return [];
      return document.querySelectorAll(selector);
    },
  };
  vm.runInNewContext(script, { document: partialDocument, window });
  assert.equal(fallbackFilter.hidden, true, 'incomplete module inventory keeps filter hidden: ' + missingSet);
}
console.log('OK - APP-A11Y-079: hidden search remains unavailable without complete JavaScript/DOM.');


// APP-A11Y-082: do not enable an apparently working search with broken
// shortcuts, duplicate module IDs or unsupported Unicode normalization.
search('');
const fallbackCases = [
  ['missing target', [{ ...links[0], getAttribute: (name) => name === 'href' ? '#enterprise-inexistente' : null }, ...links.slice(1)]],
  ['duplicate shortcut', [links[0], links[0], links[2]]],
];
for (const [reason, candidateLinks] of fallbackCases) {
  const fallbackFilter = { hidden: true };
  const fallbackDocument = {
    ...document,
    querySelector: (selector) => selector === '.enterprise-module-filter' ? fallbackFilter : document.querySelector(selector),
    querySelectorAll: (selector) => selector === '.enterprise-jump a[href^="#enterprise-"]'
      ? candidateLinks : document.querySelectorAll(selector),
  };
  vm.runInNewContext(script, { document: fallbackDocument, window });
  assert.equal(fallbackFilter.hidden, true, 'invalid shortcuts keep search hidden: ' + reason);
  assert.equal(modules.every((module) => !module.hidden), true, 'modules remain visible: ' + reason);
}
const previousId = modules[1].id;
modules[1].id = modules[0].id;
const duplicateFilter = { hidden: true };
vm.runInNewContext(script, {
  document: { ...document, querySelector: (selector) =>
    selector === '.enterprise-module-filter' ? duplicateFilter : document.querySelector(selector) },
  window,
});
assert.equal(duplicateFilter.hidden, true, 'duplicate module IDs keep search hidden');
modules[1].id = previousId;

const legacyFilter = { hidden: true };
const legacyDocument = {
  ...document,
  querySelector: (selector) => selector === '.enterprise-module-filter' ? legacyFilter : document.querySelector(selector),
};
vm.runInNewContext('String.prototype.normalize = undefined;\n' + script, { document: legacyDocument, window });
assert.equal(legacyFilter.hidden, true, 'legacy browsers keep static navigation instead of a broken search');
assert.equal(modules.every((module) => !module.hidden), true, 'legacy fallback keeps every module visible');
assert.match(css, /enterprise-module-header h4,.*enterprise-module-table th/,
  'long enterprise headings and table labels use the same responsive rule');
assert.match(css, /overflow-wrap:anywhere;/, 'long labels wrap inside narrow cards');
console.log('OK - APP-A11Y-082: PJ search validates links/IDs and preserves a responsive fallback.');

// APP-A11Y-083: high-contrast mode must preserve focus and active PF/PJ navigation.
assert.match(css, /@media\s*\(forced-colors:\s*active\)/,
  'App supports Windows forced-color accessibility mode');
assert.match(css, /\.nav a\.active[^}]*outline:\s*2px solid Highlight/s,
  'active sidebar link remains distinguishable without background colors');
assert.match(css, /\.app-skip-link:focus-visible[^}]*outline:\s*3px solid Highlight/s,
  'skip link has system-color focus outline');
assert.match(css, /\.enterprise-module:target[^}]*outline:\s*3px solid Highlight/s,
  'enterprise anchor target remains visible in high contrast');
assert.match(css, /\.app-context-badge[^}]*border:\s*1px solid CanvasText/s,
  'PF/PJ visual badge remains outlined');
console.log('OK - APP-A11Y-083: foco, contexto e âncoras preservados em alto contraste.');

 
// APP-A11Y-090: eleven shortcuts must remain touch-friendly and horizontally
// scrollable on narrow phones, without hiding the native scrollbar or links.
const mobileShortcuts = css.match(/\/\* APP-A11Y-090:[\s\S]*?@media\s*\(max-width:650px\)\s*\{([\s\S]*?)\n\}/);
assert.ok(mobileShortcuts, 'mobile enterprise shortcut rules exist');
assert.match(mobileShortcuts[1], /\.enterprise-jump\s*\{[^}]*flex-wrap:\s*nowrap;[^}]*overflow-x:\s*auto;/s,
  'enterprise shortcuts scroll horizontally rather than stacking on narrow phones');
assert.match(mobileShortcuts[1], /\.enterprise-jump a\s*\{[^}]*flex:\s*0 0 auto;[^}]*min-height:\s*44px;/s,
  'each shortcut remains keyboard-reachable and at least 44px tall');
assert.doesNotMatch(mobileShortcuts[1], /scrollbar-width:\s*none|display:\s*none|visibility:\s*hidden/,
  'the native scrollbar and links must remain visible');
console.log('OK - APP-A11Y-090: atalhos PJ compactos, roláveis e com alvos de toque de 44px.');

// APP-A11Y-091: hint is visible only on narrow screens and never lingers
// after search removes all shortcuts.
assert.match(css, /\.enterprise-jump-help\[hidden\]\{display:none!important\}/,
  'hidden mobile hint stays hidden even when media query sets display:block');
assert.match(css, /@media\(max-width:650px\)\{\.enterprise-jump-help\{display:block;/,
  'mobile users receive a visible horizontal scrolling hint');
console.log('OK - APP-A11Y-091: orientação PJ acompanha a navegação filtrada.');
