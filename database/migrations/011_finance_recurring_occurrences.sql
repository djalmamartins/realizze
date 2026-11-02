-- Relação idempotente entre recorrência e lançamento financeiro.
-- Não modifica lançamentos antigos; cada data de vencimento pode ser gerada uma única vez.
CREATE TABLE IF NOT EXISTS finance_recurring_occurrences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    recurring_item_id BIGINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    entry_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY recurring_occurrence_once (recurring_item_id, due_date),
    UNIQUE KEY recurring_occurrence_entry (entry_id),
    KEY recurring_occurrence_context (financial_context_id),
    CONSTRAINT recurring_occurrence_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT recurring_occurrence_item_fk FOREIGN KEY (recurring_item_id) REFERENCES finance_recurring_items(id) ON DELETE CASCADE,
    CONSTRAINT recurring_occurrence_entry_fk FOREIGN KEY (entry_id) REFERENCES finance_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
