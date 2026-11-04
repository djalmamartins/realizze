# Realizze Empresarial — auditoria de isolamento PF/PJ (ENT-002/003)

**Estado:** implementação parcial em PR draft. **Não habilitar em produção.**

## Achados no código (28/10/2026)

- `SessionAuth::signIn()` regenera o ID da sessão e zera o estado anterior; `current()` reconsulta usuário ativo e `auth_version`. `signOut()` destrói a sessão. A rota `/minha-conta` usa `AccessPolicy::requireAccess('app')`.
- `Csrf::validate()` compara token da sessão. Logout usa POST + CSRF. O novo endpoint `POST /minha-conta/contexto` também exige autenticação, CSRF e associação autorizada.
- **Já existem** `Models/Organization.php`, `OrganizationMember.php`, `OrganizationRole.php`, `OrganizationRolePermission.php` e `Services/Authorization.php`. O teste `tests/authorization-v1.php` utiliza `organizations(owner_user_id,name)`, `organization_members(role_id,joined_at)` e permissões `organization.view`.
- A migração `001_create_user_legal_acceptances.sql` declara `user_id INT UNSIGNED` referenciando `users(id)`. A proposta anterior usava `BIGINT UNSIGNED` para essa FK, incompatível se a definição de `users.id` corresponder à migração. **O tipo real deve ser confirmado no banco**.
- A proposta anterior de recriar `organizations` e `organization_members` conflitava com Models/Authorization existentes; foi substituída por SQL de inspeção **somente leitura** em `database/proposals/2026_10_28_enterprise_organizations.sql`.
- O schema de produção/homologação **não foi consultado** nesta etapa. Não há migração executada nem alteração de dados.

## Proteções adicionadas

- `OrganizationAccess` reaproveita `Authorization::can(..., 'organization.view')`, exige usuário autenticado e retorna somente organizações com vínculo/permissão ativos.
- `OrganizationContext` guarda somente `organization_id`, `user_id` e `auth_version` na sessão, e **revalida a associação e permissão a cada leitura**. Se houver revogação, troca de login ou erro no banco, descarta o contexto.
- Seleção PJ exige **POST + CSRF**, valida ID numérico positivo e regenera ID da sessão; não existe seleção PJ por GET. Retorno ao contexto PF também exige POST + CSRF.
- A funcionalidade permanece desligada por padrão. Para habilitar **somente em homologação**, após confirmar o schema e os testes, definir `REALIZZE_ENTERPRISE_CONTEXT_ENABLED=1`. A UI exibe aviso quando desligada.
- A seleção PJ **não autoriza automaticamente** consultas financeiras. Todos os módulos financeiros continuam demonstrativos. Qualquer futura query financeira PJ deverá exigir `organization_id` vindo do contexto revalidado, nunca de GET/POST; consultas PF deverão ser escopadas por usuário, sem misturar contextos.
- Os indicadores empresariais mostram `—` e “Aguardando integração” em vez de saldo zero, evitando confundir ausência de dados com resultado financeiro real.

## Pré-requisitos antes de qualquer migração ou ativação

1. Executar as consultas de inspeção em homologação com credencial de leitura; conferir colunas, tipos, índices, FKs e volume de dados reais. **Não rodar DDL em produção**.
2. Decidir extensão **aditiva** do modelo existente para CNPJ normalizado, razão social, regime tributário, atividades e eventual matriz/filiais. CNPJ e regime não devem ser usados como critério de autorização.
3. Projetar tabelas de lançamentos/contas com escopo PF/PJ explícito e constraints que impeçam registros sem proprietário ou com proprietários conflitantes. Definir estratégia de migração para dados legados e índices por `organization_id`/usuário.
4. Implementar políticas de permissão por operação (`finance.view`, `finance.create`, `finance.approve` etc.), validação de entrada, transações, auditoria e testes de tentativa de acesso cruzado.
5. Testar cadastro PF/PJ, aceite de termos, confirmação de e-mail, CSRF, logout, troca de usuário, revogação de permissão e regressão do App em Docker. O formulário público de cadastro continua apenas demonstrativo.
6. Somente após homologação, decidir rollout controlado e observabilidade. Não habilitar cobrança, Open Finance, emissão fiscal nem integração WhatsApp nesta fase.

## Testes reproduzíveis

```bash
composer install
php tests/enterprise-001-context.php
php tests/ui-002-view-smoke.php
php -l source/Services/OrganizationContext.php
php -l source/Services/OrganizationAccess.php
php -l container/web/realizze/v1/routes.php
php scripts/publish-app-assets.php
```

O teste `enterprise-001-context.php` usa **SQLite em memória**, sem credenciais ou banco de produção, e verifica: separação entre empresas, vínculo inativo, permissão ausente, feature flag, CSRF, seleção autorizada, revogação de permissão, retorno PF, troca de usuário e invalidação de `auth_version`.

**Pendências:** validar esquema MySQL real; homologação visual desktop/mobile; integração de lançamentos reais com escopo PF/PJ; testes HTTP do novo endpoint e revisão de autorização por função.
