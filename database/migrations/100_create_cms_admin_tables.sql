-- CMS: administradores, papéis, auditoria e configurações. Migration aditiva.
CREATE TABLE cms_roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_key VARCHAR(32) NOT NULL,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY cms_roles_key_unique (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_key VARCHAR(64) NOT NULL,
  PRIMARY KEY (role_id, permission_key),
  CONSTRAINT cms_role_permissions_role_fk FOREIGN KEY (role_id) REFERENCES cms_roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY cms_admins_user_unique (user_id),
  KEY cms_admins_role_index (role_id),
  CONSTRAINT cms_admins_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT cms_admins_role_fk FOREIGN KEY (role_id) REFERENCES cms_roles (id) ON DELETE RESTRICT,
  CONSTRAINT cms_admins_creator_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT cms_admins_status_check CHECK (status IN ('active','inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id INT UNSIGNED DEFAULT NULL,
  event_type VARCHAR(80) NOT NULL,
  resource_type VARCHAR(60) NOT NULL DEFAULT '',
  resource_id VARCHAR(60) DEFAULT NULL,
  changes_json JSON DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY cms_audit_timeline_index (created_at),
  KEY cms_audit_resource_index (resource_type, resource_id),
  KEY cms_audit_actor_index (actor_user_id),
  CONSTRAINT cms_audit_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cms_settings (
  setting_key VARCHAR(80) NOT NULL,
  setting_value TEXT NOT NULL,
  updated_by INT UNSIGNED DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  CONSTRAINT cms_settings_user_fk FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cms_roles (role_key, name, description) VALUES
 ('superadmin','Superadministrador','Acesso total, inclusive permissões e administradores.'),
 ('admin','Administrador','Administra site, plataforma e atendimento.'),
 ('editor','Editor','Cria e publica conteúdos do site.'),
 ('billing','Financeiro da plataforma','Planos, assinaturas e cobranças da plataforma.'),
 ('support','Suporte','Atende chamados dos clientes.');

INSERT INTO cms_role_permissions (role_id, permission_key)
SELECT r.id, p.permission_key FROM cms_roles r JOIN (
  SELECT 'admin' role_key, 'dashboard.view' permission_key
  UNION ALL SELECT 'admin','pages.manage' UNION ALL SELECT 'admin','content.manage' UNION ALL SELECT 'admin','content.publish'
  UNION ALL SELECT 'admin','media.manage' UNION ALL SELECT 'admin','menus.manage' UNION ALL SELECT 'admin','seo.manage'
  UNION ALL SELECT 'admin','users.view' UNION ALL SELECT 'admin','users.manage' UNION ALL SELECT 'admin','companies.view' UNION ALL SELECT 'admin','companies.manage'
  UNION ALL SELECT 'admin','plans.view' UNION ALL SELECT 'admin','plans.manage'
  UNION ALL SELECT 'admin','subscriptions.view' UNION ALL SELECT 'admin','subscriptions.manage'
  UNION ALL SELECT 'admin','charges.view'
  UNION ALL SELECT 'admin','tickets.view' UNION ALL SELECT 'admin','tickets.reply' UNION ALL SELECT 'admin','tickets.assign'
  UNION ALL SELECT 'admin','tickets.categories' UNION ALL SELECT 'admin','tickets.reports' UNION ALL SELECT 'admin','notifications.configure'
  UNION ALL SELECT 'admin','audit.view' UNION ALL SELECT 'admin','settings.manage'
  UNION ALL SELECT 'editor','dashboard.view' UNION ALL SELECT 'editor','pages.manage' UNION ALL SELECT 'editor','content.manage'
  UNION ALL SELECT 'editor','content.publish' UNION ALL SELECT 'editor','media.manage' UNION ALL SELECT 'editor','menus.manage' UNION ALL SELECT 'editor','seo.manage'
  UNION ALL SELECT 'billing','dashboard.view' UNION ALL SELECT 'billing','users.view' UNION ALL SELECT 'billing','companies.view'
  UNION ALL SELECT 'billing','plans.view' UNION ALL SELECT 'billing','plans.manage'
  UNION ALL SELECT 'billing','subscriptions.view' UNION ALL SELECT 'billing','subscriptions.manage'
  UNION ALL SELECT 'billing','charges.view' UNION ALL SELECT 'billing','charges.manage'
  UNION ALL SELECT 'support','dashboard.view' UNION ALL SELECT 'support','tickets.view' UNION ALL SELECT 'support','tickets.reply'
  UNION ALL SELECT 'support','tickets.assign' UNION ALL SELECT 'support','tickets.reports'
) p ON p.role_key = r.role_key;
