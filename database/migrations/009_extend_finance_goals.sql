-- Foto, categoria, ícone, contribuição mensal e onde o dinheiro está guardado. Migration aditiva: metas existentes não mudam.
-- Exige a 006 (catálogo de instituições). A instituição é só informativa: nenhuma integração bancária.
ALTER TABLE finance_goals
    MODIFY COLUMN purpose VARCHAR(200) DEFAULT NULL,
    ADD COLUMN category VARCHAR(20) DEFAULT NULL AFTER purpose,
    ADD COLUMN icon VARCHAR(20) DEFAULT NULL AFTER category,
    ADD COLUMN image_path VARCHAR(190) DEFAULT NULL AFTER icon,
    ADD COLUMN monthly_contribution_cents BIGINT UNSIGNED DEFAULT NULL AFTER saved_cents,
    ADD COLUMN savings_place VARCHAR(120) DEFAULT NULL AFTER image_path,
    ADD COLUMN institution VARCHAR(120) DEFAULT NULL AFTER savings_place,
    ADD COLUMN institution_id BIGINT UNSIGNED DEFAULT NULL AFTER institution,
    ADD KEY finance_goals_institution_index (institution_id),
    ADD CONSTRAINT finance_goals_institution_fk FOREIGN KEY (institution_id)
        REFERENCES financial_institutions(id) ON DELETE SET NULL;
