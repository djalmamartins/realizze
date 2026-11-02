-- Cartões, conciliação bancária e centros de custo. Migration exclusivamente aditiva.
-- Não cria lançamentos financeiros automáticos: saldos continuam derivados de finance_entries.
CREATE TABLE IF NOT EXISTS finance_cost_centers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(40) DEFAULT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY finance_cost_centers_name_unique (financial_context_id, name),
    UNIQUE KEY finance_cost_centers_code_unique (financial_context_id, code),
    KEY finance_cost_centers_context_status (financial_context_id, status),
    CONSTRAINT finance_cost_centers_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_cost_centers_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT finance_cost_centers_status_check CHECK (status IN ('active','inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE finance_entries
    ADD COLUMN cost_center_id BIGINT UNSIGNED DEFAULT NULL AFTER category_id,
    ADD KEY finance_entries_cost_center_index (financial_context_id, cost_center_id),
    ADD CONSTRAINT finance_entries_cost_center_fk FOREIGN KEY (cost_center_id) REFERENCES finance_cost_centers(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS finance_card_purchases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    card_account_id BIGINT UNSIGNED NOT NULL,
    entry_id BIGINT UNSIGNED DEFAULT NULL,
    description VARCHAR(160) NOT NULL,
    purchased_on DATE NOT NULL,
    total_cents BIGINT UNSIGNED NOT NULL,
    installments SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    idempotency_key CHAR(64) NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY finance_card_purchase_once (financial_context_id, idempotency_key),
    KEY finance_card_purchase_card_date (card_account_id, purchased_on),
    CONSTRAINT finance_card_purchase_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_card_purchase_account_fk FOREIGN KEY (card_account_id) REFERENCES finance_accounts(id) ON DELETE CASCADE,
    CONSTRAINT finance_card_purchase_entry_fk FOREIGN KEY (entry_id) REFERENCES finance_entries(id) ON DELETE SET NULL,
    CONSTRAINT finance_card_purchase_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT finance_card_purchase_amount_check CHECK (total_cents > 0),
    CONSTRAINT finance_card_purchase_installments_check CHECK (installments BETWEEN 1 AND 120)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_card_installments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    purchase_id BIGINT UNSIGNED NOT NULL,
    entry_id BIGINT UNSIGNED NOT NULL,
    installment_number SMALLINT UNSIGNED NOT NULL,
    due_on DATE NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY finance_card_installment_unique (purchase_id, installment_number),
    UNIQUE KEY finance_card_installment_entry_unique (entry_id),
    KEY finance_card_installment_due (financial_context_id, due_on),
    CONSTRAINT finance_card_installment_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_card_installment_purchase_fk FOREIGN KEY (purchase_id) REFERENCES finance_card_purchases(id) ON DELETE CASCADE,
    CONSTRAINT finance_card_installment_entry_fk FOREIGN KEY (entry_id) REFERENCES finance_entries(id) ON DELETE CASCADE,
    CONSTRAINT finance_card_installment_amount_check CHECK (amount_cents > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_card_invoice_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    card_account_id BIGINT UNSIGNED NOT NULL,
    source_account_id BIGINT UNSIGNED NOT NULL,
    entry_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    idempotency_key CHAR(64) NOT NULL,
    paid_on DATE NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY finance_card_invoice_payment_once (financial_context_id, idempotency_key),
    KEY finance_card_invoice_payment_period (card_account_id, period_start, period_end),
    CONSTRAINT finance_card_invoice_payment_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_card_invoice_payment_card_fk FOREIGN KEY (card_account_id) REFERENCES finance_accounts(id) ON DELETE CASCADE,
    CONSTRAINT finance_card_invoice_payment_source_fk FOREIGN KEY (source_account_id) REFERENCES finance_accounts(id) ON DELETE CASCADE,
    CONSTRAINT finance_card_invoice_payment_entry_fk FOREIGN KEY (entry_id) REFERENCES finance_entries(id) ON DELETE CASCADE,
    CONSTRAINT finance_card_invoice_payment_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT finance_card_invoice_payment_amount_check CHECK (amount_cents > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_reconciliation_imports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(180) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(80) NOT NULL,
    checksum CHAR(64) NOT NULL,
    imported_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY finance_reconciliation_import_checksum (financial_context_id, account_id, checksum),
    CONSTRAINT finance_reconciliation_import_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_reconciliation_import_account_fk FOREIGN KEY (account_id) REFERENCES finance_accounts(id) ON DELETE CASCADE,
    CONSTRAINT finance_reconciliation_import_user_fk FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_reconciliation_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    import_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    occurred_on DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount_cents BIGINT NOT NULL,
    external_id VARCHAR(160) DEFAULT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    matched_entry_id BIGINT UNSIGNED DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY finance_reconciliation_line_external (import_id, external_id),
    KEY finance_reconciliation_line_pending (financial_context_id, account_id, status, occurred_on),
    CONSTRAINT finance_reconciliation_line_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_reconciliation_line_import_fk FOREIGN KEY (import_id) REFERENCES finance_reconciliation_imports(id) ON DELETE CASCADE,
    CONSTRAINT finance_reconciliation_line_account_fk FOREIGN KEY (account_id) REFERENCES finance_accounts(id) ON DELETE CASCADE,
    CONSTRAINT finance_reconciliation_line_entry_fk FOREIGN KEY (matched_entry_id) REFERENCES finance_entries(id) ON DELETE SET NULL,
    CONSTRAINT finance_reconciliation_line_user_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT finance_reconciliation_line_status_check CHECK (status IN ('pending','matched','ignored','reversed')),
    CONSTRAINT finance_reconciliation_line_amount_check CHECK (amount_cents <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
