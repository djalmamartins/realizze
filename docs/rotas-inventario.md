# Inventário de rotas do Realizze 2.0

Auditoria de 02/11/2026 no branch `feat/realizze-integracao-ui-20261101`. Nenhuma rota foi alterada. As tabelas saem das 276 rotas que o MovesCode Router registra de fato com `APP_ENV=local`, incluindo as alterações ainda não commitadas de Metas. A coluna "Rota proposta" registra a proposta da auditoria.

**Situação em 02/11/2026:** o período na URL foi implementado em PF e PJ. `AppUrl` gera os links, as rotas ficam em `container/app/realizze/v1/routes.php`, e os endereços antigos com `?mes`, `?visao=anual&ano` e `?inicio&fim` respondem 302. Formatos aceitos: visão geral, Receber, Pagar e Movimentações com mês, ano e datas; Relatórios, Categorias e Planejamento com mês e ano; Fluxo de caixa, só na empresa, com mês. Metas ficou sem período, porque a tela não filtra por ele, ao contrário do que a tabela abaixo propõe. Os achados 1 a 8 e 14 foram resolvidos, e `/configuracao-inicial` saiu do site junto com a página. O achado 9 foi revisto e não é duplicidade. `/cartoes` fica como está (achado 10). ERP e Send não têm o que padronizar agora (achado 16).

## Como o roteamento funciona

- `public/index.php` escolhe o ambiente pelo hostname (`EnvironmentRouter`) e carrega `container/{ambiente}/realizze/v1/routes.php`.
- O Router testa as rotas na ordem de registro, e a primeira que casa vence. Não há prioridade para rota estática.
- Todo `{parametro}` vira `[^/]+`. O Router não aceita restrição por regex, então validar ano, mês ou data cabe ao handler.
- A barra final é opcional, e `/p` e `/p/` respondem igual.
- `$router->route()` e `$router->redirect()` não são usados em nenhum ponto, e 100 rotas GET não têm nome (51 no App, 49 no CMS).
- URLs do App saem de `Moves\Services\AppUrl` (54 chamadas de `section`) ou estão escritas à mão nas views.

## Navegação AJAX e histórico

Tudo está em `container/app/realizze/v1/assets/js/dashboard.js`.

- `appPath` e `swappable` (linhas 351 a 355) decidem quais URLs trocam sem recarregar: `/`, `/p...`, `/e/{slug}...` e os dois prefixos antigos. Segmentos de período já passam por esse filtro.
- `go` faz o `fetch`, segue redirecionamentos e grava `response.url` com `pushState`. Por isso um redirecionamento de `?mes=` para a URL nova já chegaria certo à barra de endereço.
- `popstate` refaz a navegação sem empilhar outra entrada.
- Formulários `data-swap-get` usam `form.action` e acrescentam os campos em `searchParams`. Um filtro de mês nesses formulários precisa virar segmento antes do `go`.
- Os POST são interceptados pelo regex `/minha-conta/(financeiro|contatos)/`. Se esses caminhos mudarem, o regex muda junto. Essa é mais uma razão para mantê-los.

## Parâmetros GET encontrados

| Parâmetro | Onde | Proposta |
|---|---|---|
| `mes` | `finance-entries`, `movimentacoes`, `fluxo-caixa`, `Finance::period` | Vira segmento `/{ano}/{mes}` |
| `visao=anual` + `ano` | `Finance::period`, `app-toolbar`, `relatorios` | Vira segmento `/{ano}` |
| `inicio` + `fim` | `finance-entries`, `movimentacoes`, `receivables` | Vira segmento `/{data_inicio}/{data_fim}` |
| `visao=dia/semana` | `fluxo-caixa` | Fica em query. O mesmo nome tem outro significado aqui, e isso impede o redirecionamento automático de `visao` nesta tela |
| `pagina` | Seção em `/minha-conta`, página no blog, suporte e CMS | Fica em query na paginação. Como seção, continua só em `/minha-conta` |
| `page`, `pagina_log` | `receivables`, `contatos`, `perfil` | Fica em query |
| `busca`, `q`, `categoria`, `conta`, `tag`, `situacao`, `status`, `tipo`, `cliente`, `contato`, `ordem`, `prazo`, `filtro`, `aba`, `pasta` | Filtros de lista | Fica em query: são combináveis e opcionais |
| `simular`, `sim_divida`, `sim_parcelas`, `sim_taxa`, `orcamento` | `sair-das-dividas` | Fica em query: é simulação, não um endereço |
| `token` | `/redefinir-senha`, `/confirmar-email`, `/p/perfil/convite` | Fica em query: há links já enviados por e-mail, e segredo no caminho aparece mais em logs |
| `id`, `ver` | `/minha-conta/financeiro/anexo`, `meta-foto` | Pode virar segmento, com ganho pequeno |
| `editar`, `produto`, `assignee`, `type`, `aba` | Telas do CMS | Fica em query |

