# Realizze 2.0 — backlog para lançamento da V1

Estimativas são faixas de esforço ativo, sem espera por aprovações externas.

## P0 — bloqueadores de produção

| ID | Módulo | Pendência/impacto | Evidência | Solução e dependências | Esforço | Critério de aceite |
|---|---|---|---|---|---:|---|
| P0-01 | Git/release | 28 commits válidos estão fora da `main`, incluindo correções financeiras e segurança | `main...dev-claude = 6/28` | revisar e integrar seletivamente, resolver os 6 commits divergentes | 8–16 h | revisão candidata contém apenas mudanças aprovadas e árvore limpa |
| P0-02 | Financeiro | estorno de baixa e correção de totais cancelados não estão na versão servida | commit exclusivo `d25a5f5` | integrar, revisar efeitos em saldos/relatórios e executar regressão | 4–8 h | baixa pode ser estornada uma vez; saldos e categorias permanecem consistentes |
| P0-03 | Banco | código e `realizze_dev` divergem nas migrations 014–020 | inspeção de `information_schema` | inventário, backup, restauração, clone de homologação, aplicar sequência e rollback ensaiado | 8–16 h | 0 migration parcial; schema candidato reproduzível; restore aprovado |
| P0-04 | Homologação | não há base limpa/fixture que execute toda a suíte da mesma revisão | testes recusaram base e smoke autenticado sem fixture | criar base descartável com nome aceito, seed mínimo e runner que falha ao recusar | 8–12 h | suíte completa executa do zero e recusa conta como falha |
| P0-05 | Segurança | IDOR reforçado, LGPD e troca de e-mail estão fora da `main` | commits `ffd8d6b`, `68a6898`, `acd64b2` exclusivos | integrar/revisar, executar autorização e privacidade em dois usuários/empresas | 8–16 h | acesso cruzado negado; exportação/exclusão e troca de e-mail auditáveis |
| P0-06 | E-mail/jobs | confirmação, recuperação e notificações dependem de SMTP e workers não comprovados | scripts de fila existem; ambiente de produção ausente | configurar SMTP, SPF/DKIM, workers e alertas de fila | 6–12 h | mensagens chegam; falha reprocessa e gera alerta sem duplicar |
| P0-07 | Infra | backup/restore, monitoramento, logs e alertas não comprovados | só há documentação proposta | automatizar backup, testar restauração e instrumentar saúde/erros | 12–24 h | restore cronometrado; alertas testados; logs sem segredos |
| P0-08 | Deploy | não existe evidência de deploy/rollback de uma release imutável | ambiente atual é Docker local | definir artefato/tag, checklist, publicação de assets, health check e rollback | 8–16 h | ensaio completo em homologação e rollback dentro do RTO definido |
| P0-09 | Legal | termos e privacidade não têm aprovação registrada | documentação existente | revisão jurídica e versão dos termos/aceites | externo | textos aprovados e aceite versionado antes de cadastro público |

## P1 — obrigatório para a V1

| ID | Módulo | Pendência/impacto | Evidência | Solução | Esforço | Critério de aceite |
|---|---|---|---|---|---:|---|
| P1-01 | Cartões/Faturas | fluxo completo não homologado nesta revisão | suíte recusou a base; schema ausente no servido | executar compra, parcelas, fechamento, pagamento, estorno e idempotência | 6–10 h | cenários mensais/anuais e centavos aprovados |
| P1-02 | Autenticação | cadastro/recuperação/2FA não foram reproduzidos ponta a ponta | somente inspeção/rotas | teste HTTP/browser com SMTP de homologação | 4–8 h | todos os fluxos, expiração e rate limit aprovados |
| P1-03 | Relatórios | exportações/IDOR reforçados estão fora da `main` | commit `b7a7b67` exclusivo | integrar testes e validar CSV contra banco | 4–8 h | valores, filtros, escape de fórmula e permissão aprovados |
| P1-04 | CNPJ | formato alfanumérico vigente está fora da `main` | commit `176569e` exclusivo | integrar e testar numérico/alfanumérico | 2–4 h | exemplos oficiais e dados legados aprovados |
| P1-05 | Navegação/permissões | menu e rota podem divergir na `main` | commit `a30c871` exclusivo | integrar alinhamento e repetir acesso por papel | 4–8 h | tudo que aparece abre; tudo que não é permitido retorna 403 |
| P1-06 | Mobile/a11y | falta validação em aparelho real e navegador autenticado | apenas testes DOM/CSS | rodada Android/iOS, teclado, contraste e console | 6–12 h | matriz de viewports sem bloqueador e sem erro de console |
| P1-07 | Performance/capacidade | não há baseline de carga e consultas | nenhuma evidência localizada | medir páginas críticas e jobs com volume representativo | 6–12 h | limites acordados de resposta, memória e fila atendidos |

## P2 — pós-lançamento

| ID | Módulo | Item | Esforço indicativo |
|---|---|---|---:|
| P2-01 | Conciliação | paginação, volume e UX avançada; pode entrar na V1.1 | 8–16 h |
| P2-02 | Centros de custo | relatórios e UX avançados | 8–16 h |
| P2-03 | Obrigações | calendário/recorrência/anexos, sem cálculo fiscal | 8–16 h |
| P2-04 | Ordens de serviço | fluxo comercial completo e cobrança vinculada | 12–24 h |
| P2-05 | Planejamento PF/PJ | metas, recorrências e dívidas como pacote opcional | 8–16 h |
| P2-06 | Integrações | Open Finance, OFX/CSV ampliado e provedores externos | depende de fornecedor |
| P2-07 | Fiscal | NFS-e, MEI/DAS e alertas | depende de contador/integração |
| P2-08 | Produto | assistente, onboarding progressivo, PDF dedicado e SEO | 12–24 h |

## Ordem recomendada

1. congelar o escopo mínimo e integrar/revisar a branch pendente;
2. fechar estorno, isolamento, LGPD e e-mail;
3. consolidar migrations e criar homologação reproduzível;
4. executar finanças, autenticação, permissões e exportações;
5. validar mobile/acessibilidade/performance;
6. preparar SMTP, jobs, backup/restore, observabilidade e deploy/rollback;
7. realizar aceite de negócio e jurídico;
8. liberar beta controlada, observar e só depois promover V1.
