-- Reversão do Módulo 01. Executar somente em ambiente controlado e sem dados a preservar.
DROP TABLE IF EXISTS finance_entry_audit_logs;
DROP TABLE IF EXISTS finance_entry_settlements;

ALTER TABLE finance_entries
    DROP CHECK finance_entries_status_check,
    DROP INDEX finance_entries_counterparty_index,
    DROP INDEX finance_entries_status_index,
    DROP INDEX finance_entries_idempotency_unique,
    DROP COLUMN idempotency_key,
    DROP COLUMN updated_at,
    DROP COLUMN cancelled_at,
    DROP COLUMN cancellation_reason,
    DROP COLUMN status,
    DROP COLUMN payment_method,
    DROP COLUMN document_number,
    DROP COLUMN notes,
    DROP COLUMN counterparty_name,
    DROP COLUMN competency_date;