## Achados

### Problemas

1. **Depois de salvar, o App passa por um endereço antigo.** `FinanceActions.php:96` e `:100` e `ReceivableActions.php:52` redirecionam para `/minha-conta?pagina=...`, que redireciona de novo para `/p` ou `/e/{slug}`. São dois saltos a cada gravação, e o destino sai da empresa ativa na sessão, não da tela em que a pessoa estava.
2. **Salvar em Pagar perde o filtro.** `FinanceActions.php:100` devolve só `mes`, e `inicio`, `fim`, `situacao` e `busca` se perdem. Em Receber esses filtros voltam.
3. **`/minha-conta?pagina=sair-das-dividas` cai na visão geral.** `AppUrl::SECTIONS` não tem essa seção, e `sair-das-dividas.php` e `DebtActions.php:47` usam `/p/sair-das-dividas` escrito à mão.
4. **Validação de mês divergente.** `Finance::period` aceita `20\d{2}`, as outras telas aceitam `\d{4}`. `?mes=1999-01` mostra 1999 em Pagar e o mês atual na visão geral. As rotas novas precisam de uma regra só.
5. **Slug reservado incompleto.** `CmsContent::RESERVED` não tem `reenviar-confirmacao`, então o CMS aceita criar uma página com esse slug, e ela nunca abre porque a rota fixa vem antes.

### Duplicadas ou sem uso

6. **Web tem cópias de rotas do App.** `POST /minha-conta/contexto` e `POST /sair` não têm formulário em nenhuma tela do site. Em `GET /minha-conta`, o ramo que renderiza o painel só roda sem `ROUTE_APP_HOST`, e esse valor está definido no `.env`. Pelo código, o cookie de sessão não define domínio e o login acontece no host do App. Portanto, a sessão não deve valer no host do site, mas não testei isso em uma requisição real.
7. **`/configuracao-inicial` no site não tem nenhum link** e exige acesso do App, o que esbarra no mesmo ponto da sessão.
8. **Nomes fora do ambiente.** Rotas do site usam os nomes `app.account`, `app.context.switch` e `app.onboarding.preview`.
9. **Preferências de notificação têm dois caminhos, e os dois ficam.** `/minha-conta/perfil/notificacoes` define canais por categoria, e `/minha-conta/notificacoes/preferencias` define os avisos automáticos. São ajustes diferentes.
10. **Slug e seção divergem em Contas.** A tela se chama "Contas e Cartões", a seção é `contas` e a URL é `/cartoes`. Só faz sentido mudar se você quiser.

### Conflitos entre rotas estáticas e dinâmicas

11. **Hoje não há conflito real.** Em `/suporte/{protocol}`, `/conteudos/{id}`, `/chamados/{id}`, `/destaques/{id}`, `/paginas/{id}` e `/planos/{id}`, as rotas fixas vêm antes. `/{slug}` do site é a última.
12. **Mês e período personalizado têm o mesmo formato.** `/receber/2026/11` e `/receber/2026-11-10/2026-12-15` são ambos `/{modulo}/{a}/{b}`, e o Router não os distingue. Precisa ser uma rota só, com o handler decidindo pelo formato e respondendo 404 ao resto.
13. **Contatos não podem ter período.** `/e/{org}/clientes/{contact}` já ocupa o segmento seguinte, e `/e/x/clientes/2026` é o cliente 2026. O mesmo vale para `fornecedores`.
14. **`/usuarios/exportar` no CMS** precisa ser registrada antes de `/usuarios/{id}`, senão vira um 404 por ID inválido.

### Fora da padronização, mas relevante

