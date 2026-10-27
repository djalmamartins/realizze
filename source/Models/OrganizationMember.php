<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class OrganizationMember extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'organization_members',
            protected: ['id'],
            required: [
                'organization_id',
                'user_id',
                'role_id',
                'joined_at',
            ]
        );
    }
}
