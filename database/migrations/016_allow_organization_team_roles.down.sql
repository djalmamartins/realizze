-- Só volta se nenhum papel novo estiver em uso.
ALTER TABLE organization_roles DROP CHECK organization_roles_key_check;
ALTER TABLE organization_roles ADD CONSTRAINT organization_roles_key_check
    CHECK (role_key IN ('owner', 'admin', 'member'));
