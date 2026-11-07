# Pendências para produção

P0 impede produção; P1 é essencial para a v1.0; P2 pode ficar para depois.

| Pri. | Pendência | Situação | Depende de |
|---|---|---|---|
| P0 | Migrations 014 a 020 não aplicadas no `realizze_dev` (cartões, conciliação, centros de custo, pagamentos de fatura, papéis da equipe, logotipo, obrigações, ordens de serviço, grupo do DRE). Sem elas, essas telas não funcionam no ambiente principal. | `aplicar-migrations.sh` (não versionado) foi estendido até a 020; não foi executado. | Djalma executar o script, que faz backup antes |
| P0 | `app.realizze.lab` serve a `main`; a `dev-claude` tem commits novos ainda não integrados. | Aguardando revisão | Djalma integrar e enviar |
| P1 | Prévias `/p/assistente` e `/p/empresarial` fora do menu, sem função real. | Decidir: remover ou manter como página de apresentação | Decisão de Djalma |
| P1 | Preferências PF (idioma, moeda, formato de data) não existem; a antiga prévia de Configurações foi substituída por Meu perfil. | Não implementado | Definição de escopo |
| P1 | Worktree `realizze-homolog` com alterações não commitadas sobre um commit antigo. | Não tocada | Decisão de Djalma |
| P2 | Fluxos HTTP `enterprise-company-flow` e `enterprise-invite-login-flow` dependem do estado do banco de teste (falham na segunda execução: "Nada foi alterado", convidado já membro). | Ajustar os testes para preparar o próprio estado | |
