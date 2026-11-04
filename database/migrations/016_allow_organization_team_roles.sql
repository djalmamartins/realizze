-- Papéis de equipe da empresa (OrganizationTeam::ROLES). A restrição antiga só aceitava
-- owner, admin e member, e o primeiro convite falhava no MySQL ao criar o papel "finance".
-- Aditiva: nenhum papel existente deixa de ser válido.
ALTER TABLE organization_roles DROP CHECK organization_roles_key_check;
ALTER TABLE organization_roles ADD CONSTRAINT organization_roles_key_check
    CHECK (role_key IN ('owner', 'admin', 'finance', 'operational', 'accountant', 'viewer', 'member'));
