-- Notificações do App por área financeira, destino neutro de plataforma e estrutura para push.
-- Migration aditiva: linhas existentes continuam valendo, com área e destino nulos.
ALTER TABLE notifications
  ADD COLUMN financial_context_id BIGINT UNSIGNED DEFAULT NULL AFTER audience,
  ADD COLUMN target_json JSON DEFAULT NULL AFTER link,
  ADD KEY notifications_context_inbox_index (user_id, audience, financial_context_id, read_at, id),
  ADD CONSTRAINT notifications_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts (id) ON DELETE CASCADE;

-- Quais avisos automáticos o usuário quer receber. Sem linha, valem os padrões.
CREATE TABLE IF NOT EXISTS notification_preferences (
  user_id INT UNSIGNED NOT NULL,
  due_days TINYINT UNSIGNED NOT NULL DEFAULT 7,
  overdue_enabled TINYINT(1) NOT NULL DEFAULT 1,
  budget_enabled TINYINT(1) NOT NULL DEFAULT 1,
  goal_enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT notification_preferences_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT notification_preferences_days_check CHECK (due_days IN (0,1,3,7))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aparelhos que receberão push. Nada grava aqui até existir o app e a API de registro.
CREATE TABLE IF NOT EXISTS notification_devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  platform VARCHAR(10) NOT NULL,
  provider VARCHAR(10) NOT NULL,
  token VARCHAR(512) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  app_version VARCHAR(30) DEFAULT NULL,
  locale VARCHAR(12) DEFAULT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'active',
  last_seen_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY notification_devices_token_unique (token_hash),
  KEY notification_devices_user_index (user_id, status),
  CONSTRAINT notification_devices_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT notification_devices_platform_check CHECK (platform IN ('ios','android')),
  CONSTRAINT notification_devices_provider_check CHECK (provider IN ('apns','fcm')),
  CONSTRAINT notification_devices_status_check CHECK (status IN ('active','revoked'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Um envio de push aponta para a notificação e o aparelho. O canal só foi ampliado.
ALTER TABLE notification_deliveries
  DROP CHECK notification_deliveries_channel_check,
  ADD COLUMN notification_id BIGINT UNSIGNED DEFAULT NULL AFTER ticket_id,
  ADD COLUMN device_id BIGINT UNSIGNED DEFAULT NULL AFTER notification_id,
  ADD CONSTRAINT notification_deliveries_notification_fk FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE CASCADE,
  ADD CONSTRAINT notification_deliveries_device_fk FOREIGN KEY (device_id) REFERENCES notification_devices (id) ON DELETE CASCADE,
  ADD CONSTRAINT notification_deliveries_channel_check CHECK (channel IN ('telegram','email','whatsapp','push'));
