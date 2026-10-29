# Realizze 2.0 — acompanhamento App e CMS (28/10/2026)

> Trabalho em PR #15 **draft**. Não fazer merge automático, não alterar produção nem ativar WhatsApp ou cobranças.

## Arquitetura e componentes

- Ambientes separados: `container/{web,app,cms,erp,send}/{tema}/{versao}`.
- Verificação de `container/app/realizze/v1` e `container/cms/realizze/v1`: apenas `default.php` e `routes.php` na raiz da versão; demais templates em `components/`, `pages/` e recursos em `assets/`.
- O App usa a API verificada `MovesCode\View\Engine::render()` e `addFolder()`; `scripts/publish-app-assets.php` usa `MovesCode\Compress\CSS` e `JS` com `minify()`.
- Cropper/Thumb, Storage e Pager não são usados em telas que ainda não processam imagens, arquivos ou coleções reais; não criar APIs alternativas.
- A logomarca do site é `/assets/realizze/v1/images/new-logo-realizze.svg`.

## App PF — páginas existentes

| Página | Estado de interface | Pendente para operação real |
| --- | --- | --- |
| Visão geral | Componentes e estados vazios | Saldos, transações, gráficos e filtros por período com dados autorizados |
| Receber | Resumo, filtros e tabela | CRUD e liquidação com autorização e CSRF |
| Pagar | Resumo, filtros e tabela | CRUD, aprovação e liquidação |
| Movimentações | Resumo, filtros e tabela | Consultas escopadas, paginação e exportação |
| Contas e Cartões | Painéis e estados vazios | Contas, cartões, limites e faturas reais |
| Relatórios | Áreas de gráficos e filtros | Agregações reais e exportação |
| Planejamento | Indicadores e painéis | Orçamentos, categorias e histórico |
| Metas | Prévia visual | Persistência e cálculo de progresso |
| Categorias | Resumo, filtros e tabela | Cadastro e classificação |
| Configurações | Painéis de perfil e segurança | Preferências, notificações e dados pessoais |

## App Empresarial (MEI/PJ)

- `pages/empresarial.php` conserva a seleção autorizada por **POST + CSRF** de `OrganizationContext`, com feature flag desligada por padrão.
- `components/enterprise-kpis.php` e `enterprise-module-panel.php` apresentam indicadores indisponíveis e painéis reutilizáveis via MovesCode View.
- Onze módulos em prévia responsiva: clientes, fornecedores, equipe/permissões, receber, pagar, bancos/cartões, fluxo de caixa, centros de custos, relatórios/DRE, conciliação OFX/CSV e obrigações MEI/PJ.
- Navegação por âncoras internas com destino focável por teclado e indicação visual; **não há troca de empresa por GET**, dados persistidos, upload OFX/CSV, operações financeiras ou permissões novas.
- **Pendentes:** inspeção do schema MySQL de homologação, migrations aditivas planejadas, autorização por operação, queries com escopo PF/PJ, testes HTTP de sessão/CSRF/isolamento e implementação de cada módulo.

## CMS — oito seções de prévia

| Seção | Estado | Pendente |
| --- | --- | --- |
| Visão geral | Prévia | Indicadores reais e permissões |
| Páginas | Prévia | CRUD, publicação e histórico |
| Conteúdos | Prévia | Editor e fluxo editorial |
| Mídia | Prévia | Upload seguro, validação e Storage/Cropper quando necessários |
| Usuários | Prévia | Gestão administrativa por papéis |
| Planos e assinaturas | Prévia | Catálogo autorizado; cobranças fora do escopo |
| Logs e auditoria | Prévia | Trilha de auditoria persistida e filtros |
| Configurações | Prévia | Preferências com autorização e CSRF |

O CMS continua **indisponível fora de prévia local explícita** (`APP_ENV=local` e `REALIZZE_CMS_LOCAL_PREVIEW=1`) **e IP cliente permitido** (`REMOTE_ADDR` em `127.0.0.1` ou `::1` por padrão). Para o gateway Docker local, configurar `REALIZZE_CMS_PREVIEW_ALLOWED_IPS` com IPs individuais e confiáveis, separados por vírgula; não usar IP público, não confiar em `X-Forwarded-For` e não expor a prévia na internet. Não publicar rotas administrativas antes de autenticação, autorização por papéis, CSRF e auditoria. Há testes de lógica do bloqueio e checagem estática da rota; teste HTTP de proxy e rede ainda necessário.


## CMS-UI-028 a CMS-SEC-032 — evolução desta etapa

- Extraído `components/icons.php`: biblioteca de SVGs de caminho fixo, reutilizada na sidebar e nas prévias de páginas, conteúdos, mídia e usuários. Nomes desconhecidos usam um ícone conhecido; nenhuma entrada arbitrária vira HTML.
- Navegação móvel do CMS: fechamento com Escape e clique fora, lista com rolagem própria e foco visível; CSS ajustado para os SVGs das áreas vazias.
- Reforçado o bloqueio da prévia: além das duas flags locais, a rota só renderiza quando `REMOTE_ADDR` está na lista explícita de IPs locais permitidos. O gate puro fica em `components/preview-access.php` e possui testes de aceitação/negação em `tests/cms-002-preview-access.php`; não consulta cabeçalhos de proxy.
- `tests/cms-001-preview-smoke.php` agora verifica oito telas, controles administrativos desabilitados, ausência de formulários/valores financeiros fictícios, IDs duplicados, SVGs compartilhados e presença da restrição de IP no código das rotas.
- **Sem mudança em banco ou produção.** Testes HTTP de IP permitido/bloqueado e validação visual no Docker continuam pendentes. A configuração da rede Docker pode exigir adicionar o IP do gateway local à allowlist.

## Verificação e homologação

