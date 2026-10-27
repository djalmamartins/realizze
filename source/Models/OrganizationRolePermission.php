<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class OrganizationRolePermission extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'organization_role_permissions',
            protected: ['id'],
            required: [
                'role_id',
                'permission_key',
            ]
        );
    }
}
