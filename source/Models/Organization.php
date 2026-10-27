<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class Organization extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'organizations',
            protected: ['id'],
            required: [
                'owner_user_id',
                'name',
            ]
        );
    }
}

