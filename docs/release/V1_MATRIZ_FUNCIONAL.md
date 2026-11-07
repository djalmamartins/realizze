# Realizze 2.0 — matriz funcional da V1

Legenda: **Aprovado parcial** = serviço ou estrutura testado, sem homologação completa da página; **Implementado** = código existe, mas não foi revalidado ponta a ponta; **Bloqueado** = schema/ambiente impede o fluxo; **V1.1** = recomendado fora do mínimo inicial.

## Pessoa Física

| Área/rotas | V1 | Estado na `main` | Evidência e pendência |
|---|---|---|---|
| Cadastro, login, recuperação e 2FA | Sim | Implementado | Rotas e sessões existem; e-mail real, worker e fluxo completo não homologados nesta revisão |
| Dashboard `/p` | Sim | Aprovado parcial | matemática central coberta por `finance-001/006`; página não re-homologada autenticada |
| Contas e cartões `/p/cartoes` | Sim | Aprovado parcial | isolamento, saldos e exclusão de cartão do saldo passaram; fluxo de cartão depende de 014 |
| Receitas/Receber `/p/receber` | Sim | Aprovado parcial | baixa parcial e relatórios passaram; UI tem smoke estrutural |
| Despesas/Pagar `/p/pagar` | Sim | Aprovado parcial | 8 asserções de baixa parcial/idempotência passaram |
| Movimentações e transferências `/p/movimentacoes` | Sim | Aprovado parcial | transferência e preservação do total passaram |
| Categorias `/p/categorias` | Sim | Implementado com pendência | teste de CRUD e correção de cancelados estão apenas na `dev-claude` |
| Faturas `/p/faturas` | Sim | Bloqueado no schema servido | migration 014/015 ausentes em `realizze_dev`; suíte específica não executou nesta rodada |
| Relatórios `/p/relatorios` | Sim | Aprovado parcial | 10 asserções de relatório; exportação/IDOR reforçados apenas na `dev-claude` |
| Perfil/configurações `/p/perfil`, `/p/configuracoes` | Sim | Implementado com pendência | LGPD e troca confirmada de e-mail estão fora da `main` |
| Planejamento, metas, recorrências, dívidas | Não | V1.1 | manter estável se publicado, mas não bloquear o mínimo |
| Integrações e assistente | Não | V1.1 | informativos/prévias; não prometer integração externa |

## Pessoa Jurídica

| Área/rotas | V1 | Estado na `main` | Evidência e pendência |
|---|---|---|---|
| Empresa, seleção e troca de contexto | Sim | Aprovado parcial | isolamento básico passou; navegação/permissões corrigidas apenas na `dev-claude` |
| Dashboard `/e/{empresa}` | Sim | Aprovado parcial | serviços têm cobertura; suíte dedicada recusou a base isolada disponível |
| Contas, movimentações e transferências | Sim | Aprovado parcial | testes de isolamento/saldo passaram |
| Pagar e Receber | Sim | Aprovado parcial | baixas parciais e idempotência passaram; estorno ainda fora da `main` |
| Fluxo de caixa | Sim | Implementado | componentes e fórmulas inspecionados; sem E2E nesta rodada |
| DRE gerencial | Sim | Bloqueado parcialmente | migration 020 ausente no banco servido; deixar explícito que não é DRE contábil oficial |
| Faturamento | Sim | Implementado | visual padronizado; sem fluxo autenticado completo nesta rodada |
| Relatórios e exportações | Sim | Implementado com pendência | testes de exportação, fórmula e permissão estão apenas na `dev-claude` |
| Clientes e fornecedores | Sim | Implementado | filtros da `main`; correções adicionais e suíte HTTP estão na `dev-claude` |
| Cartões e faturas | Sim | Bloqueado | migrations 014/015 ausentes no banco servido |
| Equipe e permissões | Sim | Implementado com pendência | migration 016 e alinhamento menu/rota precisam de homologação da revisão final |
| Auditoria e segurança | Sim | Implementado com pendência | testes adicionais de IDOR estão fora da `main` |
| Configurações e logotipo | Sim | Bloqueado parcialmente | migration 017 ausente no banco servido |
| Conciliação bancária | Não | V1.1 / bloqueado | migration 014 ausente; paginação adicional está fora da `main` |
| Centros de custo | Não | V1.1 / bloqueado | migration 014 ausente |
| Obrigações | Não | V1.1 / bloqueado | migration 018 ausente |
| Ordens de serviço | Não | V1.1 / bloqueado | migration 019 ausente; correção de listagem está fora da `main` |

## Fluxos essenciais

| Fluxo | Resultado desta auditoria | Aceite ainda necessário |
|---|---|---|
| Cadastro → confirmação → login → dashboard | Não reproduzido | SMTP/worker real e teste HTTP isolado |
| Empresa → seleção → operação | Parcial | fixture limpa, dois usuários/dois contextos e navegador |
| Despesa → baixa parcial/total → saldo | Aprovado em serviço | navegador, estorno e regressão na revisão integrada |
| Receita → baixa parcial/total → saldo | Aprovado em serviço | navegador e estorno |
| Transferência entre contas | Aprovado em serviço | navegador e concorrência |
| Cartão → compra → parcelas → fatura → pagamento | Bloqueado | schema 014/015 e suíte específica |
| Relatórios → período → valores | Aprovado em serviço | exportação autenticada e revisão final |
| Ordem de serviço → cobrança → recebimento | Bloqueado/V1.1 | schema 019 e E2E |
| Conciliação → sugestão → confirmação/desfazer | Bloqueado/V1.1 | schema 014 e E2E |
| Permissão permitida/negada | Implementado | repetir suíte após integração final |
| Isolamento entre empresas | Aprovado básico | adicionar/repetir IDOR da `dev-claude` |
| Recuperação/troca de e-mail/LGPD | Não aprovado na `main` | integrar código e homologar e-mail/privacidade |

## Design e acessibilidade

- Menu mobile, recuperação de foco, fallback sem JavaScript, busca PJ, tabelas e componentes de relatório passaram nos testes estruturais.
- Filtros e indicadores usam componentes compartilhados em áreas prioritárias.
- Ainda faltam validação autenticada em 1440/1280/768/390/360, Android/iPhone reais, teclado completo, leitores de tela e console do navegador na revisão candidata.

## Interpretação correta dos números

As 43 rotas foram abertas anteriormente em sessão somente leitura. Isso prova roteamento/renderização, não CRUD, consistência financeira, permissões em todas as ações nem prontidão de produção. Nesta auditoria nenhuma página foi declarada integralmente homologada ponta a ponta; a aprovação observada é por fluxo de serviço e por componentes.
