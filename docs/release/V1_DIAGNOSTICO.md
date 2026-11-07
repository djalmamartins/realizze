# Realizze 2.0 — diagnóstico de prontidão da V1

Auditoria concluída em 07/11/2026 sobre a `main` local `77f1f22`, sem merge, push, migration ou escrita em `realizze_dev`.

## Decisão executiva

**Não liberar a V1 em produção no estado atual.** A aplicação local tem boa cobertura estrutural e os serviços financeiros centrais demonstraram consistência em banco isolado, mas a versão realmente servida ainda combina quatro bloqueadores:

1. a `main` não contém 28 commits da `dev-claude`, entre eles estorno de baixa, testes de IDOR, LGPD, troca confirmada de e-mail e CNPJ alfanumérico;
2. o código da `main` depende das migrations 014–020, enquanto `realizze_dev` não possui essas estruturas;
3. não existe uma homologação limpa e reproduzível da mesma revisão e do mesmo schema que seriam publicados;
4. SMTP, workers/crons, backup restaurável, monitoramento e procedimento de deploy/rollback não foram comprovados em ambiente de produção.

Os commits `556cab6`, `510d526` e `9c3700e` estão integrados na `main`; eles não são pendências.

## Inventário verificado

| Item | Evidência | Resultado |
|---|---|---|
| Worktree principal | `git worktree list --porcelain` | `realizze-main-integration`, `main`, `77f1f22`, limpa |
| `dev-claude` | `git rev-list --left-right --count main...dev-claude` | 6 commits exclusivos da `main`; **28 exclusivos da `dev-claude`** |
| `dev-codex` | mesmo comando | 56 commits na `main`; 0 exclusivo da `dev-codex` |
| Apache local | `/etc/apache2/sites-enabled/realizze.conf` no `dev-apache` | `app.realizze.lab` serve `/var/www/html/realizze-main-integration/public` |
| Guarda de acesso | `curl -k -I https://app.realizze.lab/p/pagar` | HTTP 302 para `/entrar`, cookie `Secure`, `HttpOnly`, `SameSite=Lax` |
| Runtime | resposta HTTP e `composer.json` | PHP 8.2.35; requisito `^8.2` |
| Páginas autenticadas inventariadas | matriz anterior e rotas reais | 43 rotas de página PF/PJ |
| Testes no repositório | inventário de `tests/` | 90 arquivos de teste; não equivalem a uma suíte única automatizada |

As worktrees `dev-codex` e de integração temporária estão limpas. A `dev-claude` está 75 commits à frente de `origin/dev-claude` e preserva `.claude/` não rastreado. A worktree de homologação, destacada em `4989061`, contém alterações em `perfil.php` e `app-register.php`, além de baseline/scripts não rastreados. Nenhum desses trabalhos foi alterado nesta auditoria; o estado da homologação deve ser resolvido antes de qualquer limpeza de worktree.

## Banco e compatibilidade de schema

Inspeção somente leitura confirmou:

- `realizze_dev` possui `mail_queue` e `user_sessions`, mas não possui as tabelas de cartões/parcelas/pagamentos, conciliação, centros de custo, obrigações e ordens de serviço;
- também não possui `organizations.logo_file`, `finance_categories.management_dre_group` nem o suporte de estorno verificado em bases mais novas;
- `realizze_migration014_20261103` possui as estruturas principais das migrations 014 e 015, porém não as estruturas 017–020;
- a árvore da `main` contém migrations 014–020, dois prefixos `003` e dois prefixos `108`, mas não contém o relatório/controle de migrations criado apenas na `dev-claude` nem as migrations 021/022 mencionadas na documentação dessa branch.

Consequência: abrir uma rota não demonstra que o módulo funciona. No banco servido, Cartões/Faturas, Conciliação, Centros de custo, Logotipo, Obrigações, Ordens de serviço e agrupamento do DRE ficam bloqueados ou degradados conforme o caminho executado.

## Testes realmente executados nesta auditoria

### Aprovados

- `ui-002-view-smoke.php`: templates, escape, navegação, tabelas e componentes;
- `ui-005-finance-indicators.php`;
- `ui-006-report-components.php`;
- `web-assets-smoke.php`;
- `ui-003-mobile-menu.js`: abertura, foco, `inert`, fallback e troca AJAX;
- `ui-004-enterprise-search.js`;
- `finance-001-isolation.php`: 20 asserções, incluindo centavos, isolamento, transferências, contas, empresa e orçamento;
- `finance-006-report.php`: 10 asserções, incluindo baixa parcial, cancelamentos e posição na data-base;
- `finance-023-payables-partial.php`: 8 asserções, incluindo idempotência, baixa parcial e saldo.

