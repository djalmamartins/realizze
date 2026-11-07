# Matriz de páginas — Realizze 2.0

Validação do ciclo 4 em 07/11/2026. O Apache serve `realizze-main-integration/public`, branch `main`. As 43 rotas abaixo foram abertas em sessão autenticada, sem submissões. Todas responderam com título e layout; nenhuma redirecionou para `/ops/500` ou exibiu erro fatal.

Legenda: **OK** = página carregada e coberta pelos testes funcionais indicados; **Bloqueada** = rota carrega, mas o ambiente servido não possui a estrutura de banco requerida.

| Contexto | Rotas verificadas | Quantidade | Estado | Layout, filtros, modais e ações principais |
|---|---|---:|---|---|
| PF — visão e financeiro | `/p`, `/p/receber`, `/p/pagar`, `/p/movimentacoes`, `/p/cartoes`, `/p/faturas` | 6 | OK | Layout compartilhado; filtros financeiros; modais de lançamento, baixa, transferência, conta e fatura cobertas por smoke e integração isolada. |
| PF — análise e planejamento | `/p/relatorios`, `/p/planejamento`, `/p/metas`, `/p/categorias`, `/p/recorrencias`, `/p/sair-das-dividas` | 6 | OK | Catálogo progressivo, período, formulários e modais de orçamento/metas carregados; nenhuma operação foi enviada no ambiente compartilhado. |
| PF — plataforma | `/p/configuracoes`, `/p/notificacoes`, `/p/integracoes`, `/p/perfil` | 4 | OK | Perfil validado com filtro compartilhado; abertura, Escape e retorno de foco conferidos no Apache. |
| PJ — visão e financeiro | `/e/cueca-verde`, `/receber`, `/pagar`, `/movimentacoes`, `/recorrencias`, `/fluxo-de-caixa`, `/cartoes`, `/faturas` | 8 | OK | Componentes PF/PJ compartilhados; pagamentos e recebimentos totais/parciais validados no banco isolado. |
| PJ — conciliação | `/e/cueca-verde/conciliacao` | 1 | **Bloqueada** | Rota e layout carregam; ambiente servido informa ausência da migration 014, impedindo linhas, filtro e importação. |
| PJ — cadastros | `/e/cueca-verde/clientes`, `/fornecedores`, `/categorias` | 3 | OK | Filtros de contatos padronizados; modais são emitidos após a tabela; CRUD coberto por teste isolado. |
| PJ — centros de custo | `/e/cueca-verde/centros-de-custo` | 1 | **Bloqueada** | Rota carrega; ambiente servido informa ausência da migration 014 para a estrutura financeira dependente. |
| PJ — gestão | `/e/cueca-verde/relatorios`, `/dre`, `/planejamento`, `/metas`, `/obrigacoes`, `/ordens-de-servico` | 6 | OK | Catálogo por categorias e seleção única conferidos; filtros prioritários padronizados; modais e permissões cobertos por smoke/integração. |
| PJ — administração | `/e/cueca-verde/equipe`, `/permissoes`, `/empresa`, `/seguranca`, `/auditoria` | 5 | OK | Rotas, títulos e controles carregados; papéis e isolamento cobertos pela suíte de autorização/equipe. |
| PJ — plataforma | `/e/cueca-verde/notificacoes`, `/integracoes`, `/perfil` | 3 | OK | Layout compartilhado e perfil funcional. |

## Resultado

- Páginas verificadas: **43**.
- Totalmente funcionais no ambiente servido: **41**.
- Precisam de correção do ambiente: **2** (Conciliação e Centros de custo, migration 014).
- Erros HTTP/fatais encontrados: **0**.
- Modais inventariadas no código: **45 ocorrências de `<dialog>`**. A regressão estrutural encontrada em Receber foi corrigida; as janelas agora são emitidas fora da tabela.
- Filtros consolidados neste ciclo: Contatos, Conciliação e Atividade do Perfil, além da validação de Obrigações, Ordens de serviço e Auditoria.

## Cobertura executada

- `tests/ui-002-view-smoke.php` e `tests/web-assets-smoke.php`.
- `finance-023-payables-partial`, `finance-002-receivables`, `finance-003-contacts`, `finance-001-isolation`, `finance-012-entry-modal`.
- `authorization-v1` e `enterprise-002-team`.
- Navegação autenticada e somente leitura nas 43 rotas pelo Apache.
- Perfil: abrir/cancelar por Escape/foco; Receber: detalhes/Escape/foco; Contatos: modal e campos do filtro.
- Central de Relatórios: categorias, escolha de relatório, conteúdo único, período e ações de exportação presentes.

O teste HTTP reutilizável está em `tests/http/app-pages-smoke.php`. Ele requer uma fixture autenticável no banco apontado; a base isolada disponível neste ciclo não contém os usuários legados `pj-dono@example.invalid`, portanto a cobertura de rotas foi feita pela sessão real, somente leitura.
