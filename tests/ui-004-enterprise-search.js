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
console.log('OK - APP-A11Y-062: filtro PJ, atalhos, teclado e recuperação de foco.');
