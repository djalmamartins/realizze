-- Baixas parciais e estornos de fatura: aditivo, sem reescrever lançamentos existentes.
ALTER TABLE finance_card_invoice_payments
    ADD COLUMN reversed_at TIMESTAMP NULL DEFAULT NULL AFTER paid_on,
    ADD COLUMN reversed_by INT UNSIGNED DEFAULT NULL AFTER reversed_at,
    ADD CONSTRAINT finance_card_invoice_payment_reversed_by_fk FOREIGN KEY (reversed_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS finance_card_payment_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    card_entry_id BIGINT UNSIGNED NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY finance_card_payment_allocation_once (payment_id, card_entry_id),
    KEY finance_card_payment_allocation_entry (financial_context_id, card_entry_id),
    CONSTRAINT finance_card_payment_allocation_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_card_payment_allocation_payment_fk FOREIGN KEY (payment_id) REFERENCES finance_card_invoice_payments(id) ON DELETE CASCADE,
    CONSTRAINT finance_card_payment_allocation_entry_fk FOREIGN KEY (card_entry_id) REFERENCES finance_entries(id) ON DELETE CASCADE,
    CONSTRAINT finance_card_payment_allocation_amount_check CHECK (amount_cents > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