Total observado nesta rodada: **38 asserções financeiras aprovadas**, além das suítes estruturais/UI acima.

### Não homologados nesta rodada

- os testes 017, 018, 020, 021 e 022 recusaram `realizze_migration014_20261103` porque suas proteções exigem um banco cujo nome contenha `test`;
- o teste 014 também recusou a base pelo mesmo motivo, mas encerra com código 0, o que não pode ser contado como aprovação;
- três nomes inicialmente usados (`finance-011-financial-math`, `finance-024-as-of`, `finance-025-position`) não existem; os nomes reais são `finance-011-e2e-math`, `finance-024-report-as-of` e `finance-025-account-position`;
- o smoke HTTP autenticado não é reproduzível na base isolada disponível porque falta uma fixture autenticável compatível;
- nenhum teste de escrita foi executado contra `realizze_dev`.

## Indicadores de prontidão

| Indicador | Resultado auditável |
|---|---:|
| Rotas de páginas autenticadas inventariadas | 43 |
| Rotas com evidência anterior de abertura somente leitura | 43 |
| Rotas apontadas como carregadas sem bloqueio na matriz anterior | 41 |
| Rotas bloqueadas explicitamente naquela matriz | 2 |
| Páginas re-homologadas ponta a ponta nesta auditoria | 0 |
| Asserções financeiras aprovadas nesta auditoria | 38 |
| Fluxos essenciais com evidência parcial de serviço | 6 de 12 |
| Fluxos essenciais bloqueados/não reproduzidos | 6 de 12 |
| P0 / P1 / P2 | 9 / 7 / 8 |

Não foi calculado um “percentual concluído”: somar páginas renderizadas daria peso indevido a telas simples e esconderia schema, segurança e operação.

## Escopo mínimo recomendado

### V1

- cadastro, login, recuperação, sessão, 2FA e perfil;
- PF: dashboard, contas, receitas/despesas, pagar/receber, transferências, categorias, baixas parciais, cartões/faturas e relatórios;
- PJ: empresa e troca de contexto, dashboard, contas, pagar/receber, fluxo de caixa, DRE gerencial, faturamento, relatórios, clientes/fornecedores, cartões/faturas, equipe/permissões, auditoria e configurações;
- idempotência, isolamento PF/PJ e entre empresas, CSRF, uploads privados, exportação segura;
- e-mail transacional, backup/restore, logs, monitoramento, deploy e rollback operacionais.

### V1.1

- conciliação bancária avançada;
- centros de custo avançados;
- obrigações e ordens de serviço, se não forem indispensáveis aos primeiros clientes;
- planejamento, metas, sair das dívidas, assistente e integrações externas;
- paginações e filtros adicionais, PDF dedicado e Open Finance;
- regras fiscais, NFS-e e alertas MEI somente após validação contábil e fonte oficial.

## Riscos por área

- **Financeiro:** estorno e correção de totais existem apenas na branch não integrada; isso impede considerar baixa equivocada e relatórios homologados na versão servida.
- **Segurança:** controles de sessão, CSRF e isolamento têm boa base, mas os testes adicionais de IDOR e LGPD estão fora da `main`; não houve pentest externo.
- **Visual:** componentes, menu mobile e filtros passaram nos testes estruturais; falta rodada em dispositivos reais, cache normal e navegação autenticada completa.
- **Operação:** não há evidência de produção pública configurada, métricas/alertas, restauração ensaiada ou rollback de release.
- **Conformidade:** termos e privacidade exigem revisão jurídica; funções LGPD não estão na `main`.

## Conclusão

A base técnica permite chegar a uma beta sem reescrever o produto, mas a `main` atual é uma candidata incompleta. O caminho seguro é integrar e revisar seletivamente os 28 commits, consolidar migrations e uma base de homologação descartável, executar a suíte funcional completa e só então preparar produção.

Consulte também [V1_MATRIZ_FUNCIONAL.md](V1_MATRIZ_FUNCIONAL.md), [V1_BACKLOG.md](V1_BACKLOG.md), [V1_CRONOGRAMA.md](V1_CRONOGRAMA.md) e [V1_CHECKLIST_PRODUCAO.md](V1_CHECKLIST_PRODUCAO.md).
