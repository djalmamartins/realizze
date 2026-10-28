-- ENT-002 / AUDITORIA (somente leitura) — NÃO é uma migração.
-- A proposta anterior criava tabelas organizations e organization_members incompatíveis
-- com o código existente (Models/Organization*, Services/Authorization.php e
-- tests/authorization-v1.php). NÃO executar o DDL anterior.
-- Também existe evidência de users.id INT UNSIGNED em
-- database/migrations/001_create_user_legal_acceptances.sql.
-- Inspecione o schema REAL do ambiente de homologação antes de escrever migrações.

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'users', 'organizations', 'organization_members',
    'organization_roles', 'organization_role_permissions'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME,
       REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'users', 'organizations', 'organization_members',
    'organization_roles', 'organization_role_permissions'
  )
ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION;

-- Modelo observado no repositório (a confirmar no banco):
-- organizations: id, owner_user_id, name, status
-- organization_members: id, organization_id, user_id, role_id, joined_at, status
-- organization_roles: id, organization_id, role_key, name
-- organization_role_permissions: role_id, permission_key
-- A autorização existente usa organization.view e status active.
-- CNPJ, regime tributário e tabelas financeiras exigem migrações ADITIVAS,
-- planejadas após verificar índices, constraints e dados legados.
