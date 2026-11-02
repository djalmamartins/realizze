-- Reverte a 012. Apaga o histórico das carteiras: rodar só depois de um backup.
DROP TABLE IF EXISTS finance_goal_schedules;
DROP TABLE IF EXISTS finance_goal_wallet_operations;
ALTER TABLE finance_goals
    DROP FOREIGN KEY finance_goals_account_fk,
    DROP KEY finance_goals_account_index,
    DROP KEY finance_goals_idempotency_unique,
    DROP COLUMN idempotency_key,
    DROP COLUMN color,
    DROP COLUMN account_id;
