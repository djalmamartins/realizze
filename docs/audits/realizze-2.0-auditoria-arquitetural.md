# Realizze 2.0 — auditoria arquitetural e sincronização Git

**Data:** 2 de novembro de 2026

**Projeto auditado:** `/Volumes/MAC HD/Docker/xampp/www/realizze`

**Branch local:** `feat/realizze-integracao-ui-20261101`

**Referência remota:** `origin/feat/realizze-integracao-ui-20261101`
**Homologação:** não acessada nem alterada.

## 1. Resumo executivo

O Mac contém a versão mais abrangente da branch de integração. Após `git fetch --all --prune --tags`, o commit local `b48c54e` estava **24 commits à frente e 0 atrás** da branch remota correspondente. As branches remotas históricas informadas também são ancestrais do estado local; não foi encontrada funcionalidade exclusiva nelas que exigisse merge ou cherry-pick.

A arquitetura por ambientes continua reconhecível e majoritariamente correta. `web`, `app`, `cms`, `erp` e `send` estão isolados e cada raiz de versão contém somente `default.php` e `routes.php`. Os principais desvios são operacionais: ausência de um executor/ledger de migrations, numeração duplicada, banco local abaixo do esquema esperado e o App renderizando seu shell principal em `pages/dashboard.php` em vez de usar `default.php` como layout efetivo.

O código possui boa cobertura automatizada para autenticação, autorização, isolamento financeiro, CMS, helpdesk, perfil, recorrências e uploads. Foram executados 44 testes PHP aplicáveis: **41 passaram e 3 falharam por migrations ausentes no banco local**. Os 3 testes JavaScript passaram. O teste SMTP opt-in foi intencionalmente omitido para não enviar e-mail real.

## 2. Estado inicial e proteção

### Git

| Item | Mac | GitHub | Conclusão |
|---|---|---|---|
| Branch ativa | `feat/realizze-integracao-ui-20261101` | branch homônima em `4989061` | Mac à frente |
| Commit inicial do Mac | `b48c54e` | — | preservado |
| Divergência | 24 à frente | 0 exclusivo no remoto | push futuro pode ser fast-forward |
| Alterações rastreadas sem commit | nenhuma | n/a | árvore rastreada limpa |
| Não rastreado | `docs/relatorio-ajustes-app-20261102.md` | ausente | relevante e preservado |

Foi criada a referência local `backup/auditoria-pre-sync-20261102` em `b48c54e`.

Backup externo verificável:

- `/Volumes/MAC HD/Docker/xampp/backups/realizze-auditoria-20261102/realizze-history.bundle`
- SHA-256: `4579987cf5cc0804aa9a30e259b47294a4ff02b7a1b7fd84fa5c3fa65f327db6`
- Relatório local copiado com SHA-256 `7f8a72b15774ff7399c9e0e32e4769602074c1e0c28c1c1e8b7f626825c358da`.

Nenhum `.env`, banco, log, upload ou credencial foi incluído no backup destinado ao Git.

## 3. Matriz Mac × GitHub

| Componente | Mac | GitHub | Diferença | Risco de perda | Recomendação |
|---|---|---|---|---|---|
| Dashboard e navegação App | consolidado até `6bfd7ec` | versão anterior | CSS, JS, cabeçalho, loading e navegação | alto se remoto sobrescrever Mac | publicar a branch local por fast-forward |
| Gráfico do dashboard | correção `c5f86f4` | ausente | largura e espaçamento corrigidos | médio | preservar commit local |
| Recorrências | fluxo, processamento diário e testes | cadastro básico até `4989061` | 3 commits locais adicionais de processamento | alto | manter migrations 010/011 e worker |
| Cadastro e confirmação de e-mail | fluxo integrado e reenvio | parcial | páginas, serviços e testes adicionais | alto | publicar conjunto completo |
| Fila de e-mail | worker, migration, serviço e teste | ausente na branch de integração | CORE-002 incorporado localmente | alto | preservar commits `c56ebbe` e `b48c54e` |
| Site/Central de Suporte | integrado pela branch Claude | existe em branch remota separada | já incorporado localmente | baixo | não repetir merge |
| Favicons PF/PJ | presentes e publicados | incompletos | assets e seleção por contexto | baixo | preservar |
| Documentação de ajustes | arquivo não rastreado | ausente | evidência de validação anterior | médio | incluir no commit documental |

