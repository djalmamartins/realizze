-- Central de Atendimento: chamados entre App e CMS, notificações e entregas externas.
CREATE TABLE helpdesk_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY helpdesk_categories_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO helpdesk_categories (name, sort_order) VALUES
 ('Problema de acesso',10),('Dúvida financeira',20),('Contas a Receber',30),('Contas a Pagar',40),('Cartões',50),
 ('Relatórios',60),('Assinatura',70),('Cobrança',80),('Erro do sistema',90),('Sugestão',100),('Outros',110);

CREATE TABLE helpdesk_sla_targets (
  priority VARCHAR(10) NOT NULL,
  first_response_minutes INT UNSIGNED DEFAULT NULL,
  resolution_minutes INT UNSIGNED DEFAULT NULL,
  updated_by INT UNSIGNED DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (priority),
  CONSTRAINT helpdesk_sla_user_fk FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Metas começam vazias: nenhum prazo é prometido antes de uma política definida.
INSERT INTO helpdesk_sla_targets (priority) VALUES ('low'),('normal'),('high'),('urgent');

CREATE TABLE helpdesk_tickets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  protocol VARCHAR(20) DEFAULT NULL,
  requester_user_id INT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED DEFAULT NULL,
  category_id INT UNSIGNED NOT NULL,
  subject VARCHAR(190) NOT NULL,
  support_type VARCHAR(12) NOT NULL DEFAULT 'technical',
  module_hint VARCHAR(60) NOT NULL DEFAULT '',
  suggested_priority VARCHAR(10) NOT NULL DEFAULT 'normal',
  priority VARCHAR(10) NOT NULL DEFAULT 'normal',
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  assignee_user_id INT UNSIGNED DEFAULT NULL,
  idempotency_key CHAR(32) NOT NULL,
  first_response_at DATETIME DEFAULT NULL,
  first_response_due_at DATETIME DEFAULT NULL,
  resolution_due_at DATETIME DEFAULT NULL,
  resolved_at DATETIME DEFAULT NULL,
  closed_at DATETIME DEFAULT NULL,
  reopened_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_activity_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY helpdesk_tickets_protocol_unique (protocol),
  UNIQUE KEY helpdesk_tickets_idempotency_unique (requester_user_id, idempotency_key),
  KEY helpdesk_tickets_queue_index (status, priority, last_activity_at),
  KEY helpdesk_tickets_org_index (organization_id, created_at),
  KEY helpdesk_tickets_assignee_index (assignee_user_id, status),
  CONSTRAINT helpdesk_tickets_requester_fk FOREIGN KEY (requester_user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT helpdesk_tickets_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE RESTRICT,
  CONSTRAINT helpdesk_tickets_category_fk FOREIGN KEY (category_id) REFERENCES helpdesk_categories (id) ON DELETE RESTRICT,
  CONSTRAINT helpdesk_tickets_assignee_fk FOREIGN KEY (assignee_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT helpdesk_tickets_status_check CHECK (status IN ('open','waiting_agent','in_progress','waiting_customer','resolved','closed')),
  CONSTRAINT helpdesk_tickets_priority_check CHECK (priority IN ('low','normal','high','urgent')),
  CONSTRAINT helpdesk_tickets_suggested_check CHECK (suggested_priority IN ('low','normal','high','urgent')),
  CONSTRAINT helpdesk_tickets_type_check CHECK (support_type IN ('technical','billing'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE helpdesk_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  author_user_id INT UNSIGNED DEFAULT NULL,
  author_type VARCHAR(10) NOT NULL,
  visibility VARCHAR(10) NOT NULL DEFAULT 'public',
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY helpdesk_messages_timeline_index (ticket_id, visibility, id),
  CONSTRAINT helpdesk_messages_ticket_fk FOREIGN KEY (ticket_id) REFERENCES helpdesk_tickets (id) ON DELETE RESTRICT,
  CONSTRAINT helpdesk_messages_author_fk FOREIGN KEY (author_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT helpdesk_messages_author_check CHECK (author_type IN ('customer','staff')),
  CONSTRAINT helpdesk_messages_visibility_check CHECK (visibility IN ('public','internal')),
  CONSTRAINT helpdesk_messages_note_check CHECK (visibility = 'public' OR author_type = 'staff')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE helpdesk_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  message_id BIGINT UNSIGNED NOT NULL,
  uploaded_by INT UNSIGNED DEFAULT NULL,
  stored_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  deleted_at DATETIME DEFAULT NULL,
  deleted_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY helpdesk_attachments_message_index (message_id),
  KEY helpdesk_attachments_ticket_index (ticket_id),
  CONSTRAINT helpdesk_attachments_ticket_fk FOREIGN KEY (ticket_id) REFERENCES helpdesk_tickets (id) ON DELETE RESTRICT,
  CONSTRAINT helpdesk_attachments_message_fk FOREIGN KEY (message_id) REFERENCES helpdesk_messages (id) ON DELETE RESTRICT,
  CONSTRAINT helpdesk_attachments_uploader_fk FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT helpdesk_attachments_deleter_fk FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE helpdesk_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(30) NOT NULL,
  from_value VARCHAR(120) DEFAULT NULL,
  to_value VARCHAR(120) DEFAULT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  actor_user_id INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY helpdesk_events_timeline_index (ticket_id, id),
  CONSTRAINT helpdesk_events_ticket_fk FOREIGN KEY (ticket_id) REFERENCES helpdesk_tickets (id) ON DELETE RESTRICT,
  CONSTRAINT helpdesk_events_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE helpdesk_ratings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  rating VARCHAR(12) NOT NULL,
  comment VARCHAR(500) NOT NULL DEFAULT '',
  rated_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY helpdesk_ratings_ticket_unique (ticket_id),
  CONSTRAINT helpdesk_ratings_ticket_fk FOREIGN KEY (ticket_id) REFERENCES helpdesk_tickets (id) ON DELETE RESTRICT,
  CONSTRAINT helpdesk_ratings_user_fk FOREIGN KEY (rated_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT helpdesk_ratings_value_check CHECK (rating IN ('satisfied','neutral','unsatisfied'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Autorização específica: ser membro da empresa não dá acesso aos chamados dela.
CREATE TABLE helpdesk_organization_access (
  organization_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  granted_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (organization_id, user_id),
  CONSTRAINT helpdesk_org_access_org_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT helpdesk_org_access_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT helpdesk_org_access_granter_fk FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  audience VARCHAR(10) NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  ticket_id BIGINT UNSIGNED DEFAULT NULL,
  title VARCHAR(190) NOT NULL,
  link VARCHAR(255) NOT NULL DEFAULT '',
  dedupe_key VARCHAR(120) NOT NULL,
  read_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY notifications_dedupe_unique (user_id, audience, dedupe_key),
  KEY notifications_inbox_index (user_id, audience, read_at, id),
  CONSTRAINT notifications_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT notifications_ticket_fk FOREIGN KEY (ticket_id) REFERENCES helpdesk_tickets (id) ON DELETE CASCADE,
  CONSTRAINT notifications_audience_check CHECK (audience IN ('customer','staff'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  channel VARCHAR(12) NOT NULL,
  dedupe_key VARCHAR(160) NOT NULL,
  ticket_id BIGINT UNSIGNED DEFAULT NULL,
  payload_json JSON NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL,
  sent_at DATETIME DEFAULT NULL,
  last_error VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY notification_deliveries_dedupe_unique (channel, dedupe_key),
  KEY notification_deliveries_claim_index (status, next_attempt_at),
  CONSTRAINT notification_deliveries_ticket_fk FOREIGN KEY (ticket_id) REFERENCES helpdesk_tickets (id) ON DELETE CASCADE,
  CONSTRAINT notification_deliveries_channel_check CHECK (channel IN ('telegram','email','whatsapp')),
  CONSTRAINT notification_deliveries_status_check CHECK (status IN ('pending','sent','failed','skipped'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