15. **O App inteiro responde 503 fora de `APP_ENV=local`**, porque `$previewEnabled` exige `REALIZZE_APP_LOCAL_PREVIEW=1`. O CMS tem saída própria com `REALIZZE_CMS_ENABLED`, e o App não tem. Vale resolver antes de publicar.
16. **ERP e Send não têm host no `.env` local** (`ROUTE_ERP_HOST` e `ROUTE_SEND_HOST`) e só registram `/` com 503. Não há o que padronizar neles agora.

### O que ficou certo e não deve mudar

- Autenticação, formulários e uploads já têm caminhos estáveis. Uploads entram pelos POST de `/minha-conta/financeiro/*` e `/acao/{name}`, e callbacks externos não existem hoje.
- Links antigos seguem cobertos: `/pf/pessoal`, `/pj/minha-empresa`, `/minha-conta?pagina=`. Notificações gravadas no banco guardam links em `notifications.link`, então nenhum desses caminhos deve sair.

## Proposta para o padrão de período

Segue o idioma que `routes.php` já usa em `/clientes/{contact}`: a rota traduz segmentos para `$_GET`, e as telas continuam iguais.

1. Uma rota `/{base}/{a}` e uma rota `/{base}/{a}/{b}` por módulo, em PF e PJ, usando o mesmo laço de `$semantic`.
2. O handler valida: `AAAA` é anual, `AAAA` + `MM` é mensal, duas datas `AAAA-MM-DD` com início menor ou igual ao fim é período personalizado, e o resto é 404.
3. Um `AppUrl::period(...)` passa a gerar os links, substituindo `$toolbarLink` e os `$link` das telas. Filtros seguem em query, como em `/p/receber/2026/11?situacao=open`.
4. Quem chegar por `?mes=`, `?visao=anual&ano=` ou `?inicio=&fim=` recebe 302 para o endereço novo, mantendo os demais filtros. Passo a 301 só depois de estabilizar.

**Receber é um caso à parte:** `pages/receber.php` usa `components/receivables.php`, que só lê `inicio` e `fim`, sem `mes`. Ali, `/p/receber/2026/11` precisa ser traduzido para as duas datas, e `?mes=` não tem efeito hoje.

**Telas que hoje não aceitam o padrão inteiro:** `receber`, `pagar`, `movimentacoes` e `fluxo-de-caixa` não têm visão anual, e `relatorios`, `planejamento`, `metas`, `categorias` e a visão geral não têm período personalizado. As tabelas abaixo assumem acrescentar o anual às primeiras, sem período personalizado nas últimas, salvo a visão geral (`/p`), que seu brief cita e por isso precisaria ganhar esse suporte.

## Inventário

## Web (31 rotas)

| Rota atual | Método | Handler | Finalidade | Rota proposta | Compatibilidade |
|---|---|---|---|---|---|
| `/` | GET | pages/home | Página institucional | Manter | Não |
| `/blog` | GET | pages/blog | Lista do blog (?pagina=) | Manter | Não |
| `/blog/{slug}` | GET | pages/post | Post | Manter | Não |
| `/cadastrar` | GET | closure → App | Leva ao cadastro do App | Manter | Não |
| `/cadastrar` | POST | closure → App | Leva ao cadastro do App | Manter | Não |
| `/configuracao-inicial` | GET | pages/onboarding.php | Prévia de onboarding sem link | Decidir: remover ou mover ao App | Não |
| `/confirmar-email` | GET | EmailConfirmation | Confirmar e-mail (?token=) | Manter | Não |
| `/confirmar-email` | POST | EmailConfirmation | Confirmar e-mail (?token=) | Manter | Não |
| `/contato` | GET | pages/contact | Página institucional | Manter | Não |
| `/entrar` | GET | closure → App | Leva ao login do App | Manter | Não |
| `/entrar` | POST | closure → App | Leva ao login do App | Manter | Não |
| `/minha-conta` | GET | closure → App | Leva ao App | Manter como redirecionamento; remover o ramo que renderiza o painel | Já é redirecionamento |
| `/minha-conta/contexto` | POST | OrganizationContext | Cópia da troca de área do App | Remover | Não: nenhuma tela do site envia |
| `/planos` | GET | pages/plans | Planos | Manter | Não |
| `/privacidade` | GET | pages/privacy | Página institucional | Manter | Não |
| `/recuperar-senha` | GET | PasswordReset | Pedir nova senha | Manter | Não |
| `/recuperar-senha` | POST | PasswordReset | Pedir nova senha | Manter | Não |
| `/redefinir-senha` | GET | PasswordReset | Trocar senha (?token=) | Manter | Não |
| `/redefinir-senha` | POST | PasswordReset | Trocar senha (?token=) | Manter | Não |
| `/reenviar-confirmacao` | GET | Moves\App\Register::resend | Reenviar e-mail | Manter | Não |
| `/reenviar-confirmacao` | POST | Moves\App\Register::resend | Reenviar e-mail | Manter | Não |
| `/robots.txt` | GET | closure | Robots | Manter | Não |
| `/sair` | POST | SessionAuth::signOut | Logout sem formulário no site | Remover | Não: nenhuma tela do site envia |
| `/sitemap.xml` | GET | CmsSite::sitemapUrls | Sitemap | Manter | Não |
| `/sobre` | GET | pages/about | Página institucional | Manter | Não |
| `/solucoes` | GET | pages/solutions | Página institucional | Manter | Não |
| `/suporte` | GET | pages/support | Central de suporte (?q, ?pasta, ?pagina) | Manter | Não |
| `/suporte/artigo/{uri}` | GET | pages/support-article | Artigo de ajuda | Manter | Não |
| `/suporte/mensagem` | POST | ContactMessages | Formulário de contato | Manter | Não |
| `/termos` | GET | pages/terms | Página institucional | Manter | Não |
| `/{slug}` | GET | CmsSite::publishedPage | Página do CMS (por último) | Manter | Não |

