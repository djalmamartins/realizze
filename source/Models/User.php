<?php

declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class User extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'users',
            protected: [
                'id',
                'auth_version',
            ],
            required: [
                'first_name',
                'last_name',
                'email',
                'password',
            ]
        );
    }
}

