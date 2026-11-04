ALTER TABLE finance_debt_negotiations DROP COLUMN applied_at, DROP COLUMN previous_terms;
ALTER TABLE finance_debt_payments
    DROP FOREIGN KEY finance_debt_payments_reversed_fk,
    DROP INDEX finance_debt_payments_key_unique,
    DROP COLUMN reversed_by, DROP COLUMN reversed_at, DROP COLUMN previous_installments,
    DROP COLUMN previous_due_date, DROP COLUMN previous_status, DROP COLUMN entry_action, DROP COLUMN idempotency_key;
ALTER TABLE finance_debts DROP COLUMN description;