## App (188 rotas)

| Rota atual | Método | Handler | Finalidade | Rota proposta | Compatibilidade |
|---|---|---|---|---|---|
| `/` | GET | closure | Entrada do App | Manter | Não |
| `/__teste/erro-500` | GET | closure | Teste de erro, só local | Manter | Não |
| `/cadastrar` | GET | Moves\App\Register | Cadastro PF | Manter | Não |
| `/cadastrar` | POST | Moves\App\Register | Cadastro PF | Manter | Não |
| `/e/{organization}` | GET | dashboard.php → overview | Tela visão geral PJ | Manter e acrescentar /e/{organization}/{ano}/{mes}, /e/{organization}/{ano}, /e/{organization}/{data_inicio}/{data_fim} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/e/{organization}/assistente` | GET | dashboard.php → assistente | Tela assistente PJ | Manter | Não |
| `/e/{organization}/auditoria` | GET | dashboard.php → auditoria | Tela auditoria PJ | Manter | Não |
| `/e/{organization}/cartoes` | GET | dashboard.php → cartoes | Tela cartoes PJ | Manter | Não |
| `/e/{organization}/categorias` | GET | dashboard.php → categorias | Tela categorias PJ | Manter e acrescentar /e/{organization}/categorias/{ano}/{mes}, /e/{organization}/categorias/{ano} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/e/{organization}/centros-de-custo` | GET | dashboard.php → centros-de-custo | Tela centros-de-custo PJ | Manter | Não |
| `/e/{organization}/clientes` | GET | dashboard.php → clientes | Tela clientes PJ | Manter | Não |
| `/e/{organization}/clientes/{contact}` | GET | dashboard.php → contato-detalhe | Detalhe do contato PJ | Manter | Não |
| `/e/{organization}/conciliacao` | GET | dashboard.php → conciliacao | Tela conciliacao PJ | Manter | Não |
| `/e/{organization}/configuracoes` | GET | dashboard.php → configuracoes | Tela configuracoes PJ | Manter | Não |
| `/e/{organization}/contatos` | GET | dashboard.php → contatos | Tela contatos PJ | Manter | Não |
| `/e/{organization}/dre` | GET | dashboard.php → dre | Tela dre PJ | Manter | Não |
| `/e/{organization}/empresa` | GET | dashboard.php → empresa | Tela empresa PJ | Manter | Não |
| `/e/{organization}/empresarial` | GET | dashboard.php → empresarial | Tela empresarial PJ | Manter | Não |
| `/e/{organization}/equipe` | GET | dashboard.php → equipe | Tela equipe PJ | Manter | Não |
| `/e/{organization}/faturas` | GET | dashboard.php → faturas | Tela faturas PJ | Manter | Não |
| `/e/{organization}/fluxo-de-caixa` | GET | dashboard.php → fluxo-de-caixa | Tela fluxo-de-caixa PJ | Manter e acrescentar /e/{organization}/fluxo-de-caixa/{ano}/{mes}, /e/{organization}/fluxo-de-caixa/{ano}, /e/{organization}/fluxo-de-caixa/{data_inicio}/{data_fim} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/e/{organization}/fornecedores` | GET | dashboard.php → fornecedores | Tela fornecedores PJ | Manter | Não |
| `/e/{organization}/fornecedores/{contact}` | GET | dashboard.php → contato-detalhe | Detalhe do contato PJ | Manter | Não |
| `/e/{organization}/integracoes` | GET | dashboard.php → integracoes | Tela integracoes PJ | Manter | Não |
| `/e/{organization}/metas` | GET | dashboard.php → metas | Tela metas PJ | Manter e acrescentar /e/{organization}/metas/{ano}/{mes}, /e/{organization}/metas/{ano} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/e/{organization}/movimentacoes` | GET | dashboard.php → movimentacoes | Tela movimentacoes PJ | Manter e acrescentar /e/{organization}/movimentacoes/{ano}/{mes}, /e/{organization}/movimentacoes/{ano}, /e/{organization}/movimentacoes/{data_inicio}/{data_fim} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/e/{organization}/notificacoes` | GET | dashboard.php → notificacoes | Tela notificacoes PJ | Manter | Não |
| `/e/{organization}/obrigacoes` | GET | dashboard.php → obrigacoes | Tela obrigacoes PJ | Manter | Não |
| `/e/{organization}/ordens-de-servico` | GET | dashboard.php → ordens-de-servico | Tela ordens-de-servico PJ | Manter | Não |
| `/e/{organization}/pagar` | GET | dashboard.php → pagar | Tela pagar PJ | Manter e acrescentar /e/{organization}/pagar/{ano}/{mes}, /e/{organization}/pagar/{ano}, /e/{organization}/pagar/{data_inicio}/{data_fim} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/e/{organization}/perfil` | GET | dashboard.php → perfil | Tela perfil PJ | Manter | Não |
| `/e/{organization}/permissoes` | GET | dashboard.php → permissoes | Tela permissoes PJ | Manter | Não |
| `/e/{organization}/planejamento` | GET | dashboard.php → planejamento | Tela planejamento PJ | Manter e acrescentar /e/{organization}/planejamento/{ano}/{mes}, /e/{organization}/planejamento/{ano} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/e/{organization}/receber` | GET | dashboard.php → receber | Tela receber PJ | Manter e acrescentar /e/{organization}/receber/{ano}/{mes}, /e/{organization}/receber/{ano}, /e/{organization}/receber/{data_inicio}/{data_fim} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/e/{organization}/recorrencias` | GET | dashboard.php → recorrencias | Tela recorrencias PJ | Manter | Não |
| `/e/{organization}/relatorios` | GET | dashboard.php → relatorios | Tela relatorios PJ | Manter e acrescentar /e/{organization}/relatorios/{ano}/{mes}, /e/{organization}/relatorios/{ano} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/e/{organization}/sair-das-dividas` | GET | dashboard.php → sair-das-dividas | Tela sair-das-dividas PJ | Manter | Não |
| `/e/{organization}/seguranca` | GET | dashboard.php → seguranca | Tela seguranca PJ | Manter | Não |
| `/entrar` | GET | Moves\App\Login | Login | Manter | Não |
| `/entrar` | POST | Moves\App\Login | Login | Manter | Não |
| `/entrar/2fa` | GET | Moves\App\Login | Segundo fator | Manter | Não |
| `/entrar/2fa` | POST | Moves\App\Login | Segundo fator | Manter | Não |
| `/minha-assinatura` | GET | pages/assinatura.php | Assinatura do usuário | Manter | Não |
| `/minha-conta` | GET | closure → AppUrl::section | Alias antigo (?pagina=) | Manter só como compatibilidade | Já é redirecionamento |
| `/minha-conta/bancos/logo/{logo}` | GET | FinancialInstitutions::serveLogo | Download de logo de banco | Manter | Não |
| `/minha-conta/contatos/atualizar` | POST | ContactActions | Formulário de contato | Manter | Não |
| `/minha-conta/contatos/salvar` | POST | ContactActions | Formulário de contato | Manter | Não |
| `/minha-conta/contatos/status` | POST | ContactActions | Formulário de contato | Manter | Não |
| `/minha-conta/contexto` | POST | OrganizationContext | Trocar área PF/PJ | Manter | Não |
| `/minha-conta/dividas/atualizar` | POST | DebtActions | Formulário de dívidas | Manter | Não |
| `/minha-conta/dividas/diagnostico` | POST | DebtActions | Formulário de dívidas | Manter | Não |
| `/minha-conta/dividas/negociacao` | POST | DebtActions | Formulário de dívidas | Manter | Não |
| `/minha-conta/dividas/negociacao-situacao` | POST | DebtActions | Formulário de dívidas | Manter | Não |
| `/minha-conta/dividas/pagamento` | POST | DebtActions | Formulário de dívidas | Manter | Não |
| `/minha-conta/dividas/remover` | POST | DebtActions | Formulário de dívidas | Manter | Não |
| `/minha-conta/dividas/salvar` | POST | DebtActions | Formulário de dívidas | Manter | Não |
| `/minha-conta/financeiro/anexo` | GET | FinanceActions::attachment | Download de anexo (?id=) | Opcional: /minha-conta/financeiro/anexo/{id} | Sim, se mudar |
| `/minha-conta/financeiro/baixa` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/categoria` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/categoria-excluir` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/conta` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/empresa` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/excluir` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/exportar` | GET | FinanceActions::export | Download de CSV | Manter; período segue em query | Não |
| `/minha-conta/financeiro/lancamento` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/lancamento-editar` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta-aporte-excluir` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta-aporte-programado` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta-aporte-programado-situacao` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta-editar` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta-estornar` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta-excluir` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta-foto` | GET | FinanceActions::goalPhoto | Foto da meta (?id=&ver=) | Opcional: /minha-conta/financeiro/meta-foto/{id} | Sim, se mudar |
| `/minha-conta/financeiro/meta-guardar` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta-pagar` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta-resgatar` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta-situacao` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta-transferir` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/meta-valor` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/orcamento` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/financeiro/recebimento-baixa` | POST | ReceivableActions | Ação de recebimento | Manter | Não |
| `/minha-conta/financeiro/recebimento-cancelar` | POST | ReceivableActions | Ação de recebimento | Manter | Não |
| `/minha-conta/financeiro/recorrencia/cancelar` | POST | RecurringActions | Ação de recorrência | Manter | Não |
| `/minha-conta/financeiro/recorrencia/pausar` | POST | RecurringActions | Ação de recorrência | Manter | Não |
| `/minha-conta/financeiro/recorrencia/reativar` | POST | RecurringActions | Ação de recorrência | Manter | Não |
| `/minha-conta/financeiro/transferencia` | POST | FinanceActions::handle | Formulário financeiro | Manter | Não |
| `/minha-conta/notificacoes/abrir` | POST | NotificationActions | Ação de notificação | Manter | Não |
| `/minha-conta/notificacoes/lidas` | POST | NotificationActions | Ação de notificação | Manter | Não |
| `/minha-conta/notificacoes/preferencias` | POST | NotificationActions | Ação de notificação | Manter | Não |
| `/minha-conta/perfil/2fa/confirmar` | POST | ProfileActions | Formulário de perfil | Manter | Não |
| `/minha-conta/perfil/2fa/desativar` | POST | ProfileActions | Formulário de perfil | Manter | Não |
| `/minha-conta/perfil/2fa/iniciar` | POST | ProfileActions | Formulário de perfil | Manter | Não |
| `/minha-conta/perfil/convite` | POST | ProfileActions | Formulário de perfil | Manter | Não |
| `/minha-conta/perfil/convite/aceitar` | POST | ProfileActions | Formulário de perfil | Manter | Não |
| `/minha-conta/perfil/convite/recusar` | POST | ProfileActions | Formulário de perfil | Manter | Não |
| `/minha-conta/perfil/convite/revogar` | POST | ProfileActions | Formulário de perfil | Manter | Não |
| `/minha-conta/perfil/dados` | POST | ProfileActions | Formulário de perfil | Manter | Não |
| `/minha-conta/perfil/notificacoes` | POST | ProfileActions | Formulário de perfil | Manter | Não |
| `/minha-conta/perfil/senha` | POST | ProfileActions | Formulário de perfil | Manter | Não |
| `/minha-conta/perfil/sessao/encerrar` | POST | ProfileActions | Formulário de perfil | Manter | Não |
| `/minha-conta/perfil/sessoes/encerrar-outras` | POST | ProfileActions | Formulário de perfil | Manter | Não |
| `/p` | GET | dashboard.php → overview | Tela visão geral PF | Manter e acrescentar /p/{ano}/{mes}, /p/{ano}, /p/{data_inicio}/{data_fim} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/p/assistente` | GET | dashboard.php → assistente | Tela assistente PF | Manter | Não |
| `/p/cartoes` | GET | dashboard.php → cartoes | Tela cartoes PF | Manter | Não |
| `/p/categorias` | GET | dashboard.php → categorias | Tela categorias PF | Manter e acrescentar /p/categorias/{ano}/{mes}, /p/categorias/{ano} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/p/configuracoes` | GET | dashboard.php → configuracoes | Tela configuracoes PF | Manter | Não |
| `/p/empresarial` | GET | dashboard.php → empresarial | Tela empresarial PF | Manter | Não |
| `/p/integracoes` | GET | dashboard.php → integracoes | Tela integracoes PF | Manter | Não |
| `/p/metas` | GET | dashboard.php → metas | Tela metas PF | Manter e acrescentar /p/metas/{ano}/{mes}, /p/metas/{ano} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/p/movimentacoes` | GET | dashboard.php → movimentacoes | Tela movimentacoes PF | Manter e acrescentar /p/movimentacoes/{ano}/{mes}, /p/movimentacoes/{ano}, /p/movimentacoes/{data_inicio}/{data_fim} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/p/notificacoes` | GET | dashboard.php → notificacoes | Tela notificacoes PF | Manter | Não |
| `/p/pagar` | GET | dashboard.php → pagar | Tela pagar PF | Manter e acrescentar /p/pagar/{ano}/{mes}, /p/pagar/{ano}, /p/pagar/{data_inicio}/{data_fim} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/p/perfil` | GET | dashboard.php → perfil | Tela perfil PF | Manter | Não |
| `/p/perfil/convite` | GET | closure → PersonalAccess (sessão) | Aceitar convite por link de e-mail | Manter; token continua em query | Não |
| `/p/planejamento` | GET | dashboard.php → planejamento | Tela planejamento PF | Manter e acrescentar /p/planejamento/{ano}/{mes}, /p/planejamento/{ano} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/p/receber` | GET | dashboard.php → receber | Tela receber PF | Manter e acrescentar /p/receber/{ano}/{mes}, /p/receber/{ano}, /p/receber/{data_inicio}/{data_fim} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/p/recorrencias` | GET | dashboard.php → recorrencias | Tela recorrencias PF | Manter | Não |
| `/p/relatorios` | GET | dashboard.php → relatorios | Tela relatorios PF | Manter e acrescentar /p/relatorios/{ano}/{mes}, /p/relatorios/{ano} | Sim: ?mes, ?visao=anual&ano, ?inicio&fim → 302 |
| `/p/sair-das-dividas` | GET | dashboard.php → sair-das-dividas | Tela sair-das-dividas PF | Manter | Não |
| `/pf/pessoal` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/assistente` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/cartoes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/categorias` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/configuracoes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/empresarial` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/integracoes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/metas` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/movimentacoes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/notificacoes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/pagar` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/perfil` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/planejamento` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/receber` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/recorrencias` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/relatorios` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pf/pessoal/sair-das-dividas` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/assistente` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/auditoria` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/cartoes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/categorias` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/centros-de-custo` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/clientes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/conciliacao` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/configuracoes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/contatos` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/dre` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/empresa` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/empresarial` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/equipe` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/faturas` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/fluxo-de-caixa` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/fornecedores` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/integracoes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/metas` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/movimentacoes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/notificacoes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/obrigacoes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/ordens-de-servico` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/pagar` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/perfil` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/permissoes` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/planejamento` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/receber` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/recorrencias` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/relatorios` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/sair-das-dividas` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/pj/minha-empresa/seguranca` | GET | closure → redireciona via AppUrl | Endereço antigo | Manter como redirecionamento | Já é redirecionamento |
| `/recuperar-senha` | GET | closure → site | Leva ao site | Manter | Já é redirecionamento |
| `/reenviar-confirmacao` | GET | closure → site | Leva ao site | Manter | Já é redirecionamento |
| `/sair` | POST | SessionAuth::signOut | Logout | Manter | Não |
| `/suporte` | GET | components/support-routes.php → pages/suporte* | Suporte: app.support | Manter | Não |
| `/suporte/abrir` | POST | SupportActions | Suporte: open | Manter | Não |
| `/suporte/ajuda` | GET | components/support-routes.php → pages/suporte* | Suporte: help | Manter | Não |
| `/suporte/ajuda/{uri}` | GET | components/support-routes.php → pages/suporte* | Suporte: article | Manter | Não |
| `/suporte/ajuda/{uri}/votar` | POST | SupportActions | Suporte: vote | Manter | Não |
| `/suporte/anexo/{id}` | GET | SupportActions | Suporte: attachment | Manter | Não |
| `/suporte/atualizacoes` | GET | components/support-routes.php → pages/suporte* | Suporte: updates | Manter | Não |
| `/suporte/notificacoes` | GET | components/support-routes.php → pages/suporte* | Suporte: notifications | Manter | Não |
| `/suporte/notificacoes/lidas` | POST | SupportActions | Suporte: read | Manter | Não |
| `/suporte/novo` | GET | components/support-routes.php → pages/suporte* | Suporte: new | Manter | Não |
| `/suporte/{protocol}` | GET | components/support-routes.php → pages/suporte* | Suporte: ticket | Manter | Não |
| `/suporte/{protocol}/avaliar` | POST | SupportActions | Suporte: rate | Manter | Não |
| `/suporte/{protocol}/confirmar` | POST | SupportActions | Suporte: confirm | Manter | Não |
| `/suporte/{protocol}/reabrir` | POST | SupportActions | Suporte: reopen | Manter | Não |
| `/suporte/{protocol}/resolver` | POST | SupportActions | Suporte: resolve | Manter | Não |
| `/suporte/{protocol}/responder` | POST | SupportActions | Suporte: reply | Manter | Não |

