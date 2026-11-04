-- Módulo 01: Contas a Receber. Migração exclusivamente aditiva.
ALTER TABLE finance_entries
    ADD COLUMN competency_date DATE DEFAULT NULL AFTER due_date,
    ADD COLUMN counterparty_name VARCHAR(160) DEFAULT NULL AFTER competency_date,
    ADD COLUMN notes TEXT DEFAULT NULL AFTER counterparty_name,
    ADD COLUMN document_number VARCHAR(80) DEFAULT NULL AFTER notes,
    ADD COLUMN payment_method VARCHAR(30) DEFAULT NULL AFTER document_number,
    ADD COLUMN status VARCHAR(24) NOT NULL DEFAULT 'pending' AFTER payment_method,
    ADD COLUMN cancellation_reason VARCHAR(500) DEFAULT NULL AFTER status,
    ADD COLUMN cancelled_at DATETIME DEFAULT NULL AFTER cancellation_reason,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD COLUMN idempotency_key CHAR(64) DEFAULT NULL AFTER updated_at,
    ADD UNIQUE KEY finance_entries_idempotency_unique (financial_context_id, idempotency_key),
    ADD KEY finance_entries_status_index (financial_context_id, kind, status, due_date),
    ADD KEY finance_entries_counterparty_index (financial_context_id, counterparty_name),
    ADD CONSTRAINT finance_entries_status_check CHECK (status IN ('pending','partially_received','received','cancelled'));

UPDATE finance_entries
SET status = CASE WHEN paid_at IS NULL THEN 'pending' ELSE 'received' END,
    competency_date = COALESCE(competency_date, DATE_FORMAT(due_date, '%Y-%m-01'));

CREATE TABLE IF NOT EXISTS finance_entry_settlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    entry_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    received_at DATE NOT NULL,
    payment_method VARCHAR(30) NOT NULL,
    notes VARCHAR(500) DEFAULT NULL,
    idempotency_key CHAR(64) NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY finance_settlements_idempotency_unique (financial_context_id, idempotency_key),
    KEY finance_settlements_entry_index (entry_id, received_at),
    KEY finance_settlements_context_index (financial_context_id, received_at),
    CONSTRAINT finance_settlements_context_fk FOREIGN KEY (financial_context_id)
        REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_settlements_entry_fk FOREIGN KEY (entry_id)
        REFERENCES finance_entries(id) ON DELETE CASCADE,
    CONSTRAINT finance_settlements_account_fk FOREIGN KEY (account_id)
        REFERENCES finance_accounts(id) ON DELETE RESTRICT,
    CONSTRAINT finance_settlements_user_fk FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT finance_settlements_amount_check CHECK (amount_cents > 0),
    CONSTRAINT finance_settlements_method_check CHECK (payment_method IN ('pix','transfer','cash','boleto','card','other'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_entry_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    entry_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(30) NOT NULL,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    metadata_json JSON DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY finance_audit_entry_index (entry_id, created_at),
    KEY finance_audit_context_index (financial_context_id, created_at),
    CONSTRAINT finance_audit_context_fk FOREIGN KEY (financial_context_id)
        REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_audit_entry_fk FOREIGN KEY (entry_id)
        REFERENCES finance_entries(id) ON DELETE CASCADE,
    CONSTRAINT finance_audit_user_fk FOREIGN KEY (actor_user_id)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT finance_audit_action_check CHECK (action IN ('created','updated','settled','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
