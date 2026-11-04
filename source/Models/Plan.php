<?php
declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

final class Plan extends Model
{
    public function __construct()
    {
        parent::__construct(
            entity: 'plans',
            protected: ['id'],
            required: ['plan_key', 'name']
        );
    }
}
