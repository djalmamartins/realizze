ALTER TABLE organizations
    DROP CHECK organizations_tax_regime_check,
    DROP COLUMN tax_regime;
