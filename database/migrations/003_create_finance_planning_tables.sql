-- Orçamentos e metas. Migration aditiva, por contexto financeiro. Valores em centavos.
CREATE TABLE IF NOT EXISTS finance_budgets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    month CHAR(7) NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY finance_budgets_unique (financial_context_id, category_id, month),
    CONSTRAINT finance_budgets_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_budgets_category_fk FOREIGN KEY (category_id) REFERENCES finance_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_goals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    target_cents BIGINT UNSIGNED NOT NULL,
    saved_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
    deadline DATE DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY finance_goals_context_index (financial_context_id),
    CONSTRAINT finance_goals_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
