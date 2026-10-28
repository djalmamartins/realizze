-- Realizze Empresarial: schema proposto, aplicar somente após revisão e backup.
-- Não altera tabelas existentes nem realiza migração automaticamente.
CREATE TABLE IF NOT EXISTS organizations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 legal_name VARCHAR(180) NOT NULL,
 trade_name VARCHAR(180) NULL,
 tax_id CHAR(14) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_organizations_tax_id (tax_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_members (
 organization_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 role VARCHAR(24) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (organization_id, user_id),
 KEY idx_organization_members_user (user_id, status),
 CONSTRAINT fk_org_members_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_org_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Antes de aplicar: verificar que users.id é BIGINT UNSIGNED; ajustar FK se necessário.
-- As futuras tabelas financeiras deverão ter organization_id obrigatório para dados PJ,
-- ou user_id para dados PF, com restrições explícitas que impeçam escopo ambíguo.
