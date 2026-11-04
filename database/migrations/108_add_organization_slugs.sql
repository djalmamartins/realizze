-- Identificador estável para URLs empresariais do App. A autorização continua sendo feita por membro/permissão.
SET @realizze_add_slug = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'organizations' AND column_name = 'slug'),
    'SELECT 1',
    'ALTER TABLE organizations ADD COLUMN slug VARCHAR(190) DEFAULT NULL AFTER name'
);
PREPARE realizze_stmt FROM @realizze_add_slug;
EXECUTE realizze_stmt;
DEALLOCATE PREPARE realizze_stmt;

SET @realizze_add_slug_index = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'organizations' AND index_name = 'organizations_slug_unique'),
    'SELECT 1',
    'ALTER TABLE organizations ADD UNIQUE KEY organizations_slug_unique (slug)'
);
PREPARE realizze_stmt FROM @realizze_add_slug_index;
EXECUTE realizze_stmt;
DEALLOCATE PREPARE realizze_stmt;
