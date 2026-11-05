-- Calendário manual de obrigações. Não calcula tributos e não cria despesas.
CREATE TABLE organization_obligations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
 series_id CHAR(32) DEFAULT NULL, sequence_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 title VARCHAR(180) NOT NULL, description TEXT DEFAULT NULL, obligation_type VARCHAR(60) NOT NULL,
 competence_date DATE NOT NULL, due_date DATE NOT NULL, responsible_user_id INT UNSIGNED DEFAULT NULL,
 frequency VARCHAR(20) NOT NULL DEFAULT 'once', amount_cents BIGINT UNSIGNED DEFAULT NULL,
 notes TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', idempotency_key CHAR(64) NOT NULL,
 created_by INT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 completed_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL,
 UNIQUE KEY organization_obligations_idempotency_uq (organization_id,idempotency_key),
 UNIQUE KEY organization_obligations_series_due_uq (organization_id,series_id,due_date),
 KEY organization_obligations_due_idx (organization_id,due_date,status),
 CONSTRAINT organization_obligations_org_fk FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
 CONSTRAINT organization_obligations_responsible_fk FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT organization_obligations_creator_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT organization_obligations_frequency_ck CHECK (frequency IN ('once','monthly','quarterly','yearly')),
 CONSTRAINT organization_obligations_status_ck CHECK (status IN ('pending','completed','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE organization_obligation_attachments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, obligation_id BIGINT UNSIGNED NOT NULL,
 organization_id BIGINT UNSIGNED NOT NULL, uploaded_by INT UNSIGNED DEFAULT NULL,
 stored_path VARCHAR(255) NOT NULL, original_name VARCHAR(190) NOT NULL, mime_type VARCHAR(100) NOT NULL,
 size_bytes BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY organization_obligation_attachments_idx (obligation_id),
 CONSTRAINT organization_obligation_attachment_obligation_fk FOREIGN KEY (obligation_id) REFERENCES organization_obligations(id) ON DELETE CASCADE,
 CONSTRAINT organization_obligation_attachment_org_fk FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
 CONSTRAINT organization_obligation_attachment_user_fk FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE organization_obligation_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, obligation_id BIGINT UNSIGNED NOT NULL,
 organization_id BIGINT UNSIGNED NOT NULL, actor_user_id INT UNSIGNED DEFAULT NULL,
 action VARCHAR(30) NOT NULL, metadata_json JSON DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY organization_obligation_history_idx (obligation_id,created_at),
 CONSTRAINT organization_obligation_history_obligation_fk FOREIGN KEY (obligation_id) REFERENCES organization_obligations(id) ON DELETE CASCADE,
 CONSTRAINT organization_obligation_history_org_fk FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
 CONSTRAINT organization_obligation_history_user_fk FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