O diff local contra a branch remota envolve 42 arquivos, aproximadamente 1.007 inserções e 163 remoções. Não há commits exclusivos do GitHub na branch de destino.

## 4. Alterações consolidadas no Mac

Os 24 commits locais agregam:

- Central de Suporte pública e ajustes do CMS;
- cadastro PF e confirmação/reenvio de e-mail;
- favicons e identidade PF/PJ;
- correções de dashboard, gráfico, cabeçalhos e navegação AJAX;
- recorrências nos formulários, processamento de vencimentos e teste dedicado;
- fila de e-mail CORE-002 com worker e documentação.

Não foi necessária integração remota adicional. Esta auditoria não executou migrations nem modificou banco de dados.

## 5. Conformidade do contrato arquitetural

| Regra | Situação | Evidência |
|---|---|---|
| Ambientes `web/app/cms/erp/send` isolados | conforme | `container/*/realizze/v1/` |
| Apenas `default.php` e `routes.php` na raiz | conforme | verificação automática das cinco raízes |
| Sem `theme.php`/`themes.php` | conforme | inexistentes nas raízes auditadas |
| Assets-fonte dentro do tema | conforme | `assets/` de Web, App e CMS |
| Componentes e páginas separados | conforme | `components/` e `pages/` por ambiente |
| `default.php` como layout principal | **parcial** | Web/CMS usam o layout; o App gera o documento em `pages/dashboard.php:134` e mantém `default.php` provisório |
| ERP e Send independentes | conforme, porém incompletos | somente shell provisório e rotas de indisponibilidade |

### Achado A-01 — App contorna o layout principal

**Severidade: média.** `container/app/realizze/v1/default.php` ainda é um placeholder, enquanto `container/app/realizze/v1/pages/dashboard.php` contém `<!doctype html>`, `<head>` e o shell completo. Isso não mistura ambientes, mas viola a responsabilidade definida para `default.php` e aumenta duplicação em páginas especiais.

**Recomendação:** numa tarefa isolada e coberta por testes, mover apenas o shell compartilhado para `default.php` usando MovesCode View. Não fazer essa refatoração junto com funcionalidades financeiras.

## 6. MovesCode, Composer e autoload

O `composer.json` exige PHP `^8.2`, usa PSR-4 (`Moves\\` → `source/`) e mantém os pacotes previstos:

- Router: utilizado no bootstrap e nas cinco rotas de ambiente;
- Middleware: instalado, mas com uso direto reduzido; políticas de acesso também aparecem em services;
- Model: utilizado amplamente por services/models;
- View: utilizado nos layouts e componentes;
- Compress: fluxo oficial nos scripts `publish-web-assets.php`, `publish-app-assets.php` e `publish-cms-assets.php`;
- Cropper, Pager, Storage e SEO: presentes e referenciados;
- não foi encontrada dependência paralela substituindo esses pacotes.

### Achado A-02 — aplicação de middleware pouco uniforme

**Severidade: baixa.** Há checagens de sessão, CSRF e autorização corretas, mas parte delas é repetida em closures de `container/app/realizze/v1/routes.php` e controllers. O pacote Middleware existe, porém não centraliza todo o pipeline.

**Recomendação:** padronizar gradualmente autenticação/autorização por grupos de rota, sem criar um segundo Router e sem reescrever as rotas atuais.

## 7. Controllers, models e services

- `source/App/`: 12 controllers/actions;
- `source/Services/`: 63 services;
- `source/Models/`: 25 models;
- namespaces e PSR-4 estão coerentes;
- controllers permanecem em `source/App/`, conforme a decisão arquitetural.

### Achado A-03 — service financeiro excessivamente amplo

**Severidade: média.** `source/Services/Finance.php` possui 1.147 linhas e concentra contas, lançamentos, transferências, metas, relatórios e consultas do dashboard. O código usa prepared statements e isolamento por `financial_context_id`, mas a amplitude aumenta acoplamento e risco de regressão.

**Recomendação:** extrair por comportamento somente quando houver mudança funcional: por exemplo, `FinanceEntries`, `FinanceGoals` e `FinanceReports`. Preservar a fachada atual durante a transição.

### Achado A-04 — rotas do App acumulam orchestration

**Severidade: baixa.** `container/app/realizze/v1/routes.php` possui 368 linhas e contém mapeamento, autorização e adaptação de contexto. Ainda é administrável, mas deve evitar receber novas regras de negócio.

