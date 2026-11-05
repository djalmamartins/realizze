-- Identidade visual opcional por empresa. O arquivo permanece fora de public/.
ALTER TABLE organizations
    ADD COLUMN logo_file VARCHAR(80) DEFAULT NULL AFTER phone,
    ADD CONSTRAINT organizations_logo_file_check
        CHECK (logo_file IS NULL OR logo_file REGEXP '^[a-f0-9]{32}\\.jpg$');
