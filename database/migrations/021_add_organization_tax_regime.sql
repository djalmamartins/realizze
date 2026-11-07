-- Enquadramento informado pela empresa. Só informativo: o Realizze não calcula tributos a partir dele.
ALTER TABLE organizations
    ADD COLUMN tax_regime VARCHAR(20) DEFAULT NULL AFTER phone,
    ADD CONSTRAINT organizations_tax_regime_check
        CHECK (tax_regime IS NULL OR tax_regime IN ('mei', 'simples', 'presumido', 'real', 'outro'));
