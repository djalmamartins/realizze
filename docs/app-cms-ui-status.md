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
