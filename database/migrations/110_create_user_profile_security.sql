-- Perfil PF: dados pessoais, sessões, 2FA, delegação e auditoria.
-- Migration aditiva: não altera dados financeiros nem cria identidades artificiais.

ALTER TABLE users
  ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER email,
  ADD COLUMN phone_verified_at DATETIME DEFAULT NULL AFTER phone,
  ADD COLUMN email_verified_at DATETIME DEFAULT NULL AFTER phone_verified_at,
  ADD COLUMN locale VARCHAR(10) NOT NULL DEFAULT 'pt_BR' AFTER photo,
  ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'America/Sao_Paulo' AFTER locale,
  ADD COLUMN biography VARCHAR(500) DEFAULT NULL AFTER timezone;

CREATE TABLE user_two_factor (
  user_id INT UNSIGNED NOT NULL,
  secret_encrypted TEXT DEFAULT NULL,
  pending_secret_encrypted TEXT DEFAULT NULL,
  enabled_at DATETIME DEFAULT NULL,
  last_counter BIGINT UNSIGNED DEFAULT NULL,
  failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT user_two_factor_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_recovery_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY user_recovery_codes_hash_unique (code_hash),
  KEY user_recovery_codes_user_index (user_id, used_at),
  CONSTRAINT user_recovery_codes_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  php_session_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  device_name VARCHAR(120) DEFAULT NULL,
  trusted_at DATETIME DEFAULT NULL,
  revoked_at DATETIME DEFAULT NULL,
  revoke_reason VARCHAR(50) DEFAULT NULL,
  last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY user_sessions_token_unique (token_hash),
  KEY user_sessions_user_index (user_id, revoked_at, last_activity_at),
  CONSTRAINT user_sessions_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE personal_access_invites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_user_id INT UNSIGNED NOT NULL,
  invited_email VARCHAR(254) NOT NULL,
  invited_user_id INT UNSIGNED DEFAULT NULL,
  token_hash CHAR(64) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  expires_at DATETIME NOT NULL,
  accepted_at DATETIME DEFAULT NULL,
  revoked_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY personal_access_invites_token_unique (token_hash),
  KEY personal_access_invites_owner_index (owner_user_id, status),
  KEY personal_access_invites_email_index (invited_email, status),
  CONSTRAINT personal_access_invites_owner_fk FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT personal_access_invites_user_fk FOREIGN KEY (invited_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT personal_access_invites_status_check CHECK (status IN ('pending','accepted','declined','expired','revoked'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE personal_access_permissions (
  invite_id BIGINT UNSIGNED NOT NULL,
  permission_key VARCHAR(60) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (invite_id, permission_key),
  CONSTRAINT personal_access_permissions_invite_fk FOREIGN KEY (invite_id) REFERENCES personal_access_invites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_notification_preferences (
  user_id INT UNSIGNED NOT NULL,
  category VARCHAR(40) NOT NULL,
  in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
  email_enabled TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, category),
  CONSTRAINT user_notification_preferences_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_activity_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_user_id INT UNSIGNED NOT NULL,
  actor_user_id INT UNSIGNED DEFAULT NULL,
  session_id BIGINT UNSIGNED DEFAULT NULL,
  event_type VARCHAR(60) NOT NULL,
  result VARCHAR(20) NOT NULL DEFAULT 'success',
  context_type VARCHAR(30) NOT NULL DEFAULT 'personal',
  context_json JSON DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY user_activity_logs_owner_index (owner_user_id, created_at, id),
  KEY user_activity_logs_type_index (owner_user_id, event_type, created_at),
  CONSTRAINT user_activity_logs_owner_fk FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT user_activity_logs_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT user_activity_logs_session_fk FOREIGN KEY (session_id) REFERENCES user_sessions(id) ON DELETE SET NULL,
  CONSTRAINT user_activity_logs_result_check CHECK (result IN ('success','failure','blocked'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
