-- Consolidação aditiva do núcleo financeiro. Não remove nem reinterpreta dados existentes.
ALTER TABLE finance_accounts
    ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'active' AFTER opening_balance_cents,
    ADD COLUMN card_kind VARCHAR(10) DEFAULT NULL AFTER status,
    ADD COLUMN credit_limit_cents BIGINT UNSIGNED DEFAULT NULL AFTER card_kind,
    ADD COLUMN closing_day TINYINT UNSIGNED DEFAULT NULL AFTER credit_limit_cents,
    ADD COLUMN due_day TINYINT UNSIGNED DEFAULT NULL AFTER closing_day;

ALTER TABLE finance_goals
    ADD COLUMN purpose VARCHAR(160) DEFAULT NULL AFTER name,
    ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'active' AFTER deadline,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE TABLE IF NOT EXISTS finance_goal_contributions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    goal_id BIGINT UNSIGNED NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    contributed_at DATE NOT NULL,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY finance_goal_contributions_goal_index (goal_id, contributed_at),
    CONSTRAINT finance_goal_contributions_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_goal_contributions_goal_fk FOREIGN KEY (goal_id) REFERENCES finance_goals(id) ON DELETE CASCADE,
    CONSTRAINT finance_goal_contributions_user_fk FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT finance_goal_contributions_amount_check CHECK (amount_cents > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
