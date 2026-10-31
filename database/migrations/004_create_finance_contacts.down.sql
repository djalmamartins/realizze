ALTER TABLE finance_entries DROP FOREIGN KEY finance_entries_contact_fk, DROP INDEX finance_entries_contact_index, DROP COLUMN contact_id;
DROP TABLE IF EXISTS finance_contact_audit_logs;
DROP TABLE IF EXISTS finance_contacts;
