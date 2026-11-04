-- Sair das Dívidas: descrição, envio único, estorno e renegociação com histórico. Migration aditiva.
ALTER TABLE finance_debts
    ADD COLUMN description VARCHAR(160) DEFAULT NULL AFTER creditor_name;

ALTER TABLE finance_debt_payments
    ADD COLUMN idempotency_key VARCHAR(64) DEFAULT NULL AFTER notes,
    -- created: saída criada pelo pagamento; settled: despesa em aberto baixada; linked: despesa já paga.
    ADD COLUMN entry_action VARCHAR(10) DEFAULT NULL AFTER idempotency_key,
    ADD COLUMN previous_status VARCHAR(16) DEFAULT NULL AFTER entry_action,
    ADD COLUMN previous_due_date DATE DEFAULT NULL AFTER previous_status,
    ADD COLUMN previous_installments SMALLINT UNSIGNED DEFAULT NULL AFTER previous_due_date,
    ADD COLUMN reversed_at TIMESTAMP NULL DEFAULT NULL AFTER previous_installments,
    ADD COLUMN reversed_by INT UNSIGNED DEFAULT NULL AFTER reversed_at,
    ADD UNIQUE KEY finance_debt_payments_key_unique (financial_context_id, idempotency_key),
    ADD CONSTRAINT finance_debt_payments_reversed_fk FOREIGN KEY (reversed_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE finance_debt_negotiations
    ADD COLUMN previous_terms JSON DEFAULT NULL AFTER notes,
    ADD COLUMN applied_at TIMESTAMP NULL DEFAULT NULL AFTER previous_terms;
