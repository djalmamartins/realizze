-- Repetição, tags e anexos dos lançamentos. Migration aditiva: lançamentos existentes não mudam.
ALTER TABLE finance_entries
    ADD COLUMN series_id CHAR(32) DEFAULT NULL,
    ADD COLUMN series_type VARCHAR(12) DEFAULT NULL,
    ADD COLUMN series_index SMALLINT UNSIGNED DEFAULT NULL,
    ADD COLUMN series_total SMALLINT UNSIGNED DEFAULT NULL,
    ADD KEY finance_entries_series_index (financial_context_id, series_id);

CREATE TABLE IF NOT EXISTS finance_tags (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY finance_tags_unique (financial_context_id, name),
    CONSTRAINT finance_tags_context_fk FOREIGN KEY (financial_context_id)
        REFERENCES financial_contexts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_entry_tags (
    entry_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (entry_id, tag_id),
    KEY finance_entry_tags_tag_index (tag_id),
    CONSTRAINT finance_entry_tags_entry_fk FOREIGN KEY (entry_id) REFERENCES finance_entries(id) ON DELETE CASCADE,
    CONSTRAINT finance_entry_tags_tag_fk FOREIGN KEY (tag_id) REFERENCES finance_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_entry_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    entry_id BIGINT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED DEFAULT NULL,
    stored_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY finance_entry_attachments_entry_index (entry_id),
    CONSTRAINT finance_entry_attachments_context_fk FOREIGN KEY (financial_context_id)
        REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_entry_attachments_entry_fk FOREIGN KEY (entry_id) REFERENCES finance_entries(id) ON DELETE CASCADE,
    CONSTRAINT finance_entry_attachments_user_fk FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