## 8. Auditoria de banco e migrations

Nenhuma migration foi executada durante esta auditoria.

### Achado DB-01 — ausência de ledger/runner de migrations

**Severidade: alta.** Não foi encontrado controle como `schema_migrations`, nem executor que registre nome/hash/ordem. A aplicação depende de execução manual, o que já produziu divergência entre código e `realizze_dev`.

### Achado DB-02 — número 108 duplicado

**Severidade: alta.** Existem simultaneamente:

- `database/migrations/108_add_organization_slugs.sql`;
- `database/migrations/108_create_contact_messages.sql`.

Um runner baseado somente no prefixo pode pular uma delas ou executá-las em ordem não determinística.

### Achado DB-03 — prefixos 003/004 misturam up/down

**Severidade: média.** Os prefixos 003 e 004 incluem migrations normais e arquivos `.down.sql`. Isso é aceitável apenas se o futuro runner excluir explicitamente `*.down.sql` no fluxo de subida.

### Achado DB-04 — banco local abaixo do esquema do código

**Severidade: alta.** Três testes falharam porque o banco `realizze_dev` não possui objetos previstos:

- `finance_debts` da migration 007;
- `financial_institutions` da migration 006;
- extensões da migration 008.

O código de recorrências (010/011) passou no teste dedicado, mas a implantação completa continua dependente da ordem 002 → 003 → 004 → 005 → 006 → 007 → 008 → 009 → 010 → 011.

**Recomendação prioritária:** criar primeiro um inventário/ledger idempotente, renumerar apenas a migration 108 ainda não publicada em produção e preparar um plano de aplicação por ambiente com backup. Não executar automaticamente na inicialização HTTP.

## 9. Situação funcional dos módulos

| Módulo | Classificação | Evidência/observação |
|---|---|---|
| Dashboard PF/PJ | implementado e testado | UI smoke, isolamento e navegador documentado |
| Contas e cartões | implementado e testado | suíte financeira/UI; logos dependem de catálogo/configuração |
| Movimentações | implementado e testado | filtros, transferências e isolamento |
| Contas a pagar | implementado e testado | entradas e recorrências previstas |
| Contas a receber | implementado e testado | `finance-002-receivables.php` |
| Metas | implementado e testado | `finance-007-goal-progress.php` |
| Planejamento | implementado, cobertura parcial | tabelas/visual testados; cenários completos têm menos cobertura |
| Dívidas | implementado, ambiente inconsistente | planner passa; módulo falha sem migration 007 |
| Assinaturas e recorrências | implementado e testado | migration 010/011, serviço, CLI e `finance-008` |
| Perfil e segurança | implementado e testado | 2FA, sessões, acesso delegado e preferências |
| Notificações | implementado e testado | App, preferências e entregas |
| Autenticação/cadastro | implementado e testado | login, reset, rate limit, confirmação e cadastro |
| Usuários/organizações | implementado e testado | isolamento PF/PJ e autorização |
| CMS | implementado e testado | `cms-100-e2e.php` e controle de acesso |
| Helpdesk | implementado e testado | CMS, site público, anexos e notificações |
| Planos/assinatura da plataforma | implementado e testado | não confundir com recorrências financeiras |
| Site institucional | implementado e testado | assets e rotas Web |
| ERP | incompleto/intencionalmente indisponível | shell isolado, sem páginas de domínio |
| Send | incompleto/intencionalmente indisponível | fila de e-mail existe no core, ambiente visual é placeholder |

## 10. Auditoria visual e de assets

O fluxo de publicação usa MovesCode Compress e separa fonte/publicado. App, Web e CMS usam query de versão por `filemtime`. A logo oficial e favicons estão centralizados em `public/assets/realizze/v1/images/`.

### Pontos positivos

- fonte em `container/.../assets` e minificados em `public/assets`;
- scripts específicos por ambiente, sem segundo pipeline;
- testes de assets Web e smoke de templates;
- responsividade e menu móvel possuem testes JavaScript.

### Dívidas

- CSS do App é grande e acumulativo; recomenda-se organizar por componentes no fonte antes de qualquer troca de framework;
- assets de marcas de terceiros não devem ser incorporados sem licença;
- a consistência fonte/minificado deve virar verificação de CI para impedir publicação esquecida.

