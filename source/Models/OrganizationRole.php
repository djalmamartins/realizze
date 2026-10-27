<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class OrganizationRole extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'organization_roles',
            protected: ['id'],
            required: [
                'organization_id',
                'role_key',
                'name',
            ]
        );
    }
}
