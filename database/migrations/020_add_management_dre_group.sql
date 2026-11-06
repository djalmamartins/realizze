-- Classificação gerencial opcional por categoria. Não cria plano contábil nem cálculo fiscal.
ALTER TABLE finance_categories
    ADD COLUMN management_dre_group VARCHAR(24) DEFAULT NULL AFTER color,
    ADD CONSTRAINT finance_categories_dre_group_check CHECK (
        management_dre_group IS NULL OR management_dre_group IN (
            'gross', 'deductions', 'costs', 'operating', 'other_income', 'other_expense'
        )
    );