CI: [Realizze App UI checks #40652146016](https://github.com/djalmamartins/realizze/actions/runs/40652146016) — sucesso após os componentes empresariais e smoke test.

Comandos locais (ambiente com Composer, PHP 8.2+, Node):

```bash
php tests/ui-002-view-smoke.php
php tests/enterprise-001-context.php
php tests/cms-001-preview-smoke.php
php tests/cms-002-preview-access.php
find container/app/realizze/v1 container/cms/realizze/v1 -name '*.php' -exec php -l {} \;
node --check container/app/realizze/v1/assets/js/dashboard.js
node --check container/cms/realizze/v1/assets/js/cms.js
php scripts/publish-app-assets.php
```

**Ainda não realizados:** teste visual real em desktop/mobile no Docker do Mac, teste HTTP de bloqueio do CMS, inspeção MySQL em homologação, testes end-to-end de sessão/CSRF e auditoria de acessibilidade com leitor de tela. Nenhuma migração ou cobrança foi executada.

## APP-UI-033–035 / CMS-UI-037–039 — navegação e componentes (28/10/2026)

- App Empresarial: pesquisa **somente no navegador** para localizar os onze módulos existentes por título e descrição, com normalização de acentos, botão de limpar, contagem anunciada por `role="status"` e ocultação sincronizada de cartões, grupos e atalhos. Não consulta dados financeiros, não usa parâmetros GET para selecionar empresa e não altera o contexto PF/PJ.
- A busca foi adicionada a `pages/empresarial.php`, `assets/js/dashboard.js` e `assets/css/dashboard.css`. Sem JavaScript, os onze módulos e os atalhos continuam visíveis.
- CMS: criado `components/page-heading.php`, usado nas oito seções (visão geral, páginas, conteúdos, mídia, usuários, planos, logs e configurações). Metadados são escapados com `htmlspecialchars` e cada página recebe um único título `h1` com identificador consistente.
- Testes atualizados: `tests/ui-002-view-smoke.php` valida a marcação acessível do filtro PJ sem submissão; `tests/cms-001-preview-smoke.php` valida o cabeçalho nas oito seções e rejeita HTML injetado nos metadados.
- Não foram adicionadas rotas, operações administrativas, uploads, cobranças, integrações WhatsApp ou migrações. A prévia do CMS continua restrita pelo gate local e não substitui autenticação administrativa real.

**Ainda faltam no App PF:** dados autorizados e operações nas dez páginas existentes; filtros de dados, exportações e homologação visual. **No App Empresarial:** serviços e operações isoladas por organização para clientes/fornecedores, equipes, contas a receber/pagar, bancos/cartões, fluxo de caixa, custos, DRE, conciliação OFX/CSV e obrigações MEI/PJ. **No CMS:** as oito páginas de prévia existem; faltam autenticação administrativa, CSRF nas mutações, RBAC, auditoria persistente e operações de conteúdo/mídia/usuários/configurações. Testes HTTP, inspeção MySQL de homologação e testes visuais no Docker seguem pendentes.

## CMS-UI-041 — tabelas vazias acessíveis (28/10/2026)

- Criado `container/cms/realizze/v1/components/empty-table.php` para tabelas de prévia com legenda visível, cabeçalhos com `scope="col"`, estado vazio e `colspan` calculado.
- As seções **Planos e assinaturas** e **Logs e auditoria** usam o mesmo componente; metadados são escapados antes da renderização.
- Estilos de legenda e rolagem horizontal adicionados em `assets/css/responsive-fixes.css`; `tests/cms-001-preview-smoke.php` verifica estrutura e escape de HTML.
- **Páginas CMS ainda pendentes de operação:** as oito seções exigem autenticação administrativa, RBAC, CSRF, auditoria e serviços reais. **App PF:** dez páginas ainda sem integração financeira. **App PJ:** onze módulos ainda sem consultas/CRUD escopados.
- Nenhum acesso a banco, upload, cobrança, publicação, WhatsApp ou alteração de produção nesta etapa.


## APP-SEC/A11Y-043 — sidebar PF/PJ (28/10/2026)

- Escapados o nome do perfil, a inicial do avatar, os textos e URLs da navegação e o token CSRF de saída em `components/sidebar.php`, preservando o POST existente. Teste de regressão usa textos e atributos maliciosos sem executar HTML.
- Substituídos símbolos tipográficos de tema, ajuda e saída por SVGs estáticos com rótulos acessíveis; sem novas APIs nem operações.
- No menu móvel, `dashboard.js` aplica `inert` à sidebar fora da tela, retirando links invisíveis da navegação por Tab. A abertura restaura a interação e o fechamento devolve o foco ao botão de menu; desktop continua navegável. Testes de origem não substituem teste real em navegador com teclado/leitor de tela.
- **Inventário de páginas pendentes:** App PF: dez páginas existentes, faltam CRUD, filtros e dados autorizados; App PJ: onze módulos em prévia, faltam serviços, persistência e isolamento efetivo; CMS: oito prévias, faltam autenticação administrativa, RBAC, CSRF nas mutações, auditoria e CRUD. Sem alterações de rotas, banco, produção, WhatsApp ou cobranças.

## APP-UI/SEC-044 — tabelas financeiras PF e escape único (28/10/2026)

- Criado `container/app/realizze/v1/components/finance-empty-table.php`, reutilizando a API verificada de `MovesCode\\View\\Engine::render()` para legendas de tabelas, cabeçalhos `scope="col"`, `colspan` e estados vazios com metadados escapados.
- Aplicado às cinco páginas PF: **Receber, Pagar, Movimentações, Categorias e Metas**. Legendas visíveis e rolagem horizontal contida em dispositivos móveis; não há consultas financeiras nem valores simulados.
- Em `pages/dashboard.php`, o nome do perfil e o token CSRF são mantidos como valores brutos até o componente de apresentação aplicar escape HTML, evitando dupla codificação de caracteres. `sidebar.php` e `dashboard-header.php` já escapam os valores ao renderizar.
- `tests/ui-002-view-smoke.php` verifica a renderização das cinco tabelas com MovesCode View, legendas, ausência de valores inventados, escape de HTML malicioso e contrato de escape único.
- **Páginas PF ainda pendentes:** todas as tabelas de prévia serão padronizadas na etapa APP-UI-045; as dez páginas ainda precisam de serviços, dados autorizados e homologação visual; as demais seções precisam de operações reais e validação responsiva no Docker.
- **App PJ:** onze módulos em prévia, ainda sem CRUD/consultas escopadas. **CMS:** oito prévias; ainda sem autenticação administrativa, RBAC, CSRF nas mutações, auditoria e CRUD.
- Sem alteração de banco, migrações, produção, cobranças ou integração WhatsApp.

## APP-UI/A11Y-045 — tabelas PF restantes (28/10/2026)

- **Contas e Cartões** usa duas instâncias do componente compartilhado `finance-empty-table.php`, com legendas distintas para contas e cartões; **Relatórios** e **Planejamento** usam uma instância cada. Assim, as tabelas de prévia das páginas PF ficam padronizadas, sem consultas ou dados financeiros simulados.
- O contêiner de cada tabela compartilhada recebe `role="region"`, `tabindex="0"` e nome acessível escapado, permitindo rolagem horizontal pelo teclado em telas estreitas; foco visível definido no CSS.
- `tests/ui-002-view-smoke.php` valida as páginas, legendas, estrutura, escape da região e a quantidade de tabelas por tela.
- **App PF — faltantes:** integração e operações reais nas dez páginas, filtros, exportação, acessibilidade e homologação visual no Docker. **App Empresarial — faltantes:** CRUD e consultas isoladas por organização nos onze módulos. **CMS — faltantes:** autenticação administrativa, RBAC, CSRF nas mutações, auditoria e operações nas oito prévias.
- Nenhuma migração, operação financeira, cobrança, publicação administrativa, integração WhatsApp ou alteração em produção.

## CMS-A11Y-045 — tabelas com rolagem acessível (28/10/2026)

- `components/empty-table.php` agora expõe a tabela dentro de uma região com `role="region"`, `tabindex="0"` e nome acessível baseado na legenda escapada, permitindo rolagem horizontal por teclado.
- `assets/css/responsive-fixes.css` adiciona contorno de foco visível e barra de rolagem compacta no celular.
- `tests/cms-001-preview-smoke.php` verifica a região nomeada em **Planos e assinaturas** e **Logs e auditoria**, além de rejeitar HTML injetado no nome acessível.
- CI do commit de código `35c5b6c`: [execução #40719453512](https://github.com/djalmamartins/realizze/actions/runs/40719453512) — aprovada. Não substitui auditoria manual com teclado/leitor de tela.
- **App PF:** dez páginas visuais; faltam dados autorizados, operações, filtros reais e homologação responsiva. **App PJ:** onze módulos visuais; faltam cadastros, financeiro, DRE, equipes e importação OFX/CSV com escopo por empresa. **CMS:** oito seções visuais; faltam autenticação administrativa, RBAC, CSRF nas mutações, auditoria persistente e operações seguras em todas as oito.
- Nenhum banco, cobrança, upload, WhatsApp ou produção foi alterado.


## CMS-UI-046 — inventários de páginas e usuários (28/10/2026)

- As prévias **Páginas** e **Usuários** agora compartilham `components/empty-table.php` com **Planos** e **Logs**: legendas visíveis, quatro colunas com cabeçalhos acessíveis, estado vazio e região de rolagem por teclado.
- Os formulários de prévia permanecem desabilitados. Nenhum registro de usuário, convite, papel, página ou versão é consultado; não há novas rotas, operações, uploads ou dados fictícios.
- `tests/cms-001-preview-smoke.php` verifica as quatro tabelas, colunas, legenda e região acessível. A validação de sintaxe e a homologação HTTP/visual devem ser realizadas no CI/Docker.
- **App PF:** dez páginas de interface, pendentes operações e filtros com dados autorizados. **App Empresarial:** onze módulos em prévia, pendentes serviços escopados por organização. **CMS:** oito seções existentes, pendentes autenticação administrativa, CSRF, RBAC, auditoria persistente e CRUD; prévia local continua bloqueada externamente.
- Sem migração, cobrança, WhatsApp ou alteração de produção.


## CMS-UI-047 — inventários de conteúdos e mídia (28/10/2026)

- As páginas **Conteúdos** e **Mídia** receberam inventários vazios com o componente `components/empty-table.php`, agora compartilhado por seis seções administrativas (Páginas, Conteúdos, Mídia, Usuários, Planos e Logs).
- Cada tabela possui legenda, colunas identificadas, região rolável por teclado e estado vazio explícito; os controles de edição, publicação e upload permanecem desabilitados. Não são consultados arquivos, artigos, identidades ou registros financeiros.
- `tests/cms-001-preview-smoke.php` cobre as seis tabelas, nomes acessíveis e colunas, além das verificações existentes de controles bloqueados, IDs e proteção da prévia.
- **App PF:** dez páginas de interface, pendentes dados autorizados, operações, filtros e homologação responsiva. **App Empresarial:** onze módulos em prévia, pendentes serviços e consultas escopadas por organização. **CMS:** oito seções visuais; pendentes autenticação administrativa, RBAC, CSRF nas mutações, auditoria persistente e operações reais.
- Não houve alteração de rotas, banco, produção, cobranças, uploads ou WhatsApp.


## APP/CMS-UI-048 — configurações responsivas e governança (28/10/2026)

- **App PF / Configurações:** quatro painéis (perfil, segurança, notificações, privacidade) com rótulos, estados não consultados, controles desabilitados e grade adaptável a telas pequenas. Nenhum valor pessoal ou preferência real é exibido ou alterado.
- **CMS / Configurações:** interface ampliada para identidade institucional, SEO/publicação, autenticação, RBAC, CSRF, auditoria e retenção; os estados são explicitamente pendentes, não diagnósticos de produção. Todos os controles administrativos seguem desabilitados.
- Testes de renderização adicionados a `tests/ui-002-view-smoke.php` e `tests/cms-001-preview-smoke.php`. A homologação em navegador, testes HTTP de bloqueio, inspeção MySQL e testes de teclado/leitor de tela continuam pendentes.
- **Inventário:** App PF 10 páginas de interface (faltam CRUD, filtros e dados autorizados); App Empresarial 11 módulos de prévia (faltam serviços e consultas escopadas por empresa); CMS 8 seções de prévia (faltam autenticação administrativa, autorização por papéis, CSRF nas mutações, auditoria persistente e operações reais). Sem WhatsApp, cobrança, migração ou produção.

## CMS-A11Y-049 — navegação por teclado e status móvel (28/10/2026)

- A navegação responsiva do CMS fecha automaticamente quando o foco sai da sidebar (Tab), além do Escape, clique fora e troca para desktop. O Escape continua devolvendo o foco ao botão; nenhuma operação administrativa é acionada.
- O cabeçalho exibe permanentemente **“Prévia local · Operações administrativas indisponíveis”**, inclusive em telas de até 720px; a mensagem não fica mais oculta em celulares.
- Novo teste de comportamento com Node sem dependências: `tests/cms-003-mobile-menu.js`, executado no CI. `tests/cms-001-preview-smoke.php` verifica marcação e CSS da mensagem.
- **Pendências mantidas:** App PF — operações, filtros, relatórios reais e homologação visual das 10 páginas; App PJ — 11 módulos ainda sem CRUD/queries escopadas; CMS — 8 prévias sem autenticação administrativa, RBAC, CSRF em mutações, auditoria persistente ou CRUD. Testes HTTP, leitor de tela e MySQL de homologação continuam pendentes. Sem WhatsApp, cobrança, migração ou produção.

## APP-A11Y-050 — navegação móvel sem armadilha de teclado (28/10/2026)

- A sidebar do App PF/PJ passa a seguir o padrão de navegação expansível, não modal: **Tab** pode sair da navegação, e o menu fecha quando o foco deixa a sidebar. O bloqueio `inert` é restaurado para impedir foco em links fora da tela.
- **Escape**, botão de fechar, backdrop e mudança para desktop continuam fechando o menu; Escape restaura o foco ao botão que abriu a navegação.
- Novo teste comportamental Node `tests/ui-003-mobile-menu.js` cobre foco, estado `aria-expanded`, `inert`, Tab, Escape, backdrop e mudança de breakpoint. O GitHub Actions executa o teste.
- **App PF (10 páginas):** pendem integração autorizada e operações reais em Visão geral, Receber, Pagar, Movimentações, Contas e Cartões, Relatórios, Planejamento, Metas, Categorias e Configurações; faltam homologação responsiva e leitor de tela.
- **App Empresarial (11 módulos):** clientes, fornecedores, equipes/permissões, receber, pagar, bancos/cartões, fluxo de caixa, centros de custos, DRE, OFX/CSV e obrigações MEI/PJ continuam somente em prévia, sem persistência.
- **CMS (8 seções):** Visão geral, Páginas, Conteúdos, Mídia, Usuários, Planos e assinaturas, Logs e Configurações continuam sem CRUD; autenticação administrativa, RBAC, CSRF em mutações e auditoria persistente seguem obrigatórios antes da abertura.
- Não foram criadas rotas, queries financeiras, operações CMS, uploads, WhatsApp, cobranças, migrations ou alterações em produção. Testes visuais reais e auditoria MySQL de homologação ainda pendentes.

## APP-A11Y-051 — transições responsivas de foco (28/10/2026)

- Em `assets/js/dashboard.js`, a transição **móvel → desktop** fecha o menu sem direcionar foco ao botão móvel oculto; a sidebar visível permanece acessível.
- Na transição **desktop → móvel**, o foco dentro da sidebar é transferido para o botão visível antes de ativar `inert` no menu fechado. Assim, links fora da tela não permanecem na ordem de Tab.
- `tests/ui-003-mobile-menu.js` simula os dois sentidos da mudança de viewport e valida foco, `aria-expanded` e `inert`. Ainda requer homologação visual e teclado em navegador real.
- Inventário inalterado: **App PF** tem dez páginas visuais, sem CRUD/dados reais; **App PJ** tem onze módulos em prévia, sem persistência/isolamento de consultas reais; **CMS** tem oito prévias, ainda sem autenticação administrativa, RBAC, CSRF em mutações, auditoria e CRUD.
- Nenhuma alteração em banco, produção, cobrança, WhatsApp ou rotas administrativas.

## APP-UI-052 — busca PJ por área e colunas (28/10/2026)

- O filtro local dos onze módulos empresariais agora pesquisa **nome do módulo, descrição, área de gestão e cabeçalhos das tabelas**, com normalização de acentos e letras maiúsculas. A ajuda textual foi atualizada.
- O filtro continua apenas visual: cartões, grupos e atalhos são ocultados em conjunto, com contagem anunciada, estado vazio e limpeza por Escape ou botão. Sem consulta a empresas, dados financeiros ou mudança de contexto.
- Novo teste comportamental sem dependências em `tests/ui-004-enterprise-search.js`, integrado ao workflow, cobrindo buscas por grupo/coluna, links ocultos, estado vazio e foco ao limpar.
- **Pendências App PF (10 páginas):** integração autorizada, CRUD, filtros de dados, relatórios reais e homologação visual. **App PJ (11 módulos):** serviços e operações escopadas por organização, equipes/permissões, financeiro, DRE e OFX/CSV. **CMS (8 seções):** autenticação administrativa, RBAC, CSRF em mutações, auditoria persistente e operações. Inspeção do MySQL de homologação e testes HTTP/visuais continuam necessários.
- Sem WhatsApp, cobrança, migração, alterações de produção ou abertura do CMS.


## APP-UI/CMS-A11Y-053 — pesquisa PJ e foco responsivo (28/10/2026)

- A pesquisa dos onze módulos empresariais aceita múltiplas palavras em qualquer ordem e em campos diferentes (título, descrição, grupo e colunas), normalizando acentos e espaços. Todos os termos devem corresponder ao mesmo módulo. O filtro permanece estritamente local, sem acesso a dados ou troca de contexto PF/PJ.
- Corrigido o foco do CMS na transição desktop → celular: quando um link da navegação deixa de ficar visível, o foco é transferido ao botão móvel. Na transição inversa, o foco permanece no link visível, sem direcionamento ao botão oculto.
- Testes de regressão ampliados em `tests/ui-004-enterprise-search.js` e `tests/cms-003-mobile-menu.js`. Testes com navegador real, teclado e leitor de tela continuam necessários.
- **App PF:** dez páginas existentes ainda dependem de dados autorizados, operações financeiras, filtros persistentes, relatórios e homologação visual. **App PJ:** onze módulos em prévia ainda dependem de serviços escopados, CRUD e importação OFX/CSV segura. **CMS:** oito seções em prévia ainda dependem de autenticação administrativa, autorização por papéis, CSRF, auditoria persistente e operações reais.
- Sem alterações em banco, migrations, produção, cobranças ou WhatsApp.

## APP-A11Y-054 — filtro PJ com aprimoramento progressivo (28/10/2026)

- A pesquisa local de módulos empresariais só aparece quando o JavaScript inicializa; sem JavaScript, os onze módulos e seus atalhos continuam visíveis, sem controles de busca inoperantes.
- O botão **Limpar filtro** fica desabilitado quando o campo está vazio. Quando não há correspondências, a navegação de atalhos é ocultada junto dos cartões, e retorna ao limpar a pesquisa. O aviso de estado vazio continua acessível.
- CSS respeita o atributo `hidden` mesmo nas áreas com `display: grid/flex`; regressões de interface e comportamento são verificadas em `tests/ui-002-view-smoke.php` e `tests/ui-004-enterprise-search.js`.
- **App PF:** 10 páginas visuais, pendentes CRUD, dados autorizados, filtros reais, relatórios e homologação visual. **App PJ:** 11 módulos de prévia, pendentes serviços, isolamento de consultas, operações, DRE e OFX/CSV. **CMS:** 8 prévias, pendentes autenticação administrativa, RBAC, CSRF, auditoria persistente e CRUD.
- Não houve consultas financeiras, migrações, cobrança, WhatsApp, exposição do CMS ou alteração de produção.

## APP-A11Y/SEC-055 — prévia do assistente sem lançamentos inventados (28/10/2026)

- A tela **Assistente Financeiro** mantém a composição responsiva e o estado desabilitado, mas deixa de exibir uma conversa simulada com valor monetário, categoria e data. Agora apresenta um estado vazio inequívoco: **Nenhuma conversa iniciada**. Nenhum dado é carregado, enviado ou persistido.
- Retirada da prévia a ação de conexão com canal externo. Integração de mensagens, processamento automatizado, cobranças e WhatsApp permanecem fora do escopo desta fase.
- O destino do link **Ir para o conteúdo principal** recebe `tabindex="-1"`, permitindo foco programático sem adicionar o `main` à sequência normal de Tab.
- `tests/ui-002-view-smoke.php` verifica a ausência de valores financeiros fictícios, mensagens simuladas e controles ativos no assistente, além do destino focável do skip link.
- **Inventário corrigido:** App PF tem **11 seções visuais**, contando **Assistente Financeiro** (Visão geral, Receber, Pagar, Movimentações, Contas e Cartões, Relatórios, Planejamento, Metas, Categorias, Assistente Financeiro e Configurações). As onze ainda dependem de integrações seguras, dados autorizados e homologação visual; o assistente fica somente como prévia, sem integração nesta fase. App PJ tem **11 módulos em prévia** sem operações escopadas. CMS tem **8 seções em prévia** sem autenticação administrativa completa, RBAC, CSRF nas mutações, auditoria persistente e CRUD.
- Ainda faltam auditoria do MySQL de homologação, testes HTTP de sessão/CSRF/isolamento e homologação com teclado, leitor de tela e Docker. Nenhuma migração, upload, operação financeira, cobrança, integração externa ou alteração de produção foi feita.

## APP-A11Y/ENT-SEC-056 — navegação sem JavaScript e regressão de isolamento (29/10/2026)

- No App PF/PJ, o menu lateral em telas até 850px deixa de ficar inacessível quando o navegador não executa JavaScript: a folha `<noscript>` o exibe no fluxo normal, preservando links e saída via POST/CSRF. O aviso informa que os filtros interativos não funcionam nessa condição.
- A descrição do Assistente Financeiro no título da página agora declara que se trata de prévia sem envio nem registro, coerente com seus controles desabilitados. Não foi implementado canal de mensagens.
- `tests/ui-002-view-smoke.php` verifica a presença da navegação alternativa, do logout e do texto de prévia. `tests/enterprise-001-context.php` acrescenta casos SQLite em memória: revogação de vínculo, papel de outra empresa, empresa inativa, sessão PJ forjada e desligamento da feature flag. Os casos exigem nova seleção autorizada depois da revogação.
- **Pendências App PF (11 seções, incluindo assistente):** Visão geral, Receber, Pagar, Movimentações, Contas e Cartões, Relatórios, Planejamento, Metas, Categorias e Configurações ainda não possuem operações financeiras reais; Assistente continua desabilitado. Testes visuais reais e auditoria com leitor de tela pendentes.
- **Pendências App PJ (11 módulos):** Clientes, Fornecedores, Equipes/permissões, Receber, Pagar, Bancos/cartões, Fluxo de caixa, Centros de custos, DRE, Conciliação OFX/CSV e Obrigações MEI/PJ ainda sem persistência ou consultas financeiras autorizadas.
- **Pendências CMS (8 seções):** Visão geral, Páginas, Conteúdos, Mídia, Usuários, Planos/assinaturas, Logs/auditoria e Configurações continuam prévias sem CRUD; exigem autenticação administrativa, RBAC, CSRF, auditoria persistente e testes HTTP antes de exposição.
- Nenhuma migração, acesso a produção, cobrança, upload ou integração WhatsApp. Inspeção do MySQL de homologação e testes HTTP de isolamento continuam pendentes.

## SECURITY-001 — validação CSRF resiliente a sessão inválida (29/10/2026)

- Auditada a implementação real de `Moves\\Services\\Csrf`, usada pelo App no logout e na seleção PF/PJ por POST. Antes, um valor não textual em `$_SESSION['csrf_token']` podia causar `TypeError` durante `hash_equals`, interrompendo a requisição em vez de negar o acesso.
- `Csrf::validate()` agora **nega por padrão** se a sessão, o token recebido ou o token armazenado forem inválidos; tokens armazenados devem seguir o formato criptográfico de 32 bytes aleatórios em hexadecimal. `Csrf::token()` regenera valores corrompidos/fracos e invalida o anterior.
- Novo teste isolado `tests/security-001-csrf-session.php`, sem acesso ao MySQL, verifica token válido, entrada em array, token de sessão em array, token fraco, ausência de token e sessão encerrada. Workflow de PR atualizado para executar a regressão.
- **App PF (11 seções):** Visão geral, Receber, Pagar, Movimentações, Contas e Cartões, Relatórios, Planejamento, Metas, Categorias e Configurações aguardam dados/CRUD autorizados; Assistente Financeiro permanece apenas visual e desabilitado.
- **App PJ (11 módulos):** Clientes, Fornecedores, Equipes/permissões, Receber, Pagar, Bancos/cartões, Fluxo de caixa, Centros de custos, DRE, OFX/CSV e Obrigações MEI/PJ aguardam serviços escopados e operações seguras.
- **CMS (8 seções):** Visão geral, Páginas, Conteúdos, Mídia, Usuários, Planos/assinaturas, Logs/auditoria e Configurações aguardam autenticação administrativa, RBAC, CSRF nas mutações, auditoria persistente e CRUD. A prévia continua local e bloqueada ao público.
- Ainda pendentes: inspeção do MySQL de homologação, testes HTTP reais de sessão/CSRF/isolamento, homologação visual no Docker do Mac e testes com leitor de tela. **Sem migração, cobrança, WhatsApp, alteração de produção ou abertura pública do CMS.**

## CMS-UI-057 / APP-A11Y-057 — fluxos administrativos e tabelas PJ (29/10/2026)

- Novo `container/cms/realizze/v1/components/workflow-preview.php`: componente reutilizável, acessível e somente visual de etapas **pendentes**, sem estados fictícios de conclusão.
- Páginas, Conteúdos e Mídia apresentam fluxos distintos de rascunho/revisão/autorização/publicação e recebimento/validação/controle de acesso, com layout adaptável a celular e tablet. Nenhum envio, publicação ou persistência foi habilitado.
- As onze tabelas de módulos empresariais receberam `caption` visível e escapado, mantendo `scope="col"` e região rolável por teclado.
- Smoke tests do CMS verificam etapas e escape HTML; smoke tests do App verificam onze legendas e metadados PJ maliciosos.
- **App PF — faltantes:** 11 seções visuais (incluindo Assistente Financeiro), dez páginas financeiras ainda sem integração/CRUD/filtros reais; assistente permanece fora do escopo operacional.
- **App PJ — faltantes:** onze módulos em prévia, sem consultas ou CRUD escopados; auditoria do MySQL de homologação e testes HTTP PF/PJ obrigatórios.
- **CMS — faltantes:** oito seções em prévia; autenticação administrativa, autorização por papéis, CSRF para mutações, auditoria persistente e CRUD permanecem pendentes. A prévia local continua bloqueada por flags e IP.
- **Homologação pendente:** teste visual no Docker do Mac, testes HTTP de sessão/CSRF/isolamento e inspeção MySQL. Sem WhatsApp, cobranças, migrações ou alterações de produção.

## CMS-UI/A11Y-058 — fluxos de acesso e planos, menu progressivo (29/10/2026)

- **Usuários e permissões** e **Planos e assinaturas** agora reutilizam `components/workflow-preview.php`, com quatro etapas pendentes específicas para convites/RBAC/auditoria e rascunho/revisão/autorização/publicação futura de ofertas. Nenhum convite, plano, assinatura ou cobrança foi criado.
- **Navegação móvel CMS sem JavaScript:** o botão de menu só é mostrado após a inicialização do script (`.cms-js`); sem JS, a navegação permanece visível e não há botão inoperante.
- `tests/cms-001-preview-smoke.php` valida os cinco fluxos e a regra progressiva do menu. Testes HTTP, teclado/leitor de tela e homologação visual no Docker ainda são necessários.
- **App PF (11 seções):** dez seções financeiras sem CRUD, filtros/dados autorizados e relatórios reais; Assistente Financeiro apenas visual e desabilitado. **App PJ (11 módulos):** serviços e consultas isoladas por organização, equipes, financeiro, DRE e OFX/CSV pendentes. **CMS (8 seções):** interfaces existentes; faltam autenticação administrativa, RBAC, CSRF nas mutações, auditoria persistente e CRUD seguro.
- CMS continua restrito à prévia local por flags/IP. Sem WhatsApp, cobranças, migrações destrutivas ou alterações em produção.

## CMS-UI-059 — fluxos seguros de logs e configurações (29/10/2026)

- **Logs e auditoria:** adicionadas quatro etapas visuais usando `components/workflow-preview.php`: coleta segura, normalização, integridade/retenção e consulta autorizada. Nenhum evento real é lido, filtrado ou exportado.
- **Configurações:** quatro etapas visuais para solicitação, validação, autorização e registro/aplicação futura. Nenhuma preferência é consultada ou alterada.
- O mesmo componente de fluxo agora é reutilizado em **sete seções** do CMS (Páginas, Conteúdos, Mídia, Usuários, Planos, Logs e Configurações); Visão geral mantém sua prévia. Os passos permanecem explicitamente pendentes.
- `tests/cms-001-preview-smoke.php` valida o fluxo único e as quatro etapas pendentes em cada uma das sete seções, além dos testes existentes de controles desabilitados, HTML escapado e bloqueio estático.
- **App PF — faltantes:** integração segura de Visão geral, Receber, Pagar, Movimentações, Contas e Cartões, Relatórios, Planejamento, Metas, Categorias e Configurações. Assistente Financeiro continua somente visual e desabilitado.
- **App PJ — faltantes:** operações autorizadas e isoladas por empresa em Clientes, Fornecedores, Equipes, Receber, Pagar, Bancos/Cartões, Fluxo de caixa, Centros de custos, DRE, OFX/CSV e Obrigações MEI/PJ.
- **CMS — faltantes:** integração administrativa segura das oito seções, autenticação, RBAC, CSRF nas mutações, auditoria persistente e CRUD. Nenhuma rota pública ou operação administrativa foi ativada.
- **Homologação pendente:** CI do novo commit, teste visual real em Docker desktop/mobile, inspeção MySQL de homologação, testes HTTP de bloqueio do CMS, sessão, CSRF e isolamento PF/PJ. Sem WhatsApp, cobranças, migrations destrutivas ou produção.

## APP-A11Y-060 — foco nas transições celular/desktop (29/10/2026)

- Corrigida a perda de foco ao ampliar a tela: quando o foco estava no botão **Fechar menu** ou **Abrir menu** (ambos ocultos no desktop), o App PF/PJ agora o transfere para o primeiro link visível da navegação. Se o foco já estiver em um link que permanece visível, ele é preservado.
- O teste comportamental `tests/ui-003-mobile-menu.js` cobre os dois controles móveis, a volta ao celular, `inert` e a navegação sem armadilha de teclado. Nenhuma API, operação financeira, contexto PF/PJ ou rota foi alterada.
- **App PF — faltantes:** dez seções financeiras (Visão geral, Receber, Pagar, Movimentações, Contas e Cartões, Relatórios, Planejamento, Metas, Categorias e Configurações) precisam de dados autorizados, operações e homologação; Assistente Financeiro segue desabilitado. **App Empresarial — faltantes:** onze módulos (Clientes, Fornecedores, Equipes, Receber, Pagar, Bancos e cartões, Fluxo de caixa, Centros de custos, DRE, OFX/CSV e Obrigações MEI/PJ) precisam de serviços escopados e permissões. **CMS — faltantes:** oito seções (Visão geral, Páginas, Conteúdos, Mídia, Usuários, Planos, Logs e Configurações) ainda precisam de autenticação administrativa, RBAC, CSRF, auditoria persistente e operações reais.
- **Homologação pendente:** navegador real com teclado/leitor de tela no Docker do Mac, MySQL de homologação, testes HTTP de sessão/CSRF/isolamento e bloqueio CMS. Sem WhatsApp, cobranças, migrações ou alterações de produção.


## CMS-A11Y-061 — foco ao retornar ao desktop (29/10/2026)

- Corrigida a transição de celular para desktop na navegação responsiva do CMS: quando o foco estava no botão móvel (que fica oculto no desktop), ele agora passa para o primeiro link visível da navegação. O foco em links que continuam visíveis é preservado.
- `tests/cms-003-mobile-menu.js` cobre o retorno ao desktop, a volta ao celular, Escape, clique fora e saída da navegação por Tab. A alteração é exclusivamente de apresentação: não abre rotas administrativas, não consulta banco nem modifica permissões.
- **App PF — faltantes:** Visão geral, Receber, Pagar, Movimentações, Contas e Cartões, Relatórios, Planejamento, Metas, Categorias e Configurações precisam de dados autorizados, operações e homologação; Assistente Financeiro continua desabilitado. **App Empresarial — faltantes:** Clientes, Fornecedores, Equipes, Receber, Pagar, Bancos e cartões, Fluxo de caixa, Centros de custos, DRE, OFX/CSV e Obrigações MEI/PJ exigem serviços com isolamento por organização. **CMS — faltantes:** Visão geral, Páginas, Conteúdos, Mídia, Usuários, Planos e assinaturas, Logs e auditoria e Configurações ainda precisam de autenticação administrativa, RBAC, CSRF nas mutações, auditoria persistente e operações seguras.
- **Homologação pendente:** teste visual em navegador real/Docker do Mac, MySQL de homologação, testes HTTP de autenticação/CSRF/isolamento PF/PJ e bloqueio CMS. Nenhum WhatsApp, cobrança, migração ou produção foi alterado.


## APP-A11Y-062 — foco após filtrar módulos empresariais (29/10/2026)

- Corrigido o foco de teclado no filtro dos onze módulos empresariais: quando uma pesquisa oculta o módulo ou atalho atualmente focado, o foco volta ao campo de busca visível. Atalhos e módulos que continuam visíveis preservam o foco; a busca continua exclusivamente local, sem dados financeiros ou troca de empresa.
- `tests/ui-004-enterprise-search.js` cobre recuperação de foco em atalho oculto e módulo oculto, além da preservação de foco em atalho visível e das buscas existentes.
- **App PF — faltantes:** Visão geral, Receber, Pagar, Movimentações, Contas e Cartões, Relatórios, Planejamento, Metas, Categorias e Configurações ainda exigem dados autorizados, CRUD, filtros e relatórios reais; Assistente Financeiro permanece apenas visual e desabilitado.
- **App Empresarial — faltantes:** Clientes, Fornecedores, Equipes/permissões, Contas a receber, Contas a pagar, Bancos/cartões, Fluxo de caixa, Centros de custos, DRE, Conciliação OFX/CSV e Obrigações MEI/PJ exigem operações reais com autorização e isolamento por organização.
- **CMS — faltantes:** Visão geral, Páginas, Conteúdos, Mídia, Usuários, Planos/assinaturas, Logs/auditoria e Configurações exigem autenticação administrativa, RBAC, CSRF nas mutações, auditoria persistente e operações seguras. A prévia permanece bloqueada fora do ambiente local autorizado.
- **Homologação pendente:** teste em navegador real/Docker do Mac com teclado/leitor de tela, auditoria do MySQL de homologação, testes HTTP de sessão, CSRF, isolamento PF/PJ e bloqueio do CMS. Nenhuma migração, cobrança, WhatsApp ou produção foi alterado.


## APP-A11Y-063 — foco preservado antes de ocultar resultados (29/10/2026)

- Corrigida uma condição de corrida de acessibilidade na pesquisa do App Empresarial: o navegador pode limpar `document.activeElement` ao ocultar o módulo ou atalho focado. A busca agora captura o foco **antes** de aplicar `hidden`, e o devolve ao campo de pesquisa somente se o elemento ativo ficou invisível. Resultados visíveis preservam o foco.
- `tests/ui-004-enterprise-search.js` simula o navegador apagando `activeElement` ao ocultar módulo e atalho; verifica a recuperação em ambos os casos. Não há acesso a dados financeiros, mudanças de contexto PF/PJ, novos formulários nem novas rotas.
- **App PF (11 seções visuais):** Visão geral, Receber, Pagar, Movimentações, Contas e Cartões, Relatórios, Planejamento, Metas, Categorias e Configurações aguardam dados autorizados, CRUD, filtros e relatórios; Assistente Financeiro continua desabilitado.
- **App Empresarial (11 módulos):** Clientes, Fornecedores, Equipes/permissões, Contas a receber, Contas a pagar, Bancos/cartões, Fluxo de caixa, Centros de custos, DRE, OFX/CSV e Obrigações MEI/PJ aguardam serviços com autorização por operação e isolamento de organização.
- **CMS (8 seções):** Visão geral, Páginas, Conteúdos, Mídia, Usuários, Planos/assinaturas, Logs/auditoria e Configurações aguardam autenticação administrativa, RBAC, CSRF nas mutações, auditoria persistente e CRUD. Prévia local continua bloqueada fora das flags e IPs permitidos.
- **Homologação ainda necessária:** testes visuais/teclado/leitor de tela no Docker do Mac, MySQL de homologação e testes HTTP de sessão/CSRF/isolamento e bloqueio CMS. Nenhum WhatsApp, cobrança, migração destrutiva ou alteração em produção.


## APP-A11Y-064 / CMS-UI-064 — perfis Unicode e fluxo da visão geral (29/10/2026)

- **App PF/PJ:** criado `components/profile-initial.php`, compartilhado por sidebar e cabeçalho, para extrair o primeiro grafema UTF-8 completo do nome (inclusive nomes acentuados e emoji), com fallback neutro para dados ausentes ou inválidos. A inicial é escapada no HTML e marcada como decorativa para evitar leitura duplicada do nome por tecnologias assistivas.
- `tests/ui-002-view-smoke.php` verifica iniciais acentuadas, grafema composto, ausência de nome, UTF-8 inválido e escape da sidebar. Não há consulta adicional, mudança de sessão ou alteração de dados.
- **CMS:** a Visão geral passou a reutilizar `components/workflow-preview.php` para mostrar as quatro pré-condições de abertura: autenticação administrativa, autorização por papéis, CSRF e auditoria persistente. Agora **oito de oito** seções apresentam fluxos de prévia, todos pendentes, sem botões ou formulários administrativos ativos. `tests/cms-001-preview-smoke.php` cobre a oitava seção.
- **App PF — faltam operações nas seções:** Visão geral, Receber, Pagar, Movimentações, Contas e Cartões, Relatórios, Planejamento, Metas, Categorias e Configurações; Assistente Financeiro permanece visual e desabilitado. Faltam CRUD, dados autorizados, filtros reais, relatórios e homologação visual.
- **App Empresarial — faltam operações nos módulos:** Clientes, Fornecedores, Equipes/permissões, Contas a receber, Contas a pagar, Bancos/cartões, Fluxo de caixa, Centros de custos, DRE, Conciliação OFX/CSV e Obrigações MEI/PJ; dependem de autorização e isolamento por organização.
- **CMS — faltam operações nas oito seções:** Visão geral, Páginas, Conteúdos, Mídia, Usuários, Planos/assinaturas, Logs/auditoria e Configurações. O gate local continua obrigatório até autenticação administrativa, RBAC, CSRF nas mutações e auditoria persistente serem implementados e testados.
- **Homologação ainda pendente:** testes visuais reais no Docker do Mac, teclado/leitor de tela, inspeção MySQL de homologação e testes HTTP de sessão, CSRF, isolamento PF/PJ e bloqueio do CMS. Nenhum WhatsApp, cobrança, migração, upload ou alteração em produção foi realizado.

## APP/CMS-A11Y-065 — descoberta da rolagem horizontal (29/10/2026)

- Tabelas vazias do App PF (Receber, Pagar, Movimentações, Contas/Cartões, Relatórios, Planejamento, Metas e Categorias), dos 11 módulos PJ e das seis seções CMS com inventário compartilham orientação textual de rolagem horizontal.
- A orientação aparece em telas estreitas ou ao focar a região de tabela com teclado; mantém as legendas, o foco visível, o estado vazio e a ausência de dados fictícios.
- Testes de renderização adicionados a `tests/ui-002-view-smoke.php` e `tests/cms-001-preview-smoke.php`. Teste visual e com leitor de tela no Docker do Mac ainda pendente.
- **App PF:** 10 áreas visuais (mais Assistente em prévia desabilitada); operações reais, filtros e relatórios ainda faltam. **App PJ:** 11 módulos visuais; faltam CRUD, consultas isoladas e importação OFX/CSV. **CMS:** 8 seções visuais; faltam autenticação administrativa, autorização por papéis, CSRF nas mutações, auditoria persistente e CRUD. A prévia permanece bloqueada fora do ambiente local permitido.
- Sem migrações, cobranças, WhatsApp, operações financeiras ou alterações em produção.


## APP-A11Y-066 — rolagem do menu móvel e nomes longos (29/10/2026)

- Corrigido o bloqueio de gestos de toque no menu lateral aberto: o `touch-action:none` aplicado ao `body` podia impedir a rolagem de uma sidebar alta em celulares. O fundo permanece bloqueado por `overflow:hidden`, enquanto a sidebar aceita `pan-y`.
- A sidebar usa `100dvh` com fallback `100vh`, respeita a área segura inferior e mantém a rolagem vertical. Nomes de perfil e títulos longos passam a quebrar linha sem ampliar a largura da interface.
- Regressão estática adicionada a `tests/ui-002-view-smoke.php`; validação em navegador móvel real e leitor de tela continua pendente.
- **App PF — pendentes:** integração segura e CRUD de Visão geral, Receber, Pagar, Movimentações, Contas e Cartões, Relatórios, Planejamento, Metas, Categorias e Configurações; Assistente Financeiro desabilitado. **App Empresarial — pendentes:** clientes, fornecedores, equipes/permissões, contas a receber/pagar, bancos/cartões, fluxo de caixa, centros de custos, DRE, conciliação OFX/CSV e obrigações MEI/PJ. **CMS — pendentes:** autenticação administrativa, autorização por papéis, CSRF nas mutações, auditoria persistente e operações de Visão geral, Páginas, Conteúdos, Mídia, Usuários, Planos/assinaturas, Logs e Configurações.
- Sem alterações em rotas, banco, contexto PF/PJ, operações financeiras, cobranças, WhatsApp ou produção. CMS continua com prévia local bloqueada.

## APP-A11Y-067 — navegação progressiva quando o JavaScript não carrega (29/10/2026)

- No App PF/PJ, o menu lateral agora fica **visível e navegável no celular** enquanto a classe `app-js` não estiver presente; não há botão de menu sem ação. Isso cobre indisponibilidade do arquivo JavaScript, além do caso já coberto por `<noscript>`.
- `assets/js/dashboard.js` só ativa o comportamento off-canvas depois de encontrar shell, sidebar, botão de abrir, botão de fechar e backdrop. Com JavaScript ativo, o comportamento anterior (inert, Escape, foco, rolagem própria e responsividade) permanece.
- Regressões adicionadas a `tests/ui-003-mobile-menu.js` e `tests/ui-002-view-smoke.php`. A homologação visual e de teclado em navegador móvel real continua pendente.
- **App PF pendente:** operações e dados autorizados nas dez páginas financeiras; Assistente continua desabilitado. **App PJ pendente:** cadastros, equipes/permissões, receber/pagar, bancos/cartões, fluxo, custos, DRE, OFX/CSV e obrigações com isolamento validado. **CMS pendente:** oito seções visuais exigem autenticação administrativa, CSRF, RBAC, auditoria e CRUD; prévia local continua bloqueada publicamente.
- Nenhuma mudança em rotas, banco, migrações, produção, WhatsApp, cobranças ou controles de acesso ao CMS.


## APP-A11Y-068 — menu parcial e fallback seguro (29/10/2026)

- A navegação móvel PF/PJ só ativa a classe `app-js`, os listeners do menu e o atributo `inert` quando os cinco elementos essenciais existem. Uma falha parcial na renderização não deixa os links visíveis porém inacessíveis ao teclado.
- `tests/ui-003-mobile-menu.js` inclui regressões para a ausência individual de shell, sidebar, botão de abertura, botão de fechamento e backdrop. Sem integração financeira, alteração de sessão ou novas rotas.
- **App PF pendente:** integração autorizada das dez páginas financeiras, CRUD, filtros reais, relatórios e homologação visual mobile. **App PJ pendente:** onze módulos com serviços, escopo por organização e testes de isolamento. **CMS pendente:** oito seções com autenticação administrativa, RBAC, CSRF nas mutações, auditoria persistente e CRUD; prévia local permanece bloqueada externamente.
- Ainda faltam inspeção MySQL de homologação, testes HTTP de autenticação/CSRF/isolamento e validação em navegador real. Nenhuma migração, cobrança, WhatsApp ou produção foi alterada.


## CMS-A11Y-069 — inicialização progressiva da navegação (29/10/2026)

- O JavaScript da navegação CMS agora ativa a classe `cms-js` **somente após** encontrar sidebar, botão, navegação, primeiro link e uma API `matchMedia` funcional. Se o navegador não fornecer esses recursos, a navegação CSS permanece visível e acessível, sem botão incompleto.
- Adicionado suporte a `MediaQueryList.addListener` para mudanças de largura em navegadores antigos, preservando foco e fechamento do menu. Testes Node cobrem sete cenários de inicialização incompleta e a API legada, além dos cenários de teclado existentes.
- **Inventário inalterado:** App PF — dez páginas com layout de prévia, ainda sem CRUD, filtros reais, relatórios operacionais e homologação móvel; App Empresarial — onze módulos sem serviços financeiros, permissões operacionais e consultas isoladas; CMS — oito páginas visuais sem autenticação administrativa, RBAC, CSRF nas mutações, auditoria persistente e CRUD.
- Sem alterações de rotas, banco, migrations, produção, cobranças, upload ou WhatsApp. O CMS continua limitado à prévia local e o PR permanece draft.
