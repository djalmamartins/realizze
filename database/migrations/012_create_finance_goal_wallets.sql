-- Carteiras virtuais das metas: a reserva separa dinheiro de uma conta sem criar conta, receita ou despesa.
-- O saldo reservado vem sempre da soma de delta_cents, e as operações nunca são alteradas: um estorno é uma nova linha.
-- Depende só da 005 (finance_goals.status). Não exige 006 a 011. Migration aditiva: metas e saldos existentes não mudam.
ALTER TABLE finance_goals
    ADD COLUMN account_id BIGINT UNSIGNED DEFAULT NULL AFTER financial_context_id,
    ADD COLUMN color CHAR(7) DEFAULT NULL AFTER account_id,
    ADD COLUMN idempotency_key CHAR(64) DEFAULT NULL,
    ADD UNIQUE KEY finance_goals_idempotency_unique (financial_context_id, idempotency_key),
    ADD KEY finance_goals_account_index (account_id),
    ADD CONSTRAINT finance_goals_account_fk FOREIGN KEY (account_id) REFERENCES finance_accounts(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS finance_goal_wallet_operations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    goal_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(12) NOT NULL,
    delta_cents BIGINT NOT NULL,
    operation_key CHAR(64) NOT NULL,
    entry_id BIGINT UNSIGNED DEFAULT NULL,
    reverses_id BIGINT UNSIGNED DEFAULT NULL,
    occurred_on DATE NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY goal_wallet_operation_once (financial_context_id, operation_key, goal_id),
    UNIQUE KEY goal_wallet_reversal_once (reverses_id),
    KEY goal_wallet_account_index (financial_context_id, account_id),
    KEY goal_wallet_goal_index (goal_id, occurred_on),
    KEY goal_wallet_entry_index (entry_id),
    CONSTRAINT goal_wallet_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT goal_wallet_goal_fk FOREIGN KEY (goal_id) REFERENCES finance_goals(id) ON DELETE CASCADE,
    CONSTRAINT goal_wallet_account_fk FOREIGN KEY (account_id) REFERENCES finance_accounts(id) ON DELETE CASCADE,
    CONSTRAINT goal_wallet_entry_fk FOREIGN KEY (entry_id) REFERENCES finance_entries(id) ON DELETE SET NULL,
    CONSTRAINT goal_wallet_reverses_fk FOREIGN KEY (reverses_id) REFERENCES finance_goal_wallet_operations(id) ON DELETE SET NULL,
    CONSTRAINT goal_wallet_user_fk FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT goal_wallet_type_check CHECK (type IN ('reserve','release','transfer_out','transfer_in','expense','adjustment','reversal')),
    CONSTRAINT goal_wallet_delta_check CHECK (delta_cents <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aporte programado: uma programação por meta. Nada roda sozinho até agendar scripts/process-goal-schedules.php.
CREATE TABLE IF NOT EXISTS finance_goal_schedules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    goal_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    day_of_month TINYINT UNSIGNED NOT NULL,
    next_run_on DATE NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'active',
    last_run_on DATE DEFAULT NULL,
    last_result VARCHAR(16) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY goal_schedule_goal_unique (goal_id),
    KEY goal_schedule_due_index (status, next_run_on),
    CONSTRAINT goal_schedule_context_fk FOREIGN KEY (financial_context_id) REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT goal_schedule_goal_fk FOREIGN KEY (goal_id) REFERENCES finance_goals(id) ON DELETE CASCADE,
    CONSTRAINT goal_schedule_account_fk FOREIGN KEY (account_id) REFERENCES finance_accounts(id) ON DELETE CASCADE,
    CONSTRAINT goal_schedule_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT goal_schedule_status_check CHECK (status IN ('active','paused','canceled')),
    CONSTRAINT goal_schedule_day_check CHECK (day_of_month BETWEEN 1 AND 31),
    CONSTRAINT goal_schedule_amount_check CHECK (amount_cents > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
