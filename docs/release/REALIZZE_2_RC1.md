# Realizze 2.0 RC1: controle da consolidação

Atualizado em 07/11/2026. Branch de trabalho: `dev-claude` (sem push; Djalma envia ao GitHub).

## Etapa 1: reconhecimento (concluída)

| Item | Situação verificada |
|---|---|
| `main` (worktree `realizze-main-integration`) | `fbe9d60`. Contém toda a `dev-claude` anterior (`2457236`) e 19 commits do Codex: relatórios gerenciais e padronização visual (`4d8a476`, `fbe9d60`). |
| `dev-claude` (esta pasta) | Estava 19 commits atrás da `main` e nenhum à frente: avançada por fast-forward para `fbe9d60`, sem merge nem conflito. Commits novos desta missão ficam acima dela. |
| `dev-codex` (worktree `realizze-codex`) | `09ece0f`, sem alterações não commitadas, inteiramente contida na `main`. Não há trabalho do Codex fora da integração. |
| `integration/20261103-main-claude` (worktree `realizze-integration-temp`) | `08a988b`, contida na `main`, sem alterações. Pode ser removida quando Djalma quiser. |
| `realizze-homolog` | HEAD solto em `4989061` (01/11), commit que não está na `main`, com alterações não commitadas em `perfil.php`, `app-register.php` e scripts de homologação. Não foi tocada. Precisa de decisão de Djalma antes de qualquer limpeza. |
| origin | `origin/dev-claude` em `abb9830`, `origin/main` em `f297dde`: ambos atrás. A `dev-claude` local está 529 commits à frente da `origin/main`. |
| Apache (`dev-apache`) | `app.realizze.lab` serve `realizze-main-integration` (a `main`). `www` e `cms.realizze.lab` servem esta pasta. Para ver o trabalho da `dev-claude` no App é preciso integrá-la na `main` ou usar o servidor temporário de teste. |
| Implementações duplicadas | Nenhuma encontrada entre Claude e Codex: o Codex trabalhou em cima da integração. Duplicação interna corrigida: o menu e a rota tinham listas de permissão diferentes (ver Etapa 2). |
| Funções no código sem acesso pela interface | Faturas PF (rota existia, sem item no menu); Notificações e Ajuda na PF; Configurações PF era uma prévia com controles desativados. `/p/assistente` e `/p/empresarial` continuam prévias fora do menu (decisão pendente, ver Pendências). |

## Etapa 2: navegação (concluída)

- Um único componente de menu (`components/sidebar.php`) em áreas para PF e PJ.
- PF: Visão Geral, Receber, Pagar, Movimentações; Contas e cartões (Contas e Cartões, Faturas); Planejamento (Assinaturas e recorrências, Metas, Planejamento, Sair das Dívidas); Análise (Relatórios, Categorias); Conta (Notificações, Integrações, Configurações, Ajuda e chamados).
- PJ: Dashboard; Financeiro; Cadastros; Gestão; Administração; Plataforma (estrutura já existente).
- Permissões: `OrganizationTeam::SECTION_PERMISSIONS` e `PersonalAccessContext::SECTION_PERMISSIONS` são lidas pela rota e pelo menu. Antes, o papel Operacional via DRE no menu e recebia 403.
- Configurações PF leva a Meu perfil (dados, segurança, 2FA, acessos).
- Evidência: `tests/http/navigation-menu-flow.php` (todos os links do menu PF, PJ dono e PJ operacional respondem 200, têm item ativo e o mesmo menu; o que o menu esconde a rota recusa com 403). Capturas em `storage/tmp-pjtest/shots/menu/` (desktop 1440 e celular 390, menu aberto, grupos abertos, rolagem e clique fechando o menu), sem erro de console.
- `tests/ui-003-mobile-menu.js`: passa (a falha registrada antes foi corrigida em commits do Codex; agora confirmada também em navegador real).
- Commit: `a30c871`.

## Próxima atividade

Etapa 3: filtros PJ no padrão de Contas a Receber PF.
