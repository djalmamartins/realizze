-- Catálogo central de instituições financeiras. Migration aditiva.
-- Dados importados por scripts/import-financial-institutions.php a partir de
-- database/seeds/financial_institutions.json (fonte pública verificável).
-- COMPE e ISPB são opcionais: nem toda instituição possui código COMPE.
CREATE TABLE IF NOT EXISTS financial_institutions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    compe_code CHAR(3) DEFAULT NULL,
    ispb CHAR(8) DEFAULT NULL,
    official_name VARCHAR(255) NOT NULL,
    trade_name VARCHAR(160) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    institution_type VARCHAR(40) NOT NULL DEFAULT 'outra',
    logo_file VARCHAR(190) DEFAULT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'active',
    source VARCHAR(255) DEFAULT NULL,
    source_updated_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY financial_institutions_slug_unique (slug),
    UNIQUE KEY financial_institutions_ispb_unique (ispb),
    UNIQUE KEY financial_institutions_compe_unique (compe_code),
    KEY financial_institutions_status_index (status, trade_name),
    CONSTRAINT financial_institutions_status_check CHECK (status IN ('active','inactive')),
    CONSTRAINT financial_institutions_logo_check CHECK (logo_file IS NULL OR logo_file REGEXP '^[a-z0-9-]+$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vínculo opcional de contas e cartões ao catálogo. O texto livre "institution" continua valendo.
ALTER TABLE finance_accounts
    ADD COLUMN institution_id BIGINT UNSIGNED DEFAULT NULL AFTER institution,
    ADD KEY finance_accounts_institution_index (institution_id),
    ADD CONSTRAINT finance_accounts_institution_fk FOREIGN KEY (institution_id)
        REFERENCES financial_institutions(id) ON DELETE SET NULL;