## 11. Segurança e qualidade

### Controles confirmados

- CSRF validado nas mutações e coberto por testes;
- prepared statements predominam nos services;
- contexto financeiro e organização são aplicados às consultas;
- autorização PF delegada e PJ testadas;
- saída HTML dinâmica é escapada nos componentes auditados;
- uploads têm validação, armazenamento fora do público e testes contra traversal/symlink;
- sessão usa cookies seguros e possui testes de destruição;
- erros 403/404/500 personalizados não expõem stack trace no fluxo normal;
- `.env` não está rastreado; apenas `.env.example` aparece no Git.

### Achado SEC-01 — drift de banco reduz garantias de segurança funcional

**Severidade: alta.** Módulos podem falhar em produção antes de alcançar as verificações de autorização quando as tabelas/colunas esperadas não existem. A correção é operacional (migrations controladas), não relaxar os checks.

### Achado SEC-02 — rotas extensas favorecem omissão de guardas

**Severidade: média.** A repetição de checks em `routes.php` exige disciplina manual. Os testes atuais mitigam o risco, mas novos endpoints devem receber teste negativo de sessão, CSRF, tenant e permissão.

Não foi identificado SQL Injection explorável nas rotas auditadas; trechos SQL dinâmicos usam listas/colunas internas controladas e valores preparados. Não foram realizados testes destrutivos.

## 12. Testes executados

### PHP no container `dev-apache`

- 41 passaram;
- 3 falharam por schema local incompleto: `debt-002-module.php`, `finance-004-institutions.php`, `finance-005-entry-options.php`;
- 1 omitido: `core-001-email-smtp.php`, por ser integração opt-in com possível envio real.

### JavaScript

- `cms-003-mobile-menu.js`: passou;
- `ui-003-mobile-menu.js`: passou;
- `ui-004-enterprise-search.js`: passou.

A tentativa inicial usando o utilitário GNU `timeout` não executou testes porque esse comando não existe no Mac; ela foi descartada e a suíte foi repetida corretamente.

## 13. Prioridades

1. **Alta:** implementar ledger/runner de migrations e eliminar a colisão 108.
2. **Alta:** alinhar `realizze_dev` ao schema aprovado, após backup e plano explícito.
3. **Média:** tornar `default.php` o layout efetivo do App sem redesenhar a interface.
4. **Média:** reduzir gradualmente a amplitude de `Finance.php`.
5. **Média:** adicionar CI que execute PHP/JS, lint e compare assets publicados.
6. **Baixa:** uniformizar middlewares de rota.
7. **Baixa:** manter ERP e Send como módulos indisponíveis até existir requisito de negócio; não criar telas vazias apenas para preencher estrutura.

## 14. Resposta à pergunta principal

**Sim, o Realizze 2.0 continua seguindo a arquitetura e a filosofia originais em seus pontos fundamentais, mas não integralmente.**

Está correto e deve ser preservado:

- isolamento entre Web, App, CMS, ERP e Send;
- contrato de arquivos na raiz de cada versão;
- MovesCode Router/View/Model/Compress e demais pacotes oficiais;
- controllers em `source/App/` e regras em services/models;
- isolamento PF/PJ, CSRF, autorização e prepared statements;
- evolução incremental com testes, sem reescrita completa.

Saiu do padrão ou precisa de correção:

- o shell principal do App vive em uma página em vez de `default.php`;
- migrations não possuem controle de aplicação e há prefixo duplicado;
- o banco local não acompanha três migrations já exigidas pelo código;
- alguns services e arquivos de rota cresceram além de uma responsabilidade confortável.

Não devemos implementar agora:

- novo framework, novo Router ou pipeline paralelo de assets;
- reorganização estética de todas as classes;
- ERP/Send completos sem requisitos e casos de uso aprovados;
- aplicação automática de migrations durante requisições.

**Próxima etapa recomendada:** publicar por fast-forward a consolidação atual após aprovação, depois abrir uma tarefa exclusiva de governança de migrations. Só então alinhar os bancos dos ambientes e repetir a suíte completa.

## 15. Situação da sincronização remota

O GitHub **não foi atualizado** nesta auditoria. O push permanece bloqueado até aprovação explícita. A branch de destino proposta é `feat/realizze-integracao-ui-20261101`; o hash final deve ser informado após o commit documental desta auditoria.
