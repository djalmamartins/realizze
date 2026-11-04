-- Base financeira. Migration aditiva: todo registro pertence a um financial_context
-- (pessoa física ou empresa). Valores em centavos.
CREATE TABLE IF NOT EXISTS financial_contexts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    context_type VARCHAR(20) NOT NULL,
    personal_user_id INT UNSIGNED DEFAULT NULL,
    organization_id BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY financial_contexts_personal_unique (personal_user_id),
    UNIQUE KEY financial_contexts_org_unique (organization_id),
    CONSTRAINT financial_contexts_user_fk FOREIGN KEY (personal_user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT financial_contexts_org_fk FOREIGN KEY (organization_id)
        REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT financial_contexts_owner_check CHECK (
        (context_type = 'personal' AND personal_user_id IS NOT NULL AND organization_id IS NULL)
        OR (context_type = 'organization' AND personal_user_id IS NULL AND organization_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'checking',
    institution VARCHAR(120) DEFAULT NULL,
    opening_balance_cents BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY finance_accounts_context_index (financial_context_id),
    CONSTRAINT finance_accounts_context_fk FOREIGN KEY (financial_context_id)
        REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_accounts_type_check CHECK (type IN ('checking','savings','wallet','credit_card'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    kind VARCHAR(10) NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#078b4c',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY finance_categories_unique (financial_context_id, kind, name),
    CONSTRAINT finance_categories_context_fk FOREIGN KEY (financial_context_id)
        REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_categories_kind_check CHECK (kind IN ('income','expense'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    financial_context_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(10) NOT NULL,
    description VARCHAR(160) NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    paid_at DATE DEFAULT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    target_account_id BIGINT UNSIGNED DEFAULT NULL,
    category_id BIGINT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY finance_entries_period_index (financial_context_id, kind, due_date),
    KEY finance_entries_paid_index (financial_context_id, paid_at),
    CONSTRAINT finance_entries_context_fk FOREIGN KEY (financial_context_id)
        REFERENCES financial_contexts(id) ON DELETE CASCADE,
    CONSTRAINT finance_entries_account_fk FOREIGN KEY (account_id) REFERENCES finance_accounts(id) ON DELETE CASCADE,
    CONSTRAINT finance_entries_target_fk FOREIGN KEY (target_account_id) REFERENCES finance_accounts(id) ON DELETE CASCADE,
    CONSTRAINT finance_entries_category_fk FOREIGN KEY (category_id)
        REFERENCES finance_categories(id) ON DELETE SET NULL,
    CONSTRAINT finance_entries_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT finance_entries_kind_check CHECK (kind IN ('income','expense','transfer')),
    CONSTRAINT finance_entries_amount_check CHECK (amount_cents > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