## CMS (55 rotas)

| Rota atual | Método | Handler | Finalidade | Rota proposta | Compatibilidade |
|---|---|---|---|---|---|
| `/` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/acao/{name}` | POST | CmsActions::handle | Todas as ações de formulário e uploads | Manter | Não |
| `/administradores` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/ajuda` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/ajuda/{id}` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/assinaturas` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/assinaturas/{id}` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/auditoria` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/base-de-conhecimento` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/chamados` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/chamados/anexo/{id}` | GET | Helpdesk::streamAttachment | Download de anexo | Manter | Não |
| `/chamados/categorias` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/chamados/equipe` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/chamados/fila` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/chamados/notificacoes` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/chamados/novo` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/chamados/relatorios` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/chamados/respostas` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/chamados/{id}` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/cobrancas` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/cobrancas/{id}` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/comunicacoes` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/configuracoes` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/conteudos` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/conteudos/categorias` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/conteudos/faq` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/conteudos/novo` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/conteudos/{id}` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/depoimentos` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/destaques` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/destaques/novo` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/destaques/{id}` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/empresas` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/empresas/{id}` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/entrar` | GET | CmsAuth | Login | Manter | Não |
| `/entrar` | POST | CmsAuth | Login | Manter | Não |
| `/log` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/mensagens` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/menus` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/midia` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/notificacoes` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/paginas` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/paginas/novo` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/paginas/{id}` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/paginas/{id}/previa` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/permissoes` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/planos` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/planos/novo` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/planos/{id}` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/relatorios` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/sair` | POST | SessionAuth::signOut | Logout | Manter | Não |
| `/usuarios` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/usuarios-exportar` | GET | closure → CSV | Exportar usuários | /usuarios/exportar, antes de /usuarios/{id} | Sim: 302 |
| `/usuarios/{id}` | GET | pages/ via $pages | Tela administrativa | Manter | Não |
| `/versoes` | GET | pages/ via $pages | Tela administrativa | Manter | Não |

## ERP (1 rotas)

| Rota atual | Método | Handler | Finalidade | Rota proposta | Compatibilidade |
|---|---|---|---|---|---|
| `/` | GET | closure → default.php (503) | Ambiente reservado | Manter | Não |

## Send (1 rotas)

| Rota atual | Método | Handler | Finalidade | Rota proposta | Compatibilidade |
|---|---|---|---|---|---|
| `/` | GET | closure → default.php (503) | Ambiente reservado | Manter | Não |
